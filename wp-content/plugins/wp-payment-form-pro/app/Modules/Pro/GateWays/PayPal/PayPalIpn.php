<?php

namespace WPPayForm\App\Modules\Pro\GateWays\PayPal;

use WPPayForm\App\Models\Refund;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Models\Subscription;
use WPPayForm\App\Models\SubscriptionTransaction;
use WPPayForm\App\Models\Transaction;

class PayPalIpn
{
    public function init()
    {
        /*
        * paypal specific action hooks
        */
        // normal onetime payment process
        add_action('wppayform/paypal_action_web_accept', array($this, 'updatePaymentStatusFromIPN'), 10, 2);
        // Process PayPal subscription sign ups
        add_action('wppayform/paypal_action_subscr_signup', array($this, 'processSubscriptionSignup'), 10, 2);
        // Process PayPal subscription sign ups
        add_action('wppayform/paypal_action_subscr_payment', array($this, 'processSubscriptionPayment'), 10, 2);
        // Process PayPal subscription cancel
        add_action('wppayform/paypal_action_subscr_cancel', array($this, 'processSubscriptionPaymentCancel'), 10, 2);
        // Process PayPal subscription end of term notices
        add_action('wppayform/paypal_action_subscr_eot', array($this, 'processSubscriptionPaymentEot'), 10, 2);
        // Process PayPal payment failed
        add_action('wppayform/paypal_action_subscr_failed', array($this, 'processSubscriptionFailed'), 10, 2);
    }

    public function updatePaymentStatusFromIPN($data, $payment_id)
    {
        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log('Paypal IPN Received at ' . __METHOD__);
            error_log('IPN Data (' . $payment_id . '): ' . json_encode($data));
        }

        $data = apply_filters('wppayform/paypal_web_accept_data', $data, $payment_id);
        if ($data['txn_type'] != 'web_accept' && $data['txn_type'] != 'cart' && $data['payment_status'] != 'Refunded') {
            return;
        }

        if (empty($payment_id)) {
            return;
        }

