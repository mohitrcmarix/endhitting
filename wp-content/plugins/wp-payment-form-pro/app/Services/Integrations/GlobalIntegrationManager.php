<?php

namespace WPPayForm\App\Services\Integrations;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\Meta;
use WPPayForm\App\Models\ScheduledActions;
use WPPayForm\App\Services\FormPlaceholders;
use WPPayForm\Framework\Support\Arr;

class GlobalIntegrationManager
{
    public function getGlobalSettingsData($request)
    {
        $settingsKey = sanitize_text_field($request->settings_key);
        $settings = apply_filters('wppayform_global_integration_settings_' . $settingsKey, []);
        $fieldSettings = apply_filters('wppayform_global_integration_fields_' . $settingsKey, []);

        if (!$fieldSettings) {
            wp_send_json_error([
                'settings' => $settings,
                'settings_key' => $settingsKey,
                'message' => __('Sorry! No integration failed found with: ', 'wppayform') . $settingsKey
            ], 423);
        }

        if (empty($fieldSettings['save_button_text'])) {
            $fieldSettings['save_button_text'] = __('Save Settings', 'wppayform');
        }

        if (empty($fieldSettings['valid_message'])) {
            $fieldSettings['valid_message'] = __('Your API Key is valid', 'wppayform');
        }

        if (empty($fieldSettings['invalid_message'])) {
            $fieldSettings['invalid_message'] = __('Your API Key is not valid', 'wppayform');
        }

        wp_send_json_success([
            'integration' => $settings,
            'settings' => $fieldSettings
        ], 200);
    }

    public function saveGlobalSettingsData($request)
    {
        $settingsKey = sanitize_text_field($request->settings_key);
        $integration = wp_unslash($request->integration);
        do_action('wppayform_save_global_integration_settings_' . $settingsKey, $integration);

        // Someone should catch that above action and send response
        wp_send_json_error([
            'message' => __('Sorry, no Integration found. Please make sure that latest version of WPPayForm pro installed', 'wppayform')
        ], 423);
    }

    public function getAllFormIntegrations($formId)
    {
        $formattedFeeds = $this->getNotificationFeeds($formId);

        $availableIntegrations = apply_filters('wppayform_get_available_form_integrations', [], $formId);

        return [
            'feeds' => $formattedFeeds,
            'available_integrations' => $availableIntegrations,
            'all_module_config_url' => admin_url('admin.php?page=wppayform.php#/integrations')
        ];
    }

    public function getNotificationFeeds($formId)
    {
        $notificationKeys = apply_filters('wppayform_global_notification_types', [], $formId);
        if ($notificationKeys) {
            $feeds = Meta::where('form_id', $formId)
                ->whereIn('meta_key', $notificationKeys)
                ->orderBy('id', 'DESC')
                ->get();
        } else {
            $feeds = [];
        }

        $formattedFeeds = [];

        foreach ($feeds as $feed) {
            $data = json_decode($feed->meta_value, true);
            $enabled = $data['enabled'];
            if ($enabled && $enabled == 'true') {
                $enabled = true;
            } elseif ($enabled == 'false') {
                $enabled = false;
            }
            $feedData = [
                'id' => $feed->id,
                'name' => Arr::get($data, 'name'),
                'enabled' => $enabled,
                'provider' => $feed->meta_key,
                'feed' => $data
            ];
            $feedData = apply_filters('wppayform_global_notification_feed_' . $feed->meta_key, $feedData, $formId);
            $formattedFeeds[] = $feedData;
        }
        return $formattedFeeds;
    }

    public function updateNotificationStatus($formId, $request)
    {
        $notificationId = $request->notification_id;
        $status = $request->status;

        $feed = Meta::where('form_id', intval($formId))
            ->where('id', intval($notificationId))
            ->first();

        $notification = json_decode($feed->meta_value, true);

        if (!$status) {
            $notification['enabled'] = false;
        } else {
            $notification['enabled'] = true;
        }

        Meta::where('form_id', intval($formId))
            ->where('id', intval($notificationId))
            ->update([
                'meta_value' => json_encode($notification, JSON_NUMERIC_CHECK)
            ]);

        $feed = Meta::where('form_id', intval($formId))
            ->where('id', intval($notificationId))
            ->first();


        return [
            'message' => __('Integration successfully updated', 'wppayform')
        ];
    }

