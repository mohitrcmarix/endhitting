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
 * class MA_Automap_Author
 */
class MA_Automap_Author extends Module
{
    public $module_name = 'automap_author';

    /**
     * Instance of the module
     *
     * @var stdClass
     */
    public $module;

    /**
     * Construct the MA_Automap_Author class
     */
    public function __construct()
    {
        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => __('Auto map authors to users', 'publishpress-authors-pro'),
            'short_description' => __(
                'Add bulk action to automatically map authors to users',
                'publishpress-authors-pro'
            ),
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-edit',
            'slug' => 'automap_author',
            'default_options' => [
                'enabled' => 'on',
            ],
            'options_page' => false,
            'autoload' => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters('MA_Automap_Author', $args['default_options']);

        $legacyPlugin = Factory::getLegacyPlugin();

        $this->module = $legacyPlugin->register_module($this->module_name, $args);

        parent::__construct();
    }

    /**
     * Initialize the module. Conditionally loads if the module is enabled
     */
    public function init()
    {
        add_filter('bulk_actions-edit-author', [$this, 'addBulkActions']);
        add_filter('handle_bulk_actions-edit-author', [$this, 'handleBulkActions'], 10, 3);
    }

    /**
     * Add bulk actions to the list of authors.
     *
     * @param $bulk_actions
     *
     * @return array
     */
    public function addBulkActions($bulk_actions)
    {
        $bulk_actions['auto_map_to_user'] = __(
            'Auto map guest authors to users by the slug',
            'publishpress-authors-pro'
        );

        return $bulk_actions;
    }

    private function getUserMatchingTheSlug($slug)
    {
        $user = get_user_by('slug', $slug);

        if (empty($user) || is_wp_error($user)) {
            return false;
        }

        return $user;
    }

    private function authorIsMappedToUser($author)
    {
        return $author->user_id > 0;
    }

    private function getAuthorByTermId($termId)
    {
        return Author::get_by_term_id($termId);
    }

    private function mapAuthorToUser($authorTermId, $userId)
    {
        update_term_meta($authorTermId, 'user_id', $userId);
    }

    /**
     * Handle bulk actions from authors.
     *
     * @param string $redirectToURL
     * @param string $doAction
     * @param array $authorThermIdList
     *
     * @return mixed
     */
    public function handleBulkActions($redirectToURL, $doAction, $authorThermIdList)
    {
        $bulkActions = [
            'auto_map_to_user',
        ];

        if (empty($authorThermIdList) || ! in_array($doAction, $bulkActions, true)) {
            return $redirectToURL;
        }

        $updated = 0;

        foreach ($authorThermIdList as $authorTermId) {
            if ($doAction === 'auto_map_to_user') {
                $author = $this->getAuthorByTermId($authorTermId);

                if ($this->authorIsMappedToUser($author)) {
                    continue;
                }

                $user = $this->getUserMatchingTheSlug($author->slug);

                if (is_object($user)) {
                    $this->mapAuthorToUser($authorTermId, $user->ID);

                    $updated++;
                }
            }
        }

        $redirectToURL = add_query_arg('bulk_update_author', $updated, $redirectToURL);

        return $redirectToURL;
    }
}
