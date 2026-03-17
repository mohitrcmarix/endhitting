<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Paystack;

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\AccessControl;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class PaystackSettings
{
    public function getPaymentSettings()
    {
        return array(
            'settings' => self::getSettings(),
            'is_key_defined' => self::isPaystackKeysDefined()
        );
    }

    public static function getSettings()
    {
        $settings = get_option('wppayform_paystack_payment_settings', array());
        $defaults = [
            'is_active' => 'no',
            'payment_mode' => 'test',
            'checkout_type' => 'modal',
            'test_api_key' => '',
            'test_api_secret' => '',
            'live_api_key' => '',
            'live_api_secret' => '',
            'payment_channels' => []
        ];
        return wp_parse_args($settings, $defaults);
    }

    public static function isPaystackKeysDefined()
    {
        return defined('WP_PAY_FORM_PAYSTACK_SECRET_KEY') && defined('WP_PAY_FORM_PAYSTACK_PUB_KEY');
    }

    public function savePaymentSettings($request)
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
            'checkout_type' => sanitize_text_field(Arr::get($settings, 'checkout_type')),
            'live_api_key' => sanitize_text_field(Arr::get($settings, 'live_api_key')),
            'live_secret_key' => sanitize_text_field(Arr::get($settings, 'live_secret_key')),
            'test_api_key' => sanitize_text_field(Arr::get($settings, 'test_api_key')),
            'test_secret_key' => sanitize_text_field(Arr::get($settings, 'test_secret_key')),
            'payment_channels' => Arr::get($settings, 'payment_channels')
        );

        do_action('wppayform/before_save_paystack_settings', $data);
        update_option('wppayform_paystack_payment_settings', $data, false);
        do_action('wppayform/after_save_paystack_settings', $data);

        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public static function isLive($formId = false)
    {
        $settings = self::getSettings();
        $mode = Arr::get($settings, 'payment_mode');
        return $mode == 'live';
    }

    public static function getApiKeys($formId = false)
    {
        $isLive = self::isLive($formId);
        $settings = self::getSettings();
        if ($isLive) {
            return array(
                'api_key' => Arr::get($settings, 'live_api_key'),
                'api_secret' => Arr::get($settings, 'live_secret_key')
            );
        }
        return array(
            'api_key' => Arr::get($settings, 'test_api_key'),
            'api_secret' => Arr::get($settings, 'test_secret_key')
        );
    }
}
