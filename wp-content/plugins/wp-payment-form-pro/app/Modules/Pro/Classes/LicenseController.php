<?php

namespace WPPayForm\App\Modules\Pro\Classes;

use WPPayForm\App\Http\Controllers\Controller;
use WPPayForm\App\Modules\Pro\PluginManager\LicenseManager;

class LicenseController extends Controller
{
	protected $licenseManager;

	public function __construct()
	{
		parent::__construct();

		$this->licenseManager = new LicenseManager();
	}

	public function getStatus()
	{
		$this->licenseManager->verifyRemoteLicense(true);

		$data = $this->licenseManager->getLicenseDetails();

		$status = $data['status'];

		if ($status == 'expired') {
			$data['renew_url'] = $this->licenseManager->getRenewUrl($data['license_key']);
		}

		$data['purchase_url'] = $this->licenseManager->getVar('purchase_url');

		unset($data['license_key']);

		return $data;
	}

	public function saveLicense()
	{
		$licenseKey = $this->request->get('license_key');
		$response = $this->licenseManager->activateLicense($licenseKey);

		if (is_wp_error($response)) {
			return $this->sendError([
				'message' => $response->get_error_message()
			], 423);
		}

		return [
			'license_data' => $response,
			'message'      => __('Your license key has been successfully updated', 'wppayform')
		];
	}

	public function deactivateLicense()
	{
		$response = $this->licenseManager->deactivateLicense();

		if (is_wp_error($response)) {
			return $this->sendError([
				'message' => $response->get_error_message()
			], 423);
		}

		unset($response['license_key']);

		return [
			'license_data' => $response,
			'message'      => __('Your license key has been successfully deactivated', 'wppayform')
		];
	}
}
