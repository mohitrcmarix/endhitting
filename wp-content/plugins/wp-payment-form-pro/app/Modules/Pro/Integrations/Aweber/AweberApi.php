<?php

namespace WPPayForm\App\Modules\Pro\Integrations\Aweber;

use WPPayForm\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AweberApi
{
    private $clientId = "NXk0nC3K4XJ38OnX3kzaEmKXXqjXgBaj";
    private $clientSecret = "RWpqGw24OrcXIDp7v6Bg70QdOxdUybmW";
    private $redirectUri = "urn:ietf:wg:oauth:2.0:oob";
    private $optionKey = null;
    private $apiUrl = 'https://auth.aweber.com/oauth2/';
    private $selfAccountLink = null;
    private $accountId = null;

    public function __construct($optionKey = [])
    {
        $this->optionKey = $optionKey;
    }

    // To get the authorization url for authorization code
    public function makeAuthorizationUrl()
    {
        $scopes = join(' ', [
            'account.read',
            'list.read',
            'subscriber.read',
            'subscriber.write',
            'email.read',
            'email.write',
            'subscriber.read-extended'
        ]);

        $attr = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $this->clientSecret,
        ];
        $paramString = http_build_query($attr);

        return $this->apiUrl . 'authorize' . '?' . $paramString . '&scope=' . $scopes;
    }

    public function generateAccessToken($option)
    {
        $requestApi = $this->apiUrl . 'token';
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $option['authorizeCode'],
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret
        );
        $args = array(
            'headers' => array(
                'Content-Type'=> 'application/x-www-form-urlencoded'
            ),
            'body' => $data
        );
        $response = wp_remote_post($requestApi, $args);
        /* If WP_Error, die. Otherwise, return decoded JSON. */
        if (is_wp_error($response)) {
            return [
                'error' => 'failed',
                'message' => $response->get_error_message()
            ];
        }

        return json_decode($response['body'], true);
    }

    public function getAccessToken()
    {
        $tokens = get_option($this->optionKey);
        if (!$tokens) {
            return false;
        }
        if (($tokens['expires_in'])) {
            // It's expired so we have to re-issue again
            $refreshTokens = $this->refreshToken($tokens);
            if (!is_wp_error($refreshTokens)) {
                $tokens['access_token'] = $refreshTokens['access_token'];
                $tokens['refresh_token'] = $refreshTokens['refresh_token'];
                $tokens['expires_in'] = $refreshTokens['expires_in'];
                $tokens['created_at'] = time();

                update_option($this->optionKey, $tokens, 'no');
            } else {
                return false;
            }
        }
        return $tokens['access_token'];
    }

    public function refreshToken($tokens)
    {
        $headers = array(
            'Content-Type'=> 'application/x-www-form-urlencoded',
        );
        $args = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
        ];

        return $this->makeRequest($this->apiUrl . 'token', $args, 'POST', $headers);
    }

    public function makeRequest($url, $data = [], $method = 'GET', $headers = false)
    {
        if (!$headers) {
            $headers = array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()
            );
        }
        $args = array(
            'headers' => $headers,
            'body' => $data
        );
        if ($method == 'GET') {
            $response = wp_remote_get($url, $args);
        } else {
            if ($method == 'POST') {
                $response = wp_remote_post($url, $args);
            }
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!$body) {
            $body = wp_remote_retrieve_response_message($response);
        }
        if (!empty($body['error'])) {
            $error = 'Unknown Error';
            if (isset($body['error_description'])) {
                $error = $body['error_description'];
            } else {
                if (!empty($body['error']['message'])) {
                    $error = $body['error']['message'];
                }
            }
            return new \WP_Error(423, $error);
        }

        return $body;
    }

    public function getLists()
    {
        $this->setAccountId();
        $lists = $this->makeRequest("https://api.aweber.com/1.0/accounts/{$this->accountId}/lists", '');

        if (is_wp_error($lists) || !isset($lists['entries'])) {
            return [];
        }

        return $lists['entries'];
    }

    private function setAccountId()
    {
        $accounts = $this->makeRequest('https://api.aweber.com/1.0/accounts', []);
        $this->selfAccountLink = Arr::get($accounts, 'entries.0.self_link');
        $this->accountId = Arr::get($accounts, 'entries.0.id');
    }

    public function getCustomFields($listId)
    {
        $this->setAccountId();
        $url = "https://api.aweber.com/1.0/accounts/{$this->accountId}/lists/{$listId}/custom_fields";
        $customFields = $this->makeRequest($url, []);

        if (is_wp_error($customFields)) {
            return [];
        }

        return $customFields['entries'];
    }

    public function addContact($contact, $listId)
    {
        $this->setAccountId();
        $url = "https://api.aweber.com/1.0/accounts/{$this->accountId}/lists/{$listId}/subscribers";
        return $this->makeRequest($url, json_encode($contact), 'POST');
    }
}
