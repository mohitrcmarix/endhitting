<?php

namespace WPPayForm\App\Modules\Pro\Classes\Components;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class RecurringPaymentComponent extends BaseComponent
{
    public function __construct()
    {
        parent::__construct('recurring_payment_item', 2);
    }

    public function component()
    {
        return array(
            'type' => 'recurring_payment_item',
            'editor_title' => __('Recurring Payment Item', 'wppayform'),
            'group' => 'payment',
            'postion_group' => 'payment',
            'editor_elements' => array(
                'label' => array(
                    'label' => 'Recurring Payment Item Name',
                    'type' => 'text',
                    'group' => 'general'
                ),
                'required' => array(
                    'label' => 'Required',
                    'type' => 'switch',
                    'group' => 'general'
                ),
                'show_main_label' => array(
                    'label' => 'Show Pricing Label',
                    'type' => 'switch',
                    'group' => 'general'
                ),
                'show_payment_summary' => array(
                    'label' => 'Show Payment Summary',
                    'type' => 'switch',
                    'group' => 'general'
                ),
                'recurring_payment_options' => array(
                    'type' => 'recurring_payment_options',
                    'group' => 'general',
                    'label' => 'Configure Recurring Subscription Payment Plans',
                    'choice_label' => __('Choose your pricing plan'),
                    'choice_types' => array(
                        'simple' => __('Simple Recurring Plan (Single)', 'wppayform'),
                        'choose_single' => __('Chose One from Multiple Pricing Plans', 'wppayform'),
                        //'choose_multiple' => __('Choose Multiple Plan from Pricing Plans', 'wppayform')
                    ),
                    'selection_types' => array(
                        'radio' => __('Radio input field', 'wppayform'),
                        'select' => __('Select input field', 'wppayform')
                    )
                ),
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
                'element_class' => array(
                    'label' => 'Input element CSS Class',
                    'type' => 'text',
                    'group' => 'advanced'
                ),
            ),
            'is_system_field' => true,
            'is_payment_field' => true,
            'field_options' => array(
                'label' => __('Subscription Item', 'wppayform'),
                'required' => 'yes',
                'show_main_label' => 'yes',
                'show_payment_summary' => 'yes',
                'recurring_payment_options' => array(
                    'choice_type' => 'simple',
                    'selection_type' => 'radio',
                    'pricing_options' => [
                        [
                            'name' => __('$9.99 / Month', 'wppayform'),
                            'trial_days' => 0,
                            'has_trial_days' => 'no',
                            'trial_days' => 0,
                            'billing_interval' => 'month',
                            'bill_times' => 0,
                            'has_signup_fee' => 'no',
                            'signup_fee' => 0,
                            'subscription_amount' => '9.99',
                            'is_default' => 'yes',
                            'plan_features' => []
                        ]
                    ]
                )
            )
        );
    }

    public function validateOnSave($error, $element, $formId)
    {
        $pricingDetails = Arr::get($element, 'field_options.pricing_details', array());
        $paymentType = Arr::get($pricingDetails, 'one_time_type');
        if ($paymentType == 'single') {
            if (!Arr::get($pricingDetails, 'payment_amount')) {
                $error = __('Payment amount is required for item:', 'wppayform') . ' ' . Arr::get($element, 'field_options.label');
            }
        } elseif ($paymentType == 'choose_multiple' || $paymentType == 'choose_single') {
            if (!count(Arr::get($pricingDetails, 'multiple_pricing', array()))) {
                $error = __('Pricing Details is required for item:', 'wppayform') . ' ' . Arr::get($element, 'field_options.label');
            }
        }
        return $error;
    }

    public function render($element, $form, $elements)
    {
        $fieldOptions = Arr::get($element, 'field_options', array());
        $disable = Arr::get($fieldOptions, 'disable', false);
        $paymentOptions = Arr::get($fieldOptions, 'recurring_payment_options', array());
        if (!$paymentOptions || $disable) {
            return;
        }

        $choiceType = Arr::get($paymentOptions, 'choice_type', 'simple');
        $pricingPlans = Arr::get($paymentOptions, 'pricing_options');
        if (count($pricingPlans) == 0) {
            return;
        }
        if ($choiceType == 'simple') {
            $this->renderSimplePlan($element, $fieldOptions, $pricingPlans, $form);
            return;
        } elseif ($choiceType == 'choose_single') {
            $this->renderSingleChoice($element, $fieldOptions, $pricingPlans, $form);
            return;
        }
    }

