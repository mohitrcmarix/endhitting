<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Offline;

use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Models\Submission;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\AccessControl;

class OfflineProcessor
{
    public function init()
    {
        // Init paypal Element for Editor
        new OfflineElement();
        // Choose Payment method Here
        add_filter('wppayform/choose_payment_method_for_submission', array($this, 'choosePaymentMethod'), 10, 4);
        add_action('wppayform/form_submission_make_payment_offline', array($this, 'makeFormPayment'), 10, 4);
        add_filter('wppayform/form_entry', array($this, 'addPaymentName'));
    }


    public function choosePaymentMethod($paymentMethod, $elements, $formId, $form_data)
    {
        if ($paymentMethod) {
            // Already someone choose that it's their payment method
            return $paymentMethod;
        }
        // Now We have to analyze the elements and return our payment method
        foreach ($elements as $element) {
            if ((isset($element['type']) && $element['type'] == 'offline_gateway_element')) {
                return 'offline';
            }
        }
        return $paymentMethod;
    }

    public function makeFormPayment($transactionId, $submissionId, $form_data, $form)
    {
        $transactionModel = new Transaction();
        $transaction = $transactionModel->getTransaction($transactionId);

        $settings = (new OfflineSettings())->getPaymentSettings();

        if ($transactionId) {
            $transactionModel->updateTransaction($transactionId, array(
                'payment_mode' => $settings['payment_mode']
            ));
        }

        $submissionModel = new Submission();
        $submissionModel->updateSubmission($submissionId, array(
            'payment_mode' => $settings['payment_mode'],
        ));

        SubmissionActivity::createActivity(array(
            'form_id' => $transaction->form_id,
            'submission_id' => $transaction->submission_id,
            'type' => 'info',
            'created_by' => 'Payform Bot',
            'content' => __('Offline Payment recorded and change the status to pending', 'wppayform')
        ));
    }

    public function addPaymentName($submission)
    {
        if ($submission->payment_method == 'offline') {
            foreach ($submission->transactions as $transaction) {
                if ($transaction->payment_method == 'offline') {
                    $paymentMethod = Arr::get($submission->form_data_raw, '__offline_payment_gateway', 'offline');
                    $transaction->payment_method = $paymentMethod . ' (Offline)';
                    $transaction->transaction_url = $paymentMethod;
                }
            }
        }
        return $submission;
    }
}
