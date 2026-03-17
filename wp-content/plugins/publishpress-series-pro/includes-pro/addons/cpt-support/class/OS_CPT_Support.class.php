<?php
if (!defined('OS_CPT_VER') )
	exit('NO direct script access allowed');

/**
 * Publishpress Series Custom Post Type Support
 *
 * This is an addon for the Publishpress Series plugin
 *
 * @package		Publishpress Series Custom Post Type Support
 * @author		Darren Ethier
 * @copyright	(c)2009-2012 Rough Smooth Engine All Rights Reserved.
 * @license		http://roughsmootheng.in/license-gplv3.htm  * *
 * @link		http://organizeseries.com
 * @version		0.1
 *
 * ------------------------------------------------------------------------
 *
 * OS_CPT_Support
 *
 * Main class that initializes and sets up the addon
 *
 * @package		Publishpress Series Custom Post Type Support
 * @subpackage	/class/
 * @author		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */

class OS_CPT_Support {

	private $_settings;
	private $_version = OS_CPT_VER;
	private $_os_installed = false;
	private $_os_multi_installed = false;

	/**
	 * constructor
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->_init();
		$this->_hooks();
	}

	/**
	 * This initializes the addon and contains things that must be run before anything else gets setup.
	 * @return void
	 */
	private function _init() {
		add_action('activate_organize-series-cpt-support/os-cpt-setup.php', array($this, 'install'));
	}

	/**
	 * this checks and makes sure that the initial settings have been set for custom post types (otherwise cpt could break orgseries!)
	 * @access private
	 * @return void
	 */
	private function _do_settings_check() {
		global $orgseries;
		//make sure we have default post_type support added (i.e. posts);
		if ( !isset($orgseries->settings['post_types_for_series'] ) ) {
			$settings = get_option('org_series_options');
			$settings['post_types_for_series'] = array('post');
			update_option('org_series_options', $settings);
		}
	}

	/**
	 * this runs anything happenign during the install of the plugin
	 * @return void
	 */
	public function install() {
		update_option('os_cpt_support_version', $this->_version);
	}

	/**
	 * contains wordpress hooks that need to be added when this plugin is loaded.
	 * @access private
	 * @return void
	 */
	private function _hooks() {
		add_action('admin_init', array($this, 'admin_init_hooks') );
		add_action('plugins_loaded', array($this, 'plugins_loaded_hooks'), 20 );
		add_action('init', array($this, 'main_init' ) );
	}

	/**
	 * all the hooks that need to run in wp's admin_init
	 * @return void
	 */
	public function admin_init_hooks() {
		//hook into organizeseries options page.

		add_settings_section('series_cpt_settings', __('Post Types', 'publishpress-series-pro'), [$this, 'ppseries_cpt_section'], 'orgseries_options_page');

		add_settings_field('orgseries_cpt_settings', __('Custom Post Type Support', 'publishpress-series-pro'), array(&$this, 'settings_display'), 'orgseries_options_page', 'series_cpt_settings');
		add_filter( 'orgseries_options', array(&$this, 'settings_validate'), 10 , 3 );

		add_filter( 'ppseries_admin_settings_tabs', array(&$this, 'cpt_admin_settings_tab'));

		$this->_do_settings_check();
	}

	public function ppseries_cpt_section() {
		global $orgseries;
        ?>
        <p class="description"><?php _e('Enable PublishPress Series for custom post types.', 'publishpress-series-pro'); ?></p>
        <?php
	}

	public function cpt_admin_settings_tab($settings_tabs){

		$new_settings_tabs = [];
		foreach($settings_tabs as $settings_tab_key => $settings_tab_label){
				$new_settings_tabs[$settings_tab_key] = $settings_tab_label;
			if($settings_tab_key === 'series_automation_settings'){
					$new_settings_tabs['series_cpt_settings'] = __('Post Types', 'publishpress-series-pro');
			}
		}
		return $new_settings_tabs;
	}

	/**
	 * all the hooks/code that needs to run in wp plugins_loaded hook
	 * @return void
	 */
	public function plugins_loaded_hooks() {
		add_filter( 'orgseries_posttype_support', array($this, 'add_post_type_support') );
	}

	/**
	 * all the hooks/code that needs to run in wp's main init hook
	 * @access public
	 * @return void
	 */
	public function main_init() {
		//nothing yet
		return;
	}

	/**
	 * hook into orgseries_posttype_support and provides the new post_types.
	 */
	public function add_post_type_support($post_types) {
		global $orgseries;
		$post_types = isset($orgseries->settings['post_types_for_series'])
            ? $orgseries->settings['post_types_for_series']
            : array('post');
		return $post_types;
	}

	/**
	 * validate cpt settings from series options page
	 * @param  array $newinput new settings
	 * @param  array $input    old settings
	 * @return array           new settings
	 */
	public function settings_validate($newinput, $input) {
		$newinput['post_types_for_series'] = isset($input['post_types_for_series']) ? $input['post_types_for_series'] : [];
		return $newinput;
	}

	/**
	 * html for the settings fields used to set the custom post types that Publishpress Series works with.
	 * @return [type] [description]
	 */
	public function settings_display() {
		global $orgseries;
		$org_opt = $orgseries->settings;
		$org_name = 'org_series_options';
		$post_types = get_post_types(array( 'show_ui' => true ));
		$org_opt['post_types_for_series'] = isset($org_opt['post_types_for_series']) ? $org_opt['post_types_for_series'] : array('post');

		$excluded_post_type = ['series_group', 'wp_block', 'attachment'];
		?>

          <table class="form-table ppseries-settings-table">
            <tbody>
              <tr valign="top">
              <td>
                  <table><tbody>
                      <?php
                      foreach ( $post_types as $post_type ) {
			            if (in_array($post_type, $excluded_post_type)){
				            continue;
                        }
			            $checked = in_array($post_type, $org_opt['post_types_for_series']) ? 'checked="checked"' : '';
			            ?>
                        <tr valign="top">
                        <th scope="row"><label for="<?php echo $post_type; ?>"><?php echo $post_type; ?></label>
                        </th>
                        <td>
                            <label>
                            <input id="<?php echo $post_type; ?>" type="checkbox" name="<?php echo $org_name; ?>[post_types_for_series][]" value="<?php echo $post_type; ?>" <?php echo $checked; ?> />
                            </label>
                        </td>
                        </tr>
			            <?php
		                }
                        ?>
                      </tbody></table>
									</td>

            </tbody>
        </table>

		<?php
	}
} //end OS_CPT_Support class
