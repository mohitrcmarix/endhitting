<?php

/**
 * All registered action's handlers should be in app\Hooks\Handlers,
 * addAction is similar to add_action and addCustomAction is just a
 * wrapper over add_action which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomAction('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_action('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app WPFluent\Foundation\Application
 */

$app->addAction('admin_menu', 'AdminMenuHandler@add');
$app->addAction('wppayform/after_create_form', 'FormHandlers@insertTemplate', 10, 3);


// Handle Network new Site Activation
add_action('wp_insert_site', function ($blogId) {
    require_once(WPPAYFORM_DIR . 'includes/Classes/Activator.php');
    switch_to_blog($blogId->blog_id);
    \WPPayForm\Database\DBMigrator::migrate();
    restore_current_blog();
});


add_action('plugins_loaded', function () {
    // Let's check again if Pro version is available or not
    if (defined('WPPAYFORM_PRO_INSTALLED')) {
        if (function_exists("deactivate_plugins")) {
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }
});


// disabled update-nag
add_action('admin_init', function () {
    $disablePages = [
        'wppayform.php',
        'wppayform_settings'
    ];
    if (isset($_GET['page']) && in_array($_GET['page'], $disablePages)) {
        remove_all_actions('admin_notices');
    }
});


// Form Submission Handler
$submissionHandler = new \WPPayForm\App\Hooks\Handlers\SubmissionHandler();
add_action('wp_ajax_wpf_submit_form', array($submissionHandler, 'handleSubmission'));
add_action('wp_ajax_nopriv_wpf_submit_form', array($submissionHandler, 'handleSubmission'));

//integration
$app->addAction('wppayform/after_submission_data_insert', function ($submissionId, $formId, $formData, $formattedElements) {
    $notificationManager = new \WPPayForm\App\Services\Integrations\GlobalNotificationManager();
    $notificationManager->globalNotify($submissionId, $formId, $formData, $formattedElements);
}, 10, 4);


// Handle Exterior Pages
$app->addAction('init', function () {
    $demoPage = new \WPPayForm\App\Modules\Exterior\ProcessDemoPage();
    $demoPage->handleExteriorPages();

    $frameLessPage = new \WPPayForm\App\Modules\Exterior\FramelessProcessor();
    $frameLessPage->init();
});


// Load dependencies
$app->addAction('wppayform_loaded', function ($app) {
    $dependency = new \WPPayForm\App\Hooks\Handlers\DependencyHandler;
    $dependency->registerStripe();
    $dependency->registerShortCodes();
    $dependency->tinyMceBlock();
    $dependency->dashboardWidget();


    $app->addAction('wppayform_log_data', function ($data) {
        \WPPayForm\App\Models\SubmissionActivity::createActivity($data);
    }, 10, 1);

    $app->addAction('wppayform_global_menu', function () {
        $menu = new \WPPayForm\App\Hooks\Handlers\AdminMenuHandler();
        $menu->renderGlobalMenu();
    });

    $app->addAction('wppayform_global_settings_component_wppayform_settings', function () {
        $menu = new \WPPayForm\App\Hooks\Handlers\AdminMenuHandler();
        $menu->renderSettings();
    });

    $app->addAction('wppayform_global_notify_completed', function ($insertId, $formId) use ($app) {
        $form = \WPPayForm\App\Models\Form::getFormattedElements($formId);
        $passwordFields = [];
        foreach ($form['input'] as $key => $value) {
            if ($value['type'] === 'password') {
                $passwordFields[] = $value['id'];
            }
        }
        if (count($passwordFields) && apply_filters('wppayform_truncate_password_values', true, $formId)) {
            // lets clear the pass from DB
            (new \WPPayForm\App\Services\Integrations\GlobalNotificationManager($app))->cleanUpPassword($insertId, $passwordFields);
        }
    }, 10, 2);

    //Fluentcrm integration
    if (defined('FLUENTCRM')) {
        (new \WPPayForm\App\Services\Integrations\FluentCrm\FluentCrmInit())->init();
    };

    $app->addAction('init', function () use ($app) {
        new \WPPayForm\App\Services\Integrations\MailChimp\MailChimpIntegration($app);
        (new \WPPayForm\App\Services\Integrations\Slack\SlackNotificationActions())->register();
    });

    // Action for background process
    $asyncRequest = new \WPPayForm\App\Services\AsyncRequest();
    add_action('wp_ajax_wppayform_background_process', array($asyncRequest, 'handleBackgroundCall'));
    add_action('wp_ajax_nopriv_wppayform_background_process', array($asyncRequest, 'handleBackgroundCall'));

    if (defined('WPPAYFORM_PRO_INSTALLED')) {
        //pro version file init
        $pro = new \WPPayForm\App\Modules\Pro\Init();
        $pro->boot($app);
        $pro->registerRoutes($app->router);

        add_filter('wppayform/form_entry', array("\WPPayForm\App\Modules\Pro\Classes\RecurringInfo", 'addRecurringSubscriptions'), 10, 1);
        add_action('wppayform/after_delete_submission', array("\WPPayForm\App\Modules\Pro\Classes\RecurringInfo", 'deleteSubscriptionData'), 99, 1);
        // coupon actions
        $CouponController = new \WPPayForm\App\Modules\Pro\Classes\Coupons\CouponController();
        add_action('wp_ajax_wpf_coupon_apply', array($CouponController, 'validateCoupon'));
        add_action('wp_ajax_nopriv_wpf_coupon_apply', array($CouponController, 'validateCoupon'));
    }
});

add_action('admin_init', function () {
	if (defined('WPPAYFORM_PRO_INSTALLED')) {
		$licenseManager = new \WPPayForm\App\Modules\Pro\PluginManager\LicenseManager;
		$licenseManager->initUpdater();

		$licenseMessage = $licenseManager->getLicenseMessages();

		if ($licenseMessage) {
			add_action('admin_notices', function () use ($licenseMessage) {
				$class = 'notice notice-error fc_message';
				$message = $licenseMessage['message'];
				printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
			});
		}
	}
}, 0);
