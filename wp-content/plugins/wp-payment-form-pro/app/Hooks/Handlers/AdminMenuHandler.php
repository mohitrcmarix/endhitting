<?php

namespace WPPayForm\App\Hooks\Handlers;

use WPPayForm\App\App;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\App\Services\AccessControl;
use WPPayForm\App\Services\CountryNames;
use WPPayForm\App\Modules\AddOnModules\AddOnModule;

class AdminMenuHandler
{
    public function add()
    {
        $menuPermission = AccessControl::hasTopLevelMenuPermission();

        if (!current_user_can($menuPermission)) {
            $accessStatus = AccessControl::giveCustomAccess();

            if ($accessStatus['has_access']) {
                $menuPermission = $accessStatus['role'];
            } else {
                return;
            }
        }

        $title = __('WPPayForms', 'wppayform');
        if (defined('WPPAYFORMHASPRO')) {
            $title .= ' Pro';
        }

        global $submenu;
        add_menu_page(
            $title,
            $title,
            $menuPermission,
            'wppayform.php',
            array($this, 'render'),
            $this->getMenuIcon(),
            25
        );

        if (defined('WPPAYFORM_PRO_INSTALLED')) {
            $license = get_option('_wppayform_pro_license_status');

            if ($license != 'valid') {
                $submenu['wppayform.php']['activate_license'] = array(
                    '<span style="color:#f39c12;">Activate License</span>',
                    $menuPermission,
                    'admin.php?page=wppayform_settings#license',
                    '',
                    'wppayform_license_menu'
                );
            }
        }

        $submenu['wppayform.php']['all_forms'] = array(
            __('All Form', 'wppayform'),
            $menuPermission,
            'admin.php?page=wppayform.php#/',
        );

        $submenu['wppayform.php']['create_new_forms'] = array(
            __('New Form', 'wppayform'),
            $menuPermission,
            'admin.php?page=wppayform.php#/#new-form=1',
        );

        $entriesTitle = 'Entries';
        if (isset($_GET['page']) && in_array($_GET['page'], ['wppayform.php', 'wppayform_settings'])) {
            $entriesCount = 0;
            $entriesCount = (new Submission())->getNewEntriesCount();
            if ($entriesCount) {
                $entriesTitle .= ' <span class="wpf_unread_count" style="background: #e89d2d;color: white;border-radius: 8px;padding: 1px 8px;">' . $entriesCount . '</span>';
            }
        }

        $submenu['wppayform.php']['entries'] = array(
            __($entriesTitle, 'wppayform'),
            $menuPermission,
            'admin.php?page=wppayform.php#/entries',
        );

        add_submenu_page(
            'wppayform.php',
            __('Settings', 'wppayform'),
            __('Settings', 'wppayform'),
            $menuPermission,
            'wppayform_settings',
            array($this, 'renderGlobalSettings')
        );

        $submenu['wppayform.php']['integrations'] = array(
            __('Integrations', 'wppayform'),
            $menuPermission,
            'admin.php?page=wppayform.php#/integrations',
        );

        if (!defined('WPPAYFORM_PRO_INSTALLED')) {
            $submenu['wppayform.php']['upgrade_to_pro'] = array(
                '<span style="color: #e89d2c;">Upgrade To Pro</span>',
                $menuPermission,
                'https://wpmanageninja.com/downloads/wppayform-pro-wordpress-payments-form-builder/?utm_source=plugin&utm_medium=menu&utm_campaign=upgrade',
            );
        }

        $submenu['wppayform.php']['support'] = array(
            __('Support & Debug', 'wppayform'),
            $menuPermission,
            'admin.php?page=wppayform.php#/support',
        );
    }

