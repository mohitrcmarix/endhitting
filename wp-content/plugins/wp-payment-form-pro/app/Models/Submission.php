<?php

namespace WPPayForm\App\Models;

use WPPayForm\Framework\Foundation\App;
use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manage Submission
 * @since 1.0.0
 */
class Submission extends Model
{
    protected $table = 'wpf_submissions';
    public $metaGroup = 'wpf_submissions';

    public function createSubmission($submission)
    {
        return $this->create($submission);
    }

    public function getNewEntriesCount()
    {
        return $this->where('status', 'new')->count();
    }

    public function getAll($formId = false, $wheres = array(), $perPage = false, $skip = false, $orderBy = 'DESC', $searchString = false)
    {
        $resultQuery = $this->select(array('wpf_submissions.*', 'posts.post_title'))
            ->join('posts', 'posts.ID', '=', 'wpf_submissions.form_id')
            ->orderBy('wpf_submissions.id', $orderBy);

        if ($formId) {
            $resultQuery->where('wpf_submissions.form_id', $formId);
        }

        $queryType = Arr::get($wheres, 'payment_status');
        if (isset($wheres) && $queryType === 'abandoned') {
            $wheres['payment_status'] = 'pending';
            $resultQuery = self::makeQueryAbandoned($resultQuery, '<', true);
        }

        if (isset($wheres) && $queryType === 'all-payments') {
            unset($wheres['payment_status']);
            $resultQuery->where('wpf_submissions.payment_method', '!=', '');
        }

        foreach ($wheres as $whereKey => $where) {
            $resultQuery->where('wpf_submissions.' . $whereKey, '=', $where);
        }

        if ($searchString) {
            $resultQuery->where(function ($q) use ($searchString) {
                $q->where('wpf_submissions.customer_name', 'LIKE', "%{$searchString}%")
                    ->orWhere('wpf_submissions.customer_email', 'LIKE', "%{$searchString}%")
                    ->orWhere('wpf_submissions.payment_method', 'LIKE', "%{$searchString}%")
                    ->orWhere('wpf_submissions.payment_total', 'LIKE', "%{$searchString}%")
                    ->orWhere('wpf_submissions.form_data_formatted', 'LIKE', "%{$searchString}%")
                    ->orWhere('wpf_submissions.created_at', 'LIKE', "%{$searchString}%");
            });
        }

        $totalItems = $resultQuery->count();
        if ($perPage) {
            $resultQuery->limit($perPage);
        }
        if ($skip) {
            $resultQuery->offset($skip);
        }

        $results = $resultQuery->get();
        $formattedResults = array();

        foreach ($results as $result) {
            $result->form_data_raw = maybe_unserialize($result->form_data_raw);
            $result->form_data_formatted = maybe_unserialize($result->form_data_formatted);
            $result->payment_total += (new Subscription())->getSubscriptionPaymentTotal($result->form_id, $result->id);
            $formattedResults[] = $result;
        }

        return (object)array(
            'items' => $results,
            'total' => $totalItems
        );
    }

    public function getSubmission($submissionId, $with = array())
    {
        $result = $this->select(array('wpf_submissions.*', 'posts.post_title'))
            ->join('posts', 'posts.ID', '=', 'wpf_submissions.form_id')
            ->where('wpf_submissions.id', $submissionId)
            ->first();

        $result->form_data_raw = maybe_unserialize($result->form_data_raw);
        $result->form_data_formatted = maybe_unserialize($result->form_data_formatted);
        if ($result->user_id) {
            $result->user_profile_url = get_edit_user_link($result->user_id);
        }

        if (in_array('transactions', $with)) {
            $result->transactions = (new Transaction())->getTransactions($submissionId);
        }

        if (in_array('order_items', $with)) {
            $result->order_items = (new OrderItem())->getSingleOrderItems($submissionId);
        }

        if (in_array('discount', $with)) {
            $discounts = (new OrderItem())->getDiscountItems($submissionId);

            $totalDiscount = 0;
            if (isset($discounts)) {
                foreach ($discounts as $discount) {
                    $totalDiscount += intval($discount->line_total);
                }
            }
            $totalWithoutTax = 0;
            $orderTotal = 0;
            if (!empty($result->order_items)) {
                foreach ($result->order_items as $items) {
                    $orderTotal += intval($items->line_total);
                }
            }

            $subsTotal = intval((new Subscription())->getSubscriptionPaymentTotal($result->form_id, $submissionId));
            $totalWithoutTax = $orderTotal + $subsTotal;
            $percentDiscount = 0;
            if ($totalWithoutTax) {
                $percentDiscount = intval(($totalDiscount * 100) / $totalWithoutTax, 2);
            }

            $result->discounts = array(
                'applied' => $discounts,
                'total' => $totalDiscount,
                'percent' => $percentDiscount
            );
        }

        if (in_array('tax_items', $with)) {
            $result->tax_items = (new OrderItem())->getTaxOrderItems($submissionId);
        }

        if (in_array('activities', $with)) {
            $result->activities = SubmissionActivity::getSubmissionActivity($submissionId);
        }

        if (in_array('subscriptions', $with)) {
            $subscriptionModel = new Subscription();
            $result->subscriptions = $subscriptionModel->getSubscriptions($result->id);
        }
        if (in_array('refunds', $with)) {
            $refundModel = new Refund();
            $result->refunds = $refundModel->getRefunds($result->id);
            $refundTotal = 0;
            if ($result->refunds) {
                foreach ($result->refunds as $refund) {
                    $refundTotal += $refund->payment_total;
                }
            }
            $result->refundTotal = $refundTotal;
        }

        return $result;
    }

