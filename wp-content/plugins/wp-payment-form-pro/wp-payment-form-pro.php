<?php

/**
 * Plugin Name: WPPayForm Pro
 * Plugin URI:  https://wppayform.wpmanageninja.com/
 * Description: Create and Accept Payments in minutes with Stripe, PayPal with built-in form builder
 * Author: WPManageNinja LLC
 * Author URI:  https://wpmanageninja.com
 * Version: 3.0.1
 * Text Domain: wppayform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPPAYFORM_VERSION_LITE')) {
    define('WPPAYFORM_PRO_INSTALLED', true);
    define('WPPAYFORM_VERSION', '3.0.1');
    define('WPPAYFORM_DB_VERSION', 120);
    // Stripe API version should be in 'YYYY-MM-DD' format.
    define('WPPAYFORM_STRIPE_API_VERSION', '2019-05-16');
    define('WPPAYFORM_MAIN_FILE', __FILE__);
    define('WPPAYFORM_URL', plugin_dir_url(__FILE__));
    define('WPPAYFORM_DIR', plugin_dir_path(__FILE__));

    if (!defined('WPPAYFORM_UPLOAD_DIR')) {
        define('WPPAYFORM_UPLOAD_DIR', '/wppayform');
    }

    require __DIR__ . '/vendor/autoload.php';

    call_user_func(function ($bootstrap) {
        $bootstrap(__FILE__);
    }, require(__DIR__ . '/boot/app.php'));
} else {
    add_action('admin_notices', function () {
        $class = 'notice notice-error';
        $message = 'Please deactivate WPPayForm Free version';
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    });
}
