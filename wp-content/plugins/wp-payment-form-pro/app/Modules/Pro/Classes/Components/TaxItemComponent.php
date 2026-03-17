<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class TaxItemComponent extends BaseComponent
{
    public function __construct()
    {
        parent::__construct('tax_payment_input', 6);
        add_filter('wppayform/submitted_payment_items', array($this, 'pushTaxItems'), 999, 4);
        add_filter('wppayform/validate_component_on_save_tax_payment_input', array($this, 'validateOnSave'), 1, 3);
    }

    public function component()
    {
        return array(
            'type' => 'tax_payment_input',
            'editor_title' => 'Tax Calculated Amount',
            'group' => 'payment',
            'postion_group' => 'payment',
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Field Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'tax_percent' => array(
                    'label' => 'Tax Percentage',
                    'type' => 'number',
                    'group' => 'general'
                ),
                'target_product' => array(
                    'label' => 'Target Product Item',
                    'type' => 'onetime_products_selector',
                    'group' => 'general',
                    'info' => 'Please select the product in where this tax percentage will be applied'
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
                'label' => 'Tax Amount',
                'tax_percent' => '10'
            )
        );
    }

    public function render($element, $form, $elements)
    {
        $fieldOptions = Arr::get($element, 'field_options', false);
        $disable = Arr::get($fieldOptions, 'disable', false);
        if (!$fieldOptions || $disable) {
            return;
        }
        add_filter('wppayform/form_css_classes', function ($classes, $reneringForm) use ($form) {
            if ($reneringForm->ID == $form->ID) {
                $classes[] = 'wpf_has_tax_item';
            }
            return $classes;
        }, 10, 2);
        $inputId = 'wpf_tax_' . $form->ID . '_' . $element['id'];

        $fieldOptions['label'] = $fieldOptions['label'] . ': <span class="wpf_calc_tax" data-target_tax="' . $inputId . '"></span>';
        $controlClass = $this->elementControlClass($element);
        $taxPercent = Arr::get($fieldOptions, 'tax_percent'); ?>
        <div data-element_type="<?php echo $this->elementName; ?>"
             class="<?php echo $controlClass; ?>">
            <?php $this->buildLabel($fieldOptions, $form, array(
                'data-tax_percent' => $taxPercent,
                'class' => 'wpf_tax_line_item',
                'data-is_tax_line' => 'yes',
                'id' => $inputId,
                'data-target_product' => Arr::get($fieldOptions, 'target_product')
            )); ?>
        </div>
        <?php
    }

    public function validateOnSave($error, $element, $formId)
    {
        $disable = Arr::get($element, 'field_options.disable', false);

        if ($disable) {
            return;
        }

        if (!Arr::get($element, 'field_options.target_product')) {
            $error = __('Target Product is required for item:', 'wppayform') . ' ' . Arr::get($element, 'field_options.label');
        }
        return $error;
    }

    public function pushTaxItems($paymentItems, $formattedElements, $form_data, $discountPercent = 0)
    {
        if (!$paymentItems || !$formattedElements['payment']) {
            return $paymentItems;
        }
        $taxItems = $this->getTaxItems($paymentItems, $formattedElements['payment'], $discountPercent);
        $taxItems = apply_filters('wppayform/form_tax_items', $taxItems, $paymentItems, $formattedElements);

        if ($taxItems) {
            $paymentItems = array_merge($paymentItems, $taxItems);
        }

        return $paymentItems;
    }

    private function getTaxItems($paymentItems, $items, $discountPercent = 0)
    {
        if (Arr::get($items, 'tax_payment_input.options.disable', false)) {
            return;
        }

        if (!$paymentItems) {
            return $items;
        }
        // let's format the $paymentItems as in object
        $itemizedTotal = [];
        foreach ($paymentItems as $payItem) {
            $prductName = $payItem['parent_holder'];
            if (!isset($itemizedTotal[$prductName])) {
                $itemizedTotal[$prductName] = $payItem['line_total'];
            } else {
                $itemizedTotal[$prductName] += $payItem['line_total'];
            }
        }


        $taxItems = array();
        foreach ($items as $itemKey => $item) {
            if ($item['type'] != 'tax_payment_input') {
                continue;
            }

            $targetProduct = Arr::get($item, 'options.target_product');
            if (!isset($itemizedTotal[$targetProduct])) {
                continue;
            }
            $taxPercent = Arr::get($item, 'options.tax_percent');
            if (!$taxPercent || !$itemizedTotal[$targetProduct]) {
                continue;
            }
            if ($discountPercent) {
                $discounts = intval($itemizedTotal[$targetProduct] * $discountPercent) / 100;
                $itemizedTotal[$targetProduct] -= $discounts;
            }

            $taxItems[$itemKey] = array(
                'type' => 'tax_line',
                'parent_holder' => $targetProduct,
                'item_name' => strip_tags(Arr::get($item, 'options.label') . '(' . $taxPercent . '%)'),
                'quantity' => 1,
                'item_price' => (int)($itemizedTotal[$targetProduct] * ($taxPercent / 100)),
                'line_total' => (int)($itemizedTotal[$targetProduct] * ($taxPercent / 100)),
                'created_at' => current_time('Y-m-d H:i:s'),
                'updated_at' => current_time('Y-m-d H:i:s'),
            );
        }

        return $taxItems;
    }
}
