<?php

namespace WPPayForm\App\Services;

use WPPayForm\Framework\Support\Arr;

class ShortCodeParser
{
    protected static $formId = null;
    protected static $entry = null;

    protected static function setDependencies($formId, $entry)
    {
        static::setFormId($formId);
        static::setData($entry);
    }

    protected static function setdata($entry)
    {
        static::$entry = $entry;
    }

    protected static function setFormId($formId)
    {
        static::$formId = $formId;
    }

    public static function parse($parsable, $formId, $entry, $isUrl = false, $provider = false)
    {
        try {
            static::setDependencies($formId, $entry);

            if (is_array($parsable)) {
                return static::parseShortCodeFromArray($parsable, $isUrl, $provider);
            }

            return static::parseShortCodeFromString($parsable, $isUrl, false);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($e->getTraceAsString());
            }
            return '';
        }
    }

    protected static function parseShortCodeFromArray($parsable, $isUrl = false, $provider = false)
    {
        foreach ($parsable as $key => $value) {
            if (is_array($value)) {
                $parsable[$key] = static::parseShortCodeFromArray($value, $isUrl, $provider);
            } else {
                $isHtml = false;
                $parsable[$key] = static::parseShortCodeFromString($value, $isUrl, $isHtml);
            }
        }
        return $parsable;
    }

    /*
    * code transmitted from ff
    */

    protected static function parseShortCodeFromString($parsable, $isUrl = false, $isHtml = false)
    {
        if (!$parsable) {
            return '';
        }
        return preg_replace_callback('/{+(.*?)}/', function ($matches) use ($isUrl, $isHtml) {
            $value = '';
            if (strpos($matches[1], 'input.') !== false) {
                $formProperty = substr($matches[1], strlen('input.'));
                $value = static::getFormData($formProperty, $isHtml);
            }
//            elseif (strpos($matches[1], 'user.') !== false) {
//                $userProperty = substr($matches[1], strlen('user.'));
//                $value = static::getUserData($userProperty);
//            } elseif (strpos($matches[1], 'embed_post.') !== false) {
//                $postProperty = substr($matches[1], strlen('embed_post.'));
//                $value = static::getPostData($postProperty);
//            } elseif (strpos($matches[1], 'wp.') !== false) {
//                $wpProperty = substr($matches[1], strlen('wp.'));
//                $value = static::getWPData($wpProperty);
//            } elseif (strpos($matches[1], 'submission.') !== false) {
//                $submissionProperty = substr($matches[1], strlen('submission.'));
//                $value = static::getSubmissionData($submissionProperty);
//            } elseif (strpos($matches[1], 'cookie.') !== false) {
//                $scookieProperty = substr($matches[1], strlen('cookie.'));
//                $value = Arr::get($_COOKIE, $scookieProperty);
//            } elseif (strpos($matches[1], 'payment.') !== false) {
//                $property = substr($matches[1], strlen('payment.'));
//                $value = apply_filters('wppayform_payment_smartcode', '', $property, self::getInstance());
//            } else {
//                $value = static::getOtherData($matches[1]);
//            }
//            if (is_array($value)) {
//                $value = fluentImplodeRecursive(', ', $value);
//            }
//
//            if ($isUrl) {
//                $value = urlencode($value);
//            }

            return $value;
        }, $parsable);
    }

    protected static function getFormData($key, $isHtml = false)
    {
        return Arr::get(static::$entry->form_data_raw, $key);
//        if (strpos($key, '.label')) {
//            $key = str_replace('.label', '', $key);
//
//            $isHtml = true;
//        }

//        if (strpos($key, '.value')) {
//            $key = str_replace('.value', '', $key);
//            return Arr::get(static::$entry, $key);
//        }
//
//        if (strpos($key, '.') && !isset(static::$entry[$key])) {
//            return Arr::get(
//                static::$store['original_inputs'], $key, ''
//            );
//        }
//
//        if (!isset(static::static::$entry[$key])) {
//            static::$entry[$key] = Arr::get(
//                static::$entry, $key, ''
//            );
//        }
//
//        if (is_null(static::$formFields)) {
//            static::$formFields = FormFieldsParser::getShortCodeInputs(
//                static::getForm(), ['admin_label', 'attributes', 'options', 'raw']
//            );
//        }
//
//        $field = Arr::get(static::$formFields, $key, '');
//
//
//        if (!$field) return '';
//
//        if ($isHtml) {
//            return apply_filters(
//                'wppayform_response_render_' . $field['element'],
//                static::$store['original_inputs'][$key],
//                $field,
//                static::getForm()->id,
//                $isHtml
//            );
//        }
//
//        return static::$entry[$key] = apply_filters(
//            'wppayform_response_render_' . $field['element'],
//            static::$entry[$key],
//            $field,
//            static::getForm()->id,
//            $isHtml
//        );
    }
}