    public function renderGlobalSettings()
    {
        if (function_exists('wp_enqueue_editor')) {
            add_filter('user_can_richedit', '__return_true');
            wp_enqueue_editor();
            wp_enqueue_media();
        }

        // Fire an event letting others know the current component
        // that wppayform is rendering for the global settings
        // page. So that they can hook and load their custom
        // components on this page dynamically & easily.
        // N.B. native 'components' will always use
        // 'settings' as their current component.
        $currentComponent = apply_filters(
            'wppayform_global_settings_current_component',
            $_REQUEST['page']
        );

        $currentComponent = sanitize_key($currentComponent);
        $components = apply_filters('wppayform_global_settings_components', []);

        $defaultCom = array(
            'payments' => [
                'hash' => 'payments',
                'title' => 'Payments',
                'icon' => '<i class="el-icon-bank-card"></i>'
            ],
            'coupons' => [
                'hash' => 'coupons',
                'title' => 'Coupons',
                'icon' => '<i class="el-icon-discount"></i>'
            ],
            'reCAPTCHA' => [
                'hash' => 're_captcha',
                'title' => 'reCAPTCHA',
                'icon' => '<i class="el-icon-help"></i>'
            ],
            'tool' => [
                'hash' => 'tools',
                'title' => 'Tools',
                'icon' => '<i class="el-icon-s-cooperation"></i>'
            ],
            'licencing' => [
                'hash' => 'license',
                'title' => 'Licensing',
                'icon' => '<i class="dashicons dashicons-category"></i>'
            ],
        );

        $components = array_merge($defaultCom, $components);

        App::make('view')->render('admin.settings.index', [
            'components' => $components,
            'currentComponent' => $currentComponent
        ]);
    }

    public function render()
    {
        $this->enqueueAssets();

        $config = App::getInstance('config');
        $name = $config->get('app.name');
        $slug = 'wppayform';

        App::make('view')->render('admin.menu', compact('name', 'slug'));
    }

    public function renderSettings()
    {
        $this->enqueueAssets();

        $config = App::getInstance('config');
        $name = $config->get('app.name');
        $slug = 'wppayform';

        App::make('view')->render('admin.settings.settings', compact('name', 'slug'));
    }

    public function enqueueAssets()
    {
        $app = App::getInstance();

        $assets = $app['url.assets'];

        $slug = 'wppayform';

        do_action($slug . '_loading_app');

        $wpfPages = ['wppayform.php', 'wppayform_settings'];

        if (isset($_GET['page']) && in_array($_GET['page'], $wpfPages)) {
            if (!apply_filters($slug . '/disable_admin_footer_alter', false)) {
                add_filter('admin_footer_text', function ($text) {
                    $link = 'https://wpmanageninja.com/downloads/wppayform-pro-wordpress-payments-form-builder/';
                    return 'Thank you for using <a target="_blank" href="' . $link . '">WPPayForm</a>';
                });

                add_filter('update_footer', function ($text) {
                    return 'WPPayForm Version ' . WPPAYFORM_VERSION;
                });
            }

            if (function_exists('wp_enqueue_editor')) {
                wp_enqueue_editor();
                wp_enqueue_script('thickbox');
            }
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }

            if ($_GET['page'] === 'wppayform_settings') {
                $this->loadSettingsAssets();
            } else {
                wp_enqueue_script(
                    'wppayform_boot',
                    WPPAYFORM_URL . 'assets/js/payforms-boot.js',
                    array('jquery'),
                    WPPAYFORM_VERSION,
                    true
                );

                // 3rd party developers can now add their scripts here
                do_action($slug . '/booting_admin_app');
                wp_enqueue_script(
                    $slug . '_admin_app',
                    WPPAYFORM_URL . 'assets/js/payforms-admin.js',
                    array('wppayform_boot'),
                    WPPAYFORM_VERSION,
                    true
                );
            }

            wp_enqueue_style(
                $slug . '_admin_app',
                WPPAYFORM_URL . 'assets/css/payforms-admin.css',
                array(),
                WPPAYFORM_VERSION
            );

            $payformAdminVars = apply_filters($slug . '/admin_app_vars', array(
                'i18n' => array(
                    'All Payment Form' => __('All Payment Form', 'wppayform')
                ),
                'wpf_admin_nonce' => wp_create_nonce('wpf_admin_nonce'),
                'paymentStatuses' => GeneralSettings::getPaymentStatuses(),
                'entryStatuses' => GeneralSettings::getEntryStatuses(),
                'image_upload_url' => admin_url('admin-ajax.php?action=wpf_global_settings_handler&route=wpf_upload_image'),
                'forms_count' => Form::getTotalCount(),
                'assets_url' => WPPAYFORM_URL . 'assets/',
                'has_pro' => defined('WPPAYFORMHASPRO') && WPPAYFORMHASPRO,
                'hasValidLicense' => get_option('_wppayform_pro_license_status'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ipn_url' => site_url('?wpf_paypal_ipn=1'),
                'printStyles' => apply_filters($slug . '/print_styles', []),
                'ace_path_url' => WPPAYFORM_URL . 'assets/libs/ace',
                'icon_url' => WPPAYFORM_URL . 'assets/images/icon.png',
                'countries' => CountryNames::getAll(),
                'value_placeholders' => [],
                'slug' => $slug,
                'nonce' => wp_create_nonce('wppayform'),
                'rest' => $this->getRestInfo($app),
                'brand_logo' => $this->getMenuIcon(),
                'asset_url' => $assets,
                'wppayform_addon_modules' => AddOnModule::showAddOns()
            ));

            wp_localize_script($slug . '_boot', 'wpPayFormsAdmin', $payformAdminVars);
        }
    }

