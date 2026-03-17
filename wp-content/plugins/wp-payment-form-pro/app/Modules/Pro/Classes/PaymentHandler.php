<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Transaction;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Info Handler Class
 * @since 1.0.0
 */
class PaymentHandler
{
    public function init()
    {
        // Register PayPal Payment Gateway
        $paypal = new \WPPayForm\App\Modules\Pro\GateWays\PayPal\PayPal();
        $paypal->init();

        // Register PayPal IPN
        $paypalIpn = new \WPPayForm\App\Modules\Pro\GateWays\PayPal\PayPalIpn();
        $paypalIpn->init();

        $mollie = new \WPPayForm\App\Modules\Pro\GateWays\Mollie\MollieProcessor();
        $mollie->init();

        $razorpay = new \WPPayForm\App\Modules\Pro\GateWays\Razorpay\RazorpayProcessor();
        $razorpay->init();

        $paystack = new \WPPayForm\App\Modules\Pro\GateWays\Paystack\PaystackProcessor();
        $paystack->init();

        // Register Stripe Event Handler
        $stripeEvent = new \WPPayForm\App\Modules\Pro\GateWays\Stripe\StripeListener();
        $stripeEvent->init();

        // Register Offline Payment Gateway
        $offline = new \WPPayForm\App\Modules\Pro\GateWays\Offline\OfflineProcessor();
        $offline->init();

        if (isset($_GET['wppayform_payment']) && isset($_GET['payment_method'])) {
            add_action('wp', function () {
                $data = $_GET;
                $this->validateFrameLessPage($data);
                $paymentMethod = sanitize_text_field($_GET['payment_method']);
                do_action('wppayform_payment_frameless_' . $paymentMethod, $data);
            });
        }

        if (isset($_REQUEST['wpf_payment_api_notify'])) {
            add_action('wp', function () {
                $paymentMethod = sanitize_text_field($_REQUEST['payment_method']);
                do_action('wpf_ipn_endpoint_' . $paymentMethod);
            });
        }
    }

    private function validateFrameLessPage($data)
    {
        // We should verify the transaction hash from the URL
        $paymentMethod = sanitize_text_field(Arr::get($data, 'payment_method'));
        $submissionId = intval(Arr::get($data, 'wppayform_payment'));

        if (!$submissionId || !$paymentMethod) {
            die('Validation Failed');
        }
        if ($submissionId) {
            $transaction = Transaction::where('submission_id', $submissionId)
                ->where('payment_method', $paymentMethod)
                ->first();
            if (!$transaction) {
                die('Submission or payment method not matched!');
            }
        }

        $hash = sanitize_text_field(Arr::get($data, 'submission_hash'));
        if (!$hash) {
            die('Validation Failed');
        } else {
            $submission = Submission::where('submission_hash', $hash)->first();
            if (!$submission) {
                die('Submission Hash Invalid');
            }
        }
        return true;
    }
}
