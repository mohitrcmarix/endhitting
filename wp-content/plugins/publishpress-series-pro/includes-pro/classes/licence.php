<?php

use PublishPress\EDD_License\Core\Container as EDDContainer;
use PublishPress\EDD_License\Core\Services as EDDServices;
use PublishPress\EDD_License\Core\ServicesConfig as EDDServicesConfig;

class PPSeries_License
{

    // class instance
    static $instance;

    /**
     * @var Container
     */
    private $edd_container;

    /**
     * Constructor
     *
     * @return void
     * @author Olatechpro
     */
    public function __construct()
    {
        // Admin menu
        add_action('ppseries_pro_licence_form', [$this, 'page_ppseries_licence']);

        add_action('wp_ajax_ppseries_pro_activate_license_by_ajax', array($this, 'process_licence_save'));

        $this->init_edd_connector();
    }

    /** Singleton instance */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init_edd_connector()
    {
        $config = new EDDServicesConfig();
        $config->setApiUrl(PP_SERIES_EDD_STORE_URL);
        $config->setLicenseKey($this->get_license_key());
        $config->setLicenseStatus($this->get_license_status());
        $config->setPluginVersion(SERIES_PRO_VERSION);
        $config->setEddItemId(PP_SERIES_PRO_EDD_ITEM_ID);
        $config->setPluginAuthor(PP_SERIES_PLUGIN_AUTHOR);
        $config->setPluginFile(PP_SERIES_PLUGIN_FILE);

        $this->edd_container = new EDDContainer();
        $this->edd_container->register(new EDDServices($config));

        // Instantiate the update manager
        $this->edd_container['update_manager'];
    }

    private function get_license_key()
    {
        return get_option('ppseries_license_key');
    }

    private function get_license_status()
    {
        $status = get_option('ppseries_license_status');

        return ($status !== false && $status == 'valid') ? 'active' : 'inactive';
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Olatechpro
     */
    public function page_ppseries_licence()
    {
        echo '<div class="taxopress-licence-form-wrap">';

        $license = $this->get_license_key();
        $status  = $this->get_license_status();
        ?>


				<table class="form-table" role="presentation">
						<tbody>
						<tr>
								<th scope="row"><?php
										_e('License key:', 'publishpress-series-pro'); ?></th>
								<td><label for="ppseries_licence_key_input">
												<input type="text" id="ppseries_licence_key_input" name="ppseries_licence_key_input"
															 value="<?php
															 esc_attr_e($license); ?>">
												<div class="ppseries_licence_key_status <?php
												echo $status; ?>"><span class="ppseries_licence_key_label"><?php
																_e('Status', 'publishpress-series-pro'); ?>: </span><?php
														echo ucwords($status); ?></div>
												<p class="ppseries_settings_field_description"><?php
														_e('Your license key provides access to updates and support.', 'publishpress-series-pro'); ?></p>
										</label>
								</td>
						</tr>


                        <tr valign="top">
										<th scope="row" valign="top">
												<?php
												_e('Activate License', 'publishpress-series-pro'); ?>
										</th>

						<?php
						if (false !== $license) { ?>
								
										<td>
												<?php
												if ($status !== false && $status == 'active') { ?>
														<?php
														wp_nonce_field('ppseries_submit_licence', 'ppseries_nonce'); ?>
														<input type="submit" class="button-secondary ppseries-activate-license" name="edd_license_deactivate" value="<?php
														_e('Deactivate License', 'publishpress-series-pro'); ?>" data-action="edd_license_deactivate"/>
                            <span class="spinner ppseries-spinner"></span>
														<?php
												} else {
														wp_nonce_field('ppseries_submit_licence', 'ppseries_nonce'); ?>
														<input type="submit" class="button-secondary ppseries-activate-license" name="edd_license_activate" value="<?php
														_e('Activate License', 'publishpress-series-pro'); ?>" data-action="edd_license_activate"/>
                            <span class="spinner ppseries-spinner"></span>
														<?php
												} ?>
										</td>
								<?php
						} else { 
                                    echo '<td>';
														wp_nonce_field('ppseries_submit_licence', 'ppseries_nonce'); ?>
														<input type="submit" class="button-secondary ppseries-activate-license" name="edd_license_activate" value="<?php
														_e('Activate License', 'publishpress-series-pro'); ?>" data-action="edd_license_activate"/>
                            <span class="spinner ppseries-spinner"></span>
                        </td>


														<?php  } ?>
								</tr>

						</tbody>
				</table>

				<?php
				wp_nonce_field('ppseries_submit_licence', 'ppseries_nonce'); ?>

		</div>

        <?php
    }

    public function process_licence_save()
    {

        // run a quick security check
        check_ajax_referer('ppseries-pro-nonce', 'security');

        //instantiate response default value
        $response['status'] = 'error';
        $response['content'] = '';

        $licence_key_save = isset($_POST['licence_key']) ? sanitize_text_field($_POST['licence_key']) : '';
        $licence_action = isset($_POST['licence_action']) ? sanitize_text_field($_POST['licence_action']) : '';
        $security = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';

        if ($security) {
            update_option('ppseries_license_key', $licence_key_save);
            //activate
            $status = $this->activate_licence_key($licence_key_save);
            update_option('ppseries_license_status', $status);
        }

        if ($licence_action === 'edd_license_activate') {
            update_option('ppseries_license_key', $licence_key_save);
            //activate
            $status = $this->activate_licence_key($licence_key_save);
            update_option('ppseries_license_status', $status);
        }elseif ($licence_action === 'edd_license_deactivate') {
            update_option('ppseries_license_key', $licence_key_save);
            //deactivate
            $status = $this->deactivate_licence_key($licence_key_save);
            update_option('ppseries_license_status', 'deactivated');
        }

        $response['status'] = 'success';

        wp_send_json($response);

    }

    public function activate_licence_key($licence_key)
    {
        $licence_key = trim($licence_key);

        if (!empty($licence_key)) {
            $license_manager = $this->edd_container['license_manager'];

            return $license_manager->validate_license_key($licence_key, PP_SERIES_PRO_EDD_ITEM_ID);
        }
    }

    public function deactivate_licence_key($licence_key)
    {
        $licence_key = trim($licence_key);

        if (!empty($licence_key)) {
            $license_manager = $this->edd_container['license_manager'];

            return $license_manager->deactivate_license_key($licence_key, PP_SERIES_PRO_EDD_ITEM_ID);
        }
    }
}
