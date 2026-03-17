<?php

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */

$globalsDevFile = __DIR__ . '/globals_dev.php';

is_readable($globalsDevFile) && include $globalsDevFile;

// if (!function_exists('dd')) {
//     function dd()
//     {
//         foreach (func_get_args() as $arg) {
//             echo "<pre>";
//             print_r($arg);
//             echo "</pre>";
//         }
//         die();
//     }
// }


function wpPayFormFormatMoney($amountInCents, $formId = false)
{
    if (!$formId) {
        $currencySettings = \WPPayForm\App\Services\GeneralSettings::getGlobalCurrencySettings();
    } else {
        $currencySettings = \WPPayForm\App\Models\Form::getCurrencySettings($formId);
    }
    if (empty($currencySettings['currency_sign'])) {
        $currencySettings['currency_sign'] = \WPPayForm\App\Services\GeneralSettings::getCurrencySymbol($currencySettings['currency']);
    }
    return wpPayFormFormattedMoney($amountInCents, $currencySettings);
}

function wpPayFormFormattedMoney($amountInCents, $currencySettings)
{
    $symbol = $currencySettings['currency_sign'];
    $position = $currencySettings['currency_sign_position'];
    $decmalSeparator = '.';
    $thousandSeparator = ',';
    if ($currencySettings['currency_separator'] != 'dot_comma') {
        $decmalSeparator = ',';
        $thousandSeparator = '.';
    }
    $decimalPoints = 2;
    if ($amountInCents % 100 == 0 && $currencySettings['decimal_points'] == 0) {
        $decimalPoints = 0;
    }

    $amount = number_format($amountInCents / 100, $decimalPoints, $decmalSeparator, $thousandSeparator);

    if ('left' === $position) {
        return $symbol . $amount;
    } elseif ('left_space' === $position) {
        return $symbol . ' ' . $amount;
    } elseif ('right' === $position) {
        return $amount . $symbol;
    } elseif ('right_space' === $position) {
        return $amount . ' ' . $symbol;
    }
    return $amount;
}

function wpPayFormConverToCents($amount)
{
    if (!$amount) {
        return 0;
    }
    $amount = floatval($amount);
    return round($amount * 100, 0);
}

function wppayformUpgradeUrl()
{
    return 'https://wpmanageninja.com/downloads/wppayform-pro-wordpress-payments-form-builder/?utm_source=plugin&utm_medium=menu&utm_campaign=upgrade';
}

function wppayformPublicPath($assets_path)
{
    return WPPAYFORM_URL . '/assets/' . $assets_path;
}
