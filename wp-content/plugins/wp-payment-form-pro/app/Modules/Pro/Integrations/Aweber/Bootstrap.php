<?php
namespace WPPayForm\App\Modules\Pro\Integrations\Aweber;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\ConditionAssesor;
use WPPayForm\App\Services\Integrations\IntegrationManager;
use WPPayForm\Framework\Foundation\App;

class Bootstrap extends IntegrationManager
{
    public function __construct()
    {
        parent::__construct(
            App::getInstance(),
            'AWeber',
            'aweber',
            '_wppayform_aweber_settings',
            'wppayform_aweber_feed',
            16
        );

        $this->logo = WPPAYFORM_URL . 'assets/images/integrations/aweber.png';

        $this->description = 'WPPayForm Aweber Module allows you to create Aweber list signup forms in WordPress, so you can grow your email list.';

        $this->registerAdminHooks();

        // add_filter('wppayform_notifying_async_aweber', '__return_false');
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('AWeber API Settings', 'wppayform'),
            'menu_description' => __(
                'AWeber is an integrated email marketing, marketing automation, and small business CRM. Save time while growing your business with sales automation. Use WPPayForm to collect customer information and automatically add it to your Aweber list. If you don\'t have an Aweber account, you can <a href="https://www.aweber.com/" target="_blank">sign up for one here.</a>',
                'wppayform'
            ),
            'valid_message' => __('Your Aweber configuration is valid', 'wppayform'),
            'invalid_message' => __('Your Aweber configuration is invalid', 'wppayform'),
            'save_button_text' => __('Save Settings', 'wppayform'),
            'config_instruction' => $this->getConfigInstructions(),
            'fields' => [
                'authorizeCode' => [
                    'type' => 'password',
                    'placeholder' => __('Access token', 'wppayform'),
                    'label_tips' => __(
                        "Enter your Aweber Access token, if you do not have <br>Please click on the get Access token",
                        'wppayform'
                    ),
                    'label' => __('AWeber Access Token', 'wppayform'),
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => __('Your Aweber API integration is up and running', 'wppayform'),
                'button_text' => __('Disconnect Aweber', 'wppayform'),
                'data' => [
                    'authorizeCode' => ''
                ],
                'show_verify' => true
            ]
        ];
    }

