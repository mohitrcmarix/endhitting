<?php

use WPPayForm\App\Hooks\Handlers\ActivationHandler;
use WPPayForm\App\Hooks\Handlers\DeactivationHandler;
use WPPayForm\Framework\Foundation\Application;

return function ($file) {
    register_activation_hook($file, function () {
        (new ActivationHandler)->handle();
    });

    register_deactivation_hook($file, function () {
        (new DeactivationHandler)->handle();
    });

    add_action('plugins_loaded', function () use ($file) {
        do_action('wppayform_loaded', new Application($file));
    });
};
