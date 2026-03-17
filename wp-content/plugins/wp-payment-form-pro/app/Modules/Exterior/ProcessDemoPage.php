<?php

namespace WPPayForm\App\Modules\Exterior;

use WPPayForm\App\App;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\AccessControl;

class ProcessDemoPage
{
    public function handleExteriorPages()
    {
        if (isset($_GET['wp_paymentform_preview']) && $_GET['wp_paymentform_preview']) {
            $hasDemoAccess = AccessControl::hasTopLevelMenuPermission();
            $hasDemoAccess = apply_filters('wppayform/can_see_demo_form', $hasDemoAccess);

            if (!current_user_can($hasDemoAccess)) {
                $accessStatus = AccessControl::giveCustomAccess();
                $hasDemoAccess = $accessStatus['has_access'];
            }

            if ($hasDemoAccess) {
                $formId = intval($_GET['wp_paymentform_preview']);
                wp_enqueue_style('dashicons');
                $this->loadDefaultPageTemplate();
                $this->renderPreview($formId);
            }
        }
    }

    public function renderPreview($formId)
    {
        $form = Form::getForm($formId);
        if ($form) {
            echo App::make('view')->render('admin.show_review', [
                'form_id' => $formId,
                'form' => $form
            ]);
            exit();
        }
    }

    private function loadDefaultPageTemplate()
    {
        add_filter('template_include', function ($original) {
            return locate_template(array('page.php', 'single.php', 'index.php'));
        }, 999);
    }

    /**
     * Set the posts to one
     *
     * @param WP_Query $query
     *
     * @return void
     */
    public function preGetPosts($query)
    {
        if ($query->is_main_query()) {
            $query->set('posts_per_page', 1);
            $query->set('ignore_sticky_posts', true);
        }
    }
}
