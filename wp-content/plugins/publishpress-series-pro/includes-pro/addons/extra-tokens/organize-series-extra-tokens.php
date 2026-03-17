<?php
if (!defined('OS_ET_VERSION')) {
  define('OS_ET_VERSION', '0.8.3.rc.000');
}
require PPSERIES_PATH . '/vendor/autoload.php';
$plugin_path = PPSERIES_PATH.'includes-pro/addons/extra-tokens/';

/**
 * This takes allows OS core to take care of the PHP version check
 * and also ensures we're only using the new style of bootstrapping if the verison of OS core with it is active.
 */
add_action('AHOS__bootstrapped', function() use ($plugin_path){
    require $plugin_path . 'bootstrap.php';
});

//fallback on loading legacy-includes.php in case the bootstrapped stuff isn't ready yet.
if (! defined('OS_ET_LEGACY_LOADED')) {
    require_once $plugin_path . 'legacy-includes.php';
}
