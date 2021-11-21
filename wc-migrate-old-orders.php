<?php

namespace wpsd_archive_orders;

/**
 * @wordpress-plugin
 * Plugin Name:       Woocommerce archive old orders
 * Description:       This plugin migrates old order data to archive tables
 * Version:           1.0.0
 * Author:            Jaro Kurimsky <pixtweaks@protonmail.com>
 * Author URI:        https://wpspeeddoctor.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;


run_plugin();

function run_plugin(){

	if ( is_futile_request() ) return;

	require_once  __DIR__.'/universal-functions.php';

	if ( is_admin() )  {

		run_back_end();

	} else {

		add_action('wp','wpsd_archive_orders\run_front_end');
	}

}


function run_front_end(){

	require_once __DIR__.'/front-end-functions.php';

	add_action ('wp_head', 'wpsd_archive_orders\copy_user_orders_from_archive');

	add_action ('wp_footer', 'wpsd_archive_orders\remove_users_archived_orders');

}

function run_back_end(){

	add_action('admin_menu', 'wpsd_archive_orders\admin_menu');

	if ( !is_plugin_settings_page() ) return;

	require_once  __DIR__.'/universal-functions.php';

	require_once __DIR__. '/admin.php';

}

function admin_menu() {

	add_submenu_page('options-general.php',
	//add_menu_page( 
		'WC archive old orders', 
		'WC archive old orders', 
		'administrator', 
		'wc_archive_old_orders', 
		'wpsd_archive_orders\admin_page'
		);

}

function is_plugin_settings_page(){

	return ( $_GET['page'] ?? false ) === 'wc_archive_old_orders';
}

function is_futile_request(){

	if ( is_plugin_settings_page() ) return false;

	return !empty( $_POST ) || has_substr( $_SERVER['REQUEST_URI'], 'cron.php' ) || has_substr( $_SERVER['REQUEST_URI'], 'ajax');
}

function has_substr( $haystack, $needle ){

	return is_int( strpos( $haystack, $needle ) );
}