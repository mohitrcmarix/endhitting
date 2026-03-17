<?php
/**
 * @package     MultipleAuthors\
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PPAuthorsPro;

use MultipleAuthors\Classes\Legacy\LegacyPlugin;
use Pimple\Container;

defined('ABSPATH') or die('No direct script access allowed.');

if (! defined('PP_AUTHORS_PRO_LOADED')) {
    require_once __DIR__ . '/../includes.php';
}

/**
 * Class Factory
 */
abstract class Factory
{
    /**
     * @var Container
     */
    protected static $container = null;

    /**
     * @return LegacyPlugin
     */
    public static function getLegacyPlugin()
    {
        $container = self::getContainer();

        return $container['legacy_plugin'];
    }

    /**
     * @return Container
     */
    public static function getContainer()
    {
        if (static::$container === null) {
            $services = new Services();

            static::$container = new Container();
            static::$container->register($services);
        }

        return static::$container;
    }
}
