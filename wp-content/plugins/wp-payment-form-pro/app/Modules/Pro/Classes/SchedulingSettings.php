<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\Submission;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Scheduling and Restriction Class
 * @since 1.0.0
 */
class SchedulingSettings
{
    public function getSettings($formId)
    {
        $settings = Form::getSchedulingSettings($formId);
        return array(
            'scheduling_settings' => $settings,
            'current_date_time' => current_time('d M Y H:i:s')
        );
    }

    public function updateSettings($request, $formId)
    {
        $settings = wp_unslash($request->settings);
        if (
            Arr::get($settings, 'limitNumberOfEntries.status') == 'no' &&
            Arr::get($settings, 'scheduleForm.status') == 'no' &&
            Arr::get($settings, 'requireLogin.status') == 'no'
        ) {
            $settings = false;
        }

        update_post_meta($formId, 'wppayform_form_scheduling_settings', $settings);
        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public function checkRestrictionHooks()
    {
        add_filter('wppayform/form_wrapper_css_classes', array($this, 'checkRestrictionOnRender'), 10, 2);
        add_filter('wppayform/form_submission_validation_errors', array($this, 'validateForm'), 100, 2);
    }

    public function validateForm($errors, $formId)
    {
        if ($errors) {
            return $errors;
        }
        if (!get_post_meta($formId, 'wppayform_form_scheduling_settings', true)) {
            return $errors;
        }
        $sheduleSettings = Form::getSchedulingSettings($formId);
        $errorMessage = '';
        if ($message = $this->checkIfExceedsEntryLimit($formId, $sheduleSettings)) {
            $errorMessage = $message;
        } elseif ($timeMessage = $this->checkTimeSchedulingValidityError($formId, $sheduleSettings)) {
            $errorMessage = $timeMessage;
        } elseif ($message = $this->checkLoginValidityError($formId, $sheduleSettings)) {
            $errorMessage = $message;
        }
        if ($errorMessage) {
            $errors[] = $errorMessage;
        }
        return $errors;
    }

    public function checkRestrictionOnRender($wrapperCSSClasses, $form)
    {
        // if now sheduleing settings found then just return
        if (!get_post_meta($form->ID, 'wppayform_form_scheduling_settings', true)) {
            return $wrapperCSSClasses;
        }
        $extra_css_class = '';
        // We have some schedule settings now so we have add some wrapper class
        $sheduleSettings = $form->scheduleing_settings;
        if ($message = $this->checkIfExceedsEntryLimit($form->ID, $sheduleSettings)) {
            $extra_css_class = 'wpf_exceeds_entry_limit';
            $this->addErrorMessage($form->ID, $message);
        } elseif ($timeMessage = $this->checkTimeSchedulingValidityError($form->ID, $sheduleSettings)) {
            $extra_css_class = 'wpf_time_schedule_fail';
            $this->addErrorMessage($form->ID, $timeMessage);
        } elseif ($message = $this->checkLoginValidityError($form->ID, $sheduleSettings)) {
            $extra_css_class = 'wpf_logged_in_required';
            $this->addErrorMessage($form->ID, $message);
        }

        if ($extra_css_class) {
            $wrapperCSSClasses[] = $extra_css_class;
            $wrapperCSSClasses[] = 'wpf_restriction_action_' . $sheduleSettings['restriction_applied_type'];
        }
        return $wrapperCSSClasses;
    }

    private function checkIfExceedsEntryLimit($formId, $sheduleSettings)
    {
        if (Arr::get($sheduleSettings, 'limitNumberOfEntries.status') == 'yes') {
            $limitEntrySettings = Arr::get($sheduleSettings, 'limitNumberOfEntries');
            $limitPeriod = Arr::get($limitEntrySettings, 'limit_type');
            $numberOfEntries = Arr::get($limitEntrySettings, 'number_of_entries');
            $paymentStatuses = Arr::get($limitEntrySettings, 'limit_payment_statuses');
            $submissionModel = new Submission();
            $totalEntryCount = $submissionModel->getEntryCountByPaymentStatus($formId, $paymentStatuses, $limitPeriod);
            if ($totalEntryCount >= intval($numberOfEntries)) {
                return $limitEntrySettings['limit_exceeds_message']
                    ? $limitEntrySettings['limit_exceeds_message']
                    : __('Submission limit has been excceded.', 'wppayform');
            }
        }
        return false;
    }

    private function checkTimeSchedulingValidityError($formId, $sheduleSettings)
    {
        if (Arr::get($sheduleSettings, 'scheduleForm.status') == 'yes') {
            $timeSchedule = Arr::get($sheduleSettings, 'scheduleForm');
            $time = time();
            $start = strtotime($timeSchedule['start_date']);
            $end = strtotime($timeSchedule['end_date']);
            if ($time < $start) {
                return $timeSchedule['before_start_message']
                    ? $timeSchedule['before_start_message']
                    : __('Form submission is not started yet.', 'wppayform');
            }
            if ($time >= $end) {
                return $timeSchedule['expire_message']
                    ? $timeSchedule['expire_message']
                    : __('Form submission is now closed.', 'wppayform');
            }
        }

        return false;
    }

    private function checkLoginValidityError($formId, $sheduleSettings)
    {
        if (Arr::get($sheduleSettings, 'requireLogin.status') == 'yes') {
            if (!is_user_logged_in()) {
                $msg = Arr::get($sheduleSettings, 'requireLogin.message');
                return !empty($msg)
                    ? $msg
                    : __('You must be logged in to submit the form.', 'wppayform');
            }
        }

        return false;
    }

    private function addErrorMessage($formId, $message = '')
    {
        if ($message) {
            add_action('wppayform/form_render_after_' . $formId, function ($form) use ($message) {
                echo '<div class="wpf_form_notices wpf_form_notice_error wpf_form_restrictuon_errors">' . $message . '</div>';
            });
        }
    }
}
