<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Paystack;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class PaystackElement extends BaseComponent
{
    public $gateWayName = 'paystack';

    public function __construct()
    {
        parent::__construct('paystack_gateway_element', 11);
        add_action('wppayform/payment_method_choose_element_render_paystack', array($this, 'renderForMultiple'), 10, 3);
        add_filter('wppayform/available_payment_methods', array($this, 'pushPaymentMethod'), 2, 1);
    }

    public function pushPaymentMethod($methods)
    {
        $methods['paystack'] = array(
            'label' => 'Paystack',
            'isActive' => true,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Payment Option Label',
                    'type' => 'text',
                    'default' => 'Pay with Paystack'
                )
            )
        );
        return $methods;
    }


    public function component()
    {
        return array(
            'type' => 'paystack_gateway_element',
            'editor_title' => 'Paystack payment gateway',
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
                'label' => __('Paystack Payment Gateway', 'wppayform')
            )
        );
    }

    public function render($element, $form, $elements)
    {
        do_action('wppayform_load_checkout_js_paystack');

        $apiKeys = PaystackSettings::getApiKeys();
        if (!isset($apiKeys['api_key']) || !isset($apiKeys['api_secret'])) { ?>
            <p style="color: red">You did not configure Paystack payment gateway. Please configure paystack payment
                gateway from <b>WPPayForms->Settings->Paystack Settings</b> to start accepting payments</p>
            <?php return;
        }

        echo '<input data-wpf_payment_method="paystack" type="hidden" name="__paystack_payment_gateway" value="paystack" />';
    }

    public function renderForMultiple($paymentSettings, $form, $elements)
    {
        do_action('wppayform_load_checkout_js_paystack');

        $component = $this->component();
        $component['id'] = 'paystack_gateway_element';
        $component['field_options'] = $paymentSettings;
        $this->render($component, $form, $elements);
    }
}
