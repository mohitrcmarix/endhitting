<?php
$orgseries_groups_ver = '2.2.7.rc.000';
global $orgseries_groups_ver;
require PPSERIES_PATH . '/vendor/autoload.php';

$os_grouping_path = PPSERIES_PATH.'includes-pro/addons/grouping/';
define('OS_GROUPING_VERSION', $orgseries_groups_ver);

/**
 * This takes allows OS core to take care of the PHP version check
 * and also ensures we're only using the new style of bootstrapping if the verison of OS core with it is active.
 */
add_action('AHOS__bootstrapped', function($os_grouping_path) {
    require $os_grouping_path . 'bootstrap.php';
});

//fallback on loading legacy-includes.php in case the bootstrapped stuff isn't ready yet.
if (! defined('OS_GROUPING_LEGACY_LOADED')) {
    require_once $os_grouping_path . 'legacy-includes.php';
}
