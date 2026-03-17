<?php
/**
 * @package     PublishPressAuthorsPro
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PPAuthorsPro;

defined('ABSPATH') or die('No direct script access allowed.');

class Plugin
{
    /**
     * Constant for valid status
     */
    const LICENSE_STATUS_VALID = 'valid';

    /**
     * Constant for invalid status
     */
    const LICENSE_STATUS_INVALID = 'invalid';

    /**
     * @var Installer
     */
    private $installer;

    public function __construct()
    {
        $container = Factory::getContainer();

        $this->installer = $container['installer'];
    }

    public function init()
    {
        add_filter('ppma_module_dirs', [$this, 'filterModuleDirs']);
        add_filter('pp_multiple_authors_default_options', [$this, 'filterDefaultOptions']);
        add_action('admin_init', [$this, 'loadUpdater']);
        add_action('init', [$this, 'actionInit']);
        add_filter('cme_multiple_authors_capabilities', [$this, 'filterCMECapabilities'], 30);
        add_filter('plugin_row_meta', [$this, 'add_pro_plugin_meta'], 10, 2);

        $this->installer->init();
    }

    /**
     * @param array $dirs
     * @return array
     */
    public function filterModuleDirs($dirs)
    {
        $dirs['author-custom-fields'] = PP_AUTHORS_PRO_MODULES_PATH;
        $dirs['author-custom-layouts'] = PP_AUTHORS_PRO_MODULES_PATH;
        $dirs['prosettings'] = PP_AUTHORS_PRO_MODULES_PATH;
        $dirs['automap-author'] = PP_AUTHORS_PRO_MODULES_PATH;
        $dirs['shortcode-authors-list'] = PP_AUTHORS_PRO_MODULES_PATH;
        $dirs['reviews-pro'] = PP_AUTHORS_PRO_MODULES_PATH;

        if ($this->isBuddyPressInstalled()) {
            $dirs['buddypress-integration'] = PP_AUTHORS_PRO_MODULES_PATH;
        }

        return $dirs;
    }

    public function filterDefaultOptions($defaultOptions)
    {
        $defaultOptions['license_key'] = '';
        $defaultOptions['license_status'] = self::LICENSE_STATUS_INVALID;
        $defaultOptions['display_branding'] = 'on';

        return $defaultOptions;
    }

    /**
     * Load the update manager.
     *
     * @return mixed
     */
    public function loadUpdater()
    {
        $container = Factory::getContainer();

        return $container['edd_container']['update_manager'];
    }

    /**
     * Register the taxonomy used for managing relationships,
     * and the custom post type to store the author data.
     */
    public function actionInit()
    {
        // Allow PublishPress Authors to be easily translated
        load_plugin_textdomain(
            'publishpress-authors-pro',
            null,
            plugin_basename(PP_AUTHORS_PRO_BASE_PATH) . '/languages/'
        );
    }

    public function filterCMECapabilities($capabilities)
    {
        $capabilities = array_merge(
            $capabilities,
            [
                'ppma_manage_custom_fields',
                'ppma_manage_layouts',
            ]
        );

        return $capabilities;
    }

    public function isBuddyPressInstalled()
    {
        return function_exists('bp_is_active') && function_exists('bp_core_get_userlink');
    }

    /**
     * Add Authors and Settings to plugin row meta
     *
     * @param array $links
     * @param string $file
     * 
     * @return array
     */
    public function add_pro_plugin_meta($links, $file) 
    {
        if ($file == plugin_basename(PP_AUTHORS_PRO_FILE)) {
            $links[] = '<a href="'. esc_url(admin_url('edit-tags.php?taxonomy=author')) .'">' . esc_html__('Authors', 'publishpress-authors') . '</a>';
            $links[] = '<a href="'. esc_url(admin_url('admin.php?page=ppma-modules-settings')) .'">' . esc_html__('Settings', 'publishpress-authors') . '</a>';
        }

        return $links;
    }
}
