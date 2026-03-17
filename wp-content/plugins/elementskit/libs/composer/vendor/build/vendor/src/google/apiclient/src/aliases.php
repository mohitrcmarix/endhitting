<?php

namespace ElementskitVendor;

if (\class_exists('ElementskitVendor\\Google_Client', \false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}
$classMap = ['ElementskitVendor\\Google\\Client' => 'Google_Client', 'ElementskitVendor\\Google\\Service' => 'Google_Service', 'ElementskitVendor\\Google\\AccessToken\\Revoke' => 'Google_AccessToken_Revoke', 'ElementskitVendor\\Google\\AccessToken\\Verify' => 'Google_AccessToken_Verify', 'ElementskitVendor\\Google\\Model' => 'Google_Model', 'ElementskitVendor\\Google\\Utils\\UriTemplate' => 'Google_Utils_UriTemplate', 'ElementskitVendor\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'Google_AuthHandler_Guzzle6AuthHandler', 'ElementskitVendor\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'Google_AuthHandler_Guzzle7AuthHandler', 'ElementskitVendor\\Google\\AuthHandler\\Guzzle5AuthHandler' => 'Google_AuthHandler_Guzzle5AuthHandler', 'ElementskitVendor\\Google\\AuthHandler\\AuthHandlerFactory' => 'Google_AuthHandler_AuthHandlerFactory', 'ElementskitVendor\\Google\\Http\\Batch' => 'Google_Http_Batch', 'ElementskitVendor\\Google\\Http\\MediaFileUpload' => 'Google_Http_MediaFileUpload', 'ElementskitVendor\\Google\\Http\\REST' => 'Google_Http_REST', 'ElementskitVendor\\Google\\Task\\Retryable' => 'Google_Task_Retryable', 'ElementskitVendor\\Google\\Task\\Exception' => 'Google_Task_Exception', 'ElementskitVendor\\Google\\Task\\Runner' => 'Google_Task_Runner', 'ElementskitVendor\\Google\\Collection' => 'Google_Collection', 'ElementskitVendor\\Google\\Service\\Exception' => 'Google_Service_Exception', 'ElementskitVendor\\Google\\Service\\Resource' => 'Google_Service_Resource', 'ElementskitVendor\\Google\\Exception' => 'Google_Exception'];
foreach ($classMap as $class => $alias) {
    \class_alias($class, $alias);
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Google_Task_Composer extends \ElementskitVendor\Google\Task\Composer
{
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
\class_alias('ElementskitVendor\\Google_Task_Composer', 'Google_Task_Composer', \false);
if (\false) {
    class Google_AccessToken_Revoke extends \ElementskitVendor\Google\AccessToken\Revoke
    {
    }
    class Google_AccessToken_Verify extends \ElementskitVendor\Google\AccessToken\Verify
    {
    }
    class Google_AuthHandler_AuthHandlerFactory extends \ElementskitVendor\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Google_AuthHandler_Guzzle5AuthHandler extends \ElementskitVendor\Google\AuthHandler\Guzzle5AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle6AuthHandler extends \ElementskitVendor\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle7AuthHandler extends \ElementskitVendor\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Google_Client extends \ElementskitVendor\Google\Client
    {
    }
    class Google_Collection extends \ElementskitVendor\Google\Collection
    {
    }
    class Google_Exception extends \ElementskitVendor\Google\Exception
    {
    }
    class Google_Http_Batch extends \ElementskitVendor\Google\Http\Batch
    {
    }
    class Google_Http_MediaFileUpload extends \ElementskitVendor\Google\Http\MediaFileUpload
    {
    }
    class Google_Http_REST extends \ElementskitVendor\Google\Http\REST
    {
    }
    class Google_Model extends \ElementskitVendor\Google\Model
    {
    }
    class Google_Service extends \ElementskitVendor\Google\Service
    {
    }
    class Google_Service_Exception extends \ElementskitVendor\Google\Service\Exception
    {
    }
    class Google_Service_Resource extends \ElementskitVendor\Google\Service\Resource
    {
    }
    class Google_Task_Exception extends \ElementskitVendor\Google\Task\Exception
    {
    }
    interface Google_Task_Retryable extends \ElementskitVendor\Google\Task\Retryable
    {
    }
    class Google_Task_Runner extends \ElementskitVendor\Google\Task\Runner
    {
    }
    class Google_Utils_UriTemplate extends \ElementskitVendor\Google\Utils\UriTemplate
    {
    }
}
