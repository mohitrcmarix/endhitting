<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Services\AccessControl;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Info Handler Class
 * @since 1.1.1
 */
class CustomScripts
{
    public function registerEndpoints()
    {
        add_action('wppayform/wppayform_adding_assets', array($this, 'printJS'));
        add_action('wppayform/form_render_before', array($this, 'printCSS'));
    }

    public function getSettings($formId)
    {
        $customCss = get_post_meta($formId, '_wpf_custom_css', true);
        $customJs = get_post_meta($formId, '_wpf_custom_js', true);

        return array(
            'custom_css' => $customCss,
            'custom_js' => $customJs
        );
    }

    public function saveSettings($request, $formId)
    {
        $css = $request->custom_css;
        $js = $request->custom_js;
        $css = wp_strip_all_tags($css);
        $js = wp_unslash($js);
        update_post_meta($formId, '_wpf_custom_css', $css);
        update_post_meta($formId, '_wpf_custom_js', $js);

        return array(
            'message' => __('Custom CSS and JS successfully saved', 'wppayform')
        );
    }

    public function printJS($form)
    {
        $customJS = get_post_meta($form->ID, '_wpf_custom_js', true);
        if ($customJS) {
            add_action('wp_footer', function () use ($form, $customJS) {
                ?>
                <script type="text/javascript">
                    jQuery(document.body).on('wp_payform_inited_<?php echo $form->ID; ?>', function (event, data, formConfig) {
                        var $form = jQuery(data[0]);
                        var $ = jQuery;
                        try {
                            <?php echo $customJS; ?>
                        } catch (e) {
                            console.warn('Error in custom JS of WPPayfFrom ID: ' + $form.data('wpf_form_id'));
                            console.error(e);
                        }
                    });
                </script>
                <?php
            }, 100, 1);
        }
    }

    public function printCSS($form)
    {
        $customCSS = get_post_meta($form->ID, '_wpf_custom_css', true);
        if ($customCSS) {
            ?>
            <style type="text/css">
                <?php echo $customCSS; ?>
            </style>
            <?php
        }
    }
}
