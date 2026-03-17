<?php

namespace WPPayForm\App\Hooks\Handlers;

use WPPayForm\Database\DBMigrator;

class ActivationHandler
{
    public function handle($network_wide = false)
    {
        DBMigrator::run($network_wide);
    }
}