    public function loadSettingsAssets()
    {
        wp_enqueue_script(
            'wppayform_boot',
            WPPAYFORM_URL . 'assets/js/settings-app.js',
            array('jquery'),
            WPPAYFORM_VERSION,
            true
        );
    }

    protected function getRestInfo($app)
    {
        $ns = $app->config->get('app.rest_namespace');
        $ver = $app->config->get('app.rest_version');

        return [
            'base_url' => esc_url_raw(rest_url()),
            'url' => rest_url($ns . '/' . $ver),
            'nonce' => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version' => $ver
        ];
    }

    public function renderGlobalMenu()
    {
        App::make('view')->render('admin.global.global_menu', array(
            'brand_logo' => WPPAYFORM_URL . 'assets/images/icon.png'
        ));
    }

    protected function getMenuIcon()
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?><svg enable-background="new 0 0 512 512" version="1.1" viewBox="0 0 512 512" xml:space="preserve" xmlns="http://www.w3.org/2000/svg">
		<path d="m446 0h-380c-8.284 0-15 6.716-15 15v482c0 8.284 6.716 15 15 15h380c8.284 0 15-6.716 15-15v-482c0-8.284-6.716-15-15-15zm-15 482h-350v-452h350v452z" fill="#fff"/>
		<path d="m313 151h-2v-23c0-30.327-24.673-55-55-55s-55 24.673-55 55v23h-2c-8.284 0-15 6.716-15 15v78c0 8.284 6.716 15 15 15h114c8.284 0 15-6.716 15-15v-78c0-8.284-6.716-15-15-15zm-82-23c0-13.785 11.215-25 25-25s25 11.215 25 25v23h-50v-23zm67 101h-84v-48h84v48z" fill="#fff"/>
		<path d="m166.43 318h-22.857c-4.734 0-8.571 3.838-8.571 8.571v22.857c0 4.734 3.838 8.571 8.571 8.571h22.857c4.734 0 8.571-3.838 8.571-8.571v-22.857c0-4.733-3.838-8.571-8.571-8.571z" fill="#fff"/>
		<path d="m377 323h-142c-8.284 0-15 6.716-15 15s6.716 15 15 15h142c8.284 0 15-6.716 15-15s-6.716-15-15-15z" fill="#fff"/>
		<path d="m166.43 398h-22.857c-4.734 0-8.571 3.838-8.571 8.571v22.857c0 4.734 3.838 8.571 8.571 8.571h22.857c4.734 0 8.571-3.838 8.571-8.571v-22.857c0-4.733-3.838-8.571-8.571-8.571z" fill="#fff"/>
		<path d="m377 403h-142c-8.284 0-15 6.716-15 15s6.716 15 15 15h142c8.284 0 15-6.716 15-15s-6.716-15-15-15z" fill="#fff"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
