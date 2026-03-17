<?php
/**
 * PublishPress Authors Pro plugin bootstrap file.
 *
 * @link        https://publishpress.com/multiple-authors/
 * @package     MultipleAuthors
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 *
 * @publishpress-authors-pro
 * Plugin Name: PublishPress Authors Pro
 * Plugin URI:  https://publishpress.com/
 * Version: 3.30.1
 * Description: PublishPress Authors allows you to add multiple authors and guest authors to WordPress posts.
 * Author:      PublishPress
 * Author URI:  https://publishpress.com
 *
 * Based on Co-Authors Plus
 *  - Author: Mohammad Jangda, Daniel Bachhuber, Automattic
 *  - Copyright: 2008-2015 Shared and distributed between  Mohammad Jangda, Daniel Bachhuber, Weston Ruter
 */

function ppAuthorsProGetLegacyPluginRelativePath()
{
    $pluginsPath = dirname(__DIR__);
    $pluginsDirectories = @scandir($pluginsPath);
    $expectedFilename = 'publishpress-multiple-authors.php';

    if (empty($pluginsDirectories)) {
        return false;
    }

    foreach ($pluginsDirectories as $dir) {
        if ('.' !== $dir && '..' !== $dir && is_dir($pluginsPath . '/' . $dir)) {
            $files = @scandir($pluginsPath . '/' . $dir);

            if (! empty($files) && in_array($expectedFilename, $files)) {
                return $pluginsPath . '/' . $dir . '/' . $expectedFilename;
            }
        }
    }

    return false;
}

function ppAuthorProHasLegacyPluginActivated()
{
    // First we try to find the plugin in the correct folder.
    $expectedPath = dirname(__DIR__) . '/publishpress-multiple-authors/publishpress-multiple-authors.php';
    if (file_exists($expectedPath)) {
        $pluginPath = $expectedPath;
    } else {
        $pluginPath = ppAuthorsProGetLegacyPluginRelativePath();
    }


    if (! function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $pluginPath = plugin_basename($pluginPath);

    return is_plugin_active($pluginPath);
}

$hasLegacyPluginActive = ppAuthorProHasLegacyPluginActivated();

if (! defined('PP_AUTHORS_PRO_LOADED') && ! $hasLegacyPluginActive) {
    define('PUBLISHPRESS_AUTHORS_SKIP_VERSION_NOTICES', true);

    // Check required PHP version.
    if ( version_compare(PHP_VERSION, '7.2.5', '<') ) {
        // Send an armin warning
        add_action('admin_notices', function() {
            $data = get_plugin_data(__FILE__);

            echo '<div class="error"><p><strong>' . esc_html__('Warning:', '  publishpress-authors-pro') . '</strong> '
                . sprintf(esc_html__('The active plugin %s is not compatible with your PHP version.', '  publishpress-authors-pro') .'</p><p>',
                    '&laquo;' . esc_html($data['Name']) . ' ' . esc_html($data['Version']) . '&raquo;')
                . sprintf(esc_html__('%s is the minimum version required for this plugin.', '  publishpress-authors-pro'), 'PHP 7.2.5 ')
                . '</p></div>';
        });
        return;
    }

    require_once __DIR__ . '/includes.php';

    $plugin = new \PPAuthorsPro\Plugin();
    $plugin->init();
}

if ($hasLegacyPluginActive && is_admin()) {
    global $pagenow;

    if ('plugins.php' === $pagenow) {
        add_action(
            'admin_notices',
            function () {
                $msg = sprintf(
                    '<strong>%s:</strong> %s',
                    esc_html__('Warning', 'publishpress-authors-pro'),
                    esc_html__(
                        'Please, deactivate and remove PublishPress Multiple Authors before using PublishPress Authors Pro.',
                        'publishpress-authors-pro'
                    )
                );

                echo "<div class='notice notice-error is-dismissible' style='color:black'><p>" . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            },
            5
        );
    }
}
