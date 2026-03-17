<?php
/**
 * @package     MultipleAuthors
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.1.0
 */

namespace PPAuthorsPro\Layout;

use WP_Post;

/**
 * Representation of list of post terms by taxonomies.
 */
class PostTerms
{
    /**
     * @var WP_Post
     */
    private $post;

    /**
     * Instantiate a new post object
     *
     * @param WP_Post|int $post ID for the correlated post or the post instance.
     */
    public function __construct($post)
    {
        if ($post instanceof WP_Post) {
            $this->post = $post;
        } else {
            $this->post = get_post((int)$post);
        }
    }

    /**
     * @param string $taxonomy
     *
     * @return array|null
     */
    public function get($taxonomy)
    {
        $termsIndexBySlug = [];

        $terms = wp_get_post_terms($this->post->ID, sanitize_key($taxonomy));
        if (! empty($terms) && ! is_wp_error($terms)) {
            foreach ($terms as $termId) {
                $term = get_term($termId, sanitize_key($taxonomy));

                $termsIndexBySlug[$term->slug] = $term;
            }
        }

        return $termsIndexBySlug;
    }
}
