<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Razorpay;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class RazorpayElement extends BaseComponent
{
    public $gateWayName = 'razorpay';

    public function __construct()
    {
        parent::__construct('razorpay_gateway_element', 9);
        add_action('wppayform/payment_method_choose_element_render_razorpay', array($this, 'renderForMultiple'), 10, 3);
        add_filter('wppayform/available_payment_methods', array($this, 'pushPaymentMethod'), 2, 1);
    }

    public function pushPaymentMethod($methods)
    {
        $methods['razorpay'] = array(
            'label' => 'Razorpay',
            'isActive' => true,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Payment Option Label',
                    'type' => 'text',
                    'default' => 'Pay with Razorpay'
                )
            )
        );
        return $methods;
    }


    public function component()
    {
        return array(
            'type' => 'razorpay_gateway_element',
            'editor_title' => 'Razorpay payment gateway',
            'editor_icon' => '',
            'group' => 'payment_method_element',
            'method_handler' => $this->gateWayName,
            'postion_group' => 'payment_method',
            'single_only' => true,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Field Label',
                    'type' => 'text'
                )
            ),
            'field_options' => array(
                'label' => __('Razorpay Payment Gateway', 'wppayform')
            )
        );
    }

    public function render($element, $form, $elements)
    {
        $settings = (new RazorpaySettings())->getPaymentSettings();
        if (Arr::get($settings, 'settings.checkout_type') === 'modal') {
            do_action('wppayform_load_checkout_js_razorpay', $settings);
        };

        $apiKeys = RazorpaySettings::getApiKeys();
        if (!isset($apiKeys['api_key']) || !isset($apiKeys['api_secret'])) { ?>
            <p style="color: red">You did not configure Razorpay payment gateway. Please configure razorpay payment
                gateway from <b>WPPayForms->Settings->Razorpay Settings</b> to start accepting payments</p>
            <?php return;
        }

        echo '<input data-wpf_payment_method="razorpay" type="hidden" name="__razorpay_payment_gateway" value="razorpay" />';
    }

    public function renderForMultiple($paymentSettings, $form, $elements)
    {
        $settings = (new RazorpaySettings())->getPaymentSettings();
        if (Arr::get($settings, 'settings.checkout_type') === 'modal') {
            do_action('wppayform_load_checkout_js_razorpay', $settings);
        };

        $component = $this->component();
        $component['id'] = 'razorpay_gateway_element';
        $component['field_options'] = $paymentSettings;
        $this->render($component, $form, $elements);
    }
}
