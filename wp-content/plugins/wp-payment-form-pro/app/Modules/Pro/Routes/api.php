<?php

/**
 * @var $router WPPayForm\Framework\Http\Router
 */

use WPPayForm\Framework\Request\Request;

$router->prefix('form/{id}')->group(function ($router) {
    $router->prefix('/schedule')->group(function ($router) {
        $router->get('/', function ($formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\SchedulingSettings())->getSettings($formId);
        });

        $router->post('/', function (Request $request, $formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\SchedulingSettings())->updateSettings($request, $formId);
        });
    });

    $router->prefix('/export')->group(function ($router) {
        $router->get('/', function (Request $request, $formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\Export\Export())->exportData($request, $formId);
        });
    });

    $router->prefix('/scripts')->group(function ($router) {
        $router->get('/', function ($formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\CustomScripts())->getSettings($formId);
        });

        $router->post('/', function (Request $request, $formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\CustomScripts())->saveSettings($request, $formId);
        });
    });

    $router->prefix('/email')->group(function ($router) {
        $router->get('/', function ($formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\EmailNotification\EmailAjax())->getNotifications($formId);
        });
        $router->post('/', function (Request $request, $formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\EmailNotification\EmailAjax())->saveNotifications($request, $formId);
        });

        $router->get('/only', function ($formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\EmailNotification\EmailAjax())->getNotificationsOnly($formId);
        });

        $router->post('/resend', function (Request $request, $formId) {
            return (new WPPayForm\App\Modules\Pro\Classes\EmailNotification\EmailAjax())->resendNotifications($request, $formId);
        });
    });
});

$router->prefix('settings')->group(function ($router) {
    $router->prefix('/coupon')->group(function ($router) {
        $router->get('/', function () {
            return (new WPPayForm\App\Modules\Pro\Classes\Coupons\Coupon())->getCoupons();
        });

        $router->post('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\Classes\Coupons\Coupon())->saveCoupon($request);
        });

        $router->delete('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\Classes\Coupons\Coupon())->deleteCoupon($request);
        });

        $router->post('/activate', function () {
            return (new WPPayForm\App\Modules\Pro\Classes\Coupons\Coupon())->enableCoupons();
        });
    });

    $router->prefix('/paypal')->group(function ($router) {
        $router->get('/', function () {
            return (new WPPayForm\App\Modules\Pro\GateWays\PayPal\PayPal())->getPaymentSettings();
        });
        $router->post('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\GateWays\PayPal\PayPal())->savePaymentSettings($request);
        });
    });

    $router->prefix('/mollie')->group(function ($router) {
        $router->get('/', function () {
            return (new WPPayForm\App\Modules\Pro\GateWays\Mollie\MollieSettings())->getSettings();
        });
        $router->post('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\GateWays\Mollie\MollieSettings())->saveSettings($request);
        });
    });

    $router->prefix('/razorpay')->group(function ($router) {
        $router->get('/', function () {
            return (new WPPayForm\App\Modules\Pro\GateWays\Razorpay\RazorpaySettings())->getPaymentSettings();
        });
        $router->post('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\GateWays\Razorpay\RazorpaySettings())->savePaymentSettings($request);
        });
    });


    $router->prefix('/paystack')->group(function ($router) {
        $router->get('/', function () {
            return (new WPPayForm\App\Modules\Pro\GateWays\Paystack\PaystackSettings())->getPaymentSettings();
        });
        $router->post('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\GateWays\Paystack\PaystackSettings())->savePaymentSettings($request);
        });
    });

    $router->prefix('/offline')->group(function ($router) {
        $router->get('/', function () {
            return (new WPPayForm\App\Modules\Pro\GateWays\Offline\OfflineSettings())->getPaymentSettings();
        });
        $router->post('/', function (Request $request) {
            return (new WPPayForm\App\Modules\Pro\GateWays\Offline\OfflineSettings())->savePaymentSettings($request);
        });
    });
});


$router->prefix('license')->group(function ($router) {
    $controller = new WPPayForm\App\Modules\Pro\Classes\LicenseController();

    $router->get('/', function () use ($controller) {
        return $controller->getStatus();
    });
    $router->post('/', function () use ($controller) {
        return $controller->saveLicense();
    });
    $router->delete('/', function () use ($controller) {
        return $controller->deactivateLicense();
    });
});
