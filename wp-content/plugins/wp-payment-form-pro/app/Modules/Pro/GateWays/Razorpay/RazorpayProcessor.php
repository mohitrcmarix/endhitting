<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Razorpay;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Modules\Pro\GateWays\Razorpay\API\IPN;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\App\Services\ConfirmationHelper;

class RazorpayProcessor
{
    public $method = 'razorpay';

    protected $form;

    public function init()
    {
        new RazorpayElement();

        add_filter('wppayform/choose_payment_method_for_submission', array($this, 'choosePaymentMethod'), 10, 4);
        add_action('wppayform/form_submission_make_payment_' . $this->method, array($this, 'makeFormPayment'), 10, 6);
        add_action('wppayform_payment_frameless_' . $this->method, array($this, 'handleSessionRedirectBack'));
        add_action('wppayform_load_checkout_js_' . $this->method, array($this, 'addCheckoutJs'), 10, 3);

        add_action('wp_ajax_wppayform_razorpay_confirm_payment', array($this, 'confirmModalPayment'));
        add_action('wp_ajax_nopriv_wppayform_razorpay_confirm_payment', array($this, 'confirmModalPayment'));

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
            if ((isset($element['type']) && $element['type'] == 'razorpay_gateway_element')) {
                return 'razorpay';
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
        $this->handleRedirect($transaction, $submission, $form, $paymentMode);
    }

    private function getSuccessURL($form, $submission)
    {
        $globalSettings = get_option('wppayform_confirmation_pages');
        return add_query_arg(array(
            'wpf_submission' => $submission->submission_hash,
            'payment_method' => 'razorpay'
        ), get_permalink(intval($globalSettings['confirmation'])));
    }

    public function maybeShowModal($transaction, $submission, $form, $paymentMode)
    {
        $settings = (new RazorPaySettings())->getSettings();
        if ($settings['checkout_type'] != 'modal') {
            return;
        }

        // Create an order First
        $orderArgs = [
            'amount'   => intval($transaction->payment_total),
            'currency' => strtoupper($transaction->currency),
            'receipt'  => $submission->submission_hash,
            'notes'    => [
                'form_id'       => $form->ID,
                'submission_id' => $submission->id
            ]
        ];

        $order = (new API())->makeApiCall('orders', $orderArgs, $form->ID, 'POST');

        if (is_wp_error($order)) {
            $message = $order->get_error_message();
            do_action('wppayform_log_data', [
                'form_id' => $submission->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'title' => 'Razorpay Payment Webhook Error',
                'content' => $order->get_error_message()
            ]);

            wp_send_json([
                'errors'      => 'RazorPay Error: ' . $message,
                'append_data' => [
                    // '__entry_intermediate_hash' => Helper::getSubmissionMeta($submission->id, '__entry_intermediate_hash')
                ]
            ], 423);
        }

        $transactionModel = new Transaction();
        $transactionModel->updateTransaction($transaction->id, array(
            'charge_id' => $order['id']
        ));

        $keys = RazorPaySettings::getApiKeys($form->ID);

        $modalData = [
            'amount'       => intval($transaction->payment_total),
            'currency'     => strtoupper($transaction->currency),
            'description'  => $form->title,
            'reference_id' => $submission->submission_hash,
            'order_id'     => $order['id'],
            // 'name'         => $paymentSettings['business_name'],
            'key'          => $keys['api_key'],
            'prefill'      => [
                'email' => Arr::get($submission, 'customer_email')
            ],
            'theme'        => [
                'color' => '#3399cc'
            ]
        ];

        do_action('wppayform_log_data', [
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'title' => 'Razorpay Modal is initiated',
            'content' => 'RazorPay Modal is initiated to complete the payment'
        ]);

        $confirmation = ConfirmationHelper::getFormConfirmation($submission->form_id, $submission);
        # Tell the client to handle the action
        wp_send_json_success([
            'nextAction'       => 'razorpay',
            'actionName'       => 'initRazorPayModal',
            'submission_id'    => $submission->id,
            'modal_data'       => $modalData,
            'transaction_hash' => $submission->submission_hash,
            'message'          => __('Payment Modal is opening, Please complete the payment', 'wppayform'),
            'result'           => [
                'insert_id' => $submission->id
            ]
        ], 200);
    }

    public function handleRedirect($transaction, $submission, $form, $methodSettings)
    {
        $successUrl = $this->getSuccessURL($form, $submission);
        $globalSettings = RazorPaySettings::getSettings();
        $listener_url = add_query_arg(array(
            'wppayform_payment' => $submission->id,
            'payment_method' => $this->method,
            'submission_hash' => $submission->submission_hash,
        ), $successUrl);

        $paymentArgs = array(
            'amount'       => intval($transaction->payment_total),
            'currency'     => $transaction->currency,
            'description'  => $form->title,
            'reference_id' => $transaction->transaction_hash,
            'customer'     => [
                'email' => Arr::get($submission, 'customer_email')
            ],
            'callback_url'  => $listener_url,
            'notes'        => [
                'form_id'       => $form->ID,
                'submission_id' => $submission->id
            ],
            'callback_method' => 'get',
            'notify' => [
                'email' => in_array('email', $globalSettings['notification']),
                'sms' => in_array('sms', $globalSettings['notification']),
            ]
        );

        $paymentArgs = apply_filters('wppayform_razorpay_payment_args', $paymentArgs, $submission, $transaction, $form);

        $paymentIntent = (new API())->makeApiCall('payment_links', $paymentArgs, $form->ID, 'POST');

        if (is_wp_error($paymentIntent)) {
            do_action('wppayform_log_data', [
                'form_id' => $submission->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'title' => 'Razorpay Payment Webhook Error',
                'content' => $paymentIntent->get_error_message()
            ]);

            wp_send_json_error(array(
                'message' => __($paymentIntent->get_error_message(), 'wppayform')
            ), 423);
        }

        do_action('wppayform_log_data', [
            'form_id' => $form->ID,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'PayForm BOT',
            'title' => 'Razorpay Payment Redirect',
            'content' => 'User redirect to Razorpay for completing the payment'
        ]);

        wp_send_json_success([
            'message' => __('You are redirecting to razorpay.com to complete the purchase. Please wait while you are redirecting....', 'wppayform'),
            'call_next_method' => 'normalRedirect',
            'redirect_url' => $paymentIntent['short_url'],
        ], 200);
    }

    public function getPaymentMode($formId = false)
    {
        $isLive = RazorpaySettings::isLive($formId);
        if ($isLive) {
            return 'live';
        }
        return 'test';
    }

    public function handleSessionRedirectBack($data)
    {
        $submissionId = intval($data['wppayform_payment']);
        $submission = (new Submission())->getSubmission($submissionId);
        $transaction = $this->getLastTransaction($submissionId);

        $payId = Arr::get($data, 'razorpay_payment_id');
        $payment = (new API())->makeApiCall('payments/'.$payId, [], $submission->form_id);
        $isSuccess = false;

        if (is_wp_error($payment)) {
            $returnData = [
                'insert_id' => $submission->id,
                'title'     => __('Failed to retrieve payment data'),
                'result'    => false,
                'error'     => $payment->get_error_message()
            ];
        } else {
            $isSuccess = $payment['status'] == 'captured';
            if ($isSuccess) {
                $returnData = $this->handlePaid($submission, $transaction, $payment);
            } else {
                $returnData = [
                    'insert_id' => $submission->id,
                    'title'     => __('Failed to retrieve payment data'),
                    'result'    => false,
                    'error'     => __('Looks like you have cancelled the payment. Please try again!', 'wppayform')
                ];
            }
        }

        $returnData['type'] = ($isSuccess) ? 'success' : 'failed';

        if (!isset($returnData['is_new'])) {
            $returnData['is_new'] = false;
        }
    }

    public function addTransactionUrl($transactions, $submissionId)
    {
        foreach ($transactions as $transaction) {
            if ($transaction->charge_id) {
                $transaction->transaction_url =  'https://dashboard.razorpay.com/app/payments/'.$transaction->charge_id;
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

        if ($transaction->payment_method != 'razorpay') {
            return; // this isn't a mollie standard IPN
        }

        $status = 'paid';

        $updateData = [
            'payment_note'     => maybe_serialize($vendorTransaction),
            'charge_id'        => sanitize_text_field($vendorTransaction['id']),
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
        $updateDate = array(
            'charge_id' => $updateData['charge_id'],
            'payment_note' => $updateData['payment_note'],
            'status' => $status,
            'updated_at' => current_time('Y-m-d H:i:s')
        );

        $transactionModel->where('id', $transaction->id)->update($updateDate);
        $transaction = $transactionModel->getTransaction($transaction->id);
        do_action('wppayform_log_data', [
            'form_id' => $transaction->form_id,
            'submission_id' => $transaction->submission_id,
            'type' => 'info',
            'created_by' => 'PayForm Bot',
            'content' => sprintf(__('Transaction Marked as paid and Razorpay Transaction ID: %s', 'wppayform'), $updateDate['charge_id'])
        ]);

        do_action('wppayform/form_payment_success_razorpay', $submission, $transaction, $transaction->form_id, $updateDate);
        do_action('wppayform/form_payment_success', $submission, $transaction, $transaction->form_id, $updateDate);
    }

    public function handleRefund($refundAmount, $submission, $vendorTransaction)
    {
        $transaction = $this->getLastTransaction($submission->id);
        $this->updateRefund($vendorTransaction['status'], $refundAmount, $transaction, $submission);
    }

    public function updateRefund($newStatus, $refundAmount, $transaction, $submission)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($submission->id);
        if ($submission->payment_status == $newStatus) {
            return;
        }

        $submissionModel->updateSubmission($submission->id, array(
            'payment_status' => $newStatus
        ));

        Transaction::where('submission_id', $submission->id)->update(array(
            'status' => $newStatus,
            'updated_at' => current_time('mysql')
        ));

        $activityContent = 'Payment status changed from <b>' . $submission->payment_status . '</b> to <b>' . $newStatus . '</b>';
        $note = wp_kses_post('Status updated by Razorpay.');
        $activityContent .= '<br />Note: ' . $note;
        do_action('wppayform_log_data', [
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'Razorpay',
            'content' => $activityContent
        ]);
    }

    public function validateSubscription($paymentItems, $formattedElements, $form_data, $subscriptionItems)
    {
        wp_send_json_error(array(
            'message' => __('Razorpay doesn\'t support subscriptions right now', 'wppayform'),
            'payment_error' => true
        ), 423);
    }

    public function addCheckoutJs($settings)
    {
        wp_enqueue_script('razorpay', 'https://checkout.razorpay.com/v1/checkout.js', [], WPPAYFORM_VERSION);
        wp_enqueue_script('wppayform_razorpay_handler', WPPAYFORM_URL . 'assets/js/razorpay-handler.js', ['jquery'], WPPAYFORM_VERSION);
    }

    public function confirmModalPayment()
    {
        $data = $_REQUEST;

        $submissionHash = sanitize_text_field(Arr::get($data, 'transaction_hash'));

        $submission = (new Submission())->getSubmissionByHash($submissionHash);

        $transaction = (new Transaction())->getLatestTransaction($submission->id);

        if (!$transaction || $transaction->status != 'pending') {
            wp_send_json([
                'errors'      => 'Payment Error: Invalid Request',
            ], 423);
        }

        $paymentId = sanitize_text_field(Arr::get($data, 'razorpay_payment_id'));
        $vendorPayment = (new API())->makeApiCall('payments/' . $paymentId, [], $transaction->form_id);


        if (is_wp_error($vendorPayment)) {
            do_action('wppayform_log_data', [
                'form_id' => $transaction->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'title' => 'RazorPay Payment is failed to verify',
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

        if ($vendorPayment['status'] == 'paid' || $vendorPayment['status'] == 'captured') {
            do_action('wppayform_log_data', [
                'form_id' => $transaction->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'title' => 'RazorPay Payment is failed to verify',
                'content' => 'Razorpay payment has been marked as paid'
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
