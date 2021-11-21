<?php

namespace wpsd_archive_orders;

defined( 'ABSPATH' ) || exit;

define ('MAX_ORDERS_PER_QUERY', 100 );

define ('ARCHIVE_TABLE_SUFFIX', '_archive' );

define ('ORDER_ARCHIVE_DEBUG', false );


if ( ! function_exists( 'deb' )){
	function deb($value) {
	

?><div style="white-space: break-spaces;background-color:#222;color:#ddd;font-family: monospace;"><?php
var_dump( $value );
?></div><?php

	
	}
}


function get_orders_tables_columns() {

	$tables_array[] = array( 'comments', 'comment_post_ID' );

	$tables_array[] = array( 'postmeta', 'post_id' );

	$tables_array[] = array( 'posts', 'ID' );

	$tables_array[] = array('wc_order_stats','order_id' );

	$tables_array[] = array('wc_order_product_lookup','order_id' );
	
	$tables_array[] = array('wc_order_tax_lookup','order_id' );

	$tables_array[] = array( 'woocommerce_order_items', 'order_id' );

	$tables_array[] = array( 'woocommerce_order_itemmeta', 'order_item_id' );
	
	return $tables_array;
}



function sort_orders_into_batch( $orders_ids ) {
	
	$i = 0;
	
	$count = 0;

	foreach ($orders_ids as $key => $value) {
		
		$result[$i][] = $value;
		
		if ( ++$count === MAX_ORDERS_PER_QUERY ) {

			$i++;

			$count = 0;
		}
	}
	
	return $result;
}



function get_tables_name() {

	$table_column_array = get_orders_tables_columns();

	foreach ($table_column_array as $key => $value) {
		
		$result[] = $value[0];
	
	}

	return $result; 

}

function copy_table_data( $orders_ids, $table_name, $column, $return_data = false ) { 
//$return_data == true to copy data from archive to main table

	$orders_for_sql = "'".implode("','", $orders_ids )."'";
	
	$table_copy_to = ( $return_data === false  ? $table_name.ARCHIVE_TABLE_SUFFIX : $table_name );

	$table_copy_from = ( $return_data === false  ? $table_name : $table_name.ARCHIVE_TABLE_SUFFIX );

	$sql = "INSERT INTO $table_copy_to 
			SELECT * FROM $table_copy_from WHERE $column IN ( $orders_for_sql );";
	
	$result = get_sql_query($sql);

	return $result;
}

function delete_table_data( $orders_ids, $table_name, $column ) {

	$orders_for_sql = "'".implode("','", $orders_ids )."'";
	
	$sql = "DELETE FROM $table_name  WHERE $column IN ( $orders_for_sql );";
	
	$result = get_sql_query($sql);

	return $result;
}


function add_table_prefix($sql) {

	global $wpdb;

	$tables = get_tables_name();

	
	foreach ($tables as $key => $value) {

		$sql = str_replace($value, $wpdb->prefix.$value, $sql);
	}
	
	return $sql;
}



// if $add_prefix true it will add table prefix
function get_sql_query( $sql = false, $add_prefix = true, $output=OBJECT ) { 
	
	if (!$sql) return false;
	
	if ( ORDER_ARCHIVE_DEBUG ) $start_time_measure = microtime(true);

	global $wpdb;

	if ( $add_prefix ) $sql = add_table_prefix($sql);

	$wpdb->hide_errors();
	
	$result = $wpdb->get_results($sql, $output );
	
	if (!empty($wpdb->last_error)) log_sql_error($wpdb->last_error);

	if ( ORDER_ARCHIVE_DEBUG ) $elapsed_time = round(microtime(true) - $start_time_measure, 5)*1000; // in ms
	
	if ( ORDER_ARCHIVE_DEBUG ) deb( $sql,'time: '.$elapsed_time.' ms' );

	return $result;
}

function log_sql_error( $new_error_message ){

	$errors = get_transient('wc-migrate-orders-errors');

	if ( !$errors ) $errors = '';

	set_transient( 'wc-migrate-orders-errors', $errors.$new_error_message.'<br>', 120 );

}

function is_user_order_page() {

	return (\is_account_page() && \is_wc_endpoint_url( 'orders' ));

}



function is_user_view_order_page() {

	return is_account_page() && is_wc_endpoint_url( 'view-order' );

}