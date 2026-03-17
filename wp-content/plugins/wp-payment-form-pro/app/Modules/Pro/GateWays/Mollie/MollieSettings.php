<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Mollie;

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\AccessControl;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class MollieSettings
{
    public function getSettings()
    {
        $defaults = [
            'payment_mode' => 'test',
            'test_api_key' => '',
            'live_api_key' => ''
        ];

        return wp_parse_args(get_option('wppayform_mollie_payment_settings', []), $defaults);
    }

    public function saveSettings($request)
    {
        AccessControl::checkAndPresponseError('set_payment_settings', 'global');
        $settings = $request->settings;
        // Validate the data first
        $mode = Arr::get($settings, 'payment_mode');

        if ($mode == 'test') {
            // We require test keys
            if (empty(Arr::get($settings, 'test_api_key'))) {
                wp_send_json_error(array(
                    'message' => __('Please provide Test Publishable key and Test Secret Key', 'wppayform')
                ), 423);
            }
        }

        if ($mode == 'live') {
            if (empty(Arr::get($settings, 'live_api_key'))) {
                wp_send_json_error(array(
                    'message' => __('Please provide Live Publishable key and Live Secret Key', 'wppayform')
                ), 423);
            }
        }

        // Validation Passed now let's make the data
        $data = array(
            'payment_mode' => sanitize_text_field($mode),
            'live_api_key' => sanitize_text_field(Arr::get($settings, 'live_api_key')),
            'test_api_key' => sanitize_text_field(Arr::get($settings, 'test_api_key')),
        );

        do_action('wppayform/before_save_mollie_settings', $data);
        update_option('wppayform_mollie_payment_settings', $data, false);
        do_action('wppayform/after_save_mollie_settings', $data);

        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public function isLive($formId = false)
    {
        $settings = $this->getSettings();
        return $settings['payment_mode'] == 'live';
    }

    public function getApiKey($formId = false)
    {
        $isLive = $this->isLive($formId);
        $settings = $this->getSettings();

        if ($isLive) {
            return $settings['live_api_key'];
        }

        return $settings['test_api_key'];
    }
}
