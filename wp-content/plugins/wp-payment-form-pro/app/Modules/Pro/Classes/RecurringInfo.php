<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Models\Subscription;
use WPPayForm\App\Models\SubscriptionTransaction;
use WPPayForm\App\Models\Transaction;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recutting Payment View Actions
 * @since 1.0.0
 */
class RecurringInfo
{
    public static function addRecurringSubscriptions($submission)
    {
        $subscriptionModel = new Subscription();
        $subscriptionTransactionModel = new SubscriptionTransaction();
        $subscriptions = $subscriptionModel->getSubscriptions($submission->id);

        $subscriptionPaymentTotal = 0;

        foreach ($subscriptions as $subscription) {
            $related_payments = $subscriptionTransactionModel->getSubscriptionTransactions($subscription->id);
            foreach ($related_payments as $related_payment) {
                if ($related_payment->status == 'paid') {
                    $subscriptionPaymentTotal += $related_payment->payment_total;
                }
                $related_payment->view_url = self::getTransactionUrl($related_payment);
            }
            $subscription->related_payments = $related_payments;
        }

        $submission->subscription_payment_total = $subscriptionPaymentTotal;
        $submission->subscriptions = $subscriptions;
        return $submission;
    }

    public static function deleteSubscriptionData($submissionId)
    {
        $allSubscriptions = Subscription::where('submission_id', $submissionId)
            ->get();
        if (!$allSubscriptions) {
            return;
        }
        $subscriptionIds = [];
        foreach ($allSubscriptions as $subscription) {
            $subscriptionIds[] = $subscription->id;
        }

        Transaction::where('transaction_type', 'subscription')
            ->whereIn('id', $subscriptionIds)
            ->delete();

        Subscription::where('submission_id', $submissionId)
            ->delete();
    }

    private static function getTransactionUrl($transaction)
    {
        if ($transaction->payment_mode == 'test') {
            return 'https://dashboard.stripe.com/test/payments/' . $transaction->charge_id;
        }
        return 'https://dashboard.stripe.com/payments/' . $transaction->charge_id;
    }
}
