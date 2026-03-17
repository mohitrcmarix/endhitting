<?php
/**
 * @package     MultipleAuthors
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (C) 2018 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PPAuthorsPro;

use Pimple\Container as Pimple;
use Pimple\ServiceProviderInterface;
use MultipleAuthors\Factory as FreeFactory;
use PublishPress\EDD_License\Core\Container as EDDContainer;
use PublishPress\EDD_License\Core\Services as EDDServices;
use PublishPress\EDD_License\Core\ServicesConfig as EDDServicesConfig;

defined('ABSPATH') or die('No direct script access allowed.');

/**
 * Class Services
 */
class Services implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Pimple $container A container instance
     *
     * @since 1.2.3
     *
     */
    public function register(Pimple $container)
    {
        $container['legacy_plugin'] = static function (Pimple $c) {
            $container = FreeFactory::get_container();

            return $container['legacy_plugin'];
        };

        $container['module'] = static function (Pimple $c) {
            $legacyPlugin = $c['legacy_plugin'];

            return $legacyPlugin->multiple_authors;
        };

        $container['module_author_custom_fields'] = static function (Pimple $c) {
            $legacyPlugin = $c['legacy_plugin'];

            return $legacyPlugin->author_custom_fields;
        };

        $container['LICENSE_KEY'] = static function (Pimple $c) {
            $key = '';
            $options = get_option('multiple_authors_multiple_authors_options');

            if (isset($options->license_key)) {
                $key = $options->license_key;
            }

            return $key;
        };

        $container['LICENSE_STATUS'] = static function (Pimple $c) {
            $status = Plugin::LICENSE_STATUS_INVALID;

            $options = get_option('multiple_authors_multiple_authors_options');

            if (isset($options->license_status)) {
                $status = $options->license_status;
            }

            return $status;
        };

        $container['edd_container'] = static function (Pimple $c) {
            $config = new EDDServicesConfig();
            $config->setApiUrl(PP_AUTHORS_PRO_SITE_URL);
            $config->setLicenseKey($c['LICENSE_KEY']);
            $config->setLicenseStatus($c['LICENSE_STATUS']);
            $config->setPluginVersion(PP_AUTHORS_PRO_VERSION);
            $config->setEddItemId(PP_AUTHORS_PRO_ITEM_ID);
            $config->setPluginAuthor(PP_AUTHORS_PRO_PLUGIN_AUTHOR);
            $config->setPluginFile(PP_AUTHORS_PRO_FILE);

            $services = new EDDServices($config);

            $eddContainer = new EDDContainer();
            $eddContainer->register($services);

            return $eddContainer;
        };

        $container['installer'] = static function (Pimple $c) {
            return new Installer();
        };
    }
}