    public function getSubmissionByHash($submissionHash, $with = array())
    {
        $submission = $this->where('submission_hash', $submissionHash)
            ->orderBy('id', 'DESC')
            ->first();

        if ($submission) {
            return $this->getSubmission($submission->id, $with);
        }
        return false;
    }

    public function getTotalCount($formId = false, $paymentStatus = false)
    {
        if ($formId) {
            $query = $this->where('form_id', $formId);
        }

        if ($paymentStatus && $paymentStatus !== 'abandoned') {
            $query = $this->where('payment_status', $paymentStatus);
        } elseif ($paymentStatus && $paymentStatus == 'abandoned') {
            $query = $this->where('payment_status', 'pending');
            $query = self::makeQueryAbandoned($query, '<', true);
        }

        return $query->count();
    }

    public function makeQueryAbandoned($query, $condition = '<', $payOnly = true)
    {
        $hour = get_option('wppayform_abandoned_time', 3);

        $beforeHour = intval($hour) * 3600;
        $now = current_time('mysql');
        $formatted_date = date('Y-m-d H:i:s', strtotime($now) - $beforeHour);

        $query->where('wpf_submissions.created_at', $condition, $formatted_date);
        if ($payOnly) {
            $query->where('wpf_submissions.payment_method', '!=', '');
        }
        return $query;
    }


    public function paymentTotal($formId, $paymentStatus = false)
    {
        $paymentTotal = 0;
        $DB = App::make('db');
        $query = $this->select($DB->raw('SUM(payment_total) as payment_total'));

        if ($formId) {
            $query = $query->where('form_id', $formId);
        }

        if ($paymentStatus == 'abandoned') {
            $query->where('payment_status', 'pending');
            $query = $this->makeQueryAbandoned($query, '<', true);
        } else {
            $query->where('payment_status', $paymentStatus);
        }

        $result = $query->first();

        if ($result && $result->payment_total) {
            $paymentTotal = $result->payment_total;
        }

        if (!$paymentStatus || $paymentStatus == 'paid') {
            $paymentTotal += (new Subscription())->getSubscriptionPaymentTotal($formId);
        }

        return $paymentTotal;
    }


    public function updateSubmission($submissionId, $data)
    {
        $data['updated_at'] = current_time('mysql');
        return $this->where('id', $submissionId)->update($data);
    }

    public function getParsedSubmission($submission)
    {
        $elements = get_post_meta($submission->form_id, 'wppayform_paymentform_builder_settings', true);
        if (!$elements) {
            return array();
        }

        $parsedSubmission = array();
        $inputValues = $submission->form_data_formatted;

        foreach ($elements as $element) {
            if ($element['group'] == 'input') {
                $elementId = Arr::get($element, 'id');
                $elementValue = apply_filters(
                    'wppayform/rendering_entry_value_' . $element['type'],
                    Arr::get($inputValues, $elementId),
                    $submission,
                    $element
                );

                if (is_array($elementValue)) {
                    $elementValue = implode(', ', $elementValue);
                }
                $parsedSubmission[$elementId] = array(
                    'label' => $this->getLabel($element),
                    'value' => $elementValue,
                    'type' => $element['type']
                );
            }
        }

        return apply_filters('wppayform/parsed_entry', $parsedSubmission, $submission);
    }

