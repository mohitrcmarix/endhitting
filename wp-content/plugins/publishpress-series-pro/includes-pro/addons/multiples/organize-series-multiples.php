<?php
require PPSERIES_PATH . '/vendor/autoload.php';

$orgseries_mult_ver  = '1.4.1.rc.000';
$os_multi_plugin_dir = PPSERIES_PATH.'includes-pro/addons/multiples/';
$os_multi_plugin_url = PPSERIES_URL.'includes-pro/addons/multiples/';

//let's set some constants
if (!defined('OS_MULTI_VER')) {
define('OS_MULTI_PATH', $os_multi_plugin_dir);
define('OS_MULTI_URL', $os_multi_plugin_url);
define('OS_MULTI_VER', $orgseries_mult_ver); //make sure the version number is available everywhere.
}


/**
 * This takes allows OS core to take care of the PHP version check
 * and also ensures we're only using the new style of bootstrapping if the verison of OS core with it is active.
 */
add_action('AHOS__bootstrapped', function() use ($os_multi_plugin_dir){
    require $os_multi_plugin_dir . 'bootstrap.php';
});

//fallback on loading legacy-includes.php in case the bootstrapped stuff isn't ready yet.
require_once OS_MULTI_PATH . 'legacy-includes.php';
