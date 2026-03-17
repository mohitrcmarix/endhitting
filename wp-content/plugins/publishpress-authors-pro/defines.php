<?php
/**
 * File responsible for defining basic general constants used by the plugin.
 *
 * @package     MultipleAuthors
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

if (! defined('ABSPATH')) {
    die('No direct script access allowed.');
}

if (! defined('PP_AUTHORS_PRO_LOADED')) {
    define('PP_AUTHORS_PRO_VERSION', '3.30.1');
    define('PP_AUTHORS_PRO_ITEM_ID', '7203');
    define('PP_AUTHORS_PRO_SITE_URL', 'https://publishpress.com');
    define('PP_AUTHORS_PRO_PLUGIN_AUTHOR', 'PublishPress');
    define('PP_AUTHORS_PRO_FILE', 'publishpress-authors-pro/publishpress-authors-pro.php');
    define('PP_AUTHORS_PRO_BASE_PATH', plugin_dir_path(__FILE__));
    define('PP_AUTHORS_PRO_MODULES_PATH', PP_AUTHORS_PRO_BASE_PATH . 'modules');
    define('PP_AUTHORS_PRO_ASSETS_URL', plugins_url('publishpress-authors-pro/assets'));
    define('PP_AUTHORS_PRO_URL', plugins_url('/', __FILE__));
    define('PP_AUTHORS_PRO_BASENAME', plugin_basename(PP_AUTHORS_PRO_BASE_PATH));
    define('PP_AUTHORS_PRO_FREE_PLUGIN_PATH', __DIR__ . '/vendor/publishpress/publishpress-authors');
    define('PP_AUTHORS_PRO_AUTOLOAD_CLASS_NAME', 'composerRequiref4b242e273fa91452b1f0abcc0dee0c2');

    if (! defined('PUBLISHPRESS_AUTHORS_LOAD_LEGACY_SHORTCODES')) {
        define('PUBLISHPRESS_AUTHORS_LOAD_LEGACY_SHORTCODES', true);
    }

    define('PP_AUTHORS_PRO_LOADED', 1);
}
