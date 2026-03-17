<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Form;

if (!defined('ABSPATH')) {
    exit;
}

class TabularProductsComponent extends BaseComponent
{
    public function __construct()
    {
        parent::__construct('tabular_products', 2);
        add_filter('wppayform/submitted_payment_items', array($this, 'pushTabularItems'), 10, 4);
        add_filter('wppayform/validate_component_on_save_tabular_products', array($this, 'validateOnSave'), 1, 3);
    }

    public function component()
    {
        return array(
            'type' => 'tabular_products',
            'editor_title' => 'Tabular Product Items',
            'group' => 'payment',
            'postion_group' => 'payment',
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Field Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'products' => array(
                    'label' => 'Setup your Tabular products',
                    'group' => 'general',
                    'type' => 'tabular_products',
                ),
                'show_sub_total' => array(
                    'label' => 'Show Subtotal',
                    'type' => 'switch',
                    'group' => 'general',
                    'info' => 'If enabled then user can see subtotal after the table'
                ),
                'table_photo_label' => array(
                    'label' => 'Photo Column Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'table_item_label' => array(
                    'label' => 'Table Item Column Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'table_price_label' => array(
                    'label' => 'Table Price Column Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'table_quantity_label' => array(
                    'label' => 'Table Quantity Column Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'table_subtotal_label' => array(
                    'label' => 'Table Sub Total Label Label',
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
            'is_system_field' => true,
            'is_payment_field' => true,
            'field_options' => array(
                'label' => 'Add quantity of the products',
                'show_sub_total' => 'yes',
                'table_item_label' => 'Product',
                'table_price_label' => 'Item Price',
                'table_quantity_label' => 'Quantity',
                'table_subtotal_label' => 'Sub Total',
                'table_photo_label' => 'Photo',
                'products' => array(
                    [
                        'product_name' => 'Product 1',
                        'default_quantity' => 1,
                        'min_quantity' => 0,
                        'max_quantity' => 100,
                        'product_price' => '10'
                    ],
                    [
                        'product_name' => 'Product 2',
                        'default_quantity' => 0,
                        'min_quantity' => 0,
                        'max_quantity' => 100,
                        'product_price' => '20'
                    ]
                )
            )
        );
    }

    public function validateOnSave($error, $element, $formId)
    {
        return $error;
    }

    public function render($element, $form, $elements)
    {
        $currenySettings = Form::getCurrencyAndLocale($form->ID);
        $controlClass = $this->elementControlClass($element);

        $fieldOptions = Arr::get($element, 'field_options');
        $disable = Arr::get($fieldOptions, 'disable');

        $tableLabel = Arr::get($fieldOptions, 'label');
        $controlAttributes = array(
            'data-element_type' => $this->elementName,
            'class' => $controlClass
        );
        $products = Arr::get($fieldOptions, 'products');
        $itemId = Arr::get($element, 'id');
        $showSubtotalSelector = '';
        $showSubtotal = Arr::get($fieldOptions, 'show_sub_total') == 'yes';
        if ($disable) {
            return;
        }
        if ($showSubtotal) {
            $showSubtotalSelector = ' wpf_show_tabular_subtotal';
        }
        $tableAttributes = array(
            'class' => 'wpf_tabular_items wpf_tabular_' . $itemId . ' wpf_regular_table' . $showSubtotalSelector,
            'data-produt_id' => $itemId,
            'data-item_total' => 0,
            'data-qty_required' => Arr::get($fieldOptions, 'required')
        );

        $enabledImage = Arr::get($fieldOptions, 'enable_image') == 'yes';
        $lightbox = Arr::get($fieldOptions, 'enable_lightbox') == 'yes';

        if ($lightbox) {
            wp_enqueue_script('lity', WPPAYFORM_URL . 'assets/libs/lity/lity.min.js', array('jquery'), '2.3.1', true);
            wp_enqueue_style('lity', WPPAYFORM_URL . 'assets/libs/lity/lity.min.css', array(), '2.3.1');
        }

        $colspan = 2;
        if ($enabledImage) {
            $colspan = 3;
        }
        ?>
        <div <?php echo $this->builtAttributes($controlAttributes); ?>>
            <?php if ($tableLabel): ?>
                <h4 class="wpf_tabular_parent_label wpf_tabular_label_<?php echo $itemId; ?>"><?php echo $tableLabel; ?></h4>
            <?php endif; ?>
            <table <?php echo $this->builtAttributes($tableAttributes); ?>>
                <thead>
                <tr>
                    <?php if ($enabledImage): ?>
                        <th class="wpf_tabular_product_photo">
                            <?php echo Arr::get($fieldOptions, 'table_photo_label'); ?>
                        </th>
                    <?php endif; ?>
                    <th class="wpf_tabular_product_title"><?php echo Arr::get($fieldOptions, 'table_item_label'); ?></th>
                    <th class="wpf_tabular_price"><?php echo Arr::get($fieldOptions, 'table_price_label'); ?></th>
                    <th class="wpf_tabular_qty"><?php echo Arr::get($fieldOptions, 'table_quantity_label'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $productIndex => $product): ?>
                    <tr>
                        <?php if ($enabledImage): ?>
                            <td class="wpf_tabular_product_photo">
                                <?php echo $this->renderImage($product['photo'], $lightbox); ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <?php echo $product['product_name']; ?>
                            <input type="hidden" name="<?php echo $itemId; ?>[<?php echo $productIndex; ?>]"
                                   value="<?php echo $product['product_name']; ?>"/>
                        </td>
                        <td class="wpf_tabular_price">
                            <?php $currentProductId = $itemId . '_price_' . $productIndex; ?>
                            <?php echo wpPayFormFormattedMoney(wpPayFormConverToCents($product['product_price']), $currenySettings); ?>

                            <?php
                            $priceAttributes = array(
                                'data-tabular_product' => $itemId,
                                'class' => 'wpf_tabular_price',
                                'name' => $currentProductId,
                                'type' => 'hidden',
                                'data-price' => wpPayFormConverToCents($product['product_price']),
                                'value' => $product['product_price']
                            );
                            ?>
                            <input <?php echo $this->builtAttributes($priceAttributes); ?> />
                        </td>
                        <td class="wpf_tabular_qty">
                            <?php
                            $qytAttributes = [
                                'name' => $itemId . '_qty_' . $productIndex,
                                'value' => $product['default_quantity'],
                                'type' => 'number',
                                'min' => $product['min_quantity'],
                                'class' => 'wpf_form_control wpf_tabular_qty',
                                'data-target_product' => $currentProductId,
                                'id' => $itemId . '_qty_' . $productIndex
                            ];

                            if (isset($product['max_quantity'])) {
                                $qytAttributes['max'] = $product['max_quantity'];
                            }

                            ?>
                            <input <?php echo $this->builtAttributes($qytAttributes); ?> />
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($showSubtotal): ?>
                    <tfoot>
                    <tr class="tablular_table_subtotal">
                        <th class="wpf_subtotal_th" colspan="<?php echo $colspan; ?>">
                            <?php echo Arr::get($fieldOptions, 'table_subtotal_label'); ?>
                        </th>
                        <th>
                            <span class="wpf_tabular_subtotal wpf_calc_tabular_<?php echo $itemId; ?>"
                                  data-target_item="<?php echo $itemId; ?>"></span>
                        </th>
                    </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php

    }

    private function renderImage($image, $lightboxed = false)
    {

        if (!$image) {
            return '';
        }

        $thumb = Arr::get($image, 'image_thumb');
        $imageFull = Arr::get($image, 'image_full');
        $altText = Arr::get($image, 'alt_text');

        if (!$thumb) {
            return '';
        }

        if ($lightboxed) {
            return '<a class="wpf_lightbox" href="' . $imageFull . '"><img src="' . $thumb . '" alt="' . $altText . '" /></a>';
        }
        return '<img src="' . $thumb . '" alt="' . $altText . '" />';
    }

    public function pushTabularItems($paymentItems, $formattedElements, $form_data)
    {
        $tabularItems = array_filter($formattedElements['payment'], function ($element) {
            return Arr::get($element, 'type') == 'tabular_products';
        });

        if (!$tabularItems) {
            return $paymentItems;
        }

        foreach ($tabularItems as $itemKey => $tabularItem) {
            $sourceProducts = Arr::get($tabularItem, 'options.products');
            if (!$sourceProducts) {
                continue;
            }
            foreach ($sourceProducts as $index => $sourceProduct) {
                $inputQty = Arr::get($form_data, $itemKey . '_qty_' . $index, 0);
                if (!$inputQty) {
                    continue;
                }
                $sourceProductPrice = wpPayFormConverToCents($sourceProduct['product_price']);
                $paymentItems[] = array(
                    'type' => 'single',
                    'parent_holder' => $itemKey,
                    'item_name' => $sourceProduct['product_name'],
                    'quantity' => $inputQty,
                    'item_price' => $sourceProductPrice,
                    'line_total' => $sourceProductPrice * $inputQty,
                    'created_at' => current_time('Y-m-d H:i:s'),
                    'updated_at' => current_time('Y-m-d H:i:s'),
                );
            }
        }

        return $paymentItems;
    }
}