<?php

namespace WPPayForm\App\Modules\Pro\Integrations\ActiveCampaign;

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\ConditionAssesor;
use WPPayForm\App\Services\Integrations\IntegrationManager;
use WPPayForm\Framework\Foundation\App;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


class Bootstrap extends IntegrationManager
{
    public function __construct()
    {
        parent::__construct(
            App::getInstance(),
            'ActiveCampaign',
            'activecampaign',
            '_wppayform_activecampaign_settings',
            'wppayform_activecampaign_feed',
            16
        );

        $this->logo = WPPAYFORM_URL . 'assets/images/integrations/activecampaign.png';

        $this->description = 'WPPayForm ActiveCampaign Module allows you to create ActiveCampaign list signup forms in WordPress, so you can grow your email list.';

        $this->registerAdminHooks();

        // add_filter('wppayform_notifying_async_activecampaign', '__return_false');
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('ActiveCampaign API Settings', 'wppayform'),
            'menu_description' => __('ActiveCampaign is an integrated email marketing, marketing automation, and small business CRM. Save time while growing your business with sales automation. Use WPPayForm to collect customer information and automatically add it to your ActiveCampaign list. If you don\'t have an ActiveCampaign account, you can <a href="https://www.activecampaign.com/" target="_blank">sign up for one here.</a>', 'wppayform'),
            'valid_message' => __('Your ActiveCampaign configuration is valid', 'wppayform'),
            'invalid_message' => __('Your ActiveCampaign configuration is invalid', 'wppayform'),
            'save_button_text' => __('Save Settings', 'wppayform'),
            'fields' => [
                'apiUrl' => [
                    'type' => 'text',
                    'placeholder' => 'API URL',
                    'label_tips' => __("Please Provide your ActiveCampaign API URL", 'wppayform'),
                    'label' => __('ActiveCampaign API URL', 'wppayform'),
                ],
                'apiKey' => [
                    'type' => 'password',
                    'placeholder' => 'API Key',
                    'label_tips' => __("Enter your ActiveCampaign API Key, if you do not have <br>Please login to your ActiveCampaign account and find the api key", 'wppayform'),
                    'label' => __('ActiveCampaign API Key', 'wppayform'),
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your ActiveCampaign API integration is up and running',
                'button_text' => 'Disconnect ActiveCampaign',
                'data' => [
                    'apiKey' => ''
                ],
                'show_verify' => true
            ]
        ];
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'apiKey' => '',
            'apiUrl' => '',
            'status' => ''
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        if (!$settings['apiKey']) {
            $integrationSettings = [
                'apiKey' => '',
                'apiUrl' => '',
                'status' => false
            ];
            // Update the reCaptcha details with siteKey & secretKey.
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_success([
                'message' => __('Your settings has been updated and discarted', 'wppayform'),
                'status' => false
            ], 200);
        }

        try {
            $settings['status'] = false;
            update_option($this->optionKey, $settings, 'no');
            $api = new ActiveCampaignApi($settings['apiUrl'], $settings['apiKey']);
            if ($api->authTest()) {
                $settings['status'] = true;
                update_option($this->optionKey, $settings, 'no');

                return wp_send_json_success([
                    'status' => true,
                    'message' => __('Your settings has been updated!', 'wppayform')
                ], 200);
            }
            throw new \Exception('Invalid Credentials', 400);
        } catch (\Exception $e) {
            wp_send_json_error([
                'status' => false,
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => '',
            'configure_message' => 'ActiveCampaign is not configured yet! Please configure your ActiveCampaign API first',
            'configure_button_text' => 'Set ActiveCampaign API'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'full_name' => '',
            'list_id' => '',
            'fieldEmailAddress' => '',
            'custom_field_mappings' => (object)[],
            'default_fields' => (object)[],
            'note' => '',
            'tags' => '',
            'tag_routers'            => [],
            'tag_ids_selection_type' => 'simple',
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'instant_responders' => false,
            'last_broadcast_campaign' => false,
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        $lists = $this->getLists();

        $data =  [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'ActiveCampaign List',
                    'placeholder' => 'Select ActiveCampaign Mailing List',
                    'tips' => 'Select the ActiveCampaign Mailing List you would like to add your contacts to.',
                    'component' => 'list_ajax_options',
                    'options' => $lists,
                ],
                [
                    'key' => 'custom_field_mappings',
                    'require_list' => true,
                    'label' => 'Map Fields',
                    'tips' => 'Select which WPPayForm fields pair with their<br /> respective ActiveCampaign fields.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'ActiveCampaign Field',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'fieldEmailAddress',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ]
                    ],
                    'default_fields' => [
                        array(
                            'name' => 'full_name',
                            'label' => esc_html__('Full Name', 'wppayform'),
                            'required' => false
                        ),
                        array(
                            'name' => 'phone',
                            'label' => esc_html__('Phone Number', 'wppayform'),
                            'required' => false
                        ),
                        array(
                            'name' => 'orgname',
                            'label' => esc_html__('Organization Name', 'wppayform'),
                            'required' => false
                        )
                    ]
                ],
                [
                    'key' => 'tags',
                    'require_list' => true,
                    'label' => 'Tags',
                    'tips' => 'Associate tags to your ActiveCampaign contacts with a comma separated list (e.g. new lead, WPPayForm, web source). Commas within a merge tag value will be created as a single tag.',
                    'component'    => 'selection_routing',
                    'simple_component' => 'value_text',
                    'routing_input_type' => 'text',
                    'routing_key'  => 'tag_ids_selection_type',
                    'settings_key' => 'tag_routers',
                    'labels'       => [
                        'choice_label'      => 'Enable Dynamic Tag Input',
                        'input_label'       => '',
                        'input_placeholder' => 'Tag'
                    ],
                    'inline_tip' => 'Please provide each tag by comma separated value, You can use dynamic smart codes'
                ],
                [
                    'key' => 'note',
                    'require_list' => true,
                    'label' => 'Note',
                    'tips' => 'You can write a note for this contact',
                    'component' => 'value_textarea'
                ],
                [
                    'key' => 'instant_responders',
                    'require_list' => true,
                    'label' => 'Instant Responders',
                    'tips' => 'When the instant responders option is enabled, ActiveCampaign will<br/>send any instant responders setup when the contact is added to the<br/>list. This option is not available to users on a free trial.',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable Instant Responder'
                ],
                [
                    'key' => 'last_broadcast_campaign',
                    'require_list' => true,
                    'label' => 'Last Broadcast Campaign',
                    'tips' => 'When the send the last broadcast campaign option is enabled,<br/>ActiveCampaign will send the last campaign sent out to the list<br/>to the contact being added. This option is not available to users<br/>on a free trial.',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable Send the last broadcast campaign'
                ],
                [
                    'require_list' => true,
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow ActiveCampaign integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => true,
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ]
            ],
            'button_require_list' => true,
            'integration_title' => $this->title
        ];

        return $data;
    }

