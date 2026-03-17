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

use MultipleAuthors\Classes\Legacy\Module;
use MultipleAuthors\Classes\Objects\Author;
use PPAuthorsPro\Factory;

/**
 * class MA_Buddypress_Integration
 */
class MA_Buddypress_Integration extends Module
{
    const BUDDYPRESS_PROFILE_LINK_PROPERTY = 'buddypress_profile_link';

    public $module_name = 'buddypress_integration';

    /**
     * Instance of the module
     *
     * @var stdClass
     */
    public $module;

    /**
     * Construct the MA_Buddypress_Integration class
     */
    public function __construct()
    {
        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => __('BuddyPress Integration', 'publishpress-authors-pro'),
            'short_description' => __(
                'Add support for BuddyPress profile links.',
                'publishpress-authors-pro'
            ),
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-edit',
            'slug' => 'buddypress-integration',
            'default_options' => [
                'enabled' => 'on',
            ],
            'options_page' => false,
            'autoload' => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters('MA_Buddypress_Integration_default_options', $args['default_options']);

        $legacyPlugin = Factory::getLegacyPlugin();

        $this->module = $legacyPlugin->register_module($this->module_name, $args);

        parent::__construct();
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
        add_filter('pp_multiple_authors_author_properties', [$this, 'filterAuthorIssetProperties'], 10);
        add_filter('publishpress_authors_author_attribute', [$this, 'filterAuthorAttributes'], 10, 3);
        add_filter('publishpress_authors_custom_fields_for_help_text', [$this, 'filterCustomFieldsForHelpText']);
    }

    private function getBuddyPressProfileLink($userId)
    {
        return bp_core_get_userlink($userId, false, true);
    }

    public function filterAuthorIssetProperties($properties)
    {
        $properties[self::BUDDYPRESS_PROFILE_LINK_PROPERTY] = true;

        return $properties;
    }

    public function filterAuthorAttributes($value, $termId, $attribute)
    {
        if ($attribute === self::BUDDYPRESS_PROFILE_LINK_PROPERTY) {
            $author = Author::get_by_term_id($termId);

            if (is_object($author) && ! $author->is_guest()) {
                $value = esc_url($this->getBuddyPressProfileLink($author->user_id));
            }
        }

        return $value;
    }

    /**
     * @param array $customFields
     * @return array
     */
    public function filterCustomFieldsForHelpText($customFields)
    {
        $customFields[] = [
            'name' => self::BUDDYPRESS_PROFILE_LINK_PROPERTY,
            'label' => __('BuddyPress Profile Link', 'publishpress-authors-pro'),
        ];

        return $customFields;
    }
}
