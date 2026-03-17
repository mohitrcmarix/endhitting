<?php

use MultipleAuthors\Classes\Legacy\LegacyPlugin;
use MultipleAuthors\Classes\Legacy\Module;
use PPAuthorsPro\Factory;

/**
 * @package     PublishPress\ChecklistsPro
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

/**
 * Class class PPCH_ProSettings extends Module
 *
 * @todo Refactor this module and all the modules system to use DI.
 */
class MA_ProSettings extends Module
{
    const OPTIONS_GROUP_NAME = 'multiple_authors_multiple_authors_options';

    const LICENSE_STATUS_VALID = 'valid';

    const LICENSE_STATUS_INVALID = 'invalid';

    const SETTINGS_SLUG = 'pp-prosettings-prosettings';

    public $module_name = 'prosettings';

    /**
     * Instance for the module
     *
     * @var stdClass
     */
    public $module;

    /**
     * @var LegacyPlugin
     */
    private $legacyPlugin;

    /**
     * @var string
     */
    private $pluginFile;

    /**
     * @var string
     */
    private $pluginVersion;

    /**
     * @var EDDContainer
     */
    private $eddConnector;

    /**
     * @var string
     */
    private $licenseKey;

    /**
     * @var string
     */
    private $licenseStatus;

    /**
     * Construct the PPCH_WooCommerce class
     *
     * @todo: Fix to inject the dependencies in the constructor as params.
     */
    public function __construct()
    {
        $container = Factory::getContainer();

        $this->legacyPlugin = $container['legacy_plugin'];
        $this->eddConnector = $container['edd_container'];
        $this->licenseKey = $container['LICENSE_KEY'];
        $this->licenseStatus = $container['LICENSE_STATUS'];

        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => __('Pro Settings', 'publishpress-authors-pro'),
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-feedback',
            'slug' => 'prosettings',
            'default_options' => [
                'enabled' => 'on',
                'license_key' => '',
                'license_status' => '',
            ],
            'options_page' => false,
            'autoload' => true,
        ];

