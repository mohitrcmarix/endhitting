<?php
/**
 * @package PublishPress Authors Pro
 * @author  PublishPress
 *
 * Copyright (C) 2018 PublishPress
 *
 * This file is part of PublishPress Authors Pro
 *
 * PublishPress Authors Pro is free software: you can redistribute it
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

use MultipleAuthors\Authors_Widget;
use MultipleAuthors\Classes\Legacy\Module;
use MultipleAuthors\Factory;

/**
 * class MA_Shortcode_Authors_List
 */
class MA_Shortcode_Authors_List extends Module
{
    /**
     * Meta data prefix.
     */
    const META_PREFIX = 'ppmashaa_';

    public $module_name = 'shortcode_authors_list';

    /**
     * Instance of the module
     *
     * @var stdClass
     */
    public $module;

    /**
     * Construct the MA_Shortcode_Authors_List class
     */
    public function __construct()
    {
        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title'             => __('Shortcode Authors List', 'publishpress-authors-pro'),
            'short_description' => __(
                'Add a shortcode for displaying list of authors',
                'publishpress-authors-pro'
            ),
            'module_url'        => $this->module_url,
            'icon_class'        => 'dashicons dashicons-edit',
            'slug'              => 'shortcode-authors-list',
            'default_options'   => [
                'enabled' => 'on',
            ],
            'options_page'      => false,
            'autoload'          => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters('MA_Shortcode_Authors_List_default_options', $args['default_options']);

        $legacyPlugin = Factory::getLegacyPlugin();

        $this->module = $legacyPlugin->register_module($this->module_name, $args);

        parent::__construct();
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
        if (PUBLISHPRESS_AUTHORS_LOAD_LEGACY_SHORTCODES) {
            /**
             * @deprecated since 3.13.3. Use publishpress_authors_list instead
             */
            add_shortcode('authors_list', [$this, 'renderShortcode']);
        }

    }

    public function renderShortcode($attributes)
    {
        $widget = new Authors_Widget('authors_list_shortcode', 'authors_list_shortcode');

        $defaults = [
            'show_title' => true
        ];

        $attributes = wp_parse_args($attributes, $defaults);

        ob_start();
        $widget->widget([], $attributes);
        return ob_get_clean();
    }
}
