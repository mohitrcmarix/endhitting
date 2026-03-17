<?php

namespace WPPayForm\App\Modules\Pro\GateWays\Mollie;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class MollieElement extends BaseComponent
{
    public $gateWayName = 'mollie';

    public function __construct()
    {
        parent::__construct('mollie_gateway_element', 8);
        add_action('wppayform/payment_method_choose_element_render_mollie', array($this, 'renderForMultiple'), 10, 3);
        add_filter('wppayform/available_payment_methods', array($this, 'pushPaymentMethod'), 2, 1);
    }

    public function pushPaymentMethod($methods)
    {
        $methods['mollie'] = array(
            'label' => 'Mollie',
            'isActive' => true,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Payment Option Label',
                    'type' => 'text',
                    'default' => 'Pay with Mollie'
                )
            )
        );
        return $methods;
    }


    public function component()
    {
        return array(
            'type' => 'mollie_gateway_element',
            'editor_title' => 'Mollie payment gateway',
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
                'label' => __('Mollie Payment Gateway', 'wppayform')
            )
        );
    }

    public function render($element, $form, $elements)
    {
        $mollie = new MollieSettings();
        if (!$mollie->getApiKey()) { ?>
            <p style="color: red">You did not configure Mollie payment gateway. Please configure mollie payment
                gateway from <b>WPPayForms->Settings->Mollie Settings</b> to start accepting payments</p>
            <?php return;
        }

        echo '<input data-wpf_payment_method="mollie" type="hidden" name="__mollie_payment_gateway" value="mollie" />';
    }

    public function renderForMultiple($paymentSettings, $form, $elements)
    {
        $component = $this->component();
        $component['id'] = 'mollie_gateway_element';
        $component['field_options'] = $paymentSettings;
        $this->render($component, $form, $elements);
    }
}
