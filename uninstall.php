<?php
/**
 * Uninstall: remove options and custom table.
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'cfc_menu_items';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

delete_option( 'cfc_db_version' );
delete_option( 'cfc_last_import' );
delete_option( 'cfc_last_import_count' );
delete_transient( 'cfc_admin_notice' );
