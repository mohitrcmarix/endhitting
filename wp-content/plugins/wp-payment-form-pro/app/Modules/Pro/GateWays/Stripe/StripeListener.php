<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Stripe;

use WPPayForm\App\Models\Refund;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Models\Subscription;
use WPPayForm\App\Models\SubscriptionTransaction;
use WPPayForm\App\Modules\PaymentMethods\Stripe\ApiRequest;
use WPPayForm\App\Modules\PaymentMethods\Stripe\CheckoutSession;
use WPPayForm\App\Modules\PaymentMethods\Stripe\StripeHostedHandler;
use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\App\Models\Transaction;

class StripeListener
{
    public function init()
    {
        add_action('init', array($this, 'verifyIPN'));
        add_filter('wppayform/stripe_onetime_payment_metadata', array($this, 'pushSingleAmountMetaData'), 10, 2);
    }

    public function pushSingleAmountMetaData($metadata, $submission)
    {
        $settings = get_option('wppayform_stripe_payment_settings', array());
        if (empty($settings['send_meta_data']) || $settings['send_meta_data'] != 'yes') {
            return $metadata;
        }

        $submissionModel = new Submission();
        $entries = $submissionModel->getUnParsedSubmission($submission);
        foreach ($entries as $entry) {
            if ($entry['type'] == 'customer_name') {
                unset($metadata['customer_name']);
            }
            if ($entry['type'] == 'customer_email') {
                unset($metadata['customer_email']);
            }
            $value = $entry['value'];
            if (is_string($value) && $value) {
                $label = \substr($entry['label'], 0, 38);
                $metadata[$label] = $value;
            }
        }

        return $metadata;
    }

    public function verifyIPN()
    {
        if (!isset($_GET['wpf_stripe_listener'])) {
            return;
        }

        // retrieve the request's body and parse it as JSON
        $body = @file_get_contents('php://input');

        $event = json_decode($body);
        $eventId = $event->id;

        if ($eventId) {
            status_header(200);
            try {
                $event = $this->retrive($eventId);
                if ($event && !is_wp_error($event)) {
                    $eventType = $event->type;
                    if ($eventType == 'charge.succeeded') {
                        $this->handleChargeSucceeded($event);
                    } elseif ($eventType == 'invoice.payment_succeeded') {
                        $this->maybeHandleSubscriptionPayment($event);
                    } elseif ($eventType == 'charge.refunded') {
                        $this->handleChargeRefund($event);
                    } elseif ($eventType == 'customer.subscription.deleted') {
                        $this->handleSubscriptionCancelled($event);
                    } elseif ($eventType == 'checkout.session.completed') {
                        $this->handleCheckoutSessionCompleted($event);
                    }
                }
            } catch (Exception $e) {
                return; // No event found for this account
            }
        } else {
            status_header(500);
            die('-1'); // Failed
        }
        die('1');
    }

    // This is an onetime payment success
    private function handleChargeSucceeded($event)
    {
        $charge = $event->data->object;
        $transaction = Transaction::where('charge_id', $charge->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$transaction) {
            return;
        }

        do_action('wppayform/form_submission_activity_start', $transaction->form_id);

        // We have the transaction so we have to update some fields
        $updateData = array(
            'status' => 'paid'
        );
        if (!$transaction->card_last_4) {
            if (!empty($charge->source->last4)) {
                $updateData['card_last_4'] = $charge->source->last4;
            } elseif (!empty($charge->payment_method_details->card->last4)) {
                $updateData['card_last_4'] = $charge->payment_method_details->card->last4;
            }
        }
        if (!$transaction->card_brand) {
            if (!empty($charge->source->brand)) {
                $updateData['card_brand'] = $charge->source->brand;
            } elseif (!empty($charge->payment_method_details->card->network)) {
                $updateData['card_brand'] = $charge->payment_method_details->card->network;
            }
        }

        Transaction::where('id', $transaction->id)
            ->update($updateData);
    }

