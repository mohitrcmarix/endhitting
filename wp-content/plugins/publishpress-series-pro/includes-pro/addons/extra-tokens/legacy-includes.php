<?php
//developers do not rely on this constant in your code.  It is temporary while I still support the legacy setup.
if (! defined('OS_ET_LEGACY_LOADED')) {
define('OS_ET_LEGACY_LOADED', 'true');
}


//* ALL HOOKS AND FILTERS HERE */

//all inits

add_filter('post_orgseries_token_replace', 'orgseries_extra_tokens',10,5);
add_action('orgseries_token_description', 'orgseries_extra_tokens_description');
//A hook for adding new template field to Series Options page
add_action('plist_ptitle_template_unpublished', 'orgseries_extra_unpub_tfield');
add_filter('orgseries_options', 'valid_unpub_post_template', 10, 2);
add_filter('unpublished_post_template', 'unpub_token_replace', 10, 3);
//add_action('plugins_loaded', 'orgseries_extra_tokens_load_defaults', 5);
add_action( 'wp_enqueue_scripts', 'orgSeries_extra_style');
add_action('admin_init', 'orgseries_extra_tokens_upgrade');


function orgseries_extra_tokens_upgrade(){
  
    if (!get_option('pp_series_extra_tokens_2_8_0_upgraded')) {
        $settings = get_option('org_series_options');
        $settings = apply_filters('org_series_settings', $settings);
        //add new series settings only if not fresh installation
        if ($settings) {
            if(!isset($settings['series_post_list_unpublished_post_template']) || empty($settings['series_post_list_unpublished_post_template'])){
                $settings['series_post_list_unpublished_post_template'] = '<li class="serieslist-li-unpub">%unpublished_post_title%</li>';
                update_option('org_series_options', $settings);
            }
        }
        update_option('pp_series_extra_tokens_2_8_0_upgraded', true);
  }

}

function orgseries_extra_tokens_load_defaults() {
    add_filter('org_series_settings', 'orgseries_extra_tokens_settings_defaults');
}

function orgseries_extra_tokens_settings_defaults($settings) {
    $settings['series_post_list_unpublished_post_template'] = '<li class="serieslist-li-unpub">%unpublished_post_title%</li>';
    return $settings;
}

function orgSeries_extra_style() {
    $settings = get_option('org_series_options');
    if ($settings['custom_css']) {
        wp_register_style('extra-style', plugins_url('orgSeries-extra.css', __FILE__));
        wp_enqueue_style('extra-style');
    }
}

function orgseries_extra_tokens($replace, $referral, $id, $p_id,  $ser_ID) {
    if ( is_array($ser_ID) ) $ser_ID = $ser_ID[0];
    if( stristr($replace, '%series_slug%') )
        $replace = str_replace('%series_slug%', get_series_name($ser_ID, true), $replace);
    if( stristr($replace, '%series_id%') )
        $replace = str_replace('%series_id%', $ser_ID, $replace);
    if( stristr($replace, '%post_author%') )
        $replace = str_replace('%post_author%', token_extra_author($p_id), $replace);
    if( stristr($replace, '%post_thumbnail%') )
        $replace = str_replace('%post_thumbnail%', token_get_thumbnail($p_id), $replace);
    if( stristr($replace, '%post_date%') )
        $replace = str_replace('%post_date%', token_post_date($id), $replace);
    if( stristr($replace, '%unpublished_post_title%') )
        $replace = str_replace('%unpublished_post_title%', series_unpub_post_title($id), $replace);
    if( stristr($replace, '%total_posts_in_series_with_unpub%') )
        $replace = str_replace('%total_posts_in_series_with_unpub%', wp_unpublished_postlist_count($ser_ID), $replace);
    return $replace;
}

function orgseries_extra_tokens_description() {
    ?>
    <br /><br />

    <strong>%series_slug%</strong><br />
    <em><?php _e('Will display the slug for the series', 'publishpress-series-pro'); ?></em><br /><br />

    <strong>%series_id%</strong><br />
    <em><?php _e('Will display the series id of the series', 'publishpress-series-pro'); ?></em><br /><br />

    <strong>%post_author%</strong><br />
    <em><?php _e('Will display the post author of the post in the series', 'publishpress-series-pro'); ?></em><br /><br />

    <strong>%post_thumbnail%</strong><br />
    <em><?php _e('Will display the post thumbnail of a post belonging to the series', 'publishpress-series-pro'); ?></em><br /><br />

    <strong>%post_date%</strong><br />
    <em><?php _e('Will display the published date of a post within a series', 'publishpress-series-pro'); ?></em><br /><br />

    <strong>%unpublished_post_title%</strong><br />
    <em><?php _e('Will be replaced with the unpublished post title of a post in the series', 'organize-series'); ?></em><br /><br />

    <strong>%total_posts_in_series_with_unpub%</strong><br />
    <em><?php _e('Will display the total number of published and unpublished posts in a series', 'organize-series'); ?></em><br /><br />
    <?php
}

function token_extra_author($p_id) {
    $postdata = get_post($p_id);
    $author_id = $postdata->post_author;
    $authorname = get_the_author_meta('nickname', $author_id);
    return $authorname;
}

function token_get_thumbnail($p_id) {
    $thumbnail = get_the_post_thumbnail($p_id);
    return $thumbnail;
}

function token_post_date($p_id) {
    $post_date = get_the_date($p_id);
    return $post_date;
}

function orgseries_extra_unpub_tfield() {
    global $orgseries;
    $org_opt = $orgseries->settings;
    $org_name = 'org_series_options';
    ?>
    <tr valign="top"><th scope="row"><label for="series_post_list_unpublished_post_template"><?php _e('Series Post List Post Title(Unpublished)', 'organize-series'); ?></label></th>
        <td><input type="text" name="<?php echo $org_name; ?>[series_post_list_unpublished_post_template]" id="series_post_list_unpublished_post_template" value="<?php echo isset($org_opt['series_post_list_unpublished_post_template']) ?  esc_attr(htmlspecialchars($org_opt['series_post_list_unpublished_post_template'])) : ''; ?>" class="ppseries-full-width">
        </td>
    </tr>
    <?php
}

function valid_unpub_post_template($newinput, $input) {
    $newinput['series_post_list_unpublished_post_template'] = trim(stripslashes($input['series_post_list_unpublished_post_template']));
    return $newinput;
}

function unpub_token_replace($settings, $seriespost = 0, $ser = 0) {
    if($seriespost == 0)
        return FALSE;
    else {
        $result = token_replace(stripslashes($settings['series_post_list_unpublished_post_template']), 'other', $seriespost['id'], $ser);
        return $result;
    }
}

function series_unpub_post_title($post_ID) {
    global $post;
    if (!isset($post_ID))
        $post_ID = (int)$post->ID;
    $title = get_the_title($post_ID);
    $return = $title.' ('.get_post_status($post_ID).')';
    return $return;
}

function wp_unpublished_postlist_count($ser_id) {
    $series = get_objects_in_term($ser_id, ppseries_get_series_slug());
    if (!empty($series)) {
        $postlist_count = count($series);
    } else {
        $postlist_count = 0;
    }
    return $postlist_count;
}