    private function renderSimplePlan($element, $fieldOptions, $pricingPlans, $form)
    {
        $plan = $pricingPlans[0];
        $currenySettings = Form::getCurrencyAndLocale($form->ID);
        $title = Arr::get($element, 'field_options.label');
        $title .= ' - ' . $plan['name'];

        $isCustomAmount = Arr::get($plan, 'user_input') == 'yes';

        if ($isCustomAmount) {
            $plan['subscription_amount'] = Arr::get($plan, 'user_input_default_value');
            $title = Arr::get($plan, 'user_input_label', $title);
        }

        $fieldOptions['label'] = $title;

        $controlAttributes = array(
            'data-element_type' => $this->elementName,
            'class' => $this->elementControlClass($element)
        );
        $paymentSummary = '';
        if (Arr::get($fieldOptions, 'show_payment_summary') == 'yes') {
            $paymentSummary = $this->getPaymentSummaryText($plan, $element, $form, $currenySettings);
        }
        $inputAttributes = [
            'type' => 'hidden',
            'class' => 'wpf_payment_item',
            'value' => '0',
            'name' => $element['id']
        ];


        $signupFee = '0';
        if (Arr::get($plan, 'has_signup_fee') == 'yes') {
            $signupFee = wpPayFormConverToCents(Arr::get($plan, 'signup_fee'));
        }

        $billingAttributes = $this->getPlanInputAttributes($plan);
        $itemInputAttributes = wp_parse_args($billingAttributes, $inputAttributes);

        if ($isCustomAmount) {
            $inputAttributes['value'] = $plan['subscription_amount'];
            $inputCustomAttributes['type'] = 'number';
            $inputCustomAttributes['placeholder'] = $title;
            $inputCustomAttributes['value'] = Arr::get($plan, 'user_input_default_value');
            $inputCustomAttributes['min'] = Arr::get($plan, 'user_input_min_value', 0);
            $inputCustomAttributes['step'] = 'any';
            $inputCustomAttributes['data-parent_name'] = $element['id'];
            $inputCustomAttributes['class'] = 'wpf_custom_subscription_input';
            $inputCustomAttributes['name'] = $element['id'] . '__0';
            $inputCustomAttributes['data-initial_amount'] = $signupFee;
        } ?>
        <div <?php echo $this->builtAttributes($controlAttributes); ?>>
            <?php if (Arr::get($fieldOptions, 'show_main_label') == 'yes'): ?>
                <?php $this->buildLabel($fieldOptions, $form); ?>
            <?php endif; ?>
            <?php if ($isCustomAmount) : ?>
                <div class="wpf_input_content">
                    <div class="wpf_form_item_group">
                        <div class="wpf_input-group-prepend">
                            <div class="wpf_input-group-text"><?php echo $currenySettings['currency_sign']; ?></div>
                        </div>
                        <input <?php echo $this->builtAttributes($inputCustomAttributes); ?> />
                    </div>
                </div>
            <?php endif; ?>
            <input <?php echo $this->builtAttributes($itemInputAttributes); ?> />
            <?php echo $paymentSummary; ?>
        </div>
        <?php
    }