    protected function getConfigInstructions()
    {
        ob_start(); ?>
        <div><h4>To Authenticate AWeber you need an access token.</h4>
            <ol>
                <li>Click here to <a
                            href="<?php echo $this->getAuthenticateUri(); ?>""
                    target="_blank">Get Access Token</a>.
                </li>
                <li>Then login and allow with your AWeber account.</li>
                <li>Copy your your access token and paste bellow field then click Verify AWeber.</li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    public function getAuthenticateUri()
    {
        $api = $this->getApiClient();
        return $api->makeAuthorizationUrl();
    }

    protected function getApiClient()
    {
        return new AweberApi(
            $this->optionKey
        );
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'authorizeCode' => '',
            'access_token' => '',
            'refresh_token' => '',
            'status' => '',
            'expires_in' => null
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        if (!$settings['authorizeCode']) {
            $integrationSettings = [
                'authorizeCode' => '',
                'access_token' => '',
                'refresh_token' => '',
                'status' => false,
                'expires_in' => null
            ];

            // Update the details with siteKey & secretKey.
            update_option($this->optionKey, $integrationSettings, 'no');

            wp_send_json_success([
                'message' => __('Your settings has been updated and discarded', 'wppayform'),
                'status' => false
            ], 200);
        }

        try {
            $settings['status'] = false;
            update_option($this->optionKey, $settings, 'no');
            $api = new AweberApi($this->optionKey);
            $auth = $api->generateAccessToken($settings);
            if (isset($auth['refresh_token'])) {
                $settings['status'] = true;
                $settings['access_token'] = $auth['access_token'];
                $settings['refresh_token'] = $auth['refresh_token'];
                $settings['expires_in'] = $auth['expires_in'];
                $settings['created_at'] = time();

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
            'configure_title' => __('Configuration required!', 'wppayform'),
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-aweber-settings'),
            'configure_message' => __('Aweber is not configured yet! Please configure your Aweber API first', 'wppayform') ,
            'configure_button_text' => __('Set Aweber API', 'wppayform')
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'fieldEmailAddress' => '',
            'merge_fields' => (object)[],
            'default_fields' => (object)[],
            'ip_address' => '{ip}',
            'tags' => '',
            'tag_routers' => [],
            'tag_ids_selection_type' => 'simple',
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }


    public function getSettingsFields($settings, $formId)
    {
        $lists = $this->getLists();
        $data = [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => __('Name', 'wppayform'),
                    'required' => true,
                    'placeholder' => __('Your Feed Name', 'wppayform'),
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => __('Aweber List', 'wppayform'),
                    'placeholder' => __('Select Aweber Segment', 'wppayform'),
                    'tips' => __('Select the Aweber segment you would like to add your contacts to.', 'wppayform'),
                    'component' => 'list_ajax_options',
                    'required' => true,
                    'options' => $lists
                ],
                [
                    'key' => 'merge_fields',
                    'require_list' => true,
                    'label' => __('Map Fields', 'wppayform'),
                    'tips' => __('Associate your Aweber merge tags to the appropriate WPPayForm fields by selecting the appropriate form field from the list.', 'wppayform'),
                    'component' => 'map_fields',
                    'field_label_remote' => __('Aweber Field', 'wppayform'),
                    'field_label_local' => __('Form Field', 'wppayform'),
                    'primary_fileds' => [
                        [
                            'key' => 'fieldEmailAddress',
                            'label' => __('Email Address', 'wppayform'),
                            'required' => true,
                            'input_options' => 'emails'
                        ],

                    ],
                    'default_fields' => [
                        [
                            'name' => 'full_name',
                            'label' => __('Name', 'wppayform'),
                            'required' => false
                        ],
                        [
                            'name' => 'ad_tracking',
                            'label' => __('Ad Tracking', 'wppayform'),
                            'required' => false,
                        ]
                    ]
                ],
                [
                    'key' => 'note',
                    'require_list' => true,
                    'label' => __('Note', 'wppayform'),
                    'placeholder' => 'write a note for this contact',
                    'tips' => 'You can write a note for this contact',
                    'component' => 'value_textarea'
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
                    'require_list' => true,
                    'key' => 'conditionals',
                    'label' => __('Conditional Logics', 'wppayform'),
                    'tips' => 'Allow Aweber integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => true,
                    'key' => 'enabled',
                    'label' => __('Status', 'wppayform'),
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Enable This feed', 'wppayform')
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
        $lists = $api->getLists();

        if (is_wp_error($lists)) {
            wp_send_json_error([
                "message" => $lists->get_error_message()
            ], 422);
            return;
        }

        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[$list['id']] = $list['name'];
        }
        return $formattedLists;
    }

    /*
     * Submission Broadcast Handler
     */

    public function getMergeFields($list, $listId, $formId)
    {
        $api = $this->getApiClient();
        $fields = $api->getCustomFields($listId);
        $formattedFileds = [];
        foreach ($fields as $field) {
            $formattedFileds[$field['name']] = $field['name'];
        }
        return $formattedFileds;
    }

    protected function getSelectedTagIds($data, $inputData, $simpleKey = 'tag_ids', $routingId = 'tag_ids_selection_type', $routersKey = 'tag_routers')
    {
        $routing = Arr::get($data, $routingId, 'simple');
        if (!$routing || $routing == 'simple') {
            return Arr::get($data, $simpleKey, []);
        }

        $routers = Arr::get($data, $routersKey);
        if (empty($routers)) {
            return [];
        }

        return $this->evaluateRoutings($routers, $inputData);
    }

    public function notify($feed, $formData, $entry, $formId)
    {
        $feedData = $feed['processedValues'];
        if (!is_email($feedData['fieldEmailAddress'])) {
            $feedData['fieldEmailAddress'] = Arr::get($formData, $feedData['fieldEmailAddress']);
        }
        if (!is_email($feedData['fieldEmailAddress'])) {
            do_action('wppayform_integration_action_result', $feed, 'failed', 'Aweber API call has been skipped because no valid email available');
            return;
        }


        $addData = [];
        $addData['email'] = $feedData['fieldEmailAddress'];

        $addData['name'] = Arr::get($feedData, 'default_fields.full_name');
        $addData['ad_tracking'] = Arr::get($feedData, 'default_fields.ad_tracking');
        if ($customFields = Arr::get($feedData, 'merge_fields')) {
            $addData['custom_fields'] = $customFields;
        }

        $tags = $this->getSelectedTagIds($feedData, $formData, 'tags');

        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }

        $tags = array_map('trim', $tags);
        $tags = array_filter($tags);

        if ($tags) {
            $addData['tags'] = $tags;
        }

        $listId = $feedData['list_id'];

        if ($entry->ip) {
            $addData['ip_address'] = $entry->ip;
        }
        $addData['misc_notes'] = Arr::get($feedData, 'note');
        $addData['last_followup_message_number_sent'] = apply_filters('wppayform_aweber_last_followup_message_number_sent', 0);
        $addData['strict_custom_fields'] = "false";
        $addData = array_filter($addData);

        // Now let's prepare the data and push to hubspot
        $api = $this->getApiClient();
        $response = $api->addContact($addData, $listId);

        if (!is_wp_error($response)) {
            $message = 'Aweber feed has been successfully initialed and pushed data';
            $this->addLog($message, $formId, $entry->id, 'success');
        } else {
            $error = is_wp_error($response) ? $response->get_error_messages() : 'Aweber API Error when submitting Data';
            if (is_array($error)) {
                $error = array_shift($error);
            }
            $this->addLog($error, $formId, $entry->id, 'failed');
        }
    }
}
