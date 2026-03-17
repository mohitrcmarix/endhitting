<?php
/*
 * PublishPress Series
 *
 * Functions with broad scope and available for all URLs (including front end), which are not contained within a class
 *
 */
if (!function_exists('pp_series_pro_addon_plugins')) {
    function pp_series_pro_addon_plugins()
    {
        $addons = [
            //cpt support addon
            'organize-series-cpt-support-master/os-cpt-setup.php',
            'organize-series-cpt-support/os-cpt-setup.php',
            //shortcodes support addon
            'organize-series-shortcodes-master/organize-series-shortcodes.php',
            'organize-series-shortcodes/organize-series-shortcodes.php',
            //extra-tokens support addon
            'organize-series-extra-tokens-master/organize-series-extra-tokens.php',
            'organize-series-extra-tokens/organize-series-extra-tokens.php',
            //publisher support addon
            'organize-series-publisher-master/series_issue_manager.php',
            'organize-series-publisher/series_issue_manager.php',
            //grouping support addon
            'organize-series-grouping-master/organize-series-grouping.php',
            'organize-series-grouping/organize-series-grouping.php',
            //multiples support addon
            'organize-series-multiples-master/organize-series-multiples.php',
            'organize-series-multiples/organize-series-multiples.php',
        ];

        return apply_filters('pp_series_pro_addon_plugins', $addons);
    }
}

if (!function_exists('pp_series_deactivate_addon_plugins')) {
//deactivate addons plugin if exist
    function pp_series_deactivate_addon_plugins()
    {
        $addon_plugins = (array)pp_series_pro_addon_plugins();
        $addon_plugins = array_filter($addon_plugins);
        if (count($addon_plugins) > 0) {
            foreach ($addon_plugins as $addon_plugin) {
                if (is_plugin_active($addon_plugin)) {
                    deactivate_plugins($addon_plugin);
                }
            }

        }
    }
}


if (!function_exists('pp_series_pro_meta_init')) {
    function pp_series_pro_meta_init()
    {
  		global $orgseries;

      $enabled_pro_addons = [];
      if( is_object($orgseries) && isset($orgseries->settings)){
        if ( !isset($orgseries->settings['enabled_pro_addons']) && (int)get_option('org_series_default_addon') === 0 ) {
          $enabled_pro_addons = ['cpt', 'shortcodes', 'extra-tokens', 'publisher', 'grouping'];
        }else{
          if(isset($orgseries->settings['enabled_pro_addons'])){
            $enabled_pro_addons = (array) $orgseries->settings['enabled_pro_addons'];
          }
        }
      }

      $addon_supports = [
        'cpt' => 'cpt-support/os-cpt-setup.php',
        'shortcodes' => 'shortcodes/organize-series-shortcodes.php',
        'extra-tokens' => 'extra-tokens/organize-series-extra-tokens.php',
        'multiples' => 'multiples/organize-series-multiples.php',
      ];

      foreach($addon_supports as $name => $path){
        if(in_array($name, $enabled_pro_addons)){
          require PPSERIES_PATH . 'includes-pro/addons/'.$path;
        }
      }

    }
}


if (!function_exists('pp_series_pro_activation')) {
    //activation functions/codes
    function pp_series_pro_activation()
    {

      /*Puplishpress Series Publisher*/
      // if option records don't already exist, create them
      if ( !get_option( 'im_published_series' ) ) {
        add_option( 'im_published_series', array() );
      }
      if ( !get_option( 'im_unpublished_series' ) ) {
        add_option( 'im_unpublished_series', array() );
      }

        
      pp_series_upgrade_function();

    }
}

if (!function_exists('pp_series_pro_deactivation')) {
    //deactivation functions/codes
    function pp_series_pro_deactivation()
    {
      /*PuplishPress Series Publisher*/
      // they don't have to exist to be deleted
      delete_option( 'im_published_series' );
      delete_option( 'im_unpublished_series' );
    }
}