    private function renderSingleChoice($element, $fieldOptions, $pricingPlans, $form)
    {
        $hasCustomAmount = false;
        foreach ($pricingPlans as $planIndex => $plan) {
            $isCustomAmount = Arr::get($plan, 'user_input') == 'yes';
            if ($isCustomAmount) {
                $hasCustomAmount = true;
                $plan['subscription_amount'] = Arr::get($plan, 'user_input_default_value');
                $pricingPlans[$planIndex] = $plan;
            }
        }

        $type = Arr::get($fieldOptions, 'recurring_payment_options.selection_type', 'radio');
        $currenySettings = Form::getCurrencyAndLocale($form->ID);
        $controlAttributes = array(
            'data-element_type' => $this->elementName,
            'data-required_element' => $type,
            'data-required' => Arr::get($fieldOptions, 'required'),
            'data-target_element' => $element['id'],
            'class' => $this->elementControlClass($element)
        ); ?>
        <div <?php echo $this->builtAttributes($controlAttributes); ?>>
            <?php if (Arr::get($fieldOptions, 'show_main_label') == 'yes'): ?>
                <?php $this->buildLabel($fieldOptions, $form); ?>
            <?php endif; ?>

            <?php if ($type == 'select') : ?>
                <?php
                $placeholder = __('--Select Plan--', 'wppayform');
                $placeholder = apply_filters('wppayform/subscription_selection_placeholder', $placeholder, $element, $form);
                $inputId = 'wpf_input_' . $form->ID . '_' . $this->elementName;
                $inputAttributes = array(
                    'data-required' => Arr::get($fieldOptions, 'required'),
                    'data-type' => 'select',
                    'name' => $element['id'],
                    'class' => $this->elementInputClass($element) . ' wpf_payment_item',
                    'id' => $inputId
                ); ?>
                <div
                        class="wpf_multi_form_controls wpf_input_content wpf_subscrion_plans_select wpf_multi_form_controls_select">
                    <select <?php echo $this->builtAttributes($inputAttributes); ?>>
                        <?php if ($placeholder): ?>
                            <option data-type="placeholder" value=""><?php echo $placeholder; ?></option>
                        <?php endif; ?>
                        <?php foreach ($pricingPlans as $index => $plan): ?>
                            <?php
                            $isCustomAmount = Arr::get($plan, 'user_input') == 'yes';
                            $optionAttributes = $this->getPlanInputAttributes($plan);
                            $optionAttributes['value'] = $index;
                            if ('yes' == $plan['is_default']) {
                                $optionAttributes['selected'] = 'true';
                            }

                            if ($isCustomAmount) {
                                $optionAttributes['data-has_custom_amount'] = 'yes';
                                $optionAttributes['data-plan_index'] = $index;
                                $optionAttributes['class'] = 'wpf_option_custom_' . $index;
                            } ?>
                            <option <?php echo $this->builtAttributes($optionAttributes); ?>><?php echo esc_attr($plan['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($hasCustomAmount): ?>
                        <div
                                class="wpf_subscription_custom_amount_input wpf_subscription_plan_summary_<?php echo $inputId; ?>">
                            <?php foreach ($pricingPlans as $planIndex => $plan) :
                                $isCustomAmount = Arr::get($plan, 'user_input') == 'yes';
                                if (!$isCustomAmount) {
                                    continue;
                                } ?>
                                <div style="display: none"
                                     class="wpf_subscription_plan_summary_item subscription_custom_amount_block wpf_subscription_plan_index_<?php echo $planIndex; ?>">
                                    <div class="wpf_input_label">
                                        <label><?php echo Arr::get($plan, 'user_input_label'); ?></label>
                                    </div>
                                    <div class="wpf_input_content">
                                        <input data-plan_index="<?php echo $planIndex; ?>"
                                               name="<?php echo $element['id']; ?>__<?php echo $planIndex; ?>"
                                               value="<?php echo Arr::get($plan, 'user_input_default_value'); ?>"
                                               min="<?php echo Arr::get($plan, 'user_input_min_value'); ?>"
                                               step="any"
                                               placeholder="<?php echo Arr::get($plan, 'user_input_label'); ?>"
                                               value="" type="number"
                                               class="wpf_form_control wpf_custom_subscription_amount_select"/>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    if (Arr::get($fieldOptions, 'show_payment_summary') == 'yes') {
                        echo '<div class="wpf_subscription_plan_summary wpf_subscription_plan_summary_' . $inputId . '">';
                        foreach ($pricingPlans as $planIndex => $plan) {
                            $paymentSummary = $this->getPaymentSummaryText($plan, $element, $form, $currenySettings);
                            echo '<div style="display: none;" class="wpf_subscription_plan_summary_item wpf_subscription_plan_index_' . $planIndex . '">' . $paymentSummary . '</div>';
                        }
                        echo '</div>';
                    } ?>
                </div>
            <?php else: ?>
                <div
                        class="wpf_multi_form_controls wpf_input_content wpf_multi_form_controls_radio wpf_subscription_controls_radio">
                    <?php foreach ($pricingPlans as $index => $plan): ?>
                        <?php
                        $optionId = $element['id'] . '_' . $index . '_' . $form->ID;
                        $attributes = $this->getPlanInputAttributes($plan);
                        $attributes['class'] = 'form-check-input wpf_payment_item';
                        $attributes['type'] = 'radio';
                        $attributes['name'] = $element['id'];
                        $attributes['id'] = $optionId;
                        $attributes['value'] = $index;
                        if ('yes' == $plan['is_default']) {
                            $attributes['checked'] = 'true';
                        }

                        $isCustomAmount = Arr::get($plan, 'user_input') == 'yes';
                        if ($isCustomAmount) {
                            $attributes['data-has_custom_amount'] = 'yes';
                            $attributes['data-plan_index'] = $index;
                            $attributes['class'] .= ' wpf_option_custom_' . $index;
                        } ?>
                        <div class="form-check">
                            <input <?php echo $this->builtAttributes($attributes); ?>>
                            <label class="form-check-label" for="<?php echo $optionId; ?>">
                                <span class="wpf_price_option_name"
                                      itemprop="description"><?php echo $plan['name']; ?></span>
                                <meta itemprop="price" content="<?php echo $plan['subscription_amount']; ?>">
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($hasCustomAmount): ?>
                        <div class="wpf_subscription_radio_custom">
                            <?php foreach ($pricingPlans as $planIndex => $plan) :
                                $isCustomAmount = Arr::get($plan, 'user_input') == 'yes';
                                if (!$isCustomAmount) {
                                    continue;
                                } ?>
                                <div style="display: none"
                                     class="subscription_radio_custom subscription_custom_amount_block subscription_radio_custom_<?php echo $planIndex; ?>">
                                    <div class="wpf_input_label">
                                        <label><?php echo Arr::get($plan, 'user_input_label'); ?></label>
                                    </div>
                                    <div class="wpf_input_content">
                                        <input data-plan_index="<?php echo $planIndex; ?>"
                                               name="<?php echo $element['id']; ?>__<?php echo $planIndex; ?>"
                                               value="<?php echo Arr::get($plan, 'user_input_default_value'); ?>"
                                               min="<?php echo Arr::get($plan, 'user_input_min_value'); ?>"
                                               step="any"
                                               placeholder="<?php echo Arr::get($plan, 'user_input_label'); ?>"
                                               value="" type="number"
                                               class="wpf_form_control wpf_custom_subscription_amount_radio"/>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    if (Arr::get($fieldOptions, 'show_payment_summary') == 'yes') {
                        echo '<div class="wpf_subscription_plan_summary wpf_subscription_plan_summary_' . $element['id'] . '">';
                        foreach ($pricingPlans as $planIndex => $plan) {
                            $paymentSummary = $this->getPaymentSummaryText($plan, $element, $form, $currenySettings);
                            echo '<div style="display: none;" class="wpf_subscription_plan_summary_item wpf_subscription_plan_index_' . $planIndex . '">' . $paymentSummary . '</div>';
                        }
                        echo '</div>';
                    } ?>

                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function getPaymentSummaryText($plan, $element, $form, $currenySettings)
    {
        $cases = apply_filters('wppayform/recurring_payment_summary_texts', [
            'has_signup_fee' => __('{first_interval_total} for the first {billing_interval} then {subscription_amount}/{billing_interval}', 'wppayform'),
            'has_trial' => __('{trial_days} days free then {subscription_amount}/{billing_interval}', 'wppayform'),
            'onetime_only' => __('One time payment of {first_interval_total}', 'wppayform'),
            'normal' => __('{subscription_amount} for each {billing_interval}', 'wppayform'),
            'bill_times' => __(', for {bill_times} installments', 'wppayform')
        ], $plan, $element, $form);


        if ($this->hasTrial($plan)) {
            $plan['signup_fee'] = 0;
        }

        if ($this->hasSignupFee($plan)) {
            $plan['trial_days'] = 0;
        }

        $signupFee = wpPayFormFormattedMoney(wpPayFormConverToCents(Arr::get($plan, 'signup_fee')), $currenySettings);
        $firstIntervalTotal = wpPayFormFormattedMoney(wpPayFormConverToCents(Arr::get($plan, 'signup_fee') + Arr::get($plan, 'subscription_amount')), $currenySettings);
        $subscriptionAmount = wpPayFormFormattedMoney(wpPayFormConverToCents(Arr::get($plan, 'subscription_amount')), $currenySettings);

        $billingInterval = $plan['billing_interval'];

        if ($billingInterval == 'daily') {
            $billingInterval = __('day', 'wppayform');
        } elseif ($billingInterval == 'month') {
            $billingInterval = __('month', 'wppayform');
        } elseif ($billingInterval == 'week') {
            $billingInterval = __('week', 'wppayform');
        } else {
            $billingInterval = __('year', 'wppayform');
        }
        $replaces = array(
            '{signup_fee}' => '<span class="wpf_bs wpfbs_signup_fee">' . $signupFee . '</span>',
            '{first_interval_total}' => '<span class="wpf_bs wpfbs_first_interval_total">' . $firstIntervalTotal . '</span>',
            '{subscription_amount}' => '<span class="wpf_bs wpfbs_subscription_amount">' . $subscriptionAmount . '</span>',
            '{billing_interval}' => '<span class="wpf_bs wpfbs_billing_interval">' . $billingInterval . '</span>',
            '{trial_days}' => '<span class="wpf_bs wpfbs_trial_days">' . $plan['trial_days'] . '</span>',
            '{bill_times}' => '<span class="wpf_bs wpfbs_bill_times">' . $plan['bill_times'] . '</span>'
        );

        if (Arr::get($plan, 'user_input') == 'yes') {
            $cases['{subscription_amount}'] = '<span class="wpf_dynamic_input_amount">' . $subscriptionAmount . '</span>';
        }

        foreach ($cases as $textKey => $text) {
            $cases[$textKey] = str_replace(array_keys($replaces), array_values($replaces), $text);
        }

        $customText = '';
        if ($this->hasSignupFee($plan)) {
            $customText = $cases['has_signup_fee'];
        } elseif ($this->hasTrial($plan)) {
            $customText = $cases['has_trial'];
        } elseif ($plan['bill_times'] == 1) {
            $customText = $cases['onetime_only'];
        } else {
            $customText = $cases['normal'];
        }
        if ($plan['bill_times'] > 1) {
            $customText .= $cases['bill_times'];
        }
        return '<div class="wpf_summary_container">' . $customText . '</div>';
    }

    private function getPlanInputAttributes($plan)
    {
        $subscriptionAmount = wpPayFormConverToCents($plan['subscription_amount']);
        $currentBillableAmount = $subscriptionAmount;
        $initialAmount = 0;
        if ($this->hasSignupFee($plan)) {
            $currentBillableAmount = wpPayFormConverToCents($plan['signup_fee'] + $plan['subscription_amount']);
            $initialAmount = wpPayFormConverToCents($plan['signup_fee']);
        }
        if ($this->hasTrial($plan)) {
            $currentBillableAmount = 0;
        }

        return [
            'data-subscription_amount' => $subscriptionAmount,
            'data-billing_interval' => $plan['billing_interval'],
            'data-price' => $currentBillableAmount,
            'data-initial_amount' => $initialAmount
        ];
    }

    private function hasTrial($plan)
    {
        $hasTrial = Arr::get($plan, 'has_trial_days') == 'yes';
        $trialDays = Arr::get($plan, 'trial_days');
        return $hasTrial && $trialDays;
    }

    private function hasSignupFee($plan)
    {
        $hasSignup = Arr::get($plan, 'has_signup_fee') == 'yes';
        $signUpFee = Arr::get($plan, 'signup_fee');
        return $hasSignup && $signUpFee;
    }
}
