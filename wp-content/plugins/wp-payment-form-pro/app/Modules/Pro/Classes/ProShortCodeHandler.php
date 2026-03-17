<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Models\Submission;
use WPPayForm\App\Modules\Builder\PaymentReceipt;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro ShortCode Handler
 * @since 1.0.0
 */
class ProShortCodeHandler
{
    public function handleUserSubmissionShortCode($args)
    {
        $defaults = apply_filters('wppayform/payform_user_submissions_shortcode_defaults', array(
            'form_id' => 'all',
            'list_by' => 'user_id',
            'no_access_text' => __('You need to login to see your submissions', 'wppayform'),
            'no_submission_text' => __('You do not have any submission yet!', 'wppayform'),
            'show_details_url' => 'yes',
            'show_payments' => 'yes',
            'limit' => 'no'
        ));
        $args = shortcode_atts($defaults, $args);

        $currentUserId = get_current_user_id();
        if (!$currentUserId) {
            return $args['no_access_text'];
        }

        $listBy = $args['list_by'];
        if (!in_array($listBy, ['user_id', 'customer_email'])) {
            return __('Wrong shortcode parameter, Please use user_id / customer_email in your shortcode', 'wppayform');
        }

        if ($listBy == 'customer_email') {
            $user = get_user_by('ID', $currentUserId);
            $listByValue = $user->user_email;
        } else {
            $listByValue = $currentUserId;
        }

        $wheres = [];

        $wheres[$listBy] = $listByValue;

        $formId = false;
        if ($args['form_id'] != 'all') {
            $formId = intval($args['form_id']);
        }

        $submissionModel = new Submission();

        $perPage = false;
        if ($args['limit']) {
            $perPage = intval($args['limit']);
        }

        $submissions = $submissionModel->getSubmissions($formId, $wheres, $perPage);
        $submissions = $submissions->items;

        if (!$submissions) {
            return $args['no_submission_text'];
        }


        $pages = get_option('wppayform_confirmation_pages');
        $confirmationPageId = $pages['confirmation'];
        $permalink = get_permalink($confirmationPageId);
        $permalink = apply_filters('wppayform/submission_view_permalink_base', $permalink);
        if (!$permalink) {
            $permalink = '#';
        }

        $paymentReceiptClass = new PaymentReceipt();
        $html = $paymentReceiptClass->loadView('elements/user_submissions_table', [
            'submissions' => $submissions,
            'load_css' => true,
            'show_payments' => $args['show_payments'] == 'yes',
            'show_url' => $args['show_details_url'] == 'yes',
            'permalink' => $permalink
        ]);

        return $html;
    }
}
