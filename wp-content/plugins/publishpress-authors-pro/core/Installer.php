<?php
/**
 * @package PublishPress Authors
 * @author  PublishPress
 *
 * Copyright (C) 2018 PublishPress
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

namespace PPAuthorsPro;

use MA_Author_Custom_Layouts;
use MultipleAuthors\Classes\Objects\Author;

class Installer
{
    public function init()
    {
        add_action('admin_init', [$this, 'checkAndTriggerInstaller'], 2010);

        $this->setAdminCapabilities();
    }

    private function setAdminCapabilities()
    {
        $adminRole = get_role('administrator');
        $adminRole->add_cap('ppma_manage_layouts');
        $adminRole->add_cap('ppma_manage_custom_fields');
    }

    public function checkAndTriggerInstaller()
    {
        $optionName = 'PP_AUTHORS_PRO_VERSION';

        $previousVersion = get_option($optionName);
        $currentVersion = PP_AUTHORS_PRO_VERSION;

        if (! apply_filters('publishpress_authors_pro_skip_installation', false, $previousVersion, $currentVersion)) {
            if (empty($previousVersion)) {
                $this->install($currentVersion);

                /**
                 * Action called when the plugin is installed.
                 *
                 * @param string $currentVersion
                 */
                do_action('publishpress_authors_pro_install', $currentVersion);
            } elseif (version_compare($previousVersion, $currentVersion, '>')) {
                /**
                 * Action called when the plugin is downgraded.
                 *
                 * @param string $previousVersion
                 */
                do_action('publishpress_authors_pro_downgrade', $previousVersion);
            } elseif (version_compare($previousVersion, $currentVersion, '<')) {
                $this->upgrade($previousVersion);

                /**
                 * Action called when the plugin is upgraded.
                 *
                 * @param string $previousVersion
                 */
                do_action('publishpress_authors_pro_upgrade', $previousVersion);
            }
        }

        if ($currentVersion !== $previousVersion) {
            update_option($optionName, $currentVersion, true);
        }
    }

    /**
     * Runs methods when the plugin is running for the first time.
     *
     * @param string $current_version
     */
    private function install($current_version)
    {
        $this->create_default_layouts();
    }

    /**
     * Create the default author layouts.
     */
    private function create_default_layouts()
    {
        MA_Author_Custom_Layouts::createDefaultLayouts();
    }

    /**
     * Runs methods when the plugin is updated.
     *
     * @param string $previous_version
     */
    private function upgrade($previous_version)
    {
        if (version_compare($previous_version, '2.3.0', '<=')) {
            self::create_default_layouts();
        }

        if (version_compare($previous_version, '2.4.0', '<=')) {
            self::add_post_custom_fields();
        }

        if (version_compare($previous_version, '3.20.0', '<=')) {
            self::create_default_layouts();
        }
    }

    /**
     * Add custom field with authors' name on all posts.
     *
     * @since 2.4.0
     */
    private function add_post_custom_fields()
    {
        global $wpdb;

        // Get the authors
        $terms = $this->get_all_author_terms();
        $names = $this->get_terms_author_names($terms);

        // Get all different combinations of authors to make a cache and save connections to the db.
        $posts_author_names = static::get_post_author_names($names);

        // Update all posts.
        foreach ($posts_author_names as $post_id => $post_names) {
            $post_names = implode(', ', $post_names);

            update_post_meta($post_id, 'ppma_authors_name', $post_names);
        }
    }

    /**
     * Return a list with al the author terms.
     *
     * @return array
     *
     * @since 2.4.0
     */
    private function get_all_author_terms()
    {
        global $wpdb;
        // Get list of authors with mapped users.
        $authors = $wpdb->get_results(
            "SELECT taxonomy.term_id
                   FROM {$wpdb->term_taxonomy} AS taxonomy
                   WHERE taxonomy.`taxonomy` = 'author'"
        );
        return $authors;
    }

    /**
     * Map a list of author terms to a list of author names indexed by the term id.
     *
     * @param array $authors
     *
     * @return array
     *
     * @since 2.4.0
     */
    private function get_terms_author_names($authors)
    {
        if (empty($authors)) {
            return;
        }
        $mappedList = [];
        foreach ($authors as $term) {
            $author = Author::get_by_term_id($term->term_id);
            $mappedList[$term->term_id] = $author->name;
        }
        return $mappedList;
    }

    /**
     * @param array $author_names
     *
     * @return array
     *
     * @since 2.4.0
     */
    private function get_post_author_names($author_names)
    {
        $term_ids = array_keys($author_names);
        $combination_names = [];
        $combinations = $this->get_taxonomy_ids_combinations($term_ids);
        foreach ($combinations as $combination_str) {
            $combination_list = explode(',', $combination_str->taxonomy_ids);
            $names = array_map(
                function ($id) use ($author_names) {
                    return $author_names[$id];
                },
                $combination_list
            );
            $combination_names[$combination_str->object_id] = $names;
        }
        return $combination_names;
    }

    /**
     *
     * @param array $term_ids
     *
     * @return mixed
     *
     * @since 2.4.0
     */
    private function get_taxonomy_ids_combinations($term_ids)
    {
        global $wpdb;
        $term_ids = array_map('esc_sql', $term_ids);
        $term_ids = implode(',', $term_ids);

        $ids = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT object_id, group_concat(r.term_taxonomy_id) as taxonomy_ids
                FROM {$wpdb->term_relationships} AS r
                WHERE r.term_taxonomy_id in ({$term_ids})
                GROUP BY r.object_id
                ORDER BY r.term_order"
        );
        return $ids;
    }
}
