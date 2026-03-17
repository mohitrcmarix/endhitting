<?php

namespace WPPayForm\App\Hooks\Handlers;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\OrderItem;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Models\Subscription;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Modules\PaymentMethods\Stripe\Stripe;
use WPPayForm\App\Services\Browser;
use WPPayForm\App\Services\GeneralSettings;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\App\Services\ConfirmationHelper;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Submission Handler
 * @since 1.0.0
 */
class SubmissionHandler
{
    private $customerName = '';
    private $customerEmail = '';
    private $selectedPaymentMethod = '';
    private $appliedCoupons = array();
    private $formID = null;
    private $validCoupons = null;

    public function handleSubmission()
    {
        parse_str($_REQUEST['form_data'], $form_data);

        // Now Validate the form please
        $formId = absint($_REQUEST['form_id']);
        $this->formID = $formId;
        // Get Original Form Elements Now
        // Get Original Form Elements Now
        $totalPayableAmount = intval($_REQUEST['main_total']);
        $tax_total = intval($_REQUEST['tax_total']);


        do_action('wppayform/form_submission_activity_start', $formId);

        $form = Form::getForm($formId);

        if (!$form) {
            wp_send_json_error(array(
                'message' => __('Invalid request. Please try again', 'wppayform')
            ), 423);
        }

        $formattedElements = Form::getFormattedElements($formId);

        $this->validate($form_data, $formattedElements, $form);

        $paymentMethod = apply_filters('wppayform/choose_payment_method_for_submission', '', $formattedElements['payment_method_element'], $formId, $form_data);

        $this->selectedPaymentMethod = $paymentMethod;

        // Extract Payment Items Here
        $paymentItems = array();
        $subscriptionItems = array();

        foreach ($formattedElements['payment'] as $paymentId => $payment) {
            $quantity = $this->getItemQuantity($formattedElements['item_quantity'], $paymentId, $form_data);
            if ($quantity == 0) {
                continue;
            }
            if ($payment['type'] == 'recurring_payment_item') {
                $subscription = $this->getSubscriptionLine($payment, $paymentId, $quantity, $form_data, $formId);
                if (!empty($subscription['type']) && $subscription['type'] == 'single') {
                    // We converted this as one time payment
                    $paymentItems[] = $subscription;
                } else {
                    $subscriptionItems = array_merge($subscriptionItems, $subscription);
                }
            } elseif ($payment['type'] == 'coupon' && isset($form_data['__wpf_all_applied_coupons'])) {
                $this->appliedCoupons = json_decode($form_data['__wpf_all_applied_coupons']);
            } else {
                $lineItems = $this->getPaymentLine($payment, $paymentId, $quantity, $form_data);

                if ($lineItems) {
                    $paymentItems = array_merge($paymentItems, $lineItems);
                }
            }
        }

        $subscriptionItems = apply_filters('wppayform/submitted_subscription_items', $subscriptionItems, $formattedElements, $form_data);

        $discountPercent = 0;
        if (!empty($this->appliedCoupons)) {
            $amountToPay = $totalPayableAmount;
            $couponModel = new \WPPayForm\App\Modules\Pro\Classes\Coupons\CouponModel();
            $coupons = $couponModel->getCouponsByCodes($this->appliedCoupons);
            $validCouponItems = $couponModel->getValidCoupons($coupons, $this->formID, $amountToPay);
            $this->validCoupons = (new \WPPayForm\App\Modules\Pro\Classes\Coupons\CouponController())->getTotalLine($validCouponItems, $amountToPay);
            $discountPercent = ($this->validCoupons['totalDiscounts'] * 100) / $amountToPay;
        }

        $paymentItems = apply_filters('wppayform/submitted_payment_items', $paymentItems, $formattedElements, $form_data, $discountPercent);
        /*
         * providing filter hook for payment method to push some payment data
         *  from $subscriptionItems
         * Some PaymentGateway like stripe may add signup fee as one time fee
         */
        if ($subscriptionItems) {
            $paymentItems = apply_filters('wppayform/submitted_payment_items_' . $paymentMethod, $paymentItems, $formattedElements, $form_data, $subscriptionItems);
        }

        // Extract Input Items Here
        $inputItems = array();

        foreach ($formattedElements['input'] as $inputName => $inputElement) {
            $value = Arr::get($form_data, $inputName);
            $inputItems[$inputName] = apply_filters('wppayform/submitted_value_' . $inputElement['type'], $value, $inputElement, $form_data);
        }

        // Calculate Payment Total Now
        $paymentTotal = 0;
        $taxTotal = 0;
        foreach ($paymentItems as $paymentItem) {
            $paymentTotal += $paymentItem['line_total'];
            if ($paymentItem['type'] == 'tax_line') {
                $taxTotal += $paymentItem['line_total'];
            }
        }

        $currentUserId = get_current_user_id();
        if (!$this->customerName && $currentUserId) {
            $currentUser = get_user_by('ID', $currentUserId);
            $this->customerName = $currentUser->display_name;
        }

        if (!$this->customerEmail && $currentUserId) {
            $currentUser = get_user_by('ID', $currentUserId);
            $this->customerEmail = $currentUser->user_email;
        }

        if ($formattedElements['payment_method_element'] && !$paymentMethod) {
            wp_send_json_error(array(
                'message' => __('Validation failed, because selected payment method could not be found', 'wppayform')
            ), 423);
            exit;
        }

        if ($formattedElements['payment_method_element'] && $paymentMethod == 'stripe' && ($paymentTotal || $subscriptionItems)) {
            // do verification for stripe stripe_inline
            // We have to see if __stripe_payment_method_id has value or not
            $stripe = new Stripe();
            $methodStyle = $stripe->getStripePaymentMethodByElement($formattedElements['payment_method_element']);
            if ($methodStyle == 'stripe_inline') {
                if (empty($form_data['__stripe_payment_method_id'])) {
                    wp_send_json_error(array(
                        'message' => __('Validation failed, Please fill up card details', 'wppayform')
                    ), 423);
                    exit;
                }
            }
        }

        $currencySetting = Form::getCurrencySettings($formId);
        $currency = $currencySetting['currency'];
        $inputItems = apply_filters('wppayform/submission_data_formatted', $inputItems, $form_data, $formId);

        $submission = array(
            'form_id' => $formId,
            'user_id' => $currentUserId,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'form_data_raw' => maybe_serialize($form_data),
            'form_data_formatted' => maybe_serialize(wp_unslash($inputItems)),
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'submission_hash' => $this->getHash(),
            'payment_total' => $paymentTotal,
            'status' => 'new',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $browser = new Browser();
        $ipLoggingStatus = GeneralSettings::ipLoggingStatus(true);
        if ($ipLoggingStatus != 'no') {
            $submission['ip_address'] = $browser->getIp();
        }

        $submission['browser'] = $browser->getBrowser();
        $submission['device'] = $browser->getPlatform();

        $submission = apply_filters('wppayform/create_submission_data', $submission, $formId, $form_data);

        do_action('wppayform/wpf_before_submission_data_insert_' . $paymentMethod, $submission, $form_data, $paymentItems, $subscriptionItems);
        do_action('wppayform/wpf_before_submission_data_insert', $submission, $form_data, $paymentItems, $subscriptionItems);

        // Insert Submission
        $submissionModel = new Submission();
        $submissionId = $submissionModel->createSubmission($submission)->id;

        do_action('wppayform/after_submission_data_insert', $submissionId, $formId, $form_data, $formattedElements);

        /*
         * Dear Payment method developers,
         * Please don't use this hook to process the payment
         * The order items is not processed yet!
         */
        do_action('wppayform/after_submission_data_insert_' . $paymentMethod, $submissionId, $formId, $formattedElements['payment_method_element']);


        $submission = $submissionModel->getSubmission($submissionId);

        do_action('wppayform/after_form_submission_complete', $submission, $formId);

        if ($paymentItems || $subscriptionItems) {
            // Insert Payment Items
            $itemModel = new OrderItem();
            foreach ($paymentItems as $payItem) {
                $payItem['submission_id'] = $submissionId;
                $payItem['form_id'] = $formId;
                $itemModel->createOrder($payItem);
            }

            // insert subscription items
            $subsTotal = 0;
            $subscription = new Subscription();
            foreach ($subscriptionItems as $subscriptionItem) {
                $quantity = isset($subscriptionItem['quantity']) ? $subscriptionItem['quantity'] : 1;
                $linePrice = $subscriptionItem['recurring_amount'] * $quantity;
                $subsTotal += intval($linePrice);

                $subscriptionItem['submission_id'] = $submissionId;
                $subscription->createSubscription($subscriptionItem);
            }

            $hasSubscriptions = (bool)$subscriptionItems;
            $transactionId = false;
            $totalPayable = $paymentTotal + $subsTotal;

            if (isset($this->validCoupons)) {
                foreach ($this->validCoupons['discounts'] as $item) {
                    $item['submission_id'] = intval($submissionId);
                    $item['form_id'] = $formId;
                    $itemModel->create($item);
                }
                //issue on bottom line- should minus discount based on percent
                $paymentTotal = ($paymentTotal - ($paymentTotal * $discountPercent) / 100) + $taxTotal;
            }

            if ($paymentItems) {
                // Insert Transaction Item Now
                $transaction = array(
                    'form_id' => $formId,
                    'user_id' => $currentUserId,
                    'submission_id' => $submissionId,
                    'charge_id' => '',
                    'payment_method' => $paymentMethod,
                    'payment_total' => $paymentTotal,
                    'currency' => $currency,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );


                $transaction = apply_filters('wppayform/submission_transaction_data', $transaction, $formId, $form_data);
                $transactionModel = new Transaction();
                $transactionId = $transactionModel->createTransaction($transaction)->id;
                do_action('wppayform/after_transaction_data_insert', $transactionId, $transaction);
            }

            SubmissionActivity::createActivity(array(
                'form_id' => $form->ID,
                'submission_id' => $submissionId,
                'type' => 'activity',
                'created_by' => 'PayForm BOT',
                'content' => 'After payment actions processed.'
            ));

            if ($paymentMethod) {
                do_action('wppayform/form_submission_make_payment_' . $paymentMethod, $transactionId, $submissionId, $form_data, $form, $hasSubscriptions, $totalPayable);
            }
        }

        $this->sendSubmissionConfirmation($submission, $formId);
    }

    private function validate($form_data, $formattedElements, $form)
    {
        $errors = array();
        $formId = $form->ID;
        $customerName = '';
        $customerEmail = '';

        // Validate Normal Inputs
        foreach ($formattedElements['input'] as $elementId => $element) {
            $error = false;
            if (Arr::get($element, 'options.required') == 'yes' && empty($form_data[$elementId]) && !Arr::get($element, 'options.disable', false)) {
                $error = $this->getErrorLabel($element, $formId);
            }
            $error = apply_filters('wppayform/validate_data_on_submission_' . $element['type'], $error, $elementId, $element, $form_data);
            if ($error) {
                $errors[$elementId] = $error;
            }

            if ($element['type'] == 'customer_name' && !$customerName && isset($form_data[$elementId])) {
                $customerName = $form_data[$elementId];
            } elseif ($element['type'] == 'customer_email' && !$customerEmail && isset($form_data[$elementId])) {
                $customerEmail = $form_data[$elementId];
            }
        }

        // Validate Payment Fields
        foreach ($formattedElements['payment'] as $elementId => $element) {
            if (Arr::get($element, 'options.required') == 'yes' && !isset($form_data[$elementId]) && !Arr::get($element, 'options.disable', false)) {
                $errors[$elementId] = $this->getErrorLabel($element, $formId);
            }
        }
        // Validate Item Quantity Elements
        foreach ($formattedElements['item_quantity'] as $elementId => $element) {
            $error = '';
            if (isset($form_data[Arr::get($element, 'options.target_product')])) {
                if (Arr::get($element, 'options.required') == 'yes' && empty($form_data[$elementId]) && !Arr::get($element, 'options.disable', false)) {
                    $error = $this->getErrorLabel($element, $formId);
                }
            }

            $error = apply_filters('wppayform/validate_data_on_submission_' . $element['type'], $error, $elementId, $element, $form_data);
            if ($error) {
                $errors[$elementId] = $error;
            }
        }

        // Maybe validate recaptcha
        $formEvents = [];
        if (!$errors) {
            $recaptchaType = Form::recaptchaType($formId);
            if ($recaptchaType == 'v2_visible' || $recaptchaType == 'v3_invisible') {
                // let's validate recaptcha here
                $recaptchaSettings = GeneralSettings::getRecaptchaSettings();
                $ip_address = $this->getIp();
                $response = wp_remote_get(add_query_arg(array(
                    'secret' => $recaptchaSettings['secret_key'],
                    'response' => isset($form_data['g-recaptcha-response']) ? $form_data['g-recaptcha-response'] : '',
                    'remoteip' => $ip_address
                ), 'https://www.google.com/recaptcha/api/siteverify'));

                if (is_wp_error($response) || empty($response['body']) || !($json = json_decode($response['body'])) || !$json->success) {
                    $errors['g-recaptcha-response'] = __('reCaptcha validation failed. Please try again.', 'wppayform');
                    $formEvents[] = 'refresh_recaptcha';
                }
            }
        }
        $errors = apply_filters('wppayform/form_submission_validation_errors', $errors, $formId, $formattedElements);
        if ($errors) {
            wp_send_json_error(array(
                'message' => __('Form Validation failed', 'wppayform'),
                'errors' => $errors,
                'form_events' => $formEvents
            ), 423);
        }

        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;

        return;
    }

    private function getErrorLabel($element, $formId)
    {
        $label = Arr::get($element, 'options.label');
        if (!$label) {
            $label = Arr::get($element, 'options.placeholder');
            if (!$label) {
                $label = $element['id'];
            }
        }
        $label = $label . __(' is required', 'wppayform');
        return apply_filters('wppayform/error_label_text', $label, $element, $formId);
    }

    private function getIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    private function getItemQuantity($quantityElements, $tragetItemId, $formData)
    {
        $state = Arr::get($quantityElements, 'item_quantity.options.disable');

        if (!$quantityElements || $state) {
            return 1;
        }

        foreach ($quantityElements as $key => $element) {
            if (Arr::get($element, 'options.target_product') == $tragetItemId) {
                return absint($formData[$key]);
            }
        }
        return 1;
    }

    private function getSubscriptionLine($payment, $paymentId, $quantity, $formData, $formId)
    {
        if (!defined('WPPAYFORM_PRO_INSTALLED')) {
            return [];
        }

        if ($payment['type'] != 'recurring_payment_item') {
            return array();
        }
        if (!isset($formData[$paymentId])) {
            return array();
        }
        $label = Arr::get($payment, 'options.label');
        if (!$label) {
            $label = $paymentId;
        }

        $pricings = Arr::get($payment, 'options.recurring_payment_options.pricing_options');

        $paymentIndex = $formData[$paymentId];

        $plan = $pricings[$paymentIndex];

        if (!$plan) {
            return array();
        }

        if (Arr::get($plan, 'user_input') == 'yes') {
            $plan['subscription_amount'] = Arr::get($formData, $paymentId . '__' . $paymentIndex);
        }

        if ($plan['bill_times'] == 1) {
            // We can convert this as one time payment
            // This plan should not have trial
            if ($plan['has_trial_days'] != 'yes') {
                $signupFee = 0;
                if ($plan['has_signup_fee'] == 'yes') {
                    $signupFee = wpPayFormConverToCents($plan['signup_fee']);
                }
                $onetimeTotal = $signupFee + wpPayFormConverToCents($plan['subscription_amount']);
                return [
                    'type' => 'single',
                    'parent_holder' => $paymentId,
                    'item_name' => $label,
                    'quantity' => $quantity,
                    'item_price' => $onetimeTotal,
                    'line_total' => $quantity * $onetimeTotal,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
            }
        }


        $subscription = array(
            'element_id' => $paymentId,
            'item_name' => $label,
            'form_id' => $formId,
            'plan_name' => $plan['name'],
            'billing_interval' => $plan['billing_interval'],
            'trial_days' => 0,
            'recurring_amount' => wpPayFormConverToCents($plan['subscription_amount']),
            'bill_times' => $plan['bill_times'],
            'initial_amount' => 0,
            'status' => 'pending',
            'original_plan' => maybe_serialize($plan),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );


        if (Arr::get($plan, 'has_signup_fee') == 'yes' && Arr::get($plan, 'signup_fee')) {
            $subscription['initial_amount'] = wpPayFormConverToCents($plan['signup_fee']);
        }

        if (Arr::get($plan, 'has_trial_days') == 'yes' && Arr::get($plan, 'trial_days')) {
            $subscription['trial_days'] = $plan['trial_days'];
            $dateTime = current_datetime();
            $localtime = $dateTime->getTimestamp() + $dateTime->getOffset();
            $expirationDate = gmdate('Y-m-d H:i:s', $localtime + absint($plan['trial_days']) * 86400);
            $subscription['expiration_at'] = $expirationDate;
        }

        if ($quantity > 1) {
            $subscription['quantity'] = $quantity;
        }

        $allSubscriptions = [$subscription];

        return $allSubscriptions;
    }

    private function getPaymentLine($payment, $paymentId, $quantity, $formData)
    {
        if (!isset($formData[$paymentId])) {
            return array();
        }

        $label = Arr::get($payment, 'options.label');
        if (!$label) {
            $label = $paymentId;
        }
        $payItem = array(
            'type' => 'single',
            'parent_holder' => $paymentId,
            'item_name' => strip_tags($label),
            'quantity' => $quantity,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        if ($payment['type'] == 'payment_item') {
            $priceDetailes = Arr::get($payment, 'options.pricing_details');
            $payType = Arr::get($priceDetailes, 'one_time_type');
            if ($payType == 'choose_single') {
                $pricings = $priceDetailes['multiple_pricing'];
                $price = $pricings[$formData[$paymentId]];
                $payItem['item_name'] = strip_tags($price['label']);
                $payItem['item_price'] = wpPayFormConverToCents($price['value']);
                $payItem['line_total'] = $payItem['item_price'] * $quantity;
            } elseif ($payType == 'choose_multiple') {
                $selctedItems = $formData[$paymentId];
                $pricings = $priceDetailes['multiple_pricing'];
                $payItems = array();
                foreach ($selctedItems as $itemIndex => $selctedItem) {
                    $itemClone = $payItem;
                    $itemClone['item_name'] = strip_tags($pricings[$itemIndex]['label']);
                    $itemClone['item_price'] = wpPayFormConverToCents($pricings[$itemIndex]['value']);
                    $itemClone['line_total'] = $itemClone['item_price'] * $quantity;
                    $payItems[] = $itemClone;
                }
                return $payItems;
            } else {
                $payItem['item_price'] = wpPayFormConverToCents(Arr::get($priceDetailes, 'payment_amount'));
                $payItem['line_total'] = $payItem['item_price'] * $quantity;
            }
        } elseif ($payment['type'] == 'custom_payment_input') {
            $payItem['item_price'] = wpPayFormConverToCents(floatval($formData[$paymentId]));
            $payItem['line_total'] = $payItem['item_price'] * $quantity;
        } else {
            return array();
        }

        return array($payItem);
    }

    private function getHash()
    {
        $localtime = current_time('timestamp');

        $prefix = 'wpf_' . $localtime;
        $uid = uniqid($prefix);
        // now let's make a unique number from 1 to 999
        $uid .= mt_rand(1, 999);
        $uid = str_replace(array("'", '/', '?', '#', "\\"), '', $uid);
        return $uid;
    }

    public function sendSubmissionConfirmation($submission, $formId)
    {
        $confirmation = ConfirmationHelper::getFormConfirmation($formId, $submission);

        wp_send_json_success(array(
            'message' => __('Form is successfully submitted', 'wppayform'),
            'submission_id' => $submission->id,
            'confirmation' => $confirmation
        ), 200);
    }
}
