<?php

namespace wpsd_archive_orders;

defined( 'ABSPATH' ) || exit;

require_once 'front-end-functions.php';


add_action ('wp_head', 'wpsd_archive_orders\copy_user_orders_from_archive');

add_action ('wp_footer', 'wpsd_archive_orders\remove_users_archived_orders');


?>