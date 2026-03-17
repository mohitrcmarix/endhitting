<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class MaskInputComponent extends BaseComponent
{
    protected $componentName = 'mask_input';

    public function __construct()
    {
        parent::__construct($this->componentName, 600);
    }

    public function component()
    {
        return array(
            'type' => $this->componentName,
            'editor_title' => 'Mask Input',
            'group' => 'input',
            'postion_group' => 'general',
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
                'required' => array(
                    'label' => 'Required',
                    'type' => 'switch',
                    'group' => 'general'
                ),
                'default_value' => array(
                    'label' => 'Default Value',
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
                'mask' => array(
                    'group' => 'general',
                    'label' => 'Mask Format',
                    'type' => 'mask_input',
                    'options' => array(
                        '' => __('None', 'wppayform'),
                        '(000) 000-0000' => '(###) ###-####',
                        '(00) 0000-0000' => '(##) ####-####',
                        '00/00/0000' => __('23/03/2018', 'wppayform'),
                        '00:00:00' => __('23:59:59', 'wppayform'),
                        '00/00/0000 00:00:00' => __('23/03/2018 23:59:59', 'wppayform'),
                        'custom' => __('Custom', 'wppayform'),
                    ),
                )
            ),
            'field_options' => array(
                'label' => 'Mask Input',
                'placeholder' => '',
                'required' => 'no',
                'mask' => '(000) 000-0000',
                'mask_custom' => '(000) 000-0000',
                'is_mask_reverse' => 'no'
            )
        );
    }

    public function render($element, $form, $elements)
    {
        $mask = Arr::get($element, 'field_options.mask');
        if ($mask == 'custom') {
            $mask = Arr::get($element, 'field_options.mask_custom');
        }
        if ($mask) {
            wp_enqueue_script('jquery.mask', WPPAYFORM_URL . 'assets/libs/mask/jquery.mask.min.js', array('jquery'), '1.14.16', true);
            $element['field_options']['extra_data_atts'] = [
                'data-mask' => $mask
            ];
            if (Arr::get($element, 'field_options.is_mask_reverse') == 'yes') {
                $element['field_options']['extra_data_atts']['data-mask-reverse'] = 'true';
            }
        }

        $element['type'] = 'text';

        $this->renderNormalInput($element, $form);
    }

}