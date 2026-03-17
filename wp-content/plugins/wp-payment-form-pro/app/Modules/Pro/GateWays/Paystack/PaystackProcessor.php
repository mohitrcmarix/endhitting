<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Paystack;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Modules\Pro\GateWays\Paystack\API\IPN;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\App\Services\ConfirmationHelper;

class PaystackProcessor
{
    public $method = 'paystack';

    protected $form;

    public function init()
    {
        new PaystackElement();

        add_filter('wppayform/choose_payment_method_for_submission', array($this, 'choosePaymentMethod'), 10, 4);
        add_action('wppayform/form_submission_make_payment_' . $this->method, array($this, 'makeFormPayment'), 10, 6);
        add_action('wppayform_load_checkout_js_' . $this->method, array($this, 'addCheckoutJs'), 10, 3);

        add_action('wp_ajax_wppayform_paystack_confirm_payment', array($this, 'confirmModalPayment'));
        add_action('wp_ajax_nopriv_wppayform_paystack_confirm_payment', array($this, 'confirmModalPayment'));
        add_filter('wppayform/entry_transactions_' . $this->method, array($this, 'addTransactionUrl'), 10, 2);
        add_filter('wppayform/submitted_payment_items_' . $this->method, array($this, 'validateSubscription'), 10, 4);
    }

    public function choosePaymentMethod($paymentMethod, $elements, $formId, $form_data)
    {
        if ($paymentMethod) {
            // Already someone choose that it's their payment method
            return $paymentMethod;
        }
        // Now We have to analyze the elements and return our payment method
        foreach ($elements as $element) {
            if ((isset($element['type']) && $element['type'] == 'paystack_gateway_element')) {
                return 'paystack';
            }
        }
        return $paymentMethod;
    }

    public function makeFormPayment($transactionId, $submissionId, $form_data, $form, $hasSubscriptions)
    {
        $paymentMode = $this->getPaymentMode();
        $transactionModel = new Transaction();
        if ($transactionId) {
            $transactionModel->updateTransaction($transactionId, array(
                'payment_mode' => $paymentMode
            ));
        }
        $transaction = $transactionModel->getTransaction($transactionId);

        $submission = (new Submission())->getSubmission($submissionId);

        $this->maybeShowModal($transaction, $submission, $form, $paymentMode);
    }

    public function addCheckoutJs($settings)
    {
        wp_enqueue_script('paystack', 'https://js.paystack.co/v1/inline.js', [], WPPAYFORM_VERSION);
        wp_enqueue_script('wppayform_paystack_handler', WPPAYFORM_URL . 'assets/js/paystack-handler.js', ['jquery'], WPPAYFORM_VERSION);
    }

    private function getSuccessURL($form, $submission)
    {
        $globalSettings = get_option('wppayform_confirmation_pages');
        return add_query_arg(array(
            'wpf_submission' => $submission->submission_hash,
            'payment_method' => 'paystack'
        ), get_permalink(intval($globalSettings['confirmation'])));
    }

    public function maybeShowModal($transaction, $submission, $form, $paymentMode)
    {
        $currency = strtoupper($submission->currency);
        if (!in_array($currency, ['NGN', 'GHS', 'ZAR', 'USD'])) {
            wp_send_json([
                'errors'      => $currency . 'is not supported by Paystack payment methood'
            ], 423);
        }

        $keys = PaystackSettings::getApiKeys($form->id);
        $modalData = [
            'key'      => $keys['api_key'],
            'email'    => $submission->customer_email,
            'ref'      => $submission->submission_hash,
            'amount'   => intval($transaction->payment_total),
            'currency' => $currency, //
            'label'    => $form->post_title,
            'metadata' => [
                'payment_handler' => 'WPPayForm',
                'form_id'         => $form->ID,
                'transaction_id'  => $transaction->id,
                'form'            => $form->post_title
            ]
        ];

        do_action('wppayform_log_data', [
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'title' => 'Paystack Modal is initiated',
            'content' => 'Paystack Modal is initiated to complete the payment'
        ]);

        $confirmation = ConfirmationHelper::getFormConfirmation($submission->form_id, $submission);
        # Tell the client to handle the action
        wp_send_json_success([
            'nextAction'       => 'paystack',
            'actionName'       => 'initPaystackModal',
            'submission_id'    => $submission->id,
            'modal_data'       => $modalData,
            'transaction_hash' => $submission->submission_hash,
            'message'          => __('Payment Modal is opening, Please complete the payment', 'wppayform'),
            'result'           => [
                'insert_id' => $submission->id
            ]
        ], 200);
    }

    protected function getPaymentMode($formId = false)
    {
        $isLive = PaystackSettings::isLive($formId);
        if ($isLive) {
            return 'live';
        }
        return 'test';
    }

