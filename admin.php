<?php

namespace wpsd_archive_orders;

defined( 'ABSPATH' ) || exit;

/**
 * Admin back-end
*/

require_once(trailingslashit(__DIR__).'admin-functions.php');


function admin_page() {

	define ('TAB_SCAN', 'scan');
	define ('TAB_MIGRATE', 'migrate');
	define ('WPPAGE','wc_archive_old_orders');


	$tab_menu = get_qs('tab');

	$scan_menu = get_qs('scan');

	$migrate_menu = get_qs('migrate');

	$migrate_run = get_qs('run');

	?>
	<br><br><h1>WooCommerce migrate old order data to archive tables</h1><br>
	<div class="my-tabs">
		<h2 class="nav-tab-wrapper">
			<a href="?page=<?php echo WPPAGE; ?>" class="nav-tab <?php echo !$tab_menu ? 'nav-tab-active' : ''; ?>">Home</a>
			<a href="?page=<?php echo WPPAGE; ?>&tab=<?php echo TAB_SCAN?>" class="nav-tab <?php echo $tab_menu == TAB_SCAN ? 'nav-tab-active' : ''; ?>">Scan</a>
			<a href="?page=<?php echo WPPAGE; ?>&tab=<?php echo TAB_MIGRATE?>" class="nav-tab <?php echo $tab_menu == TAB_MIGRATE ? 'nav-tab-active' : ''; ?>">Migrate</a>
		</h2>
	</div>
	<?php

	if ($tab_menu == TAB_SCAN) {

		scan_menu();

	} elseif ($tab_menu == TAB_MIGRATE) {

		if ($migrate_run){

			migrate_tables_action();
			
		} else {

			migrate_tables_menu();

		}

	} else {

		?>
		<h3>What this plugin does?</h3>
		<div style="max-width:500px">
			<p>This plugin migrated old orders from database tables used by WooCommerce to archive tables so database queries that WooCommerce uses to run will contain less data. This can make signicifant different in server load  and website speed for websites that has large amout of orders.</p>
			<p>First step should be to scan database for additional tables that might contain order data. Then choose which of additional tables should be added to migration.</p>
			<p>The last step is migrate orders to archive tables. You will retain all data and you can manually move them all back to the orginal state.</p>
			<h4 style="color:red">Important</h4>
			<p>Before you use this plugin on production site I urge you to test it first on staging site or Local WP if it works fine on your site. I'm not taking any responsibility of lost data or consequences of any malfunctioning.</p>
			If you need help with this plugin, speed optmization or security, hire me on <a href="https://www.upwork.com/freelancers/~01681ee53d8f2094df" target="_blank">Upwork</a> 
		</div> 
		<?php
	} 

}
