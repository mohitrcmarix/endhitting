<?php
require PPSERIES_PATH . '/vendor/autoload.php';

$os_cpt_ver  = '0.2.rc.000';
$os_cpt_path = PPSERIES_PATH.'includes-pro/addons/cpt-support/';
$os_cpt_url  = PPSERIES_URL.'includes-pro/addons/cpt-support/';

//let's define some constants
if (!defined('OS_CPT_VER')) {
define('OS_CPT_PATH', $os_cpt_path);
define('OS_CPT_URL', $os_cpt_url);
define('OS_CPT_VER', $os_cpt_ver);
}

/**
 * This takes allows OS core to take care of the PHP version check
 * and also ensures we're only using the new style of bootstrapping if the verison of OS core with it is active.
 */
add_action('AHOS__bootstrapped', function() use ($os_cpt_path){
    require $os_cpt_path . 'bootstrap.php';
});

//fallback on loading legacy-includes.php in case the bootstrapped stuff isn't ready yet.
require_once OS_CPT_PATH . 'legacy-includes.php';
