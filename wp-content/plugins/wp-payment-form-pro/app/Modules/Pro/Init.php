<?php

namespace WPPayForm\App\Modules\Pro;

if (!defined('ABSPATH')) {
    exit;
}
if (!defined('WPPAYFORMHASPRO')) {
    define('WPPAYFORMHASPRO', true);
}

class Init
{
    protected $addOns = array(
        'WPPayForm\App\Modules\Pro\Integrations\ActiveCampaign',
        'WPPayForm\App\Modules\Pro\Integrations\Aweber',
        'WPPayForm\App\Modules\Pro\Integrations\UserRegistration'
    );

    public function boot()
    {
        $this->commonHooks();
        $this->registerAddOns();
    }

    public function registerRoutes($router)
    {
        include WPPAYFORM_DIR . 'app/Modules/Pro/Routes/api.php';
    }

    public function registerAddOns()
    {
        foreach ($this->addOns as $addOn) {
            $class = "{$addOn}\Bootstrap";
            new $class();
        }
    }

    public function commonHooks()
    {
        $scheduleSettings = new \WPPayForm\App\Modules\Pro\Classes\SchedulingSettings();
        $scheduleSettings->checkRestrictionHooks();

        $paymentHandler = new \WPPayForm\App\Modules\Pro\Classes\PaymentHandler();
        $paymentHandler->init();

        // Additional Form Info Handler
        $infoHandler = new \WPPayForm\App\Modules\Pro\Classes\FormAdditionalInfo();
        $infoHandler->register();

        $emailHandler = new \WPPayForm\App\Modules\Pro\Classes\EmailNotification\EmailHandler();
        $emailHandler->register();

        $userCreation = new \WPPayForm\App\Modules\Pro\Integrations\UserRegistration\UserCreation();
        $userCreation->register();

        // Init Pro Editor Components Here
        new \WPPayForm\App\Modules\Pro\Classes\Components\RecurringPaymentComponent();
        new \WPPayForm\App\Modules\Pro\Classes\Components\TaxItemComponent();
        new \WPPayForm\App\Modules\Pro\Classes\Components\TabularProductsComponent();
        new \WPPayForm\App\Modules\Pro\Classes\Components\FileUploadComponent();
        new \WPPayForm\App\Modules\Pro\Classes\Components\AddressFieldsComponent();
        new \WPPayForm\App\Modules\Pro\Classes\Components\MaskInputComponent();
        new \WPPayForm\App\Modules\Pro\Classes\Components\CouponComponent();

        add_filter('wppayform/print_styles', function ($styles) {
            return [
                WPPAYFORM_URL . 'assets/css/payforms-admin.css',
                WPPAYFORM_URL . 'assets/css/payforms-print.css',
            ];
        }, 1, 1);


        // Custom CSS and JS
        $customCssJS = new \WPPayForm\App\Modules\Pro\Classes\CustomScripts();
        $customCssJS->registerEndpoints();

        // Default value Parser
        $formDefaultValueRenderer = new \WPPayForm\App\Modules\Pro\Classes\DefaultValueParser\FormDefaultValueRenderer();
        $formDefaultValueRenderer->register();

        add_shortcode('payform_user_submissions', function ($args) {
            $handler = new \WPPayForm\App\Modules\Pro\Classes\ProShortCodeHandler();
            return $handler->handleUserSubmissionShortCode($args);
        });
    }
}