        $this->module = $this->legacyPlugin->register_module($this->module_name, $args);
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
        $this->setHooks();
    }

    private function setHooks()
    {
        add_action('publishpress_authors_register_settings_before', [$this, 'registerSettings'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_filter('multiple_authors_validate_module_settings', [$this, 'validateModuleSettings']);
        add_filter('pp_authors_show_footer', [$this, 'filterDisplayBranding'], 11);
        add_filter('pp_authors_maintenance_actions', [$this, 'filterMaintenanceActions']);
        add_filter('publishpress_authors_show_blocks_recommendation_banner', [$this, 'filterShowBlocksRecommendationBanner']);
    }

    /**
     * Enqueue scripts and stylesheets for the admin pages.
     */
    public function enqueueAdminScripts()
    {
        wp_enqueue_style(
            'ppa_prosettings_admin',
            plugins_url('/modules/prosettings/assets/css/admin.css', PP_AUTHORS_PRO_FILE),
            [],
            PP_AUTHORS_PRO_VERSION
        );
    }

    public function registerSettings($page, $section)
    {
        add_settings_field(
            'license_key',
            __('License key:', 'publishpress-authors-pro'),
            [$this, 'settingsLicenseKeyOption'],
            $page,
            $section
        );

        add_settings_field(
            'display_branding',
            __('Display PublishPress branding in the admin:', 'publishpress-authors-pro'),
            [$this, 'settingsBrandingOption'],
            $page,
            $section
        );
    }

    public function settingsLicenseKeyOption()
    {
        $container = Factory::getContainer();

        $id = self::OPTIONS_GROUP_NAME . '_license_key';
        $value = isset($container['LICENSE_KEY']) ? $container['LICENSE_KEY'] : '';
        $status = isset($container['LICENSE_STATUS']) ? $container['LICENSE_STATUS'] : self::LICENSE_STATUS_INVALID;

        if (empty($status) || empty($value)) {
            $status = self::LICENSE_STATUS_INVALID;
        }

        if ($status === self::LICENSE_STATUS_VALID) {
            $statusLabel = __('Activated', 'publishpress-authors-pro');
        } else {
            $statusLabel = __('Inactive', 'publishpress-authors-pro');
        }

        echo '<label for="' . esc_attr($id) . '">';
        echo '<input type="text" value="' . esc_attr($value) . '" id="' . esc_attr($id) . '" name="' . esc_attr(
                self::OPTIONS_GROUP_NAME
            ) . '[license_key]"/>';
        echo '<div class="ppa_license_key_status ' . esc_attr(
                $status
            ) . '"><span class="ppa_license_key_status_label">' . esc_html__(
                'Status: ',
                'publishpress-authors-pro'
            ) . '</span>' . esc_html($statusLabel) . '</div>';
        echo '<p class="ppa_settings_field_description">' . esc_html__(
                'Enter the license key for being able to update the plugin.',
                'publishpress-authors-pro'
            ) . '</p>';
        echo '</label>';
    }

    /**
     * Branding options
     *
     * @since 0.7
     */
    public function settingsBrandingOption()
    {
        $id = self::OPTIONS_GROUP_NAME . '_display_branding';

        $options = get_option('multiple_authors_multiple_authors_options');

        $displayBranding = isset($options->display_branding) ? $options->display_branding : 'on';

        echo '<label for="' . esc_attr($id) . '">';
        echo '<input id="' . esc_attr($id) . '" name="'
            . esc_attr(self::OPTIONS_GROUP_NAME) . '[display_branding]"';
        checked($displayBranding, 'on');
        echo ' type="checkbox" value="on" /></label>';
    }

    public function validateModuleSettings($options)
    {
        if (isset($options['license_key'])) {
            if ($this->licenseKey !== $options['license_key'] || empty($this->licenseStatus) || $this->licenseStatus !== self::LICENSE_STATUS_VALID) {
                $options['license_status'] = $this->validateLicenseKey($options['license_key']);
            }
        }

        if (! isset($options['display_branding'])) {
            $options['display_branding'] = 'off';
        }

        return $options;
    }

    public function validateLicenseKey($licenseKey)
    {
        $licenseManager = $this->eddConnector['license_manager'];

        return $licenseManager->validate_license_key($licenseKey, PP_AUTHORS_PRO_ITEM_ID);
    }

    public function filterDisplayBranding($shouldDisplay)
    {
        global $current_screen;

        if ($current_screen->base === 'edit' && $current_screen->post_type === MA_Author_Custom_Fields::POST_TYPE_CUSTOM_FIELDS) {
            $shouldDisplay = true;
        } elseif ($current_screen->base === 'post' && $current_screen->post_type === MA_Author_Custom_Fields::POST_TYPE_CUSTOM_FIELDS) {
            $shouldDisplay = true;
        } elseif ($current_screen->base === 'edit' && $current_screen->post_type === MA_Author_Custom_Layouts::POST_TYPE_LAYOUT) {
            $shouldDisplay = true;
        } elseif ($current_screen->base === 'post' && $current_screen->post_type === MA_Author_Custom_Layouts::POST_TYPE_LAYOUT) {
            $shouldDisplay = true;
        }

        if ($shouldDisplay) {
            $options = get_option('multiple_authors_multiple_authors_options');

            return isset($options->display_branding) ? $options->display_branding === 'on' : true;
        }

        return false;
    }

    public function filterMaintenanceActions($actions)
    {
        $actions['create_default_layouts'] = [
            'title' => __(
                'Create default layouts',
                'publishpress-authors-pro'
            ),
            'description' => __(
                'This action creates the default custom layouts if they don\'t exist or were accidentally deleted.',
                'publishpress-authors-pro'
            ),
            'button_label' => __('Create default layouts', 'publishpress-authors-pro'),
        ];

        return $actions;
    }

    public function filterShowBlocksRecommendationBanner($show)
    {
        return false;
    }
}
