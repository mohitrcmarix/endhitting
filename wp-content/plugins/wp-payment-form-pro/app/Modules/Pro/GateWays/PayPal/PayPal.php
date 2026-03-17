<?php

namespace WPPayForm\App\Modules\Pro\GateWays\PayPal;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\OrderItem;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Models\Subscription;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Services\AccessControl;
use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\App\Services\ConfirmationHelper;
use WPPayForm\Framework\Support\Arr;

class PayPal
{
    public function init()
    {
        // Init paypal Element for Editor
        new PayPalElement();
        // Choose Payment method Here
        add_filter('wppayform/choose_payment_method_for_submission', array($this, 'choosePaymentMethod'), 10, 4);
        add_action('wppayform/form_submission_make_payment_paypal', array($this, 'makeFormPayment'), 10, 6);

        add_filter('wppayform/entry_transactions_paypal', array($this, 'addTransactionUrl'), 10, 2);
        add_action('init', array($this, 'verifyIPN'));
        add_action('wppayform/form_render_after', array($this, 'checkForCancelMessage'), 10, 1);

        add_filter('wppayform/paypal_payment_args', array($this, 'maybeHasSubscription'), 10, 5);

        // View level hooks
        add_filter('wppayform/subscription_items_paypal', array($this, 'formatSubscriptionItems'), 10, 2);

        add_filter('wppayform/submitted_payment_items_paypal', array($this, 'validateSubmittedItems'), 10, 4);
    }

    public function checkForCancelMessage($form)
    {
        if (isset($_REQUEST['wpf_paypal_cancel']) && isset($_REQUEST['wpf_form_id'])) {
            $formId = intval($_REQUEST['wpf_form_id']);
            if ($formId == $form->ID) {
                echo '<div class="wpf_form_notices wpf_form_errors wpf_paypal_error">' . __('Looks like you have canceled the payment from paypal', 'wppayform') . '</div>';
            }
        }
    }

    public function addTransactionUrl($transactions, $formId)
    {
        foreach ($transactions as $transaction) {
            if ($transaction->payment_method == 'paypal' && $transaction->charge_id) {
                $transaction->transaction_url = $this->getPayPalTransactionUrl($transaction);
            }
        }
        return $transactions;
    }

    private function getPayPalTransactionUrl($transaction)
    {
        $sandbox = 'test' == $transaction->payment_mode ? 'sandbox.' : '';
        return 'https://www.' . $sandbox . 'paypal.com/activity/payment/' . $transaction->charge_id;
    }

    public function choosePaymentMethod($paymentMethod, $elements, $formId, $form_data)
    {
        if ($paymentMethod) {
            // Already someone choose that it's their payment method
            return $paymentMethod;
        }
        // Now We have to analyze the elements and return our payment method
        foreach ($elements as $element) {
            if ((isset($element['type']) && $element['type'] == 'paypal_gateway_element')) {
                return 'paypal';
            }
        }
        return $paymentMethod;
    }

