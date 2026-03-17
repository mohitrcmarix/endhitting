<?php

namespace WPPayForm\App\Http\Controllers;

use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\SubmissionActivity;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Services\GeneralSettings;

class SubmissionController extends Controller
{
    public function index($formId = false)
    {
        $searchString = sanitize_text_field($this->request->search_string);
        $page = absint($this->request->page_number);
        $perPage = absint($this->request->per_page);
        $skip = ($page - 1) * $perPage;

        $wheres = array();

        $paymentStatus = $this->request->get('payment_status', false);

        $status = $this->request->get('status', false);

        if ($paymentStatus) {
            $wheres['payment_status'] = sanitize_text_field($paymentStatus);
        }

        if ($status) {
            $wheres['status'] = sanitize_text_field($status);
        }

        $submissions = (new Submission())->getAll($formId, $wheres, $perPage, $skip, 'DESC', $searchString);

        $currencySettings = GeneralSettings::getGlobalCurrencySettings($formId);

        foreach ($submissions->items as $submission) {
            $currencySettings['currency_sign'] = GeneralSettings::getCurrencySymbol($submission->currency);
            $submission->currencySettings = $currencySettings;
        }

        $submissionItems = apply_filters('wppayform/form_entries', $submissions->items, $formId);

        $hasPaymentItem = true;

        if ($formId) {
            $hasPaymentItem = Form::hasPaymentFields($formId);
        }

        wp_send_json_success(array(
            'submissions' => $submissionItems,
            'total' => (int)$submissions->total,
            'hasPaymentItem' => $hasPaymentItem
        ), 200);
    }

    public static function reports($formId)
    {
        $paymentStatuses = GeneralSettings::getPaymentStatuses();
        $submission = new Submission();
        $reports = [];
        $reports[''] = [
            'label' => 'All',
            'submission_count' => $submission->getTotalCount($formId),
            //issue on that line
            'payment_total' => $submission->paymentTotal($formId)
        ];

        foreach ($paymentStatuses as $status => $statusName) {
            $reports[$status] = [
                'label' => $statusName,
                'submission_count' => $submission->getTotalCount($formId, $status),
                'payment_total' => $submission->paymentTotal($formId, $status)
            ];
        }
        wp_send_json_success([
            'reports' => $reports,
            'currencySettings' => Form::getCurrencyAndLocale($formId),
            'is_payment_form' => Form::hasPaymentFields($formId)
        ], 200);
    }

    public function getSubmission($formId, $submissionId = false)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($submissionId, array('transactions', 'order_items', 'tax_items', 'activities', 'refunds', 'discount'));
        if ($submission->status == 'new') {
            $submissionModel->where('form_id', $submission->form_id)
                ->where('id', $submission->id)
                ->update(['status' => 'read']);
            $submission->status = 'read';
        }

        $currencySetting = GeneralSettings::getGlobalCurrencySettings($formId);
        $currencySetting['currency_sign'] = GeneralSettings::getCurrencySymbol($submission->currency);
        $submission->currencySetting = $currencySetting;

        if ($submission->user_id) {
            $user = get_user_by('ID', $submission->user_id);
            if ($user) {
                $submission->user = [
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'profile_url' => get_edit_user_link($user->ID)
                ];
            }
        }

        $submission = apply_filters('wppayform/form_entry', $submission);

        $parsedEntry = $submissionModel->getParsedSubmission($submission);

        $submission['widgets'] = apply_filters('wppayform_single_entry_widgets', [], array('submission' => $submission));

        wp_send_json_success(array(
            'submission' => $submission,
            'entry' => (object)$parsedEntry
        ), 200);
    }

    public function addSubmissionNote($formId, $submissionId)
    {
        $content = esc_html($this->request->note);
        $userId = get_current_user_id();
        $user = get_user_by('ID', $userId);

        $note = array(
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'type' => 'custom_note',
            'content' => $content,
            'created_by' => $user->display_name,
            'created_by_user_id' => $userId
        );

        $note = apply_filters('wppayform/add_note_by_user', $note, $formId, $submissionId);
        do_action('wppayform/before_create_note_by_user', $note);
        SubmissionActivity::createActivity($note);
        do_action('wppayform/after_create_note_by_user', $note);

        return array(
            'message' => __('Note successfully added', 'wppayform'),
            'activities' => SubmissionActivity::getSubmissionActivity($submissionId)
        );
    }

    public function deleteNote($formId, $entryId, $noteId)
    {
        return SubmissionActivity::deleteActivity($formId, $entryId, $noteId);
        do_action('wppayform/after_delete_note_by_user', $entryId, $noteId);
    }

    public function changeEntryStatus($formId, $entryId)
    {
        $newStatus = sanitize_text_field($this->request->status);
        $submissionModel = new Submission();
        $newStatus = $submissionModel->changeEntryStatus($formId, $entryId, $newStatus);
        return array(
            'message' => __('Item has been marked as ' . $newStatus, 'wppayform'),
            'status' => $newStatus
        );
    }

    public function getNextPrevSubmission($formId = false, $currentSubmissionId = null)
    {
        $queryType = sanitize_text_field($this->request->type);

        $whereOperator = '<';
        $orderBy = 'DESC';
        // find the next / previous form id
        if ($queryType == 'next') {
            $whereOperator = '>';
            $orderBy = 'ASC';
        }

        $submissionQuery = Submission::orderBy('id', $orderBy)
            ->where('id', $whereOperator, $currentSubmissionId);

        if ($formId) {
            $submissionQuery->where('form_id', $formId);
        }

        $submission = $submissionQuery->first();

        if (!$submission) {
            wp_send_json_error(array(
                'message' => __('Sorry, No Submission found', 'wppayform')
            ), 423);
        }
        $this->getSubmission($formId, $submission->id);
    }

    public function paymentStatus($submissionId)
    {
        $newStatus = sanitize_text_field($this->request->new_payment_status);
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($submissionId);
        if ($submission->payment_status == $newStatus) {
            wp_send_json_error(array(
                'message' => __('The submission have the same status', 'wppayform')
            ), 423);
        }

        do_action('wppayform/before_payment_status_change_manually', $submission, $newStatus, $submission->payment_status);

        $submissionModel->updateSubmission($submissionId, array(
            'payment_status' => $newStatus
        ));

        Transaction::where('submission_id', $submissionId)->update(array(
            'status' => $newStatus,
            'updated_at' => current_time('mysql')
        ));
        do_action('wppayform/after_payment_status_change_manually', $submissionId, $newStatus, $submission->payment_status);

        $activityContent = 'Payment status changed from <b>' . $submission->payment_status . '</b> to <b>' . $newStatus . '</b>';

        if ($changeNote = $this->request->get('status_change_note', false)) {
            $note = wp_kses_post($changeNote);
            $activityContent .= '<br />Note: ' . $note;
        }

        $userId = get_current_user_id();
        $user = get_user_by('ID', $userId);
        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'info',
            'created_by' => $user->display_name,
            'created_by_user_id' => $userId,
            'content' => $activityContent
        ));

        return array(
            'message' => __('Payment status successfully changed', 'wppayform')
        );
    }

    public function remove()
    {
        $submissionId = $this->request->get('submission_id', []);
        $formId = $this->request->get('form_id', '');
        do_action('wppayform/before_delete_submission', $submissionId, $formId);
        $submissionModel = new Submission();
        $submissionModel->deleteSubmission($submissionId);
        do_action('wppayform/after_delete_submission', $submissionId, $formId);
        return array(
            'message' => __('Selected submission successfully deleted', 'wppayform')
        );
    }
}
