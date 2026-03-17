<?php

namespace WPPayForm\App\Modules\Pro\Classes\Export;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\OrderItem;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Subscription;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Modules\Entry\Entry;

class Export
{
    public function exportData($request, $formId)
    {
        $paymentStatus = sanitize_text_field($request->payment_status);
        if (!$formId) {
            exit('No Form Found');
        }
        $type = 'csv';

        if ($request->doc_type) {
            $type = sanitize_text_field($request->doc_type);
        }

        $searchString = '';
        if (isset($request->search_string) && $request->search_string) {
            $searchString = sanitize_text_field($request->search_string);
        }

        if (!in_array($type, ['csv', 'ods', 'xlsx', 'json'])) {
            exit('Invalid requested format');
        }

        $form = Form::getForm($formId);

        if (!$form) {
            exit('No Form Found');
        }

        if ($type == 'json') {
            $this->exportAsJSON($formId, $paymentStatus);
        }

        $formattedData = $this->getExportDataArray(
            $this->getSubmissions($formId, $paymentStatus, $searchString),
            $formId
        );

        $this->downloadOfficeDoc(
            $formattedData,
            $type,
            sanitize_title($form->post_title, 'export', 'view') . '-' . current_time('Y-m-d')
        );
    }

    public function exportAsJSON($formId, $paymentStatus)
    {
        $form = Form::getForm($formId);
        if (!$form) {
            exit('No Form Found');
        }
        $formattedData = $this->getDataObjects(
            $this->getSubmissions($formId, $paymentStatus),
            $formId
        );

        header('Content-disposition: attachment; filename=' . sanitize_title($form->post_title, 'export', 'view') . '-' . date('Y-m-d') . '.json');
        header('Content-type: application/json');
        echo json_encode($formattedData);
        exit();
    }

    private function getSubmissions($formId, $paymentStatus, $search = false)
    {
        $wheres = [];
        if ($paymentStatus) {
            $wheres['payment_status'] = $paymentStatus;
        }
        $submissionModel = new Submission();
        $submissions = $submissionModel->getAll($formId, $wheres, false, false, 'ASC', $search);
        return $submissions->items;
    }

    private function getExportDataArray($submissions, $formId)
    {
        $inputLabels = (array)Form::getFormInputLabels($formId);
        $hasPaymentInputs = Form::hasPaymentFields($formId);

        $submissionColumns = array(
            'id' => __('ID', 'wppayform'),
            'created_at' => __('Submission Date', 'wppayform')
        );

        $hasTaxFields = false;
        if ($hasPaymentInputs) {
            $hasTaxFields = Form::hasTaxFields($formId);
            if ($hasTaxFields) {
                $paymentColumns = [
                    'payment_status' => __('Payment Status', 'wppayform'),
                    'sub_total' => __('Sub Total', 'wppayform'),
                    'tax_total' => __('Tax Total', 'wppayform'),
                    'payment_total_in_decimal' => __('Payment Total', 'wppayform'),
                    'payment_mode' => __('Payment Mode', 'wppayform'),
                    'payment_method' => __('Payment Method', 'wppayform'),
                    'payment_items' => __('Order Items', 'wppayform'),
                ];
            } else {
                $paymentColumns = [
                    'payment_status' => __('Payment Status', 'wppayform'),
                    'payment_total_in_decimal' => __('Payment Total', 'wppayform'),
                    'payment_mode' => __('Payment Mode', 'wppayform'),
                    'payment_method' => __('Payment Method', 'wppayform'),
                    'payment_items' => __('Order Items', 'wppayform')
                ];
            }

            $submissionColumns = array_merge($submissionColumns, $paymentColumns);
        }

        $subscriptionModel = new Subscription();

        $hasRecurring = Form::hasRecurring($formId);
        if ($hasRecurring) {
            $subscriptionColumns = [
                'subscription_items' => 'Subscription Items'
            ];
            $submissionColumns = array_merge($submissionColumns, $subscriptionColumns);
        }

        $submissionColumns = apply_filters('wppayform/exportdata_submission_columns', $submissionColumns, $formId);

        $header = array_merge(array_values($submissionColumns), array_values($inputLabels));

        $formattedData = [];
        $formattedData[] = $header;

        foreach ($submissions as $submission) {
            $entry = new Entry($submission);
            $entry->default = '';

            if ($hasTaxFields) {
                $taxTotal = $entry->getTaxTotal();
            } else {
                $taxTotal = '';
            }

            $data = [];
            foreach ($submissionColumns as $columnName => $column) {
                if ($columnName == 'payment_items') {
                    $data[] = $entry->getOrderItemsAsText();
                } elseif ($columnName == 'subscription_items') {
                    $data[] = $entry->getSubscriptionsAsText();
                } elseif ($columnName == 'tax_total') {
                    $data[] = number_format($taxTotal / 100, 2);
                } elseif ($columnName == 'sub_total') {
                    $data[] = number_format(($submission->payment_total - $taxTotal) / 100, 2);
                } else {
                    $data[] = $entry->{$columnName};
                }
            }
            foreach ($inputLabels as $inputKey => $item) {
                $data[] = $entry->getInput($inputKey, '');
            }
            $formattedData[] = $data;
        }

        return $formattedData;
    }

    private function getDataObjects($submissions, $formId)
    {
        $formattedData = [];
        $hasPaymentInputs = Form::hasPaymentFields($formId);
        $transactionModel = new Transaction();
        $orderItemModel = new OrderItem();

        $subscriptionModel = new Subscription();

        $hasRecurring = Form::hasRecurring($formId);

        foreach ($submissions as $submission) {
            $data = [
                'id' => $submission->id,
                'user_id' => $submission->user_id,
                'customer_name' => $submission->customer_name,
                'customer_email' => $submission->customer_email,
                'input_data' => $submission->form_data_formatted,
                'created_at' => $submission->created_at,
                'ip_address' => $submission->ip_address,
                'browser' => $submission->browser,
                'device' => $submission->device
            ];
            if ($hasPaymentInputs) {
                $data['currency'] = $submission->currency;
                $data['payment_status'] = $submission->payment_status;
                $data['payment_total'] = number_format($submission->payment_total / 100, 2);
                $data['payment_method'] = $submission->payment_method;
                $data['transactions'] = $transactionModel->getTransactions($submission->id);
                $data['order_items'] = $orderItemModel->getOrderItems($submission->id);
                $data['tax_items'] = $orderItemModel->getTaxOrderItems($submission->id);
            }
            if ($hasRecurring) {
                $data['subscriptions'] = $subscriptionModel->getSubscriptions($submission->id);
            }
            $formattedData[] = $data;
        }
        return $formattedData;
    }

    private function downloadOfficeDoc($data, $type = 'csv', $fileName = null)
    {
        $data = array_map(function ($item) {
            return array_map(function ($itemValue) {
                if (is_array($itemValue)) {
                    return implode(', ', $itemValue);
                }
                return $itemValue;
            }, $item);
        }, $data);
        require_once WPPAYFORM_DIR . '/app/Modules/Pro/libs/Spout/Autoloader/autoload.php';
        $fileName = ($fileName) ? $fileName . '.' . $type : 'export-data-' . current_time('d-m-Y') . '.' . $type;
        $writer = \Box\Spout\Writer\WriterFactory::create($type);
        $writer->openToBrowser($fileName);
        $writer->addRows($data);
        $writer->close();
        die();
    }
}
