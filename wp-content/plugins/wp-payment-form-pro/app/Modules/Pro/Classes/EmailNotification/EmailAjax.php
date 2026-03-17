<?php

namespace WPPayForm\App\Modules\Pro\Classes\EmailNotification;

use WPPayForm\App\Services\FormPlaceholders;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax Handler Class for Email Notification
 * @since 1.0.0
 */
class EmailAjax
{
    public function getNotifications($formId)
    {
        $notifications = get_post_meta($formId, 'wpf_email_notifications', true);
        if (!$notifications) {
            $notifications = array();
        }

        $notificationActions = array(
            'wppayform/after_form_submission_complete' => array(
                'hook_name' => 'wppayform/after_form_submission_complete',
                'hook_title' => 'After Form Submission',
                'description' => 'Send email when the form will be submitted. Please note that, If you select this, Email will be sent even form payment (if any) failed'
            ),
            'wppayform/form_payment_success' => array(
                'hook_name' => 'wppayform/form_payment_success',
                'hook_title' => 'On Payment Success',
                'description' => 'This email will be sent after payment successfully made (if you have payment enabled)'
            ),
            'wppayform/manual_trigger_notification' => array(
                'hook_name' => 'wppayform/manual_trigger_notification',
                'hook_title' => 'Manual Notification',
                'description' => 'This email should be trigger manually'
            )
        );

        $notificationActions = apply_filters('wppayform/email_notification_actions', $notificationActions, $formId);

        wp_send_json_success(array(
            'notifications' => $notifications,
            'merge_tags' => FormPlaceholders::getAllPlaceholders($formId),
            'notification_actions' => array_values($notificationActions)
        ), 200);
    }

    public function saveNotifications($request, $formId)
    {
        $notifications = wp_unslash($request->notifications);
        update_post_meta($formId, 'wpf_email_notifications', $notifications);

        return array(
            'message' => __('Email Notifications has been updated', 'wppayform')
        );
    }

    public function getNotificationsOnly($formId)
    {
        $notifications = get_post_meta($formId, 'wpf_email_notifications', true);

        $notifier = [];
        foreach ($notifications as $id => $item) {
            if ($item['status'] == 'active') {
                array_push($notifier, array(
                    "name" => $item['title'],
                    "id" => $id
                ));
            }
        }
        return array(
            'notifications' => $notifier
        );
    }

    public function resendNotifications($request, $formId)
    {
        $submissionId = intval($request->submission_id);
        $info = $request->info;
        $type = $request->type;
        $submissions = $request->submissions;
        $mailCount = 0;
        if (isset($submissions) && !empty($submissions)) {
            foreach ($submissions as $submissionId) {
                $send = (new ResendEmail())->initEmailHooks($formId, $submissionId, $info);
                $mailCount ++;
            }
        } else {
            $send = (new ResendEmail())->initEmailHooks($formId, $submissionId, $info);
            $mailCount++;
        }

        return array(
            'success' => $send,
            'data' => 'Email Notification broadcasted to '. $mailCount . ' contact!'
        );
    }
}
