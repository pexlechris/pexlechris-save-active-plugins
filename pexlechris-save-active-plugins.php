<?php
/**
 * Plugin Name: Save Active Plugins
 * Description: Save active plugins to Favorite Plugins for debugging purposes.
 * Version: 1.0
 * Author: Pexle Chris
 * Author URI: https://www.pexlechris.dev
 * Contributors: pexlechris
 * Requires at least: 4.7
 * Tested up to: 6.7.1
 * Requires PHP: 7.0
 * Tested up to PHP: 8.2
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) die;


define('PEXLECHRIS_SAVE_PLUGINS_CAP', 'administrator');




add_action('admin_bar_menu', function ($wp_admin_bar) {

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return;

	$saved_plugins = get_option('pexlechris_saved_plugins', []);


	$query = $_GET;
	$query['pexlepage'] = 'save_plugins';
	$query_result = http_build_query($query);
	if( '' != $query_result) $query_result = '?' . $query_result;
	$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . $query_result;


	$admin_item_css = 'border: 1px solid white !important; height: 20px !important; display: flex !important ; align-items: center !important; position: relative !important; top: 5px !important; color: white !important; margin: 0 10px !important; border-radius: 2px !important;';

	if($saved_plugins){
		$admin_item_css .= 'background: #3264a8 !important; border-color: #3264a8 !important;';

		$onclick  = 'var result = confirm("Are you sure you want to reset Saved Plugins?");';
		$onclick .= 'if (result != true){ return false; }';
	}

	$args = array(
		"id" => 'save_plugins',
		"title" => $saved_plugins ? 'Reset Fav Plugins' : 'Save Fav Plugins',
		"href" => $url,
		"meta" => array(
			'html'	  => '<style>#wp-admin-bar-save_plugins a{'. $admin_item_css .'</style>',
			'onclick' => $onclick ?? '',
		)
	);
	$wp_admin_bar->add_node($args);


}, 999);

add_action('wp_loaded', function(){

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return;

	$pexlepage = $_GET['pexlepage'] ?? '';
	if( $pexlepage != 'save_plugins' ) return;

	if( get_option('pexlechris_saved_plugins', null) === null ){
		update_option('pexlechris_saved_plugins', get_option('active_plugins') );
	}else{
		delete_option('pexlechris_saved_plugins');
	}




	$query = $_GET;
	unset( $query['pexlepage'] );
	$query_result = http_build_query($query);
	if( '' != $query_result) $query_result = '?' . $query_result;
	$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . $query_result;

	wp_redirect($url);
	die;

});


add_action('init', function(){

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return;
  
	$saved_plugins = get_option('pexlechris_saved_plugins', []);

	foreach($saved_plugins as $saved_plugin){


		add_filter("plugin_action_links_$saved_plugin", function($links)
		{
			$anchor = '<svg style="position: relative; top: 4px;" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="17px" height="17px" viewBox="0 0 3007 3007">
<path fill-rule="nonzero" fill="rgb(77.734375%, 18.821716%, 17.259216%)" fill-opacity="1" d="M 2866.488281 972.351562 C 2710.058594 167.71875 1729.558594 154.269531 1487.78125 906.46875 C 1246.011719 154.28125 265.480469 167.71875 109.074219 972.351562 C -32.855469 1702.371094 1487.78125 2854.109375 1487.78125 2854.109375 C 1487.78125 2854.109375 3008.410156 1702.371094 2866.488281 972.351562 Z M 2866.488281 972.351562 "/>
</svg>';
			$new = [$anchor];

			return array_merge($new, $links);
		}, 9999, 2);


	}

});


// Add custom filter to the Plugins page.
add_filter('views_plugins', function($views) {

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return $views;
  
	$fav_filter_link = add_query_arg(
		["plugin_status" => "fav_plugins"],
		admin_url("plugins.php")
	);

	$fav_plugins = get_option('pexlechris_saved_plugins', []);

	$plugin_status = $_GET['plugin_status'] ?? '';

	// Add the "Fav Plugins" filter link.
	$views['fav_plugins'] = sprintf(
		'<a href="%s" %s>%s <span class="count">(%d)</span></a>',
		esc_url($fav_filter_link),
		$plugin_status == 'fav_plugins' ? 'class="current"' : '',
		'Fav Plugins',
		count($fav_plugins)
	);

	return $views;
});

// Workaround
add_filter('plugins_auto_update_enabled', function($enabled){

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return $enabled;
  
	if( !is_admin() ) return $enabled;

	global $pagenow;
	if( $pagenow != 'plugins.php' ) return $enabled;

	$plugin_status = $_GET['plugin_status'] ?? '';
	if( $plugin_status != 'fav_plugins' ) return $enabled;

	$GLOBALS['status'] = 'fav_plugins';

	return $enabled;
});

add_action('pre_current_active_plugins', function() {

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return;
  
	global $wp_list_table;


	// Check if we're on the Plugins page and the 'Fav Plugins' filter is active.
	$plugin_status = $_GET['plugin_status'] ?? '';
	if ($plugin_status !== 'fav_plugins') return;



	// Get the favorite plugins list from the options.
	$fav_plugins = get_option('pexlechris_saved_plugins', []);


	// Filter the items property of the WP_Plugins_List_Table using the index (key).
	$wp_list_table->items = array_filter($wp_list_table->items, function($plugin_data, $plugin_file) use ($fav_plugins) {
		return in_array($plugin_file, $fav_plugins, true);
	}, ARRAY_FILTER_USE_BOTH);


	// Adjust the total counts and other properties if needed.
	$wp_list_table->set_pagination_args([
		'total_items' => count($wp_list_table->items),
		'per_page' => $wp_list_table->get_items_per_page('plugins_per_page'),
	]);
});


add_filter('wp_redirect', function($url) {

	if( !current_user_can(PEXLECHRIS_SAVE_PLUGINS_CAP) ) return $url;
  
    if( !is_admin() ) return $url;

    $plugin_status = $_GET['plugin_status'] ?? '';
    if( $plugin_status !== 'fav_plugins') return $url;

    $url = add_query_arg( ['plugin_status' => 'fav_plugins'], $url );

    return $url;
}, 10, 3);
