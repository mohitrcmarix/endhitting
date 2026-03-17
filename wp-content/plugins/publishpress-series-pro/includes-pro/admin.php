<?php
  //include licence
  require_once (PPSERIES_PATH . 'includes-pro/classes/licence.php');
  PPSeries_License::get_instance();

  add_action('admin_init', 'ppseries_addons_settings_hooks' );
  function ppseries_addons_settings_hooks() {
		global $orgseries;
    //hook into organizeseries options page.

    add_settings_section('series_addon_settings', __('Pro Features', 'publishpress-series-pro'), 'ppseries_addon_section', 'orgseries_options_page');
    add_settings_field('orgseries_addons_settings', __('Enable Features', 'publishpress-series-pro'), 'ppseries_addons_settings_display', 'orgseries_options_page', 'series_addon_settings');
    add_settings_section('series_license_settings', __('License', 'publishpress-series-pro'), 'ppseries_license_section', 'orgseries_options_page');
    add_settings_field('orgseries_license_settings', __('Licence', 'publishpress-series-pro'), 'ppseries_license_settings_display', 'orgseries_options_page', 'series_license_settings');

    add_filter( 'orgseries_options', 'ppseries_addons_settings_validate', 10 , 3 );

    add_filter( 'ppseries_admin_settings_tabs', 'addon_admin_settings_tab');

		//make sure we have default values;
		if ( !isset($orgseries->settings['enabled_pro_addons']) && (int)get_option('org_series_default_addon') === 0 ) {
			$settings = get_option('org_series_options');
			$settings['enabled_pro_addons'] = ['cpt', 'shortcodes', 'extra-tokens'];
			update_option('org_series_options', $settings);
			update_option('org_series_default_addon', 1);
		}

  }

	function addon_admin_settings_tab($settings_tabs){
    $settings_tabs['series_addon_settings'] = __('Pro Features', 'publishpress-series-pro');
    $settings_tabs['series_license_settings'] = __('License', 'publishpress-series-pro');
		return $settings_tabs;
	}


	function ppseries_addon_section() {
		global $orgseries;
        ?>
        <p class="description"><?php _e('These settings allow you enable or disable features in PublishPress Series Pro.', 'publishpress-series-pro'); ?></p>
        <?php
	}

	function ppseries_license_section() {
		global $orgseries;
    ?>
    <p class="description"><?php _e('Your PublishPress license key provides access to updates and support.', 'publishpress-series-pro'); ?></p>
    <?php
	}

	function ppseries_license_settings_display() {
    do_action('ppseries_pro_licence_form');
	}

  function ppseries_addons_settings_display() {
  		global $orgseries;
  		$org_opt = $orgseries->settings;
  		$org_name = 'org_series_options';
  		$series_addons = [
        'cpt' => ['name' =>  __('Custom Post Type Support', 'publishpress-series-pro'), 'description' =>  __('Adds support for enabling series usage with custom post types.', 'publishpress-series-pro')],
        'shortcodes' => ['name' =>  __('Shortcodes', 'publishpress-series-pro'), 'description' =>  __('This feature enables the ability for users to easily add series information to posts (or pages) via the use of shortcodes', 'publishpress-series-pro')],
        'extra-tokens' => ['name' =>  __('Extra Tokens', 'publishpress-series-pro'), 'description' =>  __('This feature provides Extra %tokens% for customizing the auto-inserted output of series related information', 'publishpress-series-pro')],
        'multiples' => ['name' =>  __('Multiple Series', 'publishpress-series-pro'), 'description' =>  __('This feature gives ability for authors to add posts to more than one series.', 'publishpress-series-pro').' <br /><strong style="color: #1d2327;">'.__('Once you enable this feature IT IS NOT POSSIBLE to roll back to the PublishPress Series Free plugin, without having to re-edit all your series.', 'publishpress-series-pro').'</strong>']
      ];
  		$org_opt['enabled_pro_addons'] = isset($org_opt['enabled_pro_addons']) ? $org_opt['enabled_pro_addons'] : array();
  		?>
		  <table class="form-table ppseries-settings-table">
            <tbody>
  		<?php
  		foreach ( $series_addons as $series_addon => $series_addon_option ) {
  			$checked = in_array($series_addon, $org_opt['enabled_pro_addons']) ? 'checked="checked"' : '';
  			?>
              <tr valign="top">
            	<th scope="row">
                    <label for="ppseries-enable-<?php echo $series_addon; ?>">
                        <?php echo $series_addon_option['name']; ?>
                	</label>
            	</th>
            	<td>
                    <label>
                    <input type="checkbox" name="<?php echo $org_name; ?>[enabled_pro_addons][]" value="<?php echo $series_addon; ?>" id="ppseries-enable-<?php echo $series_addon; ?>" <?php echo $checked; ?> />
                        <span class="description"><?php echo $series_addon_option['description']; ?></span>
                	</label>
                </td>
        	</tr>
  			<?php
  		}
      ?>
	  </tbody>
        </table>

      <?php
  	}


  	/**
  	 * validate settings from series options page
  	 * @param  array $newinput new settings
  	 * @param  array $input    old settings
  	 * @return array           new settings
  	 */
  	 function ppseries_addons_settings_validate($newinput, $input) {
  		$newinput['enabled_pro_addons'] = isset($input['enabled_pro_addons']) ? $input['enabled_pro_addons'] : [];
  		return $newinput;
  	}

		add_action( 'admin_enqueue_scripts', 'ppseries_pro_admin_enqueue_scripts' );
  	function ppseries_pro_admin_enqueue_scripts() {
      wp_register_script( 'pps-pro-admin-js', PPSERIES_URL . 'includes-pro/assets/js/admin.js', array( 'jquery' ), ORG_SERIES_VERSION );
      wp_register_style('pps-pro-admin-css', PPSERIES_URL . 'includes-pro/assets/css/admin.css', [], ORG_SERIES_VERSION);
  	   if (is_ppseries_admin_pages()) {
           wp_enqueue_script( 'pps-pro-admin-js' );
  		     wp_enqueue_style( 'pps-pro-admin-css' );

            //localize script
            wp_localize_script('pps-pro-admin-js', 'ppseries_pro', array('ajaxurl' => admin_url('admin-ajax.php'), 'check_nonce' => wp_create_nonce('ppseries-pro-nonce')
                   ));
  	     }
  	  }
