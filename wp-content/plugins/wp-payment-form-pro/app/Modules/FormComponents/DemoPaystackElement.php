<?php

namespace WPPayForm\App\Modules\FormComponents;

if (!defined('ABSPATH')) {
    exit;
}

class DemoPaystackElement extends BaseComponent
{
    public $gateWayName = 'paystack';

    public function __construct()
    {
        parent::__construct('paystack_gateway_element', 7);
    }

    public function component()
    {
        return array(
            'type' => 'paystack_gateway_element',
            'editor_title' => 'Paystack payment gateway (Pro)',
            'editor_icon' => '',
            'disabled' => true,
            'disabled_message' => 'Paystack Payment Method requires Pro version of WPPayForm. Please install Pro version to make it work.',
            'group' => 'payment_method_element',
            'method_handler' => $this->gateWayName,
            'postion_group' => 'payment_method',
            'single_only' => true,
            'editor_elements' => array(
                'info' => array(
                    'type' => 'info_html',
                    'info' => '<h3 style="color: firebrick; text-align: center;">Paystack Payment Method require Pro version of WPPayForm. Please install Pro version to make it work.</h3><br />'
                ),
                'label' => array(
                    'label' => 'Field Label',
                    'type' => 'text'
                ),
                'require_shipping_address' => array(
                    'label' => 'Require Shipping Address',
                    'type' => 'switch'
                ),
                'admin_label' => array(
                    'label' => 'Admin Label',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
                'wrapper_class' => array(
                    'label' => 'Field Wrapper CSS Class',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
                'element_class' => array(
                    'label' => 'Input element CSS Class',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
            ),
            'field_options' => array(
                'label' => __('Paystack Payment Gateway', 'wppayform'),
                'require_shipping_address' => 'no'
            )
        );
    }

    public function render($element, $form, $elements)
    {
        return '';
    }
}