<?php

namespace WPPayForm\App\Http\Controllers;

use WPPayForm\App\Models\Meta;
use WPPayForm\App\Modules\AddOnModules\AddOnModule;
use WPPayForm\App\Services\Integrations\GlobalIntegrationManager;

class IntegrationController extends Controller
{
    public function index()
    {
        return (new AddOnModule())->updateAddOnsStatus($this->request);
    }

    public function enable()
    {
        return GlobalIntegrationManager::migrate();
    }

    public function getIntegrations($formId)
    {
        return (new GlobalIntegrationManager)->getAllFormIntegrations($formId);
    }

    public function settings($formId)
    {
        return (new GlobalIntegrationManager)->getIntegrationSettings($formId, $this->request);
    }

    public function saveSettings($formId)
    {
        return (new GlobalIntegrationManager)->saveIntegrationSettings($formId, $this->request);
    }

    public function deleteSettings($formId)
    {
        return (new GlobalIntegrationManager)->deleteIntegrationFeed($formId, $this->request);
    }

    public function status($formId)
    {
        return (new GlobalIntegrationManager)->updateNotificationStatus($formId, $this->request);
    }

    public function lists($formId)
    {
        return (new GlobalIntegrationManager)->getIntegrationList($formId, $this->request);
    }

    public function getGlobalSettings()
    {
        return (new GlobalIntegrationManager)->getGlobalSettingsData($this->request);
    }

    public function setGlobalSettings()
    {
        return (new GlobalIntegrationManager)->saveGlobalSettingsData($this->request);
    }

    public function chained()
    {
        return (new GlobalIntegrationManager)->chainedData($this->request);
    }
}
