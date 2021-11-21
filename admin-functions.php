<?php

namespace wpsd_archive_orders;

defined( 'ABSPATH' ) || exit;

function get_qs( $qs_name ) {
	
	if (!isset($_GET[$qs_name]) ) return false;

	if ( empty( $_GET[$qs_name] ) && $_GET[$qs_name] !== '0' ) return true;

	return $_GET[$qs_name];

}

function get_orders_to_move( $date_from = false , $date_to = false ) {
	
	if (!$date_from) return null;

	$sql_from = '	SELECT ID FROM `posts` 
					WHERE ( post_type = "shop_order" OR post_type = "shop_order_refund" )
					AND 
					date(post_date) >= date("'.$date_from.'")'.
					( $date_to === false ? '': ' and date(post_date) <= date("'.$date_to.'")');

	$result = get_sql_query($sql_from);

	return $result;

}

function get_additional_tables() {

	$additional_tables = get_option('archive-wc-orders');
	
	if (empty($additional_tables)) return false;

	foreach ($additional_tables as $table_name => $value) {

		if ( $value['used'] == '1' ) $result[] = array( $table_name ,$value['column'] );
	
	}

	return $result ?? false;

}

function get_additional_tables_name() {

	$additional_tables = get_additional_tables();
	
	if (empty($additional_tables)) return;

	foreach ($additional_tables as $key => $value) {

		$result[] = $value[0];
	
	}

	return $result; 
}

function create_archive_tables() {
	
	$tables_array = get_tables_name();

	$additional_tables = get_additional_tables_name();

	if ( !empty( $additional_tables ) )	$tables_array = array_merge( $tables_array, $additional_tables );

	$sql = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';

	$result = get_sql_query($sql);

	foreach ($tables_array as $key => $value) {
		
		$sql = 'CREATE TABLE IF NOT EXISTS `'.$value.ARCHIVE_TABLE_SUFFIX.'` LIKE `'.$value.'`';

		$result = get_sql_query($sql);
		
	}
}

function move_orders_to_archive( $orders_ids ) {

	$tables_columns_name = get_orders_tables_columns();

	$additional_tables = get_additional_tables();

	if ( !empty($additional_tables) ) $tables_columns_name = array_merge( $tables_columns_name, $additional_tables );

	foreach ($tables_columns_name as $key => $value) {
		
		$table_name = $value[0];
  
		$column_name = $value[1];

		copy_table_data( $orders_ids, $table_name , $column_name );
		
		delete_table_data( $orders_ids, $table_name , $column_name );

	}

}

