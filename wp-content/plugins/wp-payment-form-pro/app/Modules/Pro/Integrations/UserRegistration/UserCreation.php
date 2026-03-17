<?php

namespace WPPayForm\App\Modules\Pro\Integrations\UserRegistration;

if (!defined('ABSPATH')) {
    exit;
}

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Meta;
use WPPayForm\App\Services\Integrations\GlobalNotificationManager;

class UserCreation
{

    public function register()
    {
        add_action('wppayform/form_submission_activity_start', array($this, 'initUserHooks'));
        add_action('wppayform/user_registration_trigger', array($this, 'createUser'), 10, 2);
    }

    public function initUserHooks($formId)
    {
        if (get_option('wppayform_integration_status') !== 'yes') {
            return;
        };

        $feeds = Meta::where('form_id', $formId)
            ->where('meta_key', 'user_registration_feeds')
            ->get();

        if ($feeds->count() < 1) {
            return;
        }

        $hasPayment = get_post_meta($formId, 'wpf_has_payment_field', true);
        $hasRecurring = get_post_meta($formId, 'wpf_has_recurring_field', true);

        $regAction = 'wppayform/after_form_submission_complete';
        if ($hasPayment ==='yes' || $hasRecurring === 'yes') {
            $regAction = 'wppayform/form_payment_success';
        }

        add_action($regAction, function ($submission) use ($formId) {
            do_action('wppayform/user_registration_trigger', $formId, $submission->id);
        });
    }

    public function createUser($formId, $optionId)
    {
        $feedsData = get_post_meta($formId, 'wpf_user_reg_feeds', true);
        $entryId = Arr::get($feedsData, 'entryId');
        if ($entryId !== $optionId) {
            return;
        }
        $feed = Arr::get($feedsData, 'feed');
        $formData = Arr::get($feedsData, 'formData');
        $entry = (new Submission())->getSubmission($optionId);

        (new UserRegistrationApi())->registerUser(
            $feed,
            $formData,
            $entry,
            $formId,
            'UserRegistration'
        );
    }
}
