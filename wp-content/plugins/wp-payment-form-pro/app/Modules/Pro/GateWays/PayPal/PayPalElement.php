<?php

namespace WPPayForm\App\Modules\Pro\GateWays\PayPal;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class PayPalElement extends BaseComponent
{
    public $gateWayName = 'paypal';

    public function __construct()
    {
        parent::__construct('paypal_gateway_element', 7);
        add_action('wppayform/payment_method_choose_element_render_paypal', array($this, 'renderForMultiple'), 10, 3);
        add_filter('wppayform/available_payment_methods', array($this, 'pushPaymentMethod'), 2, 1);
    }

    public function pushPaymentMethod($methods)
    {
        $methods['paypal'] = array(
            'label' => 'Paypal',
            'isActive' => true,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Payment Option Label',
                    'type' => 'text',
                    'default' => 'Pay with Paypal'
                ),
                'require_shipping_address' => array(
                    'label' => 'Require Shipping Address',
                    'type' => 'switch'
                )
            )
        );
        return $methods;
    }


    public function component()
    {
        return array(
            'type' => 'paypal_gateway_element',
            'editor_title' => 'Paypal payment gateway',
            'editor_icon' => '',
            'group' => 'payment_method_element',
            'method_handler' => $this->gateWayName,
            'postion_group' => 'payment_method',
            'single_only' => true,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Field Label',
                    'type' => 'text'
                ),
                'require_shipping_address' => array(
                    'label' => 'Require Shipping Address',
                    'type' => 'switch'
                )
            ),
            'field_options' => array(
                'label' => __('PayPal Payment Gateway', 'wppayform'),
                'require_shipping_address' => 'no'
            )
        );
    }

    public function render($element, $form, $elements)
    {
        $paypal = new Paypal();
        $paypalSettings = $paypal->getPaypalSettings();
        if (empty($paypalSettings['paypal_email'])) { ?>
            <p style="color: red">You did not configure Paypal payment gateway. Please configure paypal payment
                gateway from <b>WPPayForms->Settings->PayPal Settings</b> to start accepting payments</p>
            <?php return;
        }

        if (Arr::get($element, 'field_options.require_shipping_address') == 'yes') {
            echo '<input type="hidden" name="__payment_require_shipping_address" value="yes" />';
        }
        echo '<input data-wpf_payment_method="paypal" type="hidden" name="__paypal_payment_gateway" value="paypal" />';
    }

    public function renderForMultiple($paymentSettings, $form, $elements)
    {
        $component = $this->component();
        $component['id'] = 'paypal_gateway_element';
        $component['field_options'] = $paymentSettings;
        $this->render($component, $form, $elements);
    }
}
