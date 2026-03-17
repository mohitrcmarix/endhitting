<?php

namespace WPPayForm\App\Services\Integrations\Slack;

use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\Framework\Foundation\App;

class SlackNotificationActions
{
    public function __construct()
    {
        // add_filter('wppayform_notifying_async_slack', '__return_false');
    }

    public function register()
    {
        add_filter('wppayform_global_notification_active_types', function ($types) {
            $isEnabled = GeneralSettings::isSlackEnabled();
            if ($isEnabled) {
                $types['slack'] = 'slack';
            }
            return $types;
        });
        add_action('wppayform_integration_notify_slack', array($this, 'notify'), 20, 4);
    }

    public function notify($feed, $formData, $entry, $formId)
    {
        $i = 1;
        $isEnabled = GeneralSettings::isSlackEnabled();
        if (!$isEnabled) {
            return;
        }
        $response = Slack::handle($feed, $formData, $entry, $formId);

        if ($response['status'] === 'success') {
            do_action('wppayform_log_data', [
                'form_id' => $formId,
                'submission_id' => $entry->id,
                'type' => 'success',
                'created_by' => 'PayForm BOT',
                'title' => 'Slack',
                'content' => $response['message']
            ]);
        } else {
            do_action('wppayform_log_data', [
                'form_id' => $formId,
                'submission_id' => $entry->id,
                'type' => 'failed',
                'created_by' => 'PayForm BOT',
                'title' => 'Slack',
                'content' => $response['message']
            ]);
        }
    }
}