        $transactionModel = new Transaction();
        $transaction = $transactionModel->where('id', $payment_id)
            ->first();

        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log('IPN For Transaction: ' . json_encode($transaction));
        }

        if (!$transaction) {
            return;
        }

        do_action('wppayform/form_submission_activity_start', $transaction->form_id);

        $submissionModel = new Submission();

        if ($transaction->payment_method != 'paypal') {
            return; // this isn't a PayPal standard IPN
        }
        $business_email = isset($data['business']) && is_email($data['business']) ? trim($data['business']) : trim($data['receiver_email']);


        $paypalSettings = $this->getPaypalSettings();

        // Verify payment recipient
        if (strcasecmp($business_email, trim($paypalSettings['paypal_email'])) != 0) {
            $this->markAsFailed($transaction, $data, array(
                __('Payment failed due to invalid PayPal business email.', 'wppayform')
            ));
            return;
        }

        $currency_code = strtolower($data['mc_currency']);
        // Verify payment currency
        if ($currency_code != strtolower($transaction->currency)) {
            $this->markAsFailed($transaction, $data, array(
                __('Payment failed due to invalid currency in PayPal IPN', 'wppayform')
            ));
            return;
        }

        $payment_status = strtolower($data['payment_status']);

        if ($payment_status == 'refunded' || $payment_status == 'reversed') {
            // Process a refund
            $this->markAsRefunded($data, $transaction);
            return;
        }

        $paypal_amount = $data['mc_gross'];
        $isMismatchAmount = false;
        if (number_format((float)($transaction->payment_total / 100), 2) - number_format((float)$paypal_amount, 2) > 1) {
            $isMismatchAmount = true;
        }
        if ($isMismatchAmount) {
            $this->markAsFailed($transaction, $transactionModel, $submissionModel, array(
                __('Payment failed due to invalid amount in PayPal IPN.', 'wppayform')
            ));
            return;
        }

        if ($data['custom'] != $transaction->id) {
            $this->markAsFailed($transaction, $data, array(
                __('Payment failed due to invalid purchase key in PayPal IPN.', 'wppayform')
            ));
            return;
        }

        if ('completed' == $payment_status || $transaction->payment_mode == 'test') {
            $this->markAsPaidOrProcessing('paid', $data, $transaction);
            return;
        }

        if ('pending' == $payment_status && isset($data['pending_reason'])) {
            $this->markAsPaidOrProcessing('processing', $data, $transaction);
            $note = $this->getPendingReason($data);

            SubmissionActivity::createActivity(array(
                'form_id' => $transaction->form_id,
                'submission_id' => $transaction->submission_id,
                'type' => 'info',
                'created_by' => 'Payform Bot',
                'content' => __('Payment marked as pending. You may take a look of the payment in paypal', 'wppayform')
            ));

            if (!empty($note)) {
                SubmissionActivity::createActivity(array(
                    'form_id' => $transaction->form_id,
                    'submission_id' => $transaction->submission_id,
                    'type' => 'info',
                    'created_by' => 'Payform Bot',
                    'content' => $note
                ));
            }
        }
    }

    public function processSubscriptionSignup($data, $subscriptionId)
    {
        if (!intval($subscriptionId)) {
            $subscriptionId = $data['custom'];
        }
        if (!$subscriptionId) {
            return;
        }

        $subscriptionModel = new Subscription();

        $subscription = $subscriptionModel->getSubscription($subscriptionId);
        if (!$subscription) {
            return;
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);

        $submissionModel->updateSubmission($submission->id, [
            'payment_status' => 'paid'
        ]);

        $subscriptionStatus = 'active';
        if ($subscription->trial_days && $subscription->status == 'pending') {
            $subscriptionStatus = 'trialling';
        }
        $subscriptionModel->update($subscriptionId, [
            'vendor_response' => maybe_serialize($data),
            'vendor_customer_id' => $data['payer_id'],
            'vendor_subscriptipn_id' => $data['subscr_id'],
            'status' => $subscriptionStatus
        ]);

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'content' => __('PayPal recurring payment subscription successfully initiated', 'wppayform')
        ));

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'content' => __('Subscription status changed from pending to active', 'wppayform')
        ));
        do_action('wppayform/form_submission_activity_start', $submission->form_id);

        $subscribedItems = $subscriptionModel->getSubscriptions($subscriptionId);
        do_action('wppayform/form_recurring_subscribed_paypal', $submission, $subscribedItems, $submission->form_id);
        do_action('wppayform/form_recurring_subscribed', $submission, $subscribedItems, $submission->form_id);
    }

    public function processSubscriptionPayment($vendor_data, $subscriptionId)
    {
        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log('Paypal IPN Received at ' . __METHOD__);
            error_log('IPN Data (' . $subscriptionId . '): ' . json_encode($vendor_data));
        }

        if (!intval($subscriptionId)) {
            $subscriptionId = $vendor_data['custom'];
        }
        if (!$subscriptionId) {
            return;
        }

        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getSubscription($subscriptionId);

        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log('Subscription Data Fetch AT' . __METHOD__);
            error_log('Subscrion Data: : ' . json_encode($subscription));
        }

        if (!$subscription) {
            return;
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);

        if (!$submission) {
            return;
        }

        $subscriptionTransactionModel = new SubscriptionTransaction();

        $paymentStatus = strtolower($vendor_data['payment_status']);
        if ($paymentStatus == 'completed') {
            $paymentStatus = 'paid';
        }

        $paymentData = [
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'subscription_id' => $subscription->id,
            'transaction_type' => 'subscription',
            'payment_method' => 'paypal',
            'charge_id' => $vendor_data['txn_id'],
            'payment_total' => wpPayFormConverToCents($vendor_data['payment_gross']),
            'status' => $paymentStatus,
            'currency' => $submission->currency,
            'payment_mode' => $submission->payment_mode,
            'payment_note' => maybe_serialize($vendor_data)
        ];

        $transactionId = $subscriptionTransactionModel->maybeInsertCharge($paymentData);

        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log('Maybe Transaction Added' . __METHOD__);
            error_log('Transaction ID: : ' . $transactionId);
            error_log('Payment Data: : ' . json_encode($paymentData));
        }

        $transaction = $subscriptionTransactionModel->getTransaction($transactionId);

        $subscriptionModel->update($subscription->id, [
            'status' => 'active'
        ]);

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'content' => __('Congratulations! New Payment has been received from your subscription', 'wppayform')
        ));

        do_action('wppayform/form_submission_activity_start', $submission->form_id);

        $updatedSubscription = $subscriptionModel->getSubscription($subscription->id);

        $isNewPayment = $subscription->bill_count != $updatedSubscription->bill_count;

        if ($isNewPayment) {
            do_action('wppayform/subscription_payment_received', $submission, $updatedSubscription, $submission->form_id, $subscription);
            do_action('wppayform/subscription_payment_received_paypal', $submission, $updatedSubscription, $submission->form_id, $subscription);
        }
        if ($updatedSubscription->bill_count == 1) {
            do_action('wppayform/form_payment_success', $submission, $transaction, $submission->form_id, false);
        }
    }

    public function processSubscriptionPaymentCancel($vendor_data, $subscriptionId)
    {
        if (!intval($subscriptionId)) {
            $subscriptionId = $vendor_data['custom'];
        }
        if (!$subscriptionId) {
            return;
        }

        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getSubscription($subscriptionId);

        if ($subscription) {
            return;
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);

        $subscriptionModel->update($subscription->id, [
            'status' => 'cancelled'
        ]);

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'content' => __('Opps! The Subscription has been cancelled', 'wppayform')
        ));

        do_action('wppayform/subscription_payment_canceled', $submission, $subscription, $submission->form_id, $vendor_data);
        do_action('wppayform/subscription_payment_canceled_paypal', $submission, $subscription, $submission->form_id, $vendor_data);
    }

    public function processSubscriptionPaymentEot($vendor_data, $subscriptionId)
    {
        if (!intval($subscriptionId)) {
            $subscriptionId = $vendor_data['custom'];
        }
        if (!$subscriptionId) {
            return;
        }

        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getSubscription($subscriptionId);

        if (!$subscription) {
            return;
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);

        $subscriptionModel->update($subscription->id, [
            'status' => 'completed'
        ]);

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'content' => __('The Subscription Term Period has been completed', 'wppayform')
        ));

        do_action('wppayform/subscription_payment_eot_completed', $submission, $subscription, $submission->form_id, $vendor_data);
        do_action('wppayform/subscription_payment_eot_completed_paypal', $submission, $subscription, $submission->form_id, $vendor_data);
    }

    public function processSubscriptionFailed($vendor_data, $subscriptionId)
    {
        if (!intval($subscriptionId)) {
            $subscriptionId = $vendor_data['custom'];
        }
        if (!$subscriptionId) {
            return;
        }

        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getSubscription($subscriptionId);

        if (!$subscription) {
            return;
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($subscription->submission_id);

        $subscriptionModel->update($subscription->id, [
            'status' => 'failed'
        ]);

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'content' => __('Opps! The Subscription Payment has been failed', 'wppayform')
        ));

        do_action('wppayform/subscription_payment_failed', $submission, $subscription, $submission->form_id, $vendor_data);
        do_action('wppayform/subscription_payment_failed_paypal', $submission, $subscription, $submission->form_id, $vendor_data);
    }

    private function markAsFailed($transaction, $data, $errors = array())
    {
        $transactionModel = new Transaction();
        $submissionModel = new Submission();

        $transactionModel->update($transaction->id, array(
            'status' => 'failed'
        ));
        $submissionModel->updateSubmission($transaction->submission_id, array(
            'payment_status' => 'failed'
        ));
        foreach ($errors as $error) {
            SubmissionActivity::createActivity(array(
                'form_id' => $transaction->form_id,
                'submission_id' => $transaction->submission_id,
                'type' => 'error',
                'created_by' => 'Payform Bot',
                'content' => $error
            ));
        }

        $transaction = $transactionModel->getTransaction($transaction->id);
        do_action('wppayform/form_payment_paypal_failed', $transaction, $transaction->form_id, $data);
        do_action('wppayform/form_payment_failed', $transaction, $transaction->form_id, $data);
    }

    private function markAsRefunded($data, $transaction)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($transaction->submission_id);

        if ($submission->payment_status == 'refunded') {
            return;
        }

        $payment_amount = $transaction->payment_total / 100;
        $refund_amount = $data['mc_gross'] * -1;

        $status = 'refunded';

        if (number_format((float)$refund_amount, 2) < number_format((float)$payment_amount, 2)) {
            $status = 'partially-refunded';
        }

        do_action('wppayform/form_submission_activity_start', $transaction->form_id);

        Transaction::where('id', $transaction->id)
            ->update([
                'status' => $status
            ]);

        $submissionModel->updateSubmission($submission->id, [
            'payment_status' => $status
        ]);

        $refundData = [
            'form_id' => $transaction->form_id,
            'submission_id' => $transaction->submission_id,
            'payment_method' => 'paypal',
            'charge_id' => $data['txn_id'],
            'payment_note' => __('Refunded in PayPal account', 'wppayform'),
            'payment_total' => $refund_amount * 100,
            'payment_mode' => $transaction->payment_mode,
            'created_at' => current_time('Y-m-d H:i:s'),
            'updated_at' => current_time('Y-m-d H:i:s'),
            'status' => 'refunded',
        ];

        if ($transaction->subscription_id) {
            $refundData['subscription_id'] = $transaction->subscription_id;
        }

        $refundModel = new Refund();
        $refundId = $refundModel->create($refundData);

        SubmissionActivity::createActivity(array(
            'form_id' => $transaction->form_id,
            'submission_id' => $transaction->submission_id,
            'type' => 'info',
            'created_by' => 'Payform Bot',
            'content' => sprintf(__('Payment Refunded in Paypal. Refunded: %s', 'wppayform'), $refund_amount)
        ));

        $refund = $refundModel->getRefund($refundId);
        do_action('wppayform/payment_refunded_paypal', $refund, $refund->form_id, $data);
        do_action('wppayform/payment_refunded', $refund, $refund->form_id, $data);
    }

    private function markAsPaidOrProcessing($status, $data, $transaction)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($transaction->submission_id);
        // Now Let's try to get the address
        $address = array();
        $payer_email = sanitize_text_field($data['payer_email']);
        $address['payer_name'] = sanitize_text_field($data['first_name']) . ' ' . sanitize_text_field($data['last_name']);

        if (!empty($data['address_street'])) {
            $address['address_line1'] = sanitize_text_field($data['address_street']);
        }
        if (!empty($data['address_city'])) {
            $address['address_city'] = sanitize_text_field($data['address_city']);
        }
        if (!empty($data['address_state'])) {
            $address['address_state'] = sanitize_text_field($data['address_state']);
        }
        if (!empty($data['address_zip'])) {
            $address['address_zip'] = sanitize_text_field($data['address_zip']);
        }
        if (!empty($data['address_state'])) {
            $address['address_country'] = sanitize_text_field($data['address_country_code']);
        }

        $formDataRaw = $submission->form_data_raw;
        $formDataRaw['paypal_ipn_data'] = $data;

        $submissionData = array(
            'payment_status' => $status,
            'form_data_raw' => maybe_serialize($formDataRaw),
            'updated_at' => current_time('Y-m-d H:i:s')
        );
        if (!$submission->customer_email) {
            $submissionData['customer_email'] = $payer_email;
        }
        if (!$submission->customer_name) {
            $submissionData['customer_name'] = $address['name'];
        }
        if (count($address) > 1) {
            $formDataFormatted = $submission->form_data_formatted;
            $formDataFormatted['__checkout_shipping_address_details'] = $address;
            $submissionData['form_data_formatted'] = maybe_serialize($formDataFormatted);
        }

        $submissionModel->where('id', $submission->id)->update($submissionData);
        $transactionModel = new Transaction();

        $updateDate = array(
            'charge_id' => $data['txn_id'],
            'status' => $status,
            'updated_at' => current_time('Y-m-d H:i:s')
        );
        $transactionModel->where('id', $transaction->id)->update($updateDate);

        $transaction = $transactionModel->getTransaction($transaction->id);
        if ($status == 'paid') {
            SubmissionActivity::createActivity(array(
                'form_id' => $transaction->form_id,
                'submission_id' => $transaction->submission_id,
                'type' => 'info',
                'created_by' => 'Payform Bot',
                'content' => sprintf(__('Transaction Marked as paid and PayPal Transaction ID: %s and Payer Paypal Email ID: %s', 'wppayform'), $data['txn_id'], $payer_email)
            ));

            do_action('wppayform/form_payment_success_paypal', $submission, $transaction, $transaction->form_id, $data);
            do_action('wppayform/form_payment_success', $submission, $transaction, $transaction->form_id, $data);
        } else {
            do_action('wppayform/form_payment_processing_paypal', $submission, $transaction, $transaction->form_id, $data);
            do_action('wppayform/form_payment_processing', $submission, $transaction, $transaction->form_id, $data);
        }
    }

    private function getPendingReason($data)
    {
        $note = '';
        switch (strtolower($data['pending_reason'])) {
            case 'echeck':
                $note = __('Payment made via eCheck and will clear automatically in 5-8 days', 'easy-digital-downloads');
                break;
            case 'address':
                $note = __('Payment requires a confirmed customer address and must be accepted manually through PayPal', 'easy-digital-downloads');
                break;
            case 'intl':
                $note = __('Payment must be accepted manually through PayPal due to international account regulations', 'easy-digital-downloads');
                break;
            case 'multi-currency':
                $note = __('Payment received in non-shop currency and must be accepted manually through PayPal', 'easy-digital-downloads');
                break;
            case 'paymentreview':
            case 'regulatory_review':
                $note = __('Payment is being reviewed by PayPal staff as high-risk or in possible violation of government regulations', 'easy-digital-downloads');
                break;
            case 'unilateral':
                $note = __('Payment was sent to non-confirmed or non-registered email address.', 'easy-digital-downloads');
                break;
            case 'upgrade':
                $note = __('PayPal account must be upgraded before this payment can be accepted', 'easy-digital-downloads');
                break;

            case 'verify':
                $note = __('PayPal account is not verified. Verify account in order to accept this payment', 'easy-digital-downloads');
                break;
            case 'other':
                $note = __('Payment is pending for unknown reasons. Contact PayPal support for assistance', 'easy-digital-downloads');
                break;
        }
        return $note;
    }

    private function getPaypalSettings()
    {
        $payPal = new PayPal();
        return $payPal->getPaypalSettings();
    }
}
