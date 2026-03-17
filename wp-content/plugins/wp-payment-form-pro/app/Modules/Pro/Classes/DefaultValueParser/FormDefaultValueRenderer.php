<?php

namespace WPPayForm\App\Modules\Pro\Classes\DefaultValueParser;

use WPPayForm\App\Services\FormPlaceholders;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Info Handler Class
 * @since 1.0.0
 */
class FormDefaultValueRenderer
{
    public function register()
    {
        add_filter('wppayform/input_default_value', array($this, 'parseDefaultValue'), 99, 3);
        add_filter('wppayform/admin_app_vars', array($this, 'pushPlaceholder'), 10, 1);
    }

    public function pushPlaceholder($adminVars)
    {
        $allFields = array(
            'wp' => array(
                'title' => __('WordPress', 'wppayform'),
                'placeholders' => FormPlaceholders::getWPPlaceHolders()
            ),
            'user' => array(
                'title' => __('Current User Info'),
                'placeholders' => FormPlaceholders::getUserPlaceholders()
            ),
            'other' => array(
                'title' => __('Other', 'wppayform'),
                'placeholders' => FormPlaceholders::getOtherPlaceholders()
            )
        );
        $adminVars['value_placeholders'] = $allFields;
        return $adminVars;
    }

    public function parseDefaultValue($value, $element, $form)
    {
        if (!$value) {
            return $value;
        }
        $parsables = PlaceholderParser::parseShortcode($value);
        if (!$parsables) {
            return $value;
        }
        $formattedParsables = [];
        foreach ($parsables as $parsableKey => $parsable) {
            // Get Parsed Group
            $group = strtok($parsable, '.:');
            $itemExt = str_replace(array($group . '.', $group . ':'), '', $parsable);
            $formattedParsables[$group][$parsableKey] = $itemExt;
        }

        $wpPlaceholders = Arr::only($formattedParsables, array(
            'wp', 'post_meta', 'user_meta', 'querystring', 'other', 'user'
        ));

        $wpParseItems = $this->parseWPFields($wpPlaceholders);
        return str_replace(array_keys($wpParseItems), array_values($wpParseItems), $value);
    }


    public function parseWPFields($placeHolders)
    {
        $parsedData = array();
        $metaData = new GlobalMetaData();
        foreach ($placeHolders as $groupKey => $values) {
            foreach ($values as $placeholder => $targetItem) {
                if ($groupKey == 'wp') {
                    $parsedData[$placeholder] = $metaData->getWPValues($targetItem);
                } else if ($groupKey == 'post_meta') {
                    $parsedData[$placeholder] = $metaData->getPostMeta($targetItem);
                } else if ($groupKey == 'user') {
                    $parsedData[$placeholder] = $metaData->getuserData($targetItem);
                } else if ($groupKey == 'user_meta') {
                    $parsedData[$placeholder] = $metaData->getuserMeta($targetItem);
                } else if ($groupKey == 'querystring') {
                    $parsedData[$placeholder] = $metaData->getFromUrlQuery($targetItem);
                } else if ($groupKey == 'other') {
                    $parsedData[$placeholder] = $metaData->getOtherData($targetItem);
                }
            }
        }
        return $parsedData;
    }
}