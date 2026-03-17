<?php

use WPPayForm\App\Modules\Builder\Helper;
use WPPayForm\Framework\Support\Arr;

?>
<?php do_action('wppayform_global_menu'); ?>

<div class="wppayform_form_wrap">
    <div class="wppayform_form_wrap_area">
        <h2><?php _e('WPPayForm Global Settings', 'wppayform'); ?></h2>
        <div class="wppayform_settings_wrapper">
            <?php do_action('wppayform_before_global_settings_wrapper'); ?>
            <div class="wppayform_settings_sidebar">
                <ul class="wppayform_settings_list">
                    <li class="<?php echo Helper::getHtmlElementClass('settings', $currentComponent); ?>">
                        <a data-hash="settings"
                           href="<?php echo Helper::makeMenuUrl('wppayform_settings', [
                               'hash' => 'settings'
                           ]); ?>">
                           <i class="dashicons dashicons-translation"></i> <?php echo __('Settings'); ?>
                        </a>
                    </li>
                    <?php foreach ($components as $componentName => $component) : ?>
                        <li class="<?php echo Helper::getHtmlElementClass($component['hash'], $currentComponent); ?> wppayform_item_<?php echo  $componentName; ?>">
                            <a data-settings_key="<?php echo Arr::get($component, 'settings_key'); ?>"
                               data-component="<?php echo Arr::get($component, 'component', ''); ?>"
                               data-hash="<?php echo Arr::get($component, 'hash', ''); ?>"
                               href="<?php echo Helper::makeMenuUrl('wppayform_settings', $component); ?>"
                            >
                                <?php
                                    $title = Arr::get($component, 'icon', '') .' '. $component['title'];
                                    echo $title;
                                ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="wppayform_settings_container">
                <?php
                    do_action('wppayform_global_settings_component_' . $currentComponent);
                ?>
            </div>
            <?php do_action('wppayform_after_global_settings_wrapper'); ?>
        </div>
    </div>
</div>