<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Offline;

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\AccessControl;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class OfflineSettings
{
    public function getPaymentSettings()
    {
        $defaults = [
            'payment_mode' => 'test',
        ];

        return wp_parse_args(get_option('wppayform_offline_payment_settings', []), $defaults);
    }

    public function savePaymentSettings($request)
    {
        AccessControl::checkAndPresponseError('set_payment_settings', 'global');
        $settings = $request->settings;
        $mode = Arr::get($settings, 'payment_mode');

        $data = array(
            'payment_mode' => sanitize_text_field($mode),
        );

        do_action('wppayform/before_save_offline_settings', $data);
        update_option('wppayform_offline_payment_settings', $data, false);
        do_action('wppayform/after_save_offline_settings', $data);

        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }
}