    /*
     * Handle Subscription Payment IPN
     * Refactored in version 2.0
     */
    private function maybeHandleSubscriptionPayment($event)
    {
        $data = $event->data->object;
        $subscriptionId = false;
        if (property_exists($data, 'subscription')) {
            $subscriptionId = $data->subscription;
        }
        if (!$subscriptionId) {
            return;
        }

        $subscription = Subscription::where('vendor_subscriptipn_id', $subscriptionId)
            ->where('vendor_customer_id', $data->customer)
            ->first();

        if (!$subscription) {
            return;
        }


        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);
        if (!$submission) {
            return;
        }

        do_action('wppayform/form_submission_activity_start', $submission->form_id);

        // Maybe Insert The transaction Now
        $subscriptionTransaction = new SubscriptionTransaction();

        $totalAmount = $data->total;
        if (GeneralSettings::isZeroDecimal($data->currency)) {
            $totalAmount = intval($totalAmount * 100);
        }

        $transactionId = $subscriptionTransaction->maybeInsertCharge([
            'form_id' => $submission->form_id,
            'user_id' => $submission->user_id,
            'submission_id' => $submission->id,
            'subscription_id' => $subscription->id,
            'transaction_type' => 'subscription',
            'payment_method' => 'stripe',
            'charge_id' => $data->charge,
            'payment_total' => $totalAmount,
            'status' => $data->status,
            'currency' => $data->currency,
            'payment_mode' => ($data->livemode) ? 'live' : 'test',
            'payment_note' => maybe_serialize($data),
            'created_at' => gmdate('Y-m-d H:i:s', $data->created),
            'updated_at' => gmdate('Y-m-d H:i:s', $data->created)
        ]);

        $transaction = $subscriptionTransaction->getTransaction($transactionId);

        $subscriptionModel = new Subscription();

        $subscriptionModel->update($subscription->id, [
            'status' => 'active'
        ]);

        $mainSubscription = $subscriptionModel->getSubscription($subscription->id);

        $isNewPayment = $subscription->bill_count != $mainSubscription->bill_count;

        // Check For Payment EOT
        if ($mainSubscription->bill_times && $mainSubscription->bill_count >= $mainSubscription->bill_times) {

            // We have to cancel this subscription as total bill times done
            $response = ApiRequest::request([
                'cancel_at_period_end' => 'true'
            ], 'subscriptions/' . $mainSubscription->vendor_subscriptipn_id, 'POST');

            if (!is_wp_error($response)) {
                $subscriptionModel->update($mainSubscription->id, [
                    'status' => 'completed'
                ]);
                SubmissionActivity::createActivity(array(
                    'form_id' => $submission->form_id,
                    'submission_id' => $submission->id,
                    'type' => 'activity',
                    'created_by' => 'PayForm BOT',
                    'content' => __('The Subscription Term Period has been completed', 'wppayform')
                ));
                $updatedSubscription = $subscriptionModel->getSubscription($subscription->id);
                do_action('wppayform/subscription_payment_eot_completed', $submission, $updatedSubscription, $submission->form_id, $response);
                do_action('wppayform/subscription_payment_eot_completed_stripe', $submission, $updatedSubscription, $submission->form_id, $response);
            }
        }

