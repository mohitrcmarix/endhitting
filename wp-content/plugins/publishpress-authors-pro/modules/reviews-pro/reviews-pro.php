<?php
/**
 * @package PublishPress Authors Pro
 * @author  PublishPress
 *
 * Copyright (C) 2021 PublishPress
 *
 * This file is part of PublishPress Authors
 *
 * PublishPress Authors is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

use PublishPress\WordPressReviews\ReviewsController;
use MultipleAuthors\Classes\Legacy\Module;
use MultipleAuthors\Classes\Legacy\Util;
use MultipleAuthors\Factory;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class MA_Reviews_Pro
 *
 * This class adds a review request system for your plugin or theme to the WP dashboard.
 */
class MA_Reviews_Pro extends Module
{
    /**
     * Tracking API Endpoint.
     *
     * @var string
     */
    public static $api_url = '';

    public $module_name = 'reviews_pro';

    public $module_url = '';

    /**
     * Instance for the module
     *
     * @var stdClass
     */
    public $module;

    /**
     * The constructor
     */
    public function __construct()
    {
        global $publishpress;

        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => __('Reviews Pro', 'publishpress-authors'),
            'short_description' => false,
            'extended_description' => false,
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-feedback',
            'slug' => 'reviews-pro',
            'default_options' => [
                'enabled' => 'on',
            ],
            'general_options' => false,
            'autoload' => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters(
            'ppma_reviews_pro_default_options',
            $args['default_options']
        );

        $legacyPlugin = Factory::getLegacyPlugin();

        $this->module = $legacyPlugin->register_module($this->module_name, $args);

        parent::__construct();

        add_filter('publishpress-authors_wp_reviews_allow_display_notice', [$this, 'shouldDisplayBanner'], 20);
    }

    /**
     *
     */
    public function init()
    {
    }

    /**
     * @param $shouldDisplay
     * @return bool|null
     */
    public function shouldDisplayBanner($shouldDisplay)
    {
        global $pagenow;

        if ($shouldDisplay) {
            return true;
        }

        if ($pagenow === 'edit.php' && isset($_GET['post_type'])) {
            if ($_GET['post_type'] === 'ppmacf_field') {
                return true;
            }

            if ($_GET['post_type'] === 'ppmacf_layout') {
                return true;
            }
        }

        return $shouldDisplay;
    }
}
