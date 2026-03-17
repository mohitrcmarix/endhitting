<?php

namespace WPPayForm\App\Models;

use WPPayForm\Database\DBMigrator;

class GlobalSettings extends Model
{
    public static function updateSettings($request, $data)
    {
        update_option('wppayform_global_currency_settings', $data);
        update_option('wppayform_ip_logging_status', sanitize_text_field($request->ip_logging_status), false);
        update_option('wppayform_abandoned_time', intval($request->abandoned_time), false);

        // We will forcefully try to upgrade the DB and later we will remove this after 1-2 version
        $firstTransaction = Transaction::first();

        if (!$firstTransaction || !property_exists($firstTransaction, 'subscription_id')) {
            DBMigrator::forceUpgradeDB();
        }
        // end upgrade DB
        return;
    }
}
