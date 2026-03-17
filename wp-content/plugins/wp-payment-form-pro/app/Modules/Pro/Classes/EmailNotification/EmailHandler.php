<?php

namespace WPPayForm\App\Modules\Pro\Classes\EmailNotification;

use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Modules\Pro\Classes\Emogrifier\Emogrifier;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\Framework\Foundation\App;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Handler Class for Email Notification
 * @since 1.0.0
 */
class EmailHandler
{
    public function register()
    {
        add_action('wppayform/form_submission_activity_start', array($this, 'initEmailHooks'));
        add_action('wppayform/send_email_notification', array($this, 'processEmailNotification'), 10, 3);
    }

    public function initEmailHooks($formId)
    {
        $notifications = get_post_meta($formId, 'wpf_email_notifications', true);
        if (!$notifications) {
            return;
        }

        // Let's filter the notifications
        $validNotifiations = array();
        foreach ($notifications as $notification) {
            $status = Arr::get($notification, 'status');
            if ($status != 'active') {
                continue;
            }
            $action = Arr::get($notification, 'sending_action');
            if (!isset($validNotifiations[$action])) {
                $validNotifiations[$action] = array();
            }
            $validNotifiations[$action][] = $notification;
        }

        if (empty($validNotifiations)) {
            return;
        }


        foreach ($validNotifiations as $notifiationAction => $notifiationInfos) {
            add_action($notifiationAction, function ($submission) use ($notifiationInfos) {
                foreach ($notifiationInfos as $notifiationData) {
                    do_action('wppayform/send_email_notification', $notifiationData, $submission);
                }
            });
        }
    }

    public function processEmailNotification($notifiation, $submission, $type = "auto")
    {
        $emailBody = Arr::get($notifiation, 'email_body');
        if (strpos($emailBody, '[wppayform_reciept]') !== false) {
            $notifiation['email_body'] = str_replace('[wppayform_reciept]', '{submission.payment_receipt}', $emailBody);
        }

        do_action('wppayform/require_entry_html');

        $notification = PlaceholderParser::parseArray($notifiation, $submission);

        $notifiation = apply_filters('wppayform/email_notification_before_send', $notification, $submission);
        do_action('wppayform/require_entry_html_done');

        if (!$notification['email_to'] || !$notification['email_subject'] || !$notification['email_body']) {
            SubmissionActivity::createActivity(array(
                'form_id' => $submission->form_id,
                'submission_id' => $submission->id,
                'type' => 'info',
                'created_by' => 'PayForm BOT',
                'content' => 'Email can not be sent, because email to / email subject / email body is empty.'
            ));
            return;
        }

        $notification['email_body'] = $this->getEmailWithTemplate($notification['email_body'], $submission, $notifiation);
        $headers = $this->getEmailHeader($notifiation);

        if ($type === "manual") {
            $this->sendErrorMsg();
        } else {
            $this->saveErrorActivity($notification, $submission);
        }

        $result = wp_mail(
            $notification['email_to'],
            $notification['email_subject'],
            $notification['email_body'],
            $headers
        );

        if ($result) {
            SubmissionActivity::createActivity(array(
                'form_id' => $submission->form_id,
                'submission_id' => $submission->id,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'content' => "Email Notification broadcasted to {$notification['email_to']} and the subject: {$notification['email_subject']}."
            ));
        };

        return $result;
    }

    public function sendErrorMsg()
    {
        add_action('wp_mail_failed', function ($error) {
            wp_send_json_error($error->get_error_message());
        }, 10, 1);
    }

    public function saveErrorActivity($notification, $submission)
    {
        add_action('wp_mail_failed', function ($error) use ($notification, $submission) {
            $failedMailSubject = Arr::get($error->error_data, 'wp_mail_failed.subject');
            if ($failedMailSubject == $notification['email_subject']) {
                $reason = $error->get_error_message();
                SubmissionActivity::createActivity(array(
                    'form_id' => $submission->form_id,
                    'submission_id' => $submission->id,
                    'type' => 'error',
                    'created_by' => 'PayForm BOT',
                    'content' => "Email Notification failed to sent subject: {$notification['email_subject']}. <br/>Reason: " . $reason
                ));
            }
        }, 10, 1);
    }

    public function getEmailHeader($notification)
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];

        $formName = Arr::get($notification, 'from_name');
        $formEmail = Arr::get($notification, 'from_email');

        if ($formName && $formEmail) {
            $headers[] = "From: {$formName} <{$formEmail}>";
        } elseif ($formEmail) {
            $headers[] = "From: <{$formEmail}>";
        } elseif ($formName) {
            $headers[] = "From: {$formName}";
        }

        $bcc = Arr::get($notification, 'bcc_to');
        if ($bcc) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        $cc = Arr::get($notification, 'cc_to');
        if ($cc) {
            $headers[] = 'Cc: ' . $cc;
        }

        if (!empty($notification['reply_to'])) {
            $headers[] = "Reply-To: <{$notification['reply_to']}>";
        }

        return $headers;
    }

    public function getEmailWithTemplate($emailBody, $submission, $notification)
    {
        $originalEmailBody = $emailBody;
        ob_start();
        $emailHeader = apply_filters('wppayform/email_header', '', $submission, $notification);
        $emailFooter = apply_filters('wppayform/email_footer', '', $submission, $notification);

        $app = App::getInstance();
        if (empty($emailHeader)) {
            $emailHeader = $app->view->make('email.default.header', array(
                'submission' => $submission,
                'notification' => $notification
            ));
        }

        if (empty($emailFooter)) {
            $emailFooter = $app->view->make('email.default.footer', array(
                'submission' => $submission,
                'notification' => $notification
            ));
        }

        $css = $app->view->make('email.default.styles');
        $css = apply_filters('wppayform/email_styles', $css, $submission, $notification);
        $emailBody = $emailHeader . $emailBody . $emailFooter;
        try {
            // apply CSS styles inline for picky email clients
            $emogrifier = new Emogrifier($emailBody, $css);
            $emailBody = $emogrifier->emogrify();
        } catch (Exception $e) {
        }

        $maybeError = ob_get_clean();
        if ($maybeError) {
            return $originalEmailBody;
        }

        return $emailBody;
    }
}