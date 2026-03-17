<?php
/**
 * @package     PPAuthorsPro
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PPAuthorsPro;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin main class file.
 *
 * @package     MultipleAuthors\Classes
 * @author      UpStream <https://upstreamplugin.com>
 * @copyright   Copyright (c) 2018 UpStream Project Management
 * @license     GPL-3
 * @since       1.0.0
 * @final
 */
final class CustomFieldsModel
{
    /**
     * Retrieve all supported field types.
     *
     * @return  array
     * @since   1.0.0
     * @static
     *
     */
    public static function getFieldTypes()
    {
        $fieldTypes = [
            'text' => __('Text', 'publishpress-multiple-authors'),
            'textarea' => __('Multiline text', 'publishpress-multiple-authors'),
            'wysiwyg' => __('WYSIWYG Editor', 'publishpress-multiple-authors'),
            'url' => __('Link', 'publishpress-multiple-authors'),
            'email' => __('Email address', 'publishpress-multiple-authors'),
        ];

        return $fieldTypes;
    }
}