    public function getIntegrationSettings($formId, $request)
    {
        $integrationName = $request->get('integration_name');
        $integrationId = intval($request->get('integration_id'));

        $settings = [
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true,
            'list_id' => '',
            'list_name' => '',
            'name' => '',
            'merge_fields' => []
        ];

        $mergeFields = false;
        if ($integrationId) {
            $feed = Meta::where('form_id', $formId)
                ->where('id', $integrationId)
                ->first();

            if ($feed->meta_value) {
                $settings = json_decode($feed->meta_value, true);

                $settings = apply_filters('wppayform_get_integration_values_' . $integrationName, $settings, $feed, $formId);
                if (!empty($settings['list_id'])) {
                    $mergeFields = apply_filters('wppayform_get_integration_merge_fields_' . $integrationName, false, $settings['list_id'], $formId);
                }
            }
        } else {
            $settings = apply_filters('wppayform_get_integration_defaults_' . $integrationName, $settings, $formId);
        }

        if ($settings['enabled'] == 'true') {
            $settings['enabled'] = true;
        } elseif ($settings['enabled'] == 'false' || $settings['enabled']) {
            $settings['enabled'] = false;
        }

        $settingsFields = apply_filters('wppayform_get_integration_settings_fields_' . $integrationName, [], $formId, $settings);

        $shortCodes = FormPlaceholders::getAllShortCodes($formId);

        $inputs = Form::getInputShortcode($formId);

        return [
            'settings' => $settings,
            'settings_fields' => $settingsFields,
            'shortcodes' => $shortCodes,
            'inputs' => $inputs,
            'merge_fields' => $mergeFields
        ];
    }

    public function saveIntegrationSettings($formId, $request)
    {
        $integrationName = $request->get('integration_name');
        $integrationId = intval($request->get('integration_id'));

        if ($request->get('data_type') == 'stringify') {
            $integration = \json_decode($request->get('integration'), true);
        } else {
            $integration = wp_unslash($request->integration);
        }

        if ($integration['enabled'] && $integration['enabled'] == 'true') {
            $integration['status'] = true;
        }

        if (!$integration['name']) {
            wp_send_json_error([
                'message' => 'Validation Failed',
                'errors' => [
                    'name' => ['Feed name is required']
                ]
            ], 423);
        }

        $integration = apply_filters('wppayform_save_integration_value_' . $integrationName, $integration, $integrationId, $formId);

        $data = [
            'form_id' => $formId,
            'meta_key' => $integrationName . '_feeds',
            'meta_value' => \json_encode($integration)
        ];

        $data = apply_filters('wppayform_save_integration_settings_' . $integrationName, $data, $integrationId);

        $created = false;
        if ($integrationId) {
            Meta::where('form_id', $formId)
                ->where('id', $integrationId)
                ->update($data);
        } else {
            $created = true;
            $integrationId = Meta::insert($data);
        }

        return [
            'message' => __('Integration successfully saved', 'wppayform'),
            'integration_id' => $integrationId,
            'integration_name' => $integrationName,
            'created' => $created
        ];
    }

    public function deleteIntegrationFeed($formId, $request)
    {
        $integrationId = intval($request->get('integration_id'));

        Meta::where('form_id', $formId)
            ->where('id', $integrationId)
            ->delete();

        return [
            'message' => __('Selected integration feed successfully deleted', 'wppayform')
        ];
    }

    public function getIntegrationList($formId, $request)
    {
        $integrationName = $request->get('integration_name');
        $listId = $request->get('list_id');

        $merge_fields = apply_filters('wppayform_get_integration_merge_fields_' . $integrationName, false, $listId, $formId);

        return [
            'merge_fields' => $merge_fields
        ];
    }

    public function chainedData($request)
    {
        do_action('wppayform_chained_' . $request->route, $request);
    }

    public static function migrate()
    {
        $metaMigrated = Meta::migrate();
        $scheduleMigrated = ScheduledActions::migrate();

        if ($metaMigrated && $scheduleMigrated) {
            update_option('wppayform_integration_status', 'yes', 'no');
            return array(
                "status" => true,
                "message" => "Activated, Please reload this page."
            );
        } else {
            return array(
                "status" => false,
                "message" => "Something went wrong, Please reload this page."
            );
        }
    }
}