    public function addTransactionUrl($transactions, $submissionId)
    {
        foreach ($transactions as $transaction) {
            if ($transaction->charge_id) {
                $transaction->transaction_url =  'https://dashboard.paystack.com/#/transactions/' . $transaction->charge_id;
            }
        }
        return $transactions;
    }


    public function getLastTransaction($submissionId)
    {
        $transactionModel = new Transaction();
        $transaction = $transactionModel->where('submission_id', $submissionId)
            ->first();
        return $transaction;
    }

    public function handlePaid($submission, $transaction, $vendorTransaction)
    {
        $transaction = $this->getLastTransaction($submission->id);

        if (!$transaction || $transaction->payment_method != $this->method) {
            return;
        }

        do_action('wppayform/form_submission_activity_start', $transaction->form_id);

        if ($transaction->payment_method != 'paystack') {
            return; // this isn't a mollie standard IPN
        }

        $status = 'paid';

        $updateData = [
            'status' => $status,
            'payment_note'     => maybe_serialize($vendorTransaction),
            'charge_id'        => sanitize_text_field(Arr::get($vendorTransaction, 'data.id')),
            'payment_total' => Arr::get($vendorTransaction, 'data.amount'),
            'currency'      => strtoupper(Arr::get($vendorTransaction, 'data.amount')),
            'card_brand' => sanitize_text_field(Arr::get($vendorTransaction, 'data.authorization.card_type')),
            'card_last_4' => intval(Arr::get($vendorTransaction, 'data.authorization.last4')),
        ];
        // Let's make the payment as paid
        $this->markAsPaid('paid', $updateData, $transaction);
    }

    public function markAsPaid($status, $updateData, $transaction)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($transaction->submission_id);

        $submissionData = array(
            'payment_status' => $status,
            'updated_at' => current_time('Y-m-d H:i:s')
        );

        $submissionModel->where('id', $transaction->submission_id)->update($submissionData);

        $transactionModel = new Transaction();
        $updateData['updated_at'] = current_time('Y-m-d H:i:s');

        $transactionModel->where('id', $transaction->id)->update($updateData);
        $transaction = $transactionModel->getTransaction($transaction->id);
        do_action('wppayform_log_data', [
            'form_id' => $transaction->form_id,
            'submission_id' => $transaction->submission_id,
            'type' => 'info',
            'created_by' => 'PayForm Bot',
            'content' => sprintf(__('Transaction Marked as paid and Paystack Transaction ID: %s', 'wppayform'), $updateData['charge_id'])
        ]);

        do_action('wppayform/form_payment_success_paystack', $submission, $transaction, $transaction->form_id, $updateData);
        do_action('wppayform/form_payment_success', $submission, $transaction, $transaction->form_id, $updateData);
    }

    public function validateSubscription($paymentItems, $formattedElements, $form_data, $subscriptionItems)
    {
        wp_send_json_error(array(
            'message' => __('Paystack doesn\'t support subscriptions right now', 'wppayform'),
            'payment_error' => true
        ), 423);
    }

    public function confirmModalPayment()
    {
        $data = $_REQUEST;

        $transactionHash = sanitize_text_field(Arr::get($data, 'trxref'));

        $submission = (new Submission())->getSubmissionByHash($transactionHash);

        $transaction = (new Transaction())->getLatestTransaction($submission->id);

        if (!$transaction || $transaction->status != 'pending') {
            wp_send_json([
                'errors'      => 'Payment Error: Invalid Request',
            ], 423);
        }

        $paymentReference = sanitize_text_field(Arr::get($data, 'reference'));
        $vendorPayment = (new API())->makeApiCall('transaction/verify/' . $paymentReference, [], $transaction->form_id);

        if (is_wp_error($vendorPayment)) {
            do_action('wppayform_log_data', [
                'form_id' => $transaction->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'title' => 'Paystack Payment is failed to verify',
                'content' => $vendorPayment->get_error_message()
            ]);

            wp_send_json_error(array(
                'message' => $vendorPayment->get_error_message(),
                'payment_error' => true,
                'type' => 'error',
                'form_events' => [
                    'payment_failed'
                ]
            ), 423);
        }
        if ($vendorPayment['status'] == 'success') {
            do_action('wppayform_log_data', [
                'form_id' => $transaction->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'title' => 'Paystack Payment is failed to verify',
                'content' => 'Paystack payment has been marked as paid'
            ]);

            $returnData = $this->handlePaid($submission, $transaction, $vendorPayment);
            $confirmation = ConfirmationHelper::getFormConfirmation($submission->form_id, $submission);
            $returnData['payment'] = $vendorPayment;
            $returnData['confirmation'] = $confirmation;
            wp_send_json_success($returnData, 200);
        }

        wp_send_json_error(array(
            'message' => 'Payment could not be verified. Please contact site admin',
            'payment_error' => true,
            'type' => 'error',
            'form_events' => [
                'payment_failed'
            ]
        ), 423);
    }
}
