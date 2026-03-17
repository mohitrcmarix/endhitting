<?php
    $page = sanitize_text_field($_GET['page']);
?>
<div class="wppayform_main_nav">
    <span class="wpf_plugin-name">
        <img style="max-width:36px;"
            src="<?php echo $brand_logo;?>"
        >
    </span>
    <a href="<?php echo admin_url('admin.php?page=wppayform.php#/'); ?>" class="ninja-tab wpf-route-forms">
        <?php _e('All Forms', 'wppayform'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wppayform.php#/entries');?>" class="ninja-tab wpf-route-entries">
        <?php _e('All Entries & Payments', 'wppayform'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wppayform.php#/integrations'); ?>" class="ninja-tab wpf-route-integrations">
        <?php _e('Integrations', 'wppayform'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wppayform_settings'); ?>" class="ninja-tab <?php echo ($page == 'wppayform_settings') ? 'ninja-tab-active' : '' ?>">
        <?php _e('Settings', 'wppayform'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=wppayform.php#/support'); ?>" class="ninja-tab wpf-route-support">
        <?php _e('Support & Debug', 'wppayform'); ?>
    </a>
    <div class="wppayform-fullscreen-main">
        <span id="wpf-contract-btn"
            class="wpf-contract-btn dashicons dashicons-editor-contract">
        </span>
        <span id="wpf-expand-btn"
            class="wpf-expand-btn dashicons dashicons-editor-expand">
        </span>
    </div>

    <?php do_action('wppayform_after_global_menu'); ?>
    <?php if (!defined('WPPAYFORM_PRO_INSTALLED')) : ?>
        <a target="_blank" rel="noopener" href="<?php echo wppayformUpgradeUrl(); ?>" class="ninja-tab buy_pro_tab">
            <?php _e('Upgrade to Pro', 'wppayform'); ?>
        </a>
    <?php endif; ?>
</div>
