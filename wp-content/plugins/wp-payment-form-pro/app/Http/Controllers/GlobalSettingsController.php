<?php

namespace WPPayForm\App\Http\Controllers;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\GlobalSettings;
use WPPayForm\App\Modules\PaymentMethods\Stripe\Stripe;
use WPPayForm\App\Services\AccessControl;
use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Modules\Debug\Debug;
use WPPayForm\App\App;

class GlobalSettingsController extends Controller
{
    public function roles()
    {
        $roles = (new AccessControl)->getAccessRoles();
        return array('roles' => $roles);
    }

    public function setRoles()
    {
        return (new AccessControl)->setAccessRoles($this->request);
    }

    public function currencies()
    {
        return array(
            'currency_settings' => GeneralSettings::getGlobalCurrencySettings(),
            'currencies' => GeneralSettings::getCurrencies(),
            'locales' => GeneralSettings::getLocales(),
            'ip_logging_status' => GeneralSettings::ipLoggingStatus(),
            'abandoned_time' => GeneralSettings::getAbandonedTime()
        );
    }


    public function saveCurrencies()
    {
        $settings = $this->request->settings;
        // Validate the data
        if (empty($settings['currency'])) {
            wp_send_json_error(array(
                'message' => __('Please select a currency', 'wppayform')
            ), 423);
        }

        $data = array(
            'currency' => sanitize_text_field(Arr::get($settings, 'currency')),
            'locale' => sanitize_text_field(Arr::get($settings, 'locale')),
            'currency_sign_position' => sanitize_text_field(Arr::get($settings, 'currency_sign_position')),
            'currency_separator' => sanitize_text_field(Arr::get($settings, 'currency_separator')),
            'decimal_points' => intval(Arr::get($settings, 'decimal_points')),
        );

        GlobalSettings::updateSettings($this->request, $data);

        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public function stripe()
    {
        return (new Stripe())->getPaymentSettings();
    }

    public function saveStripe()
    {
        return (new Stripe())->savePaymentSettings($this->request);
    }

    public function forms()
    {
        $forms = Form::select(['ID', 'post_title'])
            ->where('post_type', 'wp_payform')
            ->where('post_status', 'publish')
            ->orderBy('ID', 'DESC')
            ->get();
        return array(
            'forms' => $forms
        );
    }

    public function getRecaptcha()
    {
        return array(
            'settings' => GeneralSettings::getRecaptchaSettings()
        );
    }

    public function saveRecaptcha()
    {
        $settings = $this->request->settings;

        $sanitizedSettings = [];
        foreach ($settings as $settingKey => $setting) {
            $sanitizedSettings[$settingKey] = sanitize_text_field($setting);
        }

        if ($sanitizedSettings['recaptcha_version'] != 'none') {
            if (empty($sanitizedSettings['site_key']) || empty($sanitizedSettings['secret_key'])) {
                wp_send_json_error([
                    'message' => 'Please provide site key and secret key for enable recaptcha'
                ], 423);
            }
        }

        update_option('wppayform_recaptcha_settings', $sanitizedSettings);

        return array(
            'message' => 'Settings successfully updated'
        );
    }

    public function handleFileUpload()
    {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        $uploadedfile = $_FILES['file'];

        $acceptedFilles = array(
            'image/png',
            'image/jpeg'
        );

        if (!in_array($uploadedfile['type'], $acceptedFilles)) {
            wp_send_json(__('Please upload only jpg/png format files', 'wppayform'), 423);
        }

        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        if ($movefile && !isset($movefile['error'])) {
            wp_send_json_success(array(
                'file' => $movefile
            ), 200);
        } else {
            wp_send_json(__('Something is wrong when uploading the file', 'wppayform'), 423);
        }
    }

    public function generateDebug($type)
    {
        return Debug::getDebugInfos($type);
    }
}
