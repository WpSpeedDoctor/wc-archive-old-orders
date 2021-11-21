<?php

namespace wpsd_archive_orders;

defined( 'ABSPATH' ) || exit;


function is_archive_done() {

	if ( is_user_order_page() === false && is_user_view_order_page() === false ) return false;

	global $wpdb;

	$sql = "SELECT * 
			FROM information_schema.tables
			WHERE table_schema = '$wpdb->dbname' 
			    AND table_name = 'postmeta_archive'
			LIMIT 1";

	$sql_result = get_sql_query( $sql, $add_prefix = true);

	return !empty($sql_result);
}



function get_user_archived_orders() {

	$user_id = get_current_user_id();

	$transient_name = 'user-archive-'.$user_id;

	$transient_output = get_transient($transient_name);

	if ( $transient_output ) {

		return unserialize( $transient_output );

	} else {

		$sql_from = "SELECT post_id FROM `postmeta_archive` WHERE meta_value = $user_id AND meta_key = '_customer_user'";

		$sql_result = get_sql_query($sql_from, $add_prefix = true );
		
		$result = array_column( $sql_result, 'post_id');
		
		set_transient ( $transient_name , serialize( $result ) , 120 );

		return $result;
		
	}
}

function get_individual_order_view_id() {

	global $wp;

	return ( isset($wp->query_vars['view-order']) === true ? $wp->query_vars['view-order'] : false );

}

function order_page_required_ids() {

	if ( is_user_order_page() ) return get_user_archived_orders();

	if ( is_user_view_order_page() ) {
		
		$current_order_id = get_individual_order_view_id();
		
		if (!$current_order_id) return false;

		return array($current_order_id); 

	} 

	return false; 

}



function remove_archived_orders( $orders_ids ) {

	$tables_columns_name = get_orders_tables_columns();

	foreach ($tables_columns_name as $key => $value) {
		
   		$table_name = $value[0];
  
	    $column_name = $value[1];

	   	delete_table_data( $orders_ids, $table_name , $column_name );
	}

}


function remove_users_archived_orders() {
	
	if ( !is_archive_done() ) return;

	$user_orders = order_page_required_ids();

	if ( !$user_orders ) return;

	$batch_user_orders = sort_orders_into_batch ($user_orders);
	
	foreach ($batch_user_orders as $key =>$orders_ids) {

		remove_archived_orders($orders_ids);

	}
}


function copy_all_tables_order_data_from_archive( $orders_ids ) {

	$tables_columns_name = get_orders_tables_columns();

	foreach ($tables_columns_name as $key => $value) {
		
   		$table_name = $value[0];
  
	    $column_name = $value[1];

		copy_table_data( $orders_ids, $table_name , $column_name, true );

	}

}



function copy_user_orders_from_archive() {

	if ( !is_archive_done() ) return;

	$user_orders = order_page_required_ids();

	if ( !$user_orders ) return;

	$batch_user_orders = sort_orders_into_batch ($user_orders);
	
	foreach ($batch_user_orders as $key =>$orders_ids) {

		copy_all_tables_order_data_from_archive($orders_ids);

	}
}



function display_archived_orders_in_main_db() {

	$orders_ids = get_user_archived_orders();

	$orders_for_sql = "'".implode("','", $orders_ids )."'";

	$sql = "SELECT order_id FROM wc_order_stats WHERE order_id IN ( $orders_for_sql )";

	$result = get_sql_query($sql);

	if (!$result) return;

	$result = array_column($result, 'order_id');

}

