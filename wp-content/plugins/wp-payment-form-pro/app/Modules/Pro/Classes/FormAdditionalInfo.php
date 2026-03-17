<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Models\Submission;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Info Handler Class
 * @since 1.0.0
 */
class FormAdditionalInfo
{
    public function register()
    {
        add_shortcode('payform_info', array($this, 'formInfoHandler'));
    }

    public function formInfoHandler($args)
    {
        $args = shortcode_atts(array(
            'id' => '',
            'info' => '',
            'payment_status' => ''
        ), $args);
        extract($args);
        $form = Forms::getForm($id);
        if (!$id || !$info || !$form) {
            return;
        }
        $validInfos = array(
            'submission_left' => 'getSubmissionLeftCount',
            'submission_total' => 'getSubmissionTotal'
        );
        if (isset($validInfos[$info])) {
            return $this->{$validInfos[$info]}($form, $args);
        }
        return '';
    }

    public function getSubmissionLeftCount($form, $args)
    {
        if (!get_post_meta($form->ID, 'wppayform_form_scheduling_settings', true)) {
            return '';
        }
        $sheduleSettings = Forms::getSchedulingSettings($form->ID);
        if (Arr::get($sheduleSettings, 'limitNumberOfEntries.status') == 'yes') {
            $limitEntrySettings = Arr::get($sheduleSettings, 'limitNumberOfEntries');
            $limitPeriod = Arr::get($limitEntrySettings, 'limit_type');
            $numberOfEntries = Arr::get($limitEntrySettings, 'number_of_entries');
            $paymentStatuses = Arr::get($limitEntrySettings, 'limit_payment_statuses');
            $submissionModel = new Submission();
            $totalEntryCount = $submissionModel->getEntryCountByPaymentStatus($form->ID, $paymentStatuses, $limitPeriod);
            if ($totalEntryCount >= intval($numberOfEntries)) {
                return '0';
            }
            return $numberOfEntries - $totalEntryCount;
        }
        return '';
    }

    public function getSubmissionTotal($form, $args)
    {
        $statuses = Arr::get($args, 'payment_status');
        if ($statuses) {
            $statuses = explode(',', $statuses);
        }
        $submissionModel = new Submission();
        return $submissionModel->getEntryCountByPaymentStatus($form->ID, $statuses, 'total');
    }
}