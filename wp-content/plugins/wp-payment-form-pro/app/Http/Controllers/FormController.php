<?php

namespace WPPayForm\App\Http\Controllers;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\FormPlaceholders;
use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\App\Services\GlobalTools;
use WPPayForm\App\Models\Meta;
use WPPayForm\Framework\Support\Arr;

class FormController extends Controller
{
    public function index($formId)
    {
        $form = get_post($formId, 'OBJECT');
        if (!$form || $form->post_type != 'wp_payform') {
            return false;
        }
        $form->show_title_description = get_post_meta($formId, 'wppayform_show_title_description', true);
        $form->preview_url = site_url('?wp_paymentform_preview=' . $form->ID);
        return $form;
    }

    public function store($formId)
    {
        $builderSettings = $this->request->get('builder_settings');
        $builderSettings = json_decode($builderSettings, true);

        if (!$formId || !$builderSettings) {
            wp_send_json_error(array(
                'message' => __('Validation Error, Please try again', 'wppayform'),
                'errors' => array(
                    'general' => __('Please add at least one input element', 'wppayform')
                )
            ), 423);
        }
        $errors = array();

        $hasRecurringField = 'no';
        $hasPaymentItem = 'no';

        foreach ($builderSettings as $builderSetting) {
            $error = apply_filters('wppayform/validate_component_on_save_' . $builderSetting['type'], false, $builderSetting, $formId);
            if ($error) {
                $errors[$builderSetting['id']] = $error;
            }

            if ($builderSetting['type'] == 'recurring_payment_item') {
                $hasRecurringField = 'yes';
            }

            if ($builderSetting['group'] == 'payment') {
                $hasPaymentItem = 'yes';
            }
        }

        if ($errors) {
            wp_send_json_error(array(
                'message' => __('Validation failed when saving the form', 'wppayform'),
                'errors' => $errors
            ), 423);
        }

        $submit_button_settings = $this->request->submit_button_settings;
        update_post_meta($formId, 'wppayform_paymentform_builder_settings', $builderSettings);
        update_post_meta($formId, 'wppayform_submit_button_settings', $submit_button_settings);

        update_post_meta($formId, 'wpf_has_recurring_field', $hasRecurringField);
        update_post_meta($formId, 'wpf_has_payment_field', $hasPaymentItem);

        wp_send_json_success(array(
            'message' => __('Settings successfully updated', 'wppayform')
        ), 200);
    }


    public function remove($formId)
    {
        do_action('wppayform/before_form_delete', $formId);
        Form::deleteForm($formId);
        do_action('wppayform/after_form_delete', $formId);
        return array(
            'message' => __('Selected form successfully deleted', 'wppayform')
        );
    }

    public function editors($formId)
    {
        $builderSettings = Form::getBuilderSettings($formId);

        return array(
            'builder_settings' => $builderSettings,
            'components' => GeneralSettings::getComponents(),
            'form_button_settings' => Form::getButtonSettings($formId)
        );
    }

    public function saveIntegration($formId)
    {
        $value = $this->request->get('value', '');
        $key = sanitize_text_field($this->request->get('meta_key'));
        $id = intval($this->request->get('id'));

        $data = [
            'meta_key' => $key,
            'meta_value' => $value,
            'meta_group' => 'integration',
            'form_id' => intVal($formId)
        ];
        if ($id) {
            Meta::where('id', $id)->update($data);
            $insertId = $id;
        } else {
            $insertId = Meta::create($data)->id;
        }
        return [
            'message' => __('Settings has been saved.', 'wppayform'),
            'settings' => json_decode($value, true),
            'id' => $insertId
        ];
    }

    public function getIntegration($formId)
    {
        $data = Meta::where('form_id', $formId)->where('meta_key', 'slack')->first();
        return array(
            'settings' => json_decode($data->meta_value, true),
            'id' => $data->id
        );
    }

    public function update($formId)
    {
        // validate first
        $title = $this->request->post_title;
        $description = $this->request->show_title_description;

        if (!$formId || !$title) {
            wp_send_json_error(
                array(
                    'message' => __('Please provide form title', 'wppayform')
                ),
                423
            );
        }

        $formData = array(
            'post_title' => $title,
            'post_name' => $title,
            'post_content' => wp_kses_post($this->request->post_content)
        );

        do_action('wppayform/before_update_form', $formId, $formData);
        Form::updateForm($formId, $formData);
        do_action('wppayform/after_update_form', $formId, $formData);

        update_post_meta($formId, 'wppayform_show_title_description', sanitize_text_field($description));
        wp_send_json_success(array(
            'message' => __('Form successfully updated', 'wppayform')
        ), 200);
    }

    public function designSettings($formId)
    {
        return array(
            'layout_settings' => Form::getDesignSettings($formId)
        );
    }

    public function updateDesignSettings($formId)
    {
        $layoutSettings = wp_unslash($this->request->layout_settings);
        update_post_meta($formId, 'wppayform_form_design_settings', $layoutSettings);
        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public function settings($formId)
    {
        $allPages = Form::select(array('ID', 'post_title'))
            ->where('post_type', 'page')
            ->where('post_status', 'publish')
            ->get();

        return array(
            'confirmation_settings' => Form::getConfirmationSettings($formId),
            'receipt_settings' => Form::getReceiptSettings($formId),
            'currency_settings' => Form::getCurrencySettings($formId),
            'editor_shortcodes' => FormPlaceholders::getAllPlaceholders($formId),
            'currencies' => GeneralSettings::getCurrencies(),
            'locales' => GeneralSettings::getLocales(),
            'pages' => $allPages,
            'recaptcha_settings' => GeneralSettings::getRecaptchaSettings(),
            'form_recaptcha_status' => get_post_meta($formId, '_recaptcha_status', true)
        );
    }

    public function saveSettings($formId)
    {
        if ($this->request->confirmation_settings) {
            $confirmationSettings = wp_unslash($this->request->confirmation_settings);
            update_post_meta($formId, 'wppapyform_paymentform_confirmation_settings', $confirmationSettings);
        }
        if ($this->request->currency_settings) {
            $currency_settings = wp_unslash($this->request->currency_settings);
            update_post_meta($formId, 'wppayform_paymentform_currency_settings', $currency_settings);
        }

        if ($this->request->form_recaptcha_status) {
            update_post_meta($formId, '_recaptcha_status', sanitize_text_field($this->request->form_recaptcha_status));
        }

        if ($this->request->receipt_settings) {
            $confirmationSettings = wp_unslash($this->request->receipt_settings);
            update_post_meta($formId, 'wppapyform_receipt_settings', $confirmationSettings);
        }

        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public function duplicateForm($formId)
    {
        $globalTools = new GlobalTools();
        $oldForm = $globalTools->getForm($formId);
        $oldForm['post_title'] = '(Duplicate) ' . $oldForm['post_title'];
        $oldForm = apply_filters('wppayform/form_duplicate', $oldForm);

        if (!$oldForm) {
            wp_send_json_error(array(
                'message' => __('No form found when duplicating the form', 'wppayform')
            ), 423);
        }
        $newForm = $globalTools->createFormFromData($oldForm);
        return array(
            'message' => __('Form successfully duplicated', 'wppayform'),
            'form' => $newForm
        );
    }

    public function export($formId)
    {
        $globalTools = new GlobalTools();
        $globalTools->exportFormJson($formId);
    }
}