    protected function getLists()
    {
        $api = $this->getApiClient();
        if (!$api) {
            return [];
        }

        $lists = $api->get_lists();

        if (is_wp_error($lists)) {
            wp_send_json_error([
                "message" => $lists->get_error_message()
            ], 422);
            return;
        }

        $formattedLists = [];
        foreach ($lists as $list) {
            if (is_array($list)) {
                $formattedLists[strval($list['id'])] = $list['name'];
            }
        }

        return $formattedLists;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        $fields = [];
        $api = $this->getApiClient();
        $response = $api->get_custom_fields();
        if (is_wp_error($response)) {
            return array(
                "message" => $response->get_error_message()
            );
        }
        if ($response['result_code']) {
            $fields = array_filter($response, function ($item) {
                return is_array($item);
            });
            $formattedFileds = [];
            foreach ($fields as $field) {
                $formattedFileds[$field['id']] = $field['title'];
            }
            return $formattedFileds;
        }
        return $fields;
    }

    /*
     * Submission Broadcast Handler
     */

    public function notify($feed, $formData, $entry, $formId)
    {
        $feedData = $feed['processedValues'];

        if (!is_email($feedData['fieldEmailAddress'])) {
            $feedData['fieldEmailAddress'] = Arr::get($formData, $feedData['fieldEmailAddress']);
        }

        if (!is_email($feedData['fieldEmailAddress'])) {
            $this->addLog('Active Campaign API call has been skipped because no valid email available', $formId, $entry->id, 'failed');
            return;
        }

        $contact = [];
        $fullName = Arr::get($feedData, 'default_fields.full_name');
        if ($fullName) {
            $nameArray = explode(' ', $fullName);
            if (count($nameArray) > 1) {
                $contact['last_name'] = array_pop($nameArray);
                $contact['first_name'] = implode(' ', $nameArray);
            } else {
                $contact['first_name'] = $fullName;
            }
        }

        $addData = [
            'email' => $feedData['fieldEmailAddress'],
            'first_name' => Arr::get($contact, 'first_name'),
            'last_name' => Arr::get($contact, 'last_name'),
            'phone' => Arr::get($feedData, 'default_fields.phone'),
            'orgname' => Arr::get($feedData, 'default_fields.orgname'),
        ];

        $tags = $this->getSelectedTagIds($feedData, $formData, 'tags');
        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }

        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);

        if ($tags) {
            $addData['tags'] = implode(',', $tags);
        }

        $list_id = $feedData['list_id'];
        $addData['p[' . $list_id . ']'] = $list_id;
        $addData['status[' . $list_id . ']'] = '1';

        foreach (Arr::get($feedData, 'custom_field_mappings', []) as $key => $value) {
            if (!$value) {
                continue;
            }
            $contact_key = 'field[' . $key . ',0]';
            $addData[$contact_key] = $value;
        }

        if (Arr::isTrue($feedData, 'instant_responders')) {
            $addData['instantresponders[' . $list_id . ']'] = 1;
        }

        if (Arr::isTrue($feedData, 'last_broadcast_campaign')) {
            $addData['lastmessage[' . $list_id . ']'] = 1;
        }

        $addData = array_filter($addData);

        $addData = apply_filters('wppayform_integration_data_'.$this->integrationKey, $addData, $feed, $entry);

        // Now let's prepare the data and push to hubspot
        $api = $this->getApiClient();
        $response = $api->sync_contact($addData);

        if (is_wp_error($response)) {
            $this->addLog($response->get_error_message(), $formId, $entry->id, 'failed');
            return false;
        } elseif ($response['result_code'] == 1) {
            $this->addLog('Active Campaign has been successfully initiated and pushed data', $formId, $entry->id);
            if (Arr::get($feedData, 'note')) {
                // Contact Added
                $api->add_note($response['subscriber_id'], $list_id, Arr::get($feedData, 'note'));
            }
            return true;
        }

        $this->addLog($response['result_message'], $formId, $entry->id);
    }

    protected function getApiClient()
    {
        $settings = get_option($this->optionKey);

        return new ActiveCampaignApi(
            $settings['apiUrl'],
            $settings['apiKey']
        );
    }
}