        if ($isNewPayment) {
            // New Payment Made so we have to fire some events here
            do_action('wppayform/subscription_payment_received', $submission, $transaction, $submission->form_id, $subscription);
            do_action('wppayform/subscription_payment_received_stripe', $submission, $transaction, $submission->form_id, $subscription);
        }
    }

    /*
     * Refactored at version 2.0
     * We are logging refunds now for both subscription and
     * One time payments
     */
    private function handleChargeRefund($event)
    {
        $data = $event->data->object;

        $chargeId = $data->id;

        // Get the Transaction from database
        $transaction = Transaction::where('charge_id', $chargeId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$transaction) {
            // Not our transaction
            return;
        }

        do_action('wppayform/form_submission_activity_start', $transaction->form_id);

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($transaction->submission_id);

        if (!$submission) {
            return;
        }

        $remainingAmount = $data->amount - $data->amount_refunded;

        if (GeneralSettings::isZeroDecimal($transaction->currency)) {
            $remainingAmount = intval($remainingAmount * 100);
        }

        if ($remainingAmount == 0) {
            $status = 'refunded';
        } else {
            $status = 'partially-refunded';
        }

        Transaction::where('id', $transaction->id)
            ->update([
                'status' => $status
            ]);

        $submissionModel->updateSubmission($submission->id, [
            'payment_status' => $status
        ]);

        // We have to record this refund to be honest
        $refunds = $data->refunds->data;
        $refundModel = new Refund();

        foreach ($refunds as $refund) {
            $exist = $refundModel->getRefundByChargeId($refund->id);
            if (!$exist) {
                $refundAmount = $refund->amount;
                if (GeneralSettings::isZeroDecimal($transaction->currency)) {
                    $refundAmount = $refundAmount * 100;
                }

                $refundData = [
                    'form_id' => $transaction->form_id,
                    'submission_id' => $transaction->submission_id,
                    'payment_method' => 'stripe',
                    'charge_id' => $refund->id,
                    'payment_note' => $refund->reason,
                    'payment_total' => $refundAmount,
                    'payment_mode' => $transaction->payment_mode,
                    'created_at' => gmdate('Y-m-d H:i:s', $refund->created),
                    'updated_at' => current_time('Y-m-d H:i:s'),
                    'status' => 'refunded',
                ];

                if ($transaction->subscription_id) {
                    $refundData['subscription_id'] = $transaction->subscription_id;
                }

                $refundId = $refundModel->create($refundData);

                $refundedMoney = $refundAmount / 100;
                SubmissionActivity::createActivity(array(
                    'form_id' => $transaction->form_id,
                    'submission_id' => $transaction->submission_id,
                    'type' => 'info',
                    'created_by' => 'Payform Bot',
                    'content' => sprintf(__('Payment Refunded By Stripe. Refunded: %s', 'wppayform'), $refundedMoney)
                ));
                $refund = $refundModel->getRefund($refundId);
                do_action('wppayform/payment_refunded_stripe', $refund, $refund->form_id, $data);
                do_action('wppayform/payment_refunded', $refund, $refund->form_id, $data);
            }
        }
    }

    /*
     * Handle Subscription Canceled
     */
    private function handleSubscriptionCancelled($event)
    {
        $data = $event->data->object;
        $subscriptionId = $data->id;
        $subscriptionModel = new Subscription();

        $subscription = Subscription::where('vendor_subscriptipn_id', $subscriptionId)
            ->where('status', '!=', 'completed')
            ->first();

        if (!$subscription) {
            return;
        }

        do_action('wppayform/form_submission_activity_start', $subscription->form_id);


        $subscriptionModel->update($subscription->id, [
            'status' => 'cancelled'
        ]);


        $subscription = $subscriptionModel->getSubscription($subscription->id);

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);

        // New Payment Made so we have to fire some events here
        do_action('wppayform/subscription_payment_canceled', $submission, $subscription, $submission->form_id, $data);
        do_action('wppayform/subscription_payment_canceled_stripe', $submission, $subscription, $submission->form_id, $data);
    }


    private function handleCheckoutSessionCompleted($event)
    {
        $data = $event->data->object;

        $session = CheckoutSession::retrive($data->id, [
            'expand' => [
                'subscription.latest_invoice.payment_intent',
                'payment_intent'
            ]
        ]);

        $submissionId = $session->client_reference_id;

        if (!$session || !$submissionId) {
            return;
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($submissionId);
        if (!$submission) {
            return;
        }
        $stripeHostedHandler = new StripeHostedHandler();
        $stripeHostedHandler->handleCheckoutSessionSuccess($submission, $session);
    }

    /*
     *
     */
    public function retrive($eventId)
    {
        return ApiRequest::request([], 'events/' . $eventId, 'GET');
    }
}
