<?php

namespace WPPayForm\App\Models;

use WPPayForm\Framework\Foundation\App;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subscriptions Model
 * @since 1.2.0
 */
class Subscription extends Model
{
    protected $table = 'wpf_subscriptions';

    public function createSubscription($item)
    {
        return $this->create($item);
    }

    public function getSubscriptions($submissionId)
    {
        $subscriptions = $this->where('submission_id', $submissionId)
            ->get();
        foreach ($subscriptions as $subscription) {
            $subscription->original_plan = maybe_unserialize($subscription->original_plan);
            $subscription->vendor_response = maybe_unserialize($subscription->vendor_response);
        }
        return $subscriptions;
    }

    public function getSubscription($id)
    {
        $subscription = $this->where('id', $id)
            ->first();
        if ($subscription) {
            $subscription->original_plan = maybe_unserialize($subscription->original_plan);
        }

        return $subscription;
    }

    public function updateSubscription($id, $data)
    {
        $data['updated_at'] = current_time('mysql');
        return Subscription::where('id', $id)
            ->update($data);
    }

    public function updateBySubmissionId($submissionId, $data)
    {
        return $this->where('submission_id', $submissionId)
            ->update($data);
    }

    public function getSubscriptionPaymentTotal($formId, $submissionId = false)
    {
        $paymentTotal = 0;

        $DB = App::make('db');

        // Calculate from subscriptions
        $query = $this->select($DB->raw('SUM(payment_total - initial_amount) as payment_total'));
        if ($formId) {
            $query = $query->where('form_id', $formId);
        }

        if ($submissionId) {
            $query = $query->where('submission_id', $submissionId);
        }

        $result = $query->first();
        if ($result && $result->payment_total) {
            $paymentTotal = $result->payment_total;
        }

        return $paymentTotal;
    }

    public function updateMeta($optionId, $key, $value)
    {
        $value = maybe_serialize($value);
        $exists = (new Meta())->where('meta_group', 'wpf_subscriptions')
            ->where('meta_key', $key)
            ->where('option_id', $optionId)
            ->first();

        if ($exists) {
            (new Meta())->where('id', $exists->id)
                ->update([
                    'meta_group' => $this->dbName,
                    'option_id' => $optionId,
                    'meta_key' => $key,
                    'meta_value' => $value,
                    'updated_at' => current_time('mysql')
                ]);
            return $exists->id;
        }

        return (new Meta())->insert([
            'meta_group' => $this->dbName,
            'option_id' => $optionId,
            'meta_key' => $key,
            'meta_value' => $value,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
    }

    public function getMetas($optionId)
    {
        $metas = (new Meta())->where('meta_group', $this->dbName)
            ->where('option_id', $optionId)
            ->get();
        $formatted = array();
        foreach ($metas as $meta) {
            $formatted[$meta->meta_key] = maybe_unserialize($meta->meta_value);
        }
        return (object)$formatted;
    }

    public function getIntentedSubscriptions($submissionId)
    {
        $subscriptions = $this->where('submission_id', $submissionId)
            ->where('status', 'intented')
            ->get();
        foreach ($subscriptions as $subscription) {
            $subscription->original_plan = maybe_unserialize($subscription->original_plan);
            $subscription->vendor_response = maybe_unserialize($subscription->vendor_response);
        }
        return $subscriptions;
    }
}
