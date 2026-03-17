<?php

namespace WPPayForm\App\Modules\AddOnModules;

use WPPayForm\App\Services\GeneralSettings;

class AddOnModule
{
    /**
     * Show the add-ons list.
     */
    public static function showAddOns()
    {
        $status = get_option('wppayform_integration_status');

        $addOns = apply_filters('wppayform_global_addons', []);

        $addOns['slack'] = [
            'title' => 'Slack',
            'description' => 'Get realtime notification in slack channel when a new submission will be added.',
            'logo' => WPPAYFORM_URL . '/assets/images/integrations/slack.png',
            'enabled' => GeneralSettings::isSlackEnabled() ? 'yes' : 'no',
            'config_url' => '',
            'category' => 'crm'
        ];

        if (!defined('WPPAYFORM_PRO_INSTALLED')) {
            $addOns = array_merge($addOns, self::getPremiumAddOns());
        }
        if (!defined('FLUENTCRM')) {
            $addOns = array_merge($addOns, self::getFluentCrm());
        }
        return array(
            'status' => $status,
            'addOns' => $addOns
        );
    }

    public function updateAddOnsStatus($request)
    {
        $addons = wp_unslash($request->addons);
        update_option('wppayform_global_modules_status', $addons, 'no');

        return [
            'message' => 'Status successfully updated'
        ];
    }


    public static function getPremiumAddOns()
    {
        $purchaseUrl = wppayformUpgradeUrl();
        return array(
            'activecampaign'    => array(
                'title'        => 'ActiveCampaign',
                'description'  => 'WPPayForm ActiveCampaign Module allows you to create ActiveCampaign list signup forms in WordPress, so you can grow your email list.',
                'logo'         => WPPAYFORM_URL . 'assets/images/integrations/activecampaign.png',
                'enabled'      => 'no',
                'purchase_url' => $purchaseUrl,
                'category'     => 'crm',
                'btnTxt'       => 'Upgrade To Pro'
            ),
            'UserRegistration'  => array(
                'title'        => 'User Registration',
                'description'  => 'Create WordPress user when when a form is submitted.',
                'logo'         => WPPAYFORM_URL . 'assets/images/integrations/user_registration.png',
                'enabled'      => 'no',
                'purchase_url' => $purchaseUrl,
                'category'     => 'wp_core',
                'btnTxt'       => 'Upgrade To Pro',
            ),
            'Aweber'  => array(
                'title'        => 'Aweber',
                'description'  => 'WPPayForm Aweber Module allows you to create Aweber list signup forms in WordPress, so you can grow your email list.',
                'logo'         => WPPAYFORM_URL . 'assets/images/integrations/aweber.png',
                'enabled'      => 'no',
                'purchase_url' => $purchaseUrl,
                'category'     => 'wp_core',
                'btnTxt'       => 'Upgrade To Pro',
            )
        );
    }

    public static function getFluentCrm()
    {
        return array(
            'fluent-crm'   => array(
                'title'        => 'Fluent CRM',
                'description'  => 'Connect FluentCRM with WPPayForm and subscribe a contact when a form is submitted',
                'logo'         =>  WPPAYFORM_URL . 'assets/images/integrations/fluentcrm-logo.png',
                'enabled'      => 'no',
                'purchase_url' => 'https://wordpress.org/plugins/fluent-crm/',
                'category'     => 'crm',
                'btnTxt'       => 'Install & Activate'
            ),
        );
    }
}
