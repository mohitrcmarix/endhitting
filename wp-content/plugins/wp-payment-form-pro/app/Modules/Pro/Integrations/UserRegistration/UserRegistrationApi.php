<?php
namespace WPPayForm\App\Modules\Pro\Integrations\UserRegistration;

if (!defined('ABSPATH')) {
    exit;
}

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Meta;

class UserRegistrationApi
{
    public function getUserRoles()
    {
        if (! function_exists('get_editable_roles')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $roles = get_editable_roles();

        $validRoles = [];
        foreach ($roles as $roleKey => $role) {
            if (!Arr::get($role, 'capabilities.manage_options')) {
                $validRoles[$roleKey] = $role['name'];
            }
        }
        return apply_filters('wppayform_UserRegistration_creatable_roles', $validRoles);
    }

    public function validate($settings, $settingsFields)
    {
        foreach ($settingsFields['fields'] as $field) {
            if ($field['key'] != 'CustomFields') {
                continue;
            }

            $errors = [];

            foreach ($field['primary_fileds'] as $primaryField) {
                if (!empty($primaryField['required'])) {
                    if (empty($settings[$primaryField['key']])) {
                        $errors[$primaryField['key']] = $primaryField['label'] . ' is required.';
                    }
                }
            }

            if ($errors) {
                wp_send_json_error([
                    'message' => array_shift($errors),
                    'errors' => $errors
                ], 422);
            }
        }

        return $settings;
    }

    public function registerUser($feed, $formData, $entry, $formId, $integrationKey)
    {
        if (get_current_user_id()) {
            return;
        }

        $parsedValue = $feed['processedValues'];

        if (!is_email($parsedValue['Email'])) {
            $parsedValue['Email'] = Arr::get(
                $formData,
                $parsedValue['Email']
            );
        }

        if (!is_email($parsedValue['Email'])) {
            $this->addLog(
                "User not created, Email address is required!",
                $formId,
                $entry->id,
                'failed'
            );
            return;
        }

        if (email_exists($parsedValue['Email'])) {
            $this->addLog(
                "User not created Email(" . $parsedValue['Email'] . ") already exist",
                $formId,
                $entry->id,
                'failed'
            );
            return;
        }

        if (!empty($parsedValue['username'])) {
            $userName = Arr::get($formData, $parsedValue['username']);
            if (is_array($userName)) {
                return;
            }
            if ($userName && username_exists($userName)) {
                return;
            }
            if ($userName) {
                $parsedValue['username'] = $userName;
            }
        }

        if (empty($parsedValue['username'])) {
            $parsedValue['username'] = $parsedValue['Email'];
        }

        $feed['processedValues'] = $parsedValue;

        $fullName = Arr::get($parsedValue, 'full_name');
        if ($fullName) {
            $nameArray = explode(' ', $fullName);
            if (count($nameArray) > 1) {
                $feed['processedValues']['last_name'] = array_pop($nameArray);
                $feed['processedValues']['first_name'] = implode(' ', $nameArray);
            } else {
                $feed['processedValues']['first_name'] = $fullName;
            }
        }

        do_action('wppayform_user_registration_before_start', $feed, $entry, $formId);

        $this->createUser($feed, $formData, $entry, $formId, $integrationKey);
    }

    protected function createUser($feed, $formData, $entry, $formId, $integrationKey)
    {
        $feed = apply_filters('wppayform_user_registration_feed', $feed, $entry, $formId);

        $parsedData = $feed['processedValues'];

        $email = $parsedData['Email'];
        $userName = $parsedData['username'];

        if (empty($parsedData['password'])) {
            $password = wp_generate_password(8);
        } else {
            $password = $parsedData['password'];
        }
        $userId = wp_create_user($userName, $password, $email);

        if (is_wp_error($userId)) {
            return $this->addLog(
                $userId->get_error_message(),
                $formId,
                $entry->id,
                'failed'
            );
        }

        do_action('wppayform_created_user', $userId, $feed, $entry, $formId);
        (new Meta())->updateOrderMeta('formSettings', $entry->id, '__created_user_id', '', $formId);

        $this->updateUser($parsedData, $userId);

        $this->addUserRole($parsedData, $userId);

        $this->addUserMeta($parsedData, $userId, $formId);

        $this->maybeLogin($parsedData, $userId, $entry);

        $this->maybeSendEmail($parsedData, $userId);

        do_action('wppayform_user_registration_completed', $userId, $feed, $entry, $formId);

        $this->addLog(
            'user has been successfully created. Created User ID: ' . $userId,
            $formId,
            $entry->id,
            'success'
        );

        Submission::where('id', $entry->id)
            ->update([
                'user_id' => $userId
            ]);
    }

    protected function updateUser($parsedData, $userId)
    {
        $name = trim(Arr::get($parsedData, 'first_name'). ' ' . Arr::get($parsedData, 'last_name'));

        $data = array_filter([
            'ID' => $userId,
            'user_nicename' => $name,
            'display_name' => $name,
            'user_url' => Arr::get($parsedData, 'user_url')
        ]);

        if ($name) {
            wp_update_user($data);
        }
    }

    protected function addUserRole($parsedData, $userId)
    {
        $userRoles = $this->getUserRoles();
        $assignedRole = $parsedData['userRole'];

        if (!isset($userRoles[$assignedRole])) {
            $assignedRole = 'subscriber';
        }

        $user = new \WP_User($userId);
        $user->set_role($assignedRole);
    }

    protected function addUserMeta($parsedData, $userId, $formId)
    {
        foreach ($parsedData['userMeta'] as $userMeta) {
            $userMetas[$userMeta['label']] = $userMeta['item_value'];
        }

        $userMetas = array_merge($userMetas, [
            'first_name' => Arr::get($parsedData, 'first_name'),
            'last_name' => Arr::get($parsedData, 'last_name')
        ]);

        if (!isset($userMetas['nickname'])) {
            $userMetas['nickname'] = Arr::get($parsedData, 'first_name') . ' ' . Arr::get($parsedData, 'last_name');
        }

        foreach ($userMetas as $metaKey => $metaValue) {
            if (trim($metaValue)) {
                update_user_meta($userId, $metaKey, trim($metaValue));
            }
        }

        update_user_meta($userId, 'wppayform_user_id', $formId);
    }

    protected function maybeLogin($parsedData, $userId, $entry = false)
    {
        if (Arr::isTrue($parsedData, 'enableAutoLogin')) {
            // check if it's payment success page
            // or direct url
            if (isset($_REQUEST['wppayform_payment_api_notify']) && $entry) {
                // This payment IPN request so let's keep a reference for real request
                (new Meta())->updateOrderMeta('formSettings', $entry->id, '_make_auto_login', $value, $entry->form_id);
                return;
            }

            wp_clear_auth_cookie();
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId);
        }
    }

    protected function maybeSendEmail($parsedData, $userId)
    {
        if (Arr::isTrue($parsedData, 'sendEmailToNewUser')) {
            // This will send an email with password setup link
            \wp_new_user_notification($userId, null, 'user');
        }
    }

    protected function addLog($content, $formId, $entryId, $type = 'activity')
    {
        do_action('wppayform_log_data', [
            'form_id' => $formId,
            'submission_id' => $entryId,
            'type' => $type,
            'created_by' => 'PayForm BOT',
            'content' => $content
        ]);
    }
}
