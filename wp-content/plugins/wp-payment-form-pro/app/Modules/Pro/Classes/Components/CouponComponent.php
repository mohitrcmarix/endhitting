<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class CouponComponent extends BaseComponent
{
    private $coupons = 'coupon';

    public function __construct()
    {
        parent::__construct('coupon', 20);
        add_filter('wppayform/submitted_value_' . $this->coupons, array($this, 'addCouponsToSubmission'), 10, 3);
    }

    public function component()
    {
        $components = array(
            'type' => 'coupon',
            'editor_title' => 'Coupon',
            'group' => 'payment',
            'postion_group' => 'payment',
            'is_system_field' => false,
            'is_payment_field' => false,
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Field Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'placeholder' => array(
                    'label' => 'Placeholder',
                    'type' => 'text',
                    'group' => 'general'
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
                'label' => 'Coupon Code',
                'placeholder' => '',
                'required' => 'no'
            )
        );

        $hasTable = get_option('wppayform_coupon_status', false);

        if (!$hasTable == 'yes') {
            $migrateInfo = array(
                'migrate' => true,
                'migrate_message' => 'Please activate coupon module from Payment settings. And reload this page.',
                'url' => admin_url('admin.php?page=wppayform_settings#coupons'),
                'btnText' => 'Activate Coupon Module'
            );

            $components = array_merge($components, $migrateInfo);
        }

        return $components;
    }

    public function render($element, $form, $elements)
    {
        add_filter('wppayform/form_css_classes', function ($classes, $reneringForm) use ($form) {
            if ($reneringForm->ID == $form->ID) {
                $classes[] = 'wpf_has_coupons';
            }
            return $classes;
        }, 10, 2);

        $fieldOptions = Arr::get($element, 'field_options', false);
        $disable = Arr::get($fieldOptions, 'disable', false);

        if (!$fieldOptions || $disable) {
            return;
        }

        $html = Arr::get($element, 'field_options.custom_html', '');
        $controlClass = $this->elementControlClass($element);
        $inputClass = $this->elementInputClass($element);
        $inputId = 'wpf_input_' . $form->ID . '_' . $element['id'];
        $attributes = array(
            'data-type' => 'input',
            'name' => $element['id'],
            'placeholder' => Arr::get($fieldOptions, 'placeholder'),
            'type' => 'text',
            'id' => $inputId,
            'class' => $inputClass . ' wpf_coupon_field input-append',
        );

        if (Arr::get($fieldOptions, 'required') == 'yes') {
            $attributes['required'] = true;
        }
        ?>
        <style type="text/css">
            .wpf_coupon_action {
                cursor: pointer;
                margin-right: -3px;
            }
        </style>

        <div data-element_type="<?php echo $this->elementName; ?>"
             class="<?php echo $controlClass; ?>">
            <?php $this->buildLabel($fieldOptions, $form, array('for' => $inputId)); ?>
            <div class="wpf_input_content">
                <div class="wpf_form_item_group">
                    <input <?php echo $this->builtAttributes($attributes); ?> />
                    <div class="wpf_input-group-append">
                        <div class=" wpf_input-group-text wpf_input-group-text-append wpf_coupon_action"
                             id="<?php echo $inputId . '_action' ?>">Apply Coupon
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function addCouponsToSubmission($value, $field, $submissionData)
    {
        if (isset($submissionData['__wpf_all_applied_coupons'])) {
            $allCoupons = $submissionData['__wpf_all_applied_coupons'];
            $allCoupons = \json_decode($allCoupons, true);
            if ($allCoupons) {
                $value = implode(', ', $allCoupons);
            }
        }
        return $value;
    }
}