    public function getUnParsedSubmission($submission)
    {
        $elements = get_post_meta($submission->form_id, 'wppayform_paymentform_builder_settings', true);
        if (!$elements) {
            return array();
        }
        $parsedSubmission = array();

        $inputValues = $submission->form_data_formatted;

        foreach ($elements as $element) {
            if ($element['group'] == 'input') {
                $elementId = Arr::get($element, 'id');
                $elementValue = Arr::get($inputValues, $elementId);

                if (is_array($elementValue)) {
                    $elementValue = implode(', ', $elementValue);
                }
                $parsedSubmission[$elementId] = array(
                    'label' => $this->getLabel($element),
                    'value' => $elementValue,
                    'type' => $element['type']
                );
            }
        }

        return apply_filters('wppayform/unparsed_entry', $parsedSubmission, $submission);
    }

    private function getLabel($element)
    {
        $elementId = Arr::get($element, 'id');
        if (!$label = Arr::get($element, 'field_options.admin_label')) {
            $label = Arr::get($element, 'field_options.label');
        }
        if (!$label) {
            $label = $elementId;
        }
        return $label;
    }

    public function deleteSubmission($submissionId)
    {
        foreach ($submissionId as $value) {
            Submission::where('id', intval($value))
                ->delete();

            OrderItem::where('submission_id', intval($value))
                ->delete();

            Refund::where('submission_id', intval($value))
                ->where('transaction_type', 'one_time')
                ->delete();

            SubmissionActivity::where('submission_id', intval($value))
                ->delete();
        }
    }

    public function getEntryCountByPaymentStatus($formId, $paymentStatuses = array(), $period = 'total')
    {
        $query = $this->where('form_id', $formId);
        $DB = App::make('db');
        if ($paymentStatuses && count($paymentStatuses)) {
            $query->whereIn('payment_status', $paymentStatuses);
        }

        if ($period && $period != 'total') {
            $col = 'created_at';
            if ($period == 'day') {
                $year = "YEAR(`{$col}`) = YEAR(NOW())";
                $month = "MONTH(`{$col}`) = MONTH(NOW())";
                $day = "DAY(`{$col}`) = DAY(NOW())";
                $query->where($DB->raw("{$year} AND {$month} AND {$day}"));
            } elseif ($period == 'week') {
                $query->where(
                    $DB->raw("YEARWEEK(`{$col}`, 1) = YEARWEEK(CURDATE(), 1)")
                );
            } elseif ($period == 'month') {
                $year = "YEAR(`{$col}`) = YEAR(NOW())";
                $month = "MONTH(`{$col}`) = MONTH(NOW())";
                $query->where($DB->raw("{$year} AND {$month}"));
            } elseif ($period == 'year') {
                $query->where($DB->raw("YEAR(`{$col}`) = YEAR(NOW())"));
            }
        }
        return $query->count();
    }

    public function changeEntryStatus($formId, $entryId, $newStatus)
    {
        $this->where('form_id', $formId)
            ->where('id', $entryId)
            ->update(['status' => $newStatus]);
        return $newStatus;
    }

    public function updateMeta($submissionId, $metaKey, $metaValue)
    {
        $exist = Meta::where('meta_group', 'wpf_submissions')
            ->where('option_id', $submissionId)
            ->where('meta_key', $metaKey)
            ->first();

        if ($exist) {
            Meta::where('id', $exist->id)
                ->update([
                    'meta_value' => maybe_serialize($metaValue),
                    'updated_at' => current_time('mysql')
                ]);
        } else {
            Meta::create([
                'meta_key' => $metaKey,
                'option_id' => $submissionId,
                'meta_group' => $this->table,
                'meta_value' => maybe_serialize($metaValue),
                'updated_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    public function getMeta($submissionId, $metaKey, $default = '')
    {
        $exist = Meta::where('meta_group', $this->metaGroup)
            ->where('option_id', $submissionId)
            ->where('meta_key', $metaKey)
            ->first();

        if ($exist) {
            $value = maybe_unserialize($exist->meta_value);
            if ($value) {
                return $value;
            }
        }

        return $default;
    }
}
