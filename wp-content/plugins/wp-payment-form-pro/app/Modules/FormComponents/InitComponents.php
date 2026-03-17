<?php

namespace WPPayForm\App\Modules\FormComponents;

class InitComponents
{
    public function __init()
    {
        // Load and Register Form Components
        new \WPPayForm\App\Modules\FormComponents\CustomerNameComponent();
        new \WPPayForm\App\Modules\FormComponents\CustomerEmailComponent();
        new \WPPayForm\App\Modules\FormComponents\TextComponent();
        new \WPPayForm\App\Modules\FormComponents\NumberComponent();
        new \WPPayForm\App\Modules\FormComponents\SelectComponent();
        new \WPPayForm\App\Modules\FormComponents\RadioComponent();
        new \WPPayForm\App\Modules\FormComponents\CheckBoxComponent();
        new \WPPayForm\App\Modules\FormComponents\TextAreaComponent();
        new \WPPayForm\App\Modules\FormComponents\HtmlComponent();
        new \WPPayForm\App\Modules\FormComponents\PaymentItemComponent();
        new \WPPayForm\App\Modules\FormComponents\ItemQuantityComponent();
        new \WPPayForm\App\Modules\FormComponents\DateComponent();
        new \WPPayForm\App\Modules\FormComponents\CustomAmountComponent();
        new \WPPayForm\App\Modules\FormComponents\ChoosePaymentMethodComponent();
        new \WPPayForm\App\Modules\FormComponents\HiddenInputComponent();
        new \WPPayForm\App\Modules\FormComponents\ConsentComponent();
        new \WPPayForm\App\Modules\FormComponents\PasswordComponent();

        if (!defined('WPPAYFORM_PRO_INSTALLED')) {
            new \WPPayForm\App\Modules\FormComponents\DemoFileUploadComponent();
            new \WPPayForm\App\Modules\FormComponents\DemoTaxItemComponent();
            new \WPPayForm\App\Modules\FormComponents\DemoPayPalElement();
            new \WPPayForm\App\Modules\FormComponents\DemoMollieElement();
            new \WPPayForm\App\Modules\FormComponents\DemoRazorpayElement();
            new \WPPayForm\App\Modules\FormComponents\DemoPaystackElement();
            new \WPPayForm\App\Modules\FormComponents\DemoTabularProductsComponent();
            new \WPPayForm\App\Modules\FormComponents\DemoRecurringPaymentComponent();
            new \WPPayForm\App\Modules\FormComponents\DemoCouponComponent();
        }
    }
}
