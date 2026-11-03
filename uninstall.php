<?php
/**
 * Removes the plugin's options.
 *
 * @package Hibira_Live_Visitors
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'app_id', 'key', 'secret', 'host', 'viewers', 'min', 'store_count', 'popups', 'stock', 'position', 'delay', 'max_age', 'recent' ) as $hibira_lv_name ) {
	delete_option( 'hibira_lv_' . $hibira_lv_name );
}