    public function makeFormPayment($transactionId, $submissionId, $form_data, $form, $hasSubscriptions)
    {
        $paypalSettings = $this->getPaypalSettings();
        $paymentMode = 'live';
        if ($paypalSettings['payment_mode'] == 'test') {
            $paymentMode = 'test';
        }

        if ($paymentMode == 'test') {
            $paypal_redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr/?';
        } else {
            $paypal_redirect = 'https://www.paypal.com/cgi-bin/webscr/?';
        }

        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($submissionId);

        $listener_url = apply_filters('wppayform/paypal_ipn_url', site_url('?wpf_paypal_ipn=1'), $submission);

        // For Dev purpose only
        // $listener_url = 'https://19be48af.ngrok.io?wpf_paypal_ipn=1';

        $paypal_args = array(
            'cmd' => '_cart',
            'upload' => '1',
            'business' => $paypalSettings['paypal_email'],
            'email' => $submission->customer_email,
            'no_shipping' => (Arr::get($form_data, '__payment_require_shipping_address') == 'yes') ? '0' : '1',
            'no_note' => '1',
            'currency_code' => $submission->currency,
            'charset' => 'UTF-8',
            'custom' => $transactionId,
            'return' => $this->getSuccessURL($form, $submission),
            'notify_url' => $listener_url,
            'cancel_return' => $this->getCancelURL($form_data, $submission),
            'image_url' => Arr::get($paypalSettings, 'checkout_logo'),
        );

        $cart_summary = $this->getCartSummery($submissionId, $form->ID);

        // We have to check if it's $0 order. If it's $0 then we must have to return just from here
        if (!$cart_summary && !$hasSubscriptions) {
            return;
        }

        // Now the problem is this payment may hve $0 subscription which is a really pain
        // That we have to handle

        $paypal_args = array_merge($cart_summary, $paypal_args);
        $paypal_args = apply_filters('wppayform/paypal_payment_args', $paypal_args, $submission, $form_data, $paymentMode, $hasSubscriptions);

        if (!$cart_summary && $paypal_args['cmd'] == '_cart') {
            return;
        }

        $submissionModel->updateSubmission($submissionId, array(
            'payment_mode' => $paymentMode
        ));

        if ($transactionId) {
            $transactionModel = new Transaction();
            $transactionModel->updateTransaction($transactionId, array(
                'payment_mode' => $paymentMode
            ));
        }


        $paypal_redirect .= http_build_query($paypal_args);

        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'info',
            'created_by' => 'Payform Bot',
            'content' => __('Redirect to paypal for payment', 'wppayform')
        ));

        wp_send_json_success(array(
            'message' => __('Form is successfully submitted', 'wppayform'),
            'submission_id' => $submissionId,
            'confirmation' => array(
                'confirmation_type' => 'custom',
                'redirectTo' => 'customUrl',
                'customUrl' => $paypal_redirect,
                'messageToShow' => __('Your are redirecting to paypal now', 'wppayform'),
                'samePageFormBehavior' => 'reset_form',
            )
        ), 200);
        exit;
    }

    private function getCancelURL($form_data, $submission)
    {
        $formUrl = Arr::get($form_data, '__wpf_current_url');
        if (!$formUrl) {
            $formUrl = home_url();
        }
        $url = add_query_arg(array(
            'wpf_form_id' => $submission->form_id,
            'wpf_paypal_cancel' => $submission->id
        ), $formUrl);
        $url .= '#wpf_form_id_' . $submission->form_id;
        return $url;
    }

    private function getSuccessURL($form, $submission)
    {
        // Check If the form settings have success URL
        $confirmation = Form::getConfirmationSettings($form->ID);
        $confirmation = ConfirmationHelper::parseConfirmation($confirmation, $submission);
        if (
            ($confirmation['redirectTo'] == 'customUrl' && $confirmation['customUrl']) ||
            ($confirmation['redirectTo'] == 'customPage' && $confirmation['customPage'])
        ) {
            if ($confirmation['redirectTo'] == 'customUrl') {
                $url = $confirmation['customUrl'];
            } else {
                $url = get_permalink(intval($confirmation['customPage']));
            }
            $url = add_query_arg(array(
                'payment_method' => 'paypal'
            ), $url);
            return PlaceholderParser::parse($url, $submission);
        }
        // now we have to check for global Success Page
        $globalSettings = get_option('wppayform_confirmation_pages');
        if (isset($globalSettings['confirmation']) && $globalSettings['confirmation']) {
            return add_query_arg(array(
                'wpf_submission' => $submission->submission_hash,
                'payment_method' => 'paypal'
            ), get_permalink(intval($globalSettings['confirmation'])));
        }
        // In case we don't have global settings
        return add_query_arg(array(
            'wpf_submission' => $submission->submission_hash,
            'payment_method' => 'paypal'
        ), home_url());
    }

    private function getCartSummery($submissionId, $formId)
    {
        $orderItemModel = new OrderItem();
        $items = $orderItemModel->getOrderItems($submissionId);
        $discountItems = $orderItemModel->getDiscountItems($submissionId);

        $paypal_args = array();
        if ($items) {
            $counter = 1;

            foreach ($items as $item) {
                if (!$item->item_price) {
                    continue;
                }

                $paypal_args['item_name_' . $counter] = $item->item_name;
                $paypal_args['quantity_' . $counter] = $item->quantity;
                $paypal_args['amount_' . $counter] = round($item->item_price / 100, 2);
                $counter = $counter + 1;
            }
        }

        if ($discountItems) {
            $discountTotal = 0;
            foreach ($discountItems as $discountItem) {
                $discountTotal += intval($discountItem->line_total);
            }
            $paypal_args['discount_amount_cart'] = round($discountTotal / 100, 2);
        }
        return $paypal_args;
    }

    public function verifyIPN()
    {
        if (!isset($_REQUEST['wpf_paypal_ipn'])) {
            return;
        }

        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log(json_encode($_REQUEST));
        }

        // Check the request method is POST
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
            return;
        }

        // Set initial post data to empty string
        $post_data = '';

        // Fallback just in case post_max_size is lower than needed
        if (ini_get('allow_url_fopen')) {
            $post_data = file_get_contents('php://input');
        } else {
            // If allow_url_fopen is not enabled, then make sure that post_max_size is large enough
            ini_set('post_max_size', '12M');
        }
        // Start the encoded data collection with notification command
        $encoded_data = 'cmd=_notify-validate';

        // Get current arg separator
        $arg_separator = ini_get('arg_separator.output');

        // Verify there is a post_data
        if ($post_data || strlen($post_data) > 0) {
            // Append the data
            $encoded_data .= $arg_separator . $post_data;
        } else {
            // Check if POST is empty
            if (empty($_POST)) {
                // Nothing to do
                return;
            } else {
                // Loop through each POST
                foreach ($_POST as $key => $value) {
                    // Encode the value and append the data
                    $encoded_data .= $arg_separator . "$key=" . urlencode($value);
                }
            }
        }

        // Convert collected post data to an array
        parse_str($encoded_data, $encoded_data_array);

        foreach ($encoded_data_array as $key => $value) {
            if (false !== strpos($key, 'amp;')) {
                $new_key = str_replace('&amp;', '&', $key);
                $new_key = str_replace('amp;', '&', $new_key);
                unset($encoded_data_array[$key]);
                $encoded_data_array[$new_key] = $value;
            }
        }

        /**
         * PayPal Web IPN Verification
         *
         * Allows filtering the IPN Verification data that PayPal passes back in via IPN with PayPal Standard
         *
         *
         * @param array $data The PayPal Web Accept Data
         */
        $encoded_data_array = apply_filters('wppayform/process_paypal_ipn_data', $encoded_data_array);

        $paymentSettings = $this->getPaypalSettings();

        if ($paymentSettings['disable_ipn_verification'] != 'yes') {
            // Validate the IPN
            $remote_post_vars = array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array(
                    'host' => 'www.paypal.com',
                    'connection' => 'close',
                    'content-type' => 'application/x-www-form-urlencoded',
                    'post' => '/cgi-bin/webscr HTTP/1.1',
                    'user-agent' => 'WPPayForm IPN Verification/' . WPPAYFORM_VERSION . '; ' . get_bloginfo('url')
                ),
                'sslverify' => false,
                'body' => $encoded_data_array
            );
            // Get response
            $api_response = wp_remote_post($this->getPaypalRedirect(true, true), $remote_post_vars);
            if (is_wp_error($api_response)) {
                if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
                    error_log('WPPYFORM: IPN Verification Failed for api reponse error');
                }
                do_action('wppayform/paypal_ipn_verification_failed', $remote_post_vars, $encoded_data_array);
                return; // Something went wrong
            }
            if (wp_remote_retrieve_body($api_response) !== 'VERIFIED') {
                if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
                    error_log('WPPYFORM: IPN Verification Failed');
                }

                do_action('wppayform/paypal_ipn_not_verified', $api_response, $remote_post_vars, $encoded_data_array);
                return; // Response not okay
            }
        }

        // Check if $post_data_array has been populated
        if (!is_array($encoded_data_array) && !empty($encoded_data_array)) {
            return;
        }

        $defaults = array(
            'txn_type' => '',
            'payment_status' => '',
            'custom' => ''
        );

        $encoded_data_array = wp_parse_args($encoded_data_array, $defaults);

        $payment_id = 0;

        if (!empty($encoded_data_array['parent_txn_id'])) {
            $payment_id = $this->getPaymentIdByTransactionId($encoded_data_array['parent_txn_id']);
        } elseif (!empty($encoded_data_array['txn_id'])) {
            $payment_id = $this->getPaymentIdByTransactionId($encoded_data_array['txn_id']);
        }

        if (empty($payment_id)) {
            $payment_id = !empty($encoded_data_array['custom']) ? absint($encoded_data_array['custom']) : 0;
        }

        if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
            error_log('IPN DATA: ');
            error_log(json_encode($encoded_data_array));
        }

        if (has_action('wppayform/paypal_action_' . $encoded_data_array['txn_type'])) {
            // Allow PayPal IPN types to be processed separately
            do_action('wppayform/paypal_action_' . $encoded_data_array['txn_type'], $encoded_data_array, $payment_id);
        } else {
            if (defined('PAYFORM_PAYPAL_IPN_DEBUG')) {
                error_log('paypal_action_web_accept IPN: ');
                error_log(json_encode($encoded_data_array));
            }
            // Fallback to web accept just in case the txn_type isn't present
            do_action('wppayform/paypal_action_web_accept', $encoded_data_array, $payment_id);
        }
        exit;
    }

    private function getPaypalRedirect($ssl_check = false, $ipn = false)
    {
        $protocol = 'http://';
        if (is_ssl() || !$ssl_check) {
            $protocol = 'https://';
        }

        // Check the current payment mode
        if ($this->isTestMode()) {
            // Test mode
            if ($ipn) {
                $paypal_uri = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
            } else {
                $paypal_uri = $protocol . 'www.sandbox.paypal.com/cgi-bin/webscr';
            }
        } else {
            // Live mode
            if ($ipn) {
                $paypal_uri = 'https://ipnpb.paypal.com/cgi-bin/webscr';
            } else {
                $paypal_uri = $protocol . 'www.paypal.com/cgi-bin/webscr';
            }
        }
        return apply_filters('wppayform/paypal_url', $paypal_uri, $ssl_check, $ipn);
    }

    private function getPaymentIdByTransactionId($chargeId)
    {
        $payment = Transaction::where('charge_id', $chargeId)
            ->where('payment_method', 'payapl')
            ->first();
        if ($payment) {
            return $payment->id;
        }
        return false;
    }

    public function savePaymentSettings($request)
    {
        AccessControl::checkAndPresponseError('set_payment_settings', 'global');
        $settings = $request->settings;
        // Validate the data first
        $mode = $settings['payment_mode'];

        // We require paypal Email Adddress
        if (empty($settings['paypal_email']) || !is_email($settings['paypal_email'])) {
            wp_send_json_error(array(
                'message' => __('Please enter valid email address', 'wppayform')
            ), 423);
        }

        // Validation Passed now let's make the data
        $data = array(
            'payment_mode' => sanitize_text_field($mode),
            'paypal_email' => sanitize_text_field($settings['paypal_email']),
            'disable_ipn_verification' => sanitize_text_field($settings['disable_ipn_verification']),
            'checkout_logo' => sanitize_text_field($settings['checkout_logo'])
        );
        do_action('wppayform/before_save_paypal_settings', $data);
        update_option('wppayform_paypal_payment_settings', $data, false);
        do_action('wppayform/after_save_paypal_settings', $data);

        $confrimationSettings = $request->confirmation_pages;
        $confirmationPages = array(
            'confirmation' => intval($confrimationSettings['confirmation']),
            'failed' => intval($confrimationSettings['failed'])
        );
        update_option('wppayform_confirmation_pages', $confirmationPages, false);

        return array(
            'message' => __('Settings successfully updated', 'wppayform')
        );
    }

    public function getPaymentSettings()
    {
        $pages = Form::select(array('ID', 'post_title'))
            ->where('post_type', 'page')
            ->where('post_status', 'publish')
            ->get();

        return array(
            'settings' => $this->getPaypalSettings(),
            'confirmation_pages' => GeneralSettings::getConfirmationPageSettings(),
            'pages' => $pages
        );
    }

    public function getPaypalSettings()
    {
        $settings = get_option('wppayform_paypal_payment_settings');
        if (!$settings) {
            $settings = array();
        }
        $defaults = array(
            'payment_mode' => 'live',
            'paypal_email' => '',
            'disable_ipn_verification' => 'no',
            'checkout_logo' => ''
        );
        return wp_parse_args($settings, $defaults);
    }

    public function maybeHasSubscription($originalArgs, $submission, $form_data, $paymentMode, $hasSubscriptions)
    {
        if (!$hasSubscriptions) {
            return $originalArgs;
        }

        $subscriptionModel = new Subscription();
        $subscriptions = $subscriptionModel->getSubscriptions($submission->id);

        $validSubscriptions = [];
        foreach ($subscriptions as $subscriptionItem) {
            if ($subscriptionItem->recurring_amount) {
                $validSubscriptions[] = $subscriptionItem;
            }
        }

        if (!$validSubscriptions || count($validSubscriptions) > 1) {
            // PayPal Standard does not support more than 1 subscriptions
            // We may add paypal express later for this on.
            return $originalArgs;
        }

        // We just need the first subscriptipn
        $subscription = $validSubscriptions[0];

        if (!$subscription->recurring_amount) {
            return $originalArgs;
        }


        unset($originalArgs['item_name_1']);
        unset($originalArgs['quantity_1']);
        unset($originalArgs['amount_1']);
        unset($originalArgs['cmd']);


        // $originalArgs['notify_url'] .= '&wpf_subscription_id='.$subscription->id;
        $originalArgs['custom'] = $subscription->id;

        // Form that subscription we have to create a transaction as parent

        $customerName = $submission->customer_name;
        $names = explode(' ', $customerName, 2);

        if (count($names) == 2) {
            $firstName = $names[0];
            $lastName = $names[1];
        } else {
            $firstName = $customerName;
            $lastName = '';
        }

        $paypal_args = array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'invoice' => $subscription->id,
            'no_shipping' => '1',
            'shipping' => '0',
            'no_note' => '1',
            'rm' => '2',
            'cbt' => get_bloginfo('name'),
            'sra' => '1',
            'src' => '1',
            'cmd' => '_xclick-subscriptions'
        );

        $initial_amount = round($subscription->initial_amount / 100, 2);
        $recurring_amount = round($subscription->recurring_amount / 100, 2);

        if ($subscription->quantity) {
            $recurring_amount = $recurring_amount * intval($subscription->quantity);
        }

        if ($initial_amount) {
            $paypal_args['a1'] = round($initial_amount + $recurring_amount, 2);
            $paypal_args['p1'] = 1;
        } elseif ($subscription->trial_days) {
            $paypal_args['a1'] = 0;
            $paypal_args['p1'] = $subscription->trial_days;
            $paypal_args['t1'] = 'D';
        }

        $paypal_args['a3'] = $recurring_amount;

        $paypal_args['item_name'] = $subscription->item_name . ' (' . $subscription->plan_name . ') - ' . $subscription->form_id;

        $paypal_args['p3'] = 1; // for now it's 1 as 1 times per period

        switch ($subscription->billing_interval) {
            case 'daily':
                $paypal_args['t3'] = 'D';
                break;
            case 'week':
                $paypal_args['t3'] = 'W';
                break;
            case 'month':
                $paypal_args['t3'] = 'M';
                break;
            case 'year':
                $paypal_args['t3'] = 'Y';
                break;
        }
        if ($initial_amount) {
            $paypal_args['t1'] = $paypal_args['t3'];
        }

        if ($subscription->bill_times > 1) {
            if ($initial_amount) {
                $subscription->bill_times = $subscription->bill_times - 1;
            }
            $billTimes = $subscription->bill_times <= 52 ? absint($subscription->bill_times) : 52;
            $paypal_args['srt'] = $billTimes;
        }

        $orderItemModel = new OrderItem();
        $discountItems = $orderItemModel->getDiscountItems($submission->id);
        if ($discountItems) {
            $discountTotal = 0;
            foreach ($discountItems as $discountItem) {
                $discountTotal += intval($discountItem->line_total);
            }
            if (isset($paypal_args['a3'])) {
                $paypal_args['a3'] -= round($discountTotal / 100, 2);
            }
        }

        return wp_parse_args($paypal_args, $originalArgs);
    }

    public function formatSubscriptionItems($items, $transaction)
    {
        $paymentResponse = $transaction->payment_note;
        $items[] = array(
            'item_name' => Arr::get($paymentResponse, 'transaction_subject'),
            'status' => $transaction->status,
            'payment_total' => $transaction->payment_total,
            'transaction_id' => $transaction->charge_id,
            'payment_method' => 'paypal',
            'view_url' => $this->getPayPalTransactionUrl($transaction)
        );
        return $items;
    }

    private function isTestMode()
    {
        $settings = $this->getPaypalSettings();
        return $settings['payment_mode'] != 'live';
    }

    public function validateSubmittedItems($paymentItems, $formattedElements, $form_data, $subscriptionItems)
    {
        $singleItemTotal = 0;
        foreach ($paymentItems as $paymentItem) {
            if ($paymentItem['line_total']) {
                $singleItemTotal += $paymentItem['line_total'];
            }
        }

        $validSubscriptions = [];
        foreach ($subscriptionItems as $subscriptionItem) {
            if ($subscriptionItem['recurring_amount']) {
                $validSubscriptions[] = $subscriptionItem;
            }
        }

        if ($singleItemTotal && count($validSubscriptions)) {
            wp_send_json_error(array(
                'message' => __('PayPal does not support subscriptions payment and Single Amount Payment at one request', 'wppayform'),
                'payment_error' => true
            ), 423);
        }

        if (count($validSubscriptions) > 2) {
            wp_send_json_error(array(
                'message' => __('PayPal does not support multiple subscriptions at one request', 'wppayform'),
                'payment_error' => true
            ), 423);
        }

        return $paymentItems;
    }
}