function load_datepicker_css() {

	wp_enqueue_script('jquery-ui-datepicker');
	wp_register_style('jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
	wp_enqueue_style('jquery-ui');
}

if ( get_qs('tab') === 'migrate') {

	add_action('admin_print_style', 'load_datepicker_css');
}

function get_suggested_plugin_data( $suggested_plugins ) {

	$active_plugins = implode( ',' , get_option( 'active_plugins' ) ); 

	$result = array();
	
	$table_start_html = '<td><p class="p-cell">';

	foreach ($suggested_plugins as $suggested_plugin_name => $suggested_plugin_data ) {
		
		$suggested_plugin_file = $suggested_plugin_data['plugin_file'];

		if ( strpos($active_plugins, $suggested_plugin_file) ) { 

			$result[] = $table_start_html.$suggested_plugin_name.'</td>'.$table_start_html.$suggested_plugin_data['table'].'</td>'.$table_start_html.$suggested_plugin_data['column'].'</td>';

		}

	}

	return $result ?? false;

}

function display_suggested_plugins() {

	$suggested_plugins = array  (
		'WooCommerce' => array(
						'plugin_file'	=>	'woocommerce.php',
						'table'			=>	'wc-table',
						'column'		=>	'wc-column' )
							  );

  $suggested_plugins_result = get_suggested_plugin_data( $suggested_plugins );

  if ($suggested_plugins_result) {

	?>
	<p>Following plugins are active that may have order data.<br>
	Consider to add them into tables extra list to migrate old orders.</p>
	<table class="wc-order-table">
	  <col style="width:280px">
	<tbody>
	  <tr>
			  <td><p class="p-cell bold">Plugin name</p>
			  <td><p class="p-cell bold">Table name</p>
			  <td><p class="p-cell bold">Column name</p>
			</tr>
	  <tr>
	  <?php
	  foreach ( $suggested_plugins_result as $key => $value ) {
				
			echo $value.PHP_EOL;
	  }
	  ?>
	</tbody>
	</table>
	<?php
	}

}

function get_table_colums( $table_name=false ) {
	
	if (!$table_name) return null;

	$sql = "SHOW COLUMNS FROM $table_name";

	$sql_result = get_sql_query($sql,false);

	$excluded_colums = array( 'datetime', 'timestamp' );

	foreach ($sql_result as $key => $value) {

		if ( strpos( $value->Field, 'date' ) === false AND !in_array($value->Type, $excluded_colums ) ) $result[] = $value->Field;

	}

	return $result;
}

function filter_archive_table( $tables_array ) {
	
	foreach ($tables_array as $key => $value) {
		
		if ( strpos($value, (string) ARCHIVE_TABLE_SUFFIX ) ) continue;

		$result[] = $value;
	}

return $result; 

}


function get_all_db_tables() {

	$sql = "SHOW FULL TABLES";

	$sql_result = get_sql_query($sql);
	
	$db_table_key = array_keys(json_decode(json_encode($sql_result[0]),true))[0];


	$result = array_column($sql_result, $db_table_key);

	return $result; 
	
}

function is_default_table( $table_name) {
	
	global $wpdb;

	$default_tables_columns =  get_orders_tables_columns();

	foreach ($default_tables_columns as $key => $value) {
		$default_tables[] = $wpdb->prefix.$value[0];
	}

	return in_array( $table_name , $default_tables );
}


function add_sql_quotes($value) {

	return '`'.$value.'`';

}


function is_column_with_order( $table_name, $column_name, $orders_ids ) {
	
	$table_name = add_sql_quotes($table_name);

	$column_name_sql = add_sql_quotes($column_name);

	$sql_column_string = implode( ' OR '.add_sql_quotes($column_name).' = ', $orders_ids );

	$sql = "SELECT * FROM $table_name WHERE $column_name_sql = $sql_column_string LIMIT 1";
	
	$sql_array = get_sql_query( $sql, false );
	
	return !empty($sql_array);
	
}

function get_table_column_with_order( $table_name, $table_columns, $orders_ids ) {
	
	foreach ($table_columns as $key => $column_name) {
		
		if ( is_column_with_order( $table_name, $column_name, $orders_ids ) ) {

			return $column_name;
		}
	}
	
	return false;
}

function get_example_orders_ids() {
	
	$sql = "SELECT order_id FROM `wc_order_stats` ORDER BY `wc_order_stats`.`date_created` ASC LIMIT 1";
	
	$sql_result = get_sql_query($sql);

	$two_orders_first = array_column( $sql_result, 'order_id');


	$sql = "SELECT order_id FROM `wc_order_stats` ORDER BY `wc_order_stats`.`date_created` DESC LIMIT 1";
	
	$sql_result = get_sql_query($sql);

	$two_orders_last = array_column( $sql_result, 'order_id');

	$result = array_merge( $two_orders_first, $two_orders_last );

	return $result; 

}	

function scan_tables_for_orders() {
	
	$orders_ids = get_example_orders_ids();

	$tables_array = filter_archive_table( get_all_db_tables() );

	$i=0;

	foreach ($tables_array as $key => $table_name) {

		if ( is_default_table( $table_name) ) continue;

		$table_name_sql = add_sql_quotes( $table_name );

		if (!empty($table_name)) $table_columns = get_table_colums( $table_name_sql );

		$table_column_result = get_table_column_with_order( $table_name, $table_columns, $orders_ids );

		if ( !empty($table_column_result) ) { 
		
			$result[$table_name] = $table_column_result;
		
			// if ($i++ == 2 ) break;
		}

	}		

	return $result ?? false;
	
}

function migrate_tables_action() {
	
	?><br><h2>Moving DB tables data</h2><br><?php
	
	$selected_orders_ids = get_list_of_orders_to_move();

	echo $selected_orders_ids['message'];

	if ( !$selected_orders_ids['order_ids'] ) return;

	migarate_tables_run( $selected_orders_ids['order_ids'] );

	display_mysql_erorrs();
}

function display_mysql_erorrs(){
	
	$errors = get_transient('wc-migrate-orders-errors');

	if ( $errors ) {
		
		?>
		<br>
		<div style="color: red; font-weight: bold;">During process occured errors:</div>
		<p><?=$errors?></p>
		<?php

	} else {

		?>
		<br>
		<div style="color: green; font-weight: bold;">No errors during migration process.</div>
		<?php

	}

	delete_transient('wc-migrate-orders-errors');
}

function get_list_of_orders_to_move(){

	$start_date = $_GET['date_from'];

	$end_date = $_GET['date_to'];
	
	$list_of_orders_to_move = get_orders_to_move ( $start_date, $end_date );

	return get_order_ids_and_status( $list_of_orders_to_move );
}

function get_order_ids_and_status( $list_of_orders_to_move ){

	if ( empty( $list_of_orders_to_move ) ) {

		return [	'order_ids'	=>	false,
					'message'	=>	'No orders within selected timeframe.'
				];
	}
		

	$selected_orders_ids = array_column( $list_of_orders_to_move , 'ID' );

	if( empty($selected_orders_ids ) ) {

		return [	'order_ids'	=>	false,
					'message'	=>	'Something went wrong, no order IDs found.'
				];
	}
			
	return [	'order_ids'	=>	$selected_orders_ids,
				'message'	=>	'Order migration started.'
				];		

}

function migarate_tables_run( $selected_orders_ids ){

	$batch_sorted_orders_ids = sort_orders_into_batch( $selected_orders_ids );

	create_archive_tables();

	foreach ($batch_sorted_orders_ids as $key => $value) {
	 
		$start = microtime(true);

		move_orders_to_archive($value);

		$time_elapsed_page = round(microtime(true) - $start, 5)*1000;
		  
		?>
		<p>Orders number <?=reset($value)?> to <?=end($value)?> migrated in <?=$time_elapsed_page?> ms<br>
		<?php

		refresh_page_for_php_timeout();
	}
}

function refresh_page_for_php_timeout(){

	if (!is_php_timout_iminent()) return;

	wp_redirect( get_home_url().$_SERVER['REQUEST_URI'] );

	die;
	
}

function is_php_timout_iminent(){

	$elapsed_time_seconds = (int) round( microtime(true) - $_SERVER['REQUEST_TIME'] );

	return ini_get('max_execution_time') - $elapsed_time_seconds < 5; 

}

function display_scan_button( $button_text = 'Scan tables') {

	?>
	<form method="GET" action="options-general.php" _lpchecked="1">
		<input type="hidden" name="page" value="<?php echo WPPAGE; ?>">
		<input type="hidden" name="tab" value="<?php echo TAB_SCAN ?>">
		<input type="hidden" name="perform-scan">
		<button class="button button-primary" type="submit" id="submit"><?php echo $button_text ?></button>
	</form>
	<br>
	<?php
}

function display_tables( $tables_array ) {

	load_inline_css();
	?>
<form method="GET" action="options-general.php" _lpchecked="1" autocomplete="off"  >
	<input type="hidden" name="page" value="<?php echo WPPAGE; ?>">
	<input type="hidden" name="tab" value="<?php echo TAB_SCAN; ?>">
	<input type="hidden" name="save">
		<table class="wc-order-table">
		<col style="width:250px">
		<tbody>
		<tr>
			<td><p class="p-cell bold t-cell">Table name</p>
			<td><p class="p-cell bold">Column name</p>
			<td><p class="p-cell bold">Included in migration</p></td>
		</tr>
			<?php
			$table_count = 1;
			foreach ($tables_array as $table_name => $value) {
				$column_name = $value['column'];
				$used_value = ( $value['used'] == '1' ? 'checked': '');


				?>
				<tr>
					<td><?php echo $table_name; ?></td>
					<td><?php echo $column_name ?></td>
					<td><input type="checkbox" placeholder="" autocomplete="off" name="t<?php echo $table_count++ ?>"  value="<?php echo $table_name ?>" <?php echo $used_value;?> ></td>
				</tr>

				
				
			<?php } ?>

		</tbody>
		</table>
	<br>
	<button class="button button-primary" type="submit" id="submit">Save settings</button>
</form><br><br>
<?php
	
}

function get_selected_tables () {

	$qs = $_GET;
	for ($i=1; $i < 99; $i++) { 
		
		$table_number = 't'.$i;
		if ( isset( $qs[$table_number] ) ) $result[$qs[$table_number]] = '1';
	}
	
	return $result ?? false;
	
}

function perform_the_scan() {

	$detected_tables = scan_tables_for_orders();
	
	if ( empty ($detected_tables ) ) {
		
		?><br><h2>No other tables been detected with orders</h2><br><?php
		
		return;
	
	} else {

		?><h2>Detected tables with orders</h2><?php
	
		$scan_settings = get_option('archive-wc-orders');
	

		foreach ($detected_tables as $key => $value) {
			
			if ($scan_settings !==false && isset($scan_settings[$key]) ) {

				$used_value = $scan_settings[$key]['used'];

			} else {

				$used_value = '0';

			}


			$detected_tables[$key] = array( 'column'=> $value, 'used' => $used_value );
		}

		
		update_option('archive-wc-orders',$detected_tables);

		display_tables($detected_tables);

		display_table_data_example($detected_tables);
	}

}

function display_table_data_example( $detected_tables ){
	

	$example_table_data = get_table_data_example( $detected_tables );


	foreach ($example_table_data as $table_name => $table_data) {

		$detection_column = $detected_tables[$table_name]['column'];
	
		display_table_with_data( $table_name, reset($table_data), $detection_column);	

		echo '<br>';
	}


}

function display_table_with_data( $table_name, $table_data, $detection_column ){
	
	if ( empty($table_data) ) return;

	?>
	<table class="wc-order-table">
		<tbody>
			<tr>
				<td><p class="p-cell bold t-cell">Table name</p>
				<td><p class="p-cell"><?=$table_name?></p>
			</tr>
			<tr>
			<?php
			foreach (array_keys($table_data) as $column_name) {
				
				$css_column = $column_name == $detection_column ? ' bold' : '';

				?>
				<td class="td-cell<?=$css_column?>"><?=$column_name?></td>
				<?php
			
			}
			?>
			</tr>
			<tr>
			<?php
			foreach ( $table_data as $column_value) {
				
				?>
				<td class="td-cell"><?=esc_html($column_value)?></td>
				<?php
			
			}
			?>
			</tr>
		</tbody>
	</table>
	<?php
}

function get_table_data_example( $detected_tables ){

	$example_ids = get_example_orders_ids();

	foreach ($detected_tables as $table_name => $column_name) {
		
		$sql = "SELECT * FROM $table_name WHERE {$column_name['column']} = '{$example_ids[0]}' OR {$column_name['column']} = '{$example_ids[1]}' LIMIT 1";

		$result[$table_name] = get_sql_query($sql, true, ARRAY_A );

	}

	return $result; 
}

function save_scanned_data() {
	
	$detected_tables = get_option('archive-wc-orders-scan');

	$scan_settings = get_option('archive-wc-orders');

	if (!$scan_settings) {
		
		$scan_settings = $detected_tables;
		
		?>New tables saved<br><br><?php

	} else {

		?><br>Update saved<br><br><?php

	}
		
	$selected_tables = get_selected_tables();

	foreach ($scan_settings as $key => $value) {

		if ( isset( $selected_tables[$key] ) ) {

			$scan_settings_update[$key] = array('column' => $value['column'], 'used' => '1');
		
		} else {

			$scan_settings_update[$key] = array('column' => $value['column'], 'used' => '0');

		}

	}


	update_option('archive-wc-orders', $scan_settings_update);

	delete_option('archive-wc-orders-scan');

}

function scan_menu() {

	$perform_scan = get_qs('perform-scan');


	if ( $perform_scan ) {

		perform_the_scan();

	} else {
		
		if ( get_selected_tables () or get_qs('save')) save_scanned_data();
		
		$scan_settings = get_option('archive-wc-orders');

		if ($scan_settings) {

			?><h2>Tables with orders detected by previous scan.</h2><?php
			
			display_tables($scan_settings);
			
			display_scan_button('Scan tables again'); 

		} else {
			
			if (!$scan_settings) {

				?><h2>Scan the database for tables with orders' data</h2>
<p style="max-width:600px;font-size: 17px;">Here you can scan the whole database and find another tables where orders' information is stored.<br><br>
How it works?<br>
It takes first and last order ID and searches them in other than default tables. IMPORTANT! You can find false positives. For example first order ID can be the same as a user ID. If you include that table in the migration, you will in this case move this user's data and he/she won't be able to log in. If you not sure I strongly recommend NOT to include such a table in mirgation.</p>
<br>Scan of tables for orders haven't been done yet<br><br>
				<?php

				display_scan_button();

			} else {

				display_tables($scan_settings);

			}
		}

	}
	
}

function migrate_tables_menu() {

	global $wpdb;

	load_datepicker_css();
	
	$last_year = intval(date('Y'))-1;
	
	$tables = get_orders_tables_columns();

	load_inline_css();
	?>
	<h2>Migrate orders to archive tables</h2><br>
	<form method="GET" action="options-general.php" _lpchecked="1" autocomplete="off" >

	<input type="hidden" name="page" value="<?php echo WPPAGE; ?>">
	
	<input type="hidden" name="tab" value="migrate" >

	<input type="hidden" name="run">
	
	<input type="date" name="date_from" value="2010-01-01">
	
	<input type="date" name="date_to" value="<?php echo $last_year; ?>-01-31" >
	
	<button class="button button-primary" type="submit" id="submit">Migrate old orders</button>
	</form><br>
	<?php
	$php_timeout = ini_get('max_execution_time');
		
		if ( $php_timeout<10 ) {

			?><p style='color: red;'>PHP timout is a bit low: <?=$php_timeout?> seconds. Recommended value is 30 seconds.</p><?php
		}
	?>
	<p>Tables with WooCommerce order data that will be migrated
	<table class="wc-order-table">
	<col style="width:280px">

	<tbody>

		<?php
		
		foreach ( $tables as $key => $table_data ) {
			if ($key == 0 ){
			?>
			<tr>
				<td><p class="p-cell bold t-cell">Default tables name</p>
				<td><p class="p-cell bold">Column name</p>
			</tr>
			<?php
			}
		?>
		<tr>
		
			<td><p class="p-cell t-cell"> <?php echo $wpdb->prefix.$table_data[0];?></p></td>
			<td><p class="p-cell"> <?php echo $table_data[1];?></p></td>

		</tr>
		<?php }

		$additional_tables = get_additional_tables();

		if ( !empty($additional_tables) ) {

			?>
			<tr>
				<td><p class="p-cell bold t-cell">Additional tables</p>
			</tr>
			<?php
			
			foreach ( $additional_tables as $key => $table_data ) {
				
				?>
				 <tr>
		
					<td><p class="p-cell t-cell"> <?php echo $wpdb->prefix.$table_data[0];?></p></td>
					<td><p class="p-cell"> <?php echo $table_data[1];?></p></td>

				</tr>
				
				<?php
				
				
			}
		}

		?>
	</tbody>
	</table>    
	
	<?php

}



function load_inline_css() {
?>
<style type="text/css">
.p-cell{
  margin:0 10px 10px 0;
  width: max-content;
}
td.td-cell{
	padding-right: 15px;
}

.wc-order-table{
  border: 1px solid black;
  padding: 5px 10px 10px;
  margin-top: 20px;
  min-width: 550px;
}
.wc-order-table tr{
  padding: 5px;
  border-bottom: 1px solid black;
}
.bold{
  font-weight: bold; 
}
</style>
<?php
}