<?php

namespace WPPayForm\App\Modules\Pro\Classes\EmailNotification;

use WPPayForm\App\Models\Submission;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Handler Class for Email Notification
 * @since 1.0.0
 */
class ResendEmail
{
    public function initEmailHooks($formId, $submissionId, $info)
    {
        $notifications = get_post_meta($formId, 'wpf_email_notifications', true);
        if (!$notifications) {
            return;
        }

        // Let's filter the notifications
        $validNotifications = array();
        foreach ($notifications as $notification) {
            $status = Arr::get($notification, 'status');
            $validNotifications[] = $notification;
        }

        if (empty($validNotifications)) {
            return;
        }

        $id = intval($info['notification_id']);
        $submission = (new Submission())->getSubmission($submissionId);

        $notification = $validNotifications[$id];

        if ($info['send_to_type'] == 'custom' && isset($info['send_to_custom_email'])) {
            if (!is_email($info['send_to_custom_email'])) {
                wp_send_json_error(array(
                    'err' => 'Please provide a valid email address.'
                ), 400);
            }
            $notification['email_to'] = sanitize_email($info['send_to_custom_email']);
        }

        return (new EmailHandler())->processEmailNotification($notification, $submission, $type = "manual");
    }
}
