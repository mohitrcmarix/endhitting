<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\CountryNames;

if (!defined('ABSPATH')) {
    exit;
}

class AddressFieldsComponent extends BaseComponent
{

    protected $componentName = 'address_input';

    public function __construct()
    {
        parent::__construct($this->componentName, 600);
        add_filter('wppayform/submitted_value_' . $this->componentName, array($this, 'formatValue'), 10, 1);
    }

    public function component()
    {
        return array(
            'type' => $this->componentName,
            'editor_title' => 'Address Field',
            'group' => 'input',
            'postion_group' => 'general',
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Label',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'subfields' => [
                    'label' => 'Address Fields',
                    'type' => 'address_subfields',
                    'group' => 'general',
                    'fields' => [
                        'address_line_1' => 'Address Line 1',
                        'address_line_2' => 'Address Line 2',
                        'city' => 'City',
                        'state' => 'State',
                        'zip_code' => 'ZIP Code',
                        'country' => 'Country'
                    ]
                ],
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
            ),
            'field_options' => array(
                'label' => 'Address',
                'subfields' => [
                    'address_line_1' => [
                        'label' => 'Address Line 1',
                        'placeholder' => 'Address Line 1',
                        'visibility' => 'yes',
                        'required' => 'yes',
                        'type' => 'text',
                        'id' => 'address_line_1',
                        'default_value' => ''
                    ],
                    'address_line_2' => [
                        'label' => 'Address Line 2',
                        'placeholder' => 'Address Line 2',
                        'visibility' => 'yes',
                        'required' => 'no',
                        'type' => 'text',
                        'id' => 'address_line_2',
                        'default_value' => ''
                    ],
                    'city' => [
                        'label' => 'City',
                        'placeholder' => 'City',
                        'visibility' => 'yes',
                        'required' => 'no',
                        'type' => 'text',
                        'id' => 'city',
                        'default_value' => ''
                    ],
                    'state' => [
                        'label' => 'State',
                        'placeholder' => 'State',
                        'visibility' => 'yes',
                        'required' => 'yes',
                        'type' => 'text',
                        'id' => 'state',
                        'default_value' => ''
                    ],
                    'zip_code' => [
                        'label' => 'ZIP Code',
                        'placeholder' => 'ZIP Code',
                        'visibility' => 'yes',
                        'required' => 'no',
                        'type' => 'text',
                        'id' => 'zip_code',
                        'default_value' => ''
                    ],
                    'country' => [
                        'label' => 'Country',
                        'placeholder' => 'Select Country',
                        'visibility' => 'yes',
                        'required' => 'yes',
                        'type' => 'select',
                        'id' => 'country',
                        'default_value' => ''
                    ],
                ]
            )
        );
    }

    public function formatValue($value)
    {
        if (is_array($value)) {
            $value = array_filter($value);
            if (!empty($value['country'])) {
                $countryCode = $value['country'];
                $countries = CountryNames::getAll();
                if (isset($countries[$countryCode])) {
                    $value['country'] = $countries[$countryCode];
                }
            }
            $value = implode(', ', $value);
        }

        return $value;
    }

    public function render($element, $form, $elements)
    {
        $subFields = Arr::get($element, 'field_options.subfields', []);
        $disable = Arr::get($element, 'field_options.disable', false);
        if ($disable) {
            return;
        }
        $inputFields = [];
        $fieldName = Arr::get($element, 'id');
        foreach ($subFields as $fieldKey => $subField) {
            $field = $this->getFormattedElement($fieldKey, $fieldName, $subField, $form);
            if ($field) {
                $inputFields[] = $field;
            }
        }

        echo '<div class="wpf_address_wrapper">';
        if ($addressLabel = Arr::get($element, 'field_options.label')) {
            echo '<label class="wpf_address_heading">' . $addressLabel . '</label>';
        }
        foreach (array_chunk($inputFields, 2) as $itemGroup) {
            echo '<div class="wpf-t-container">';
            foreach ($itemGroup as $field) {
                echo '<div class="wpf-t-cell">';
                $this->renderSubField($field, $form);
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';

    }

    private function getFormattedElement($fieldKey, $fieldName, $field, $form)
    {
        if (Arr::get($field, 'visibility') != 'yes') {
            return false;
        }

        $element = [
            'type' => Arr::get($field, 'type', 'text'),
            'group' => 'input',
            'postion_group' => 'general',
            'editor_elements' => [
            ],
            'field_options' => [
                'label' => Arr::get($field, 'label'),
                'placeholder' => Arr::get($field, 'placeholder'),
                'required' => Arr::get($field, 'required'),
                'default_value' => Arr::get($field, 'default_value')
            ],
            'id' => $fieldName . '[' . $field['id'] . ']'
        ];

        if ($field['id'] == 'country') {
            $countries = CountryNames::getAll();
            $countries = apply_filters('wppayform/address_countries', $countries, $form);
            $countriesOptions = [];
            foreach ($countries as $isoCode => $country) {
                $countriesOptions[] = [
                    'label' => $country,
                    'value' => $isoCode
                ];
            }
            $element['field_options']['options'] = $countriesOptions;
            $element['field_options']['type'] = 'select';
        }

        return $element;
    }

    private function renderSubField($field, $form)
    {
        if ($field['type'] == 'select') {
            $this->renderSelectInput($field, $form);
        } else {
            $this->renderNormalInput($field, $form);
        }
    }

}
