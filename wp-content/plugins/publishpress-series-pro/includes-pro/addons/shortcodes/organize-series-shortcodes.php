<?php
require PPSERIES_PATH . '/vendor/autoload.php';

$os_shortcodes_ver       = '1.3.4.rc.000';
$os_shortcode_plugin_dir =  PPSERIES_PATH.'includes-pro/addons/shortcodes/';
$os_shortcode_plugin_url = PPSERIES_URL.'includes-pro/addons/shortcodes/';

//let's setup constants
if (!defined('OS_SHORTCODE_VER')) {
define('OS_SHORTCODE_VER', $os_shortcodes_ver );
define('OS_SHORTCODE_PATH', $os_shortcode_plugin_dir );
define('OS_SHORTCODE_URL', $os_shortcode_plugin_url);
}

/**
 * This allows OS core to take care of the PHP version check
 * and also ensures we're only using the new style of bootstrapping if the version of OS core with it is active.
 */
add_action('AHOS__bootstrapped', function($os_shortcode_plugin_dir) {
    require $os_shortcode_plugin_dir . 'bootstrap.php';
});

//fallback on loading legacy-includes.php in case the bootstrapped stuff isn't ready yet.
require_once $os_shortcode_plugin_dir . 'legacy-includes.php';
