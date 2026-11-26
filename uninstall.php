<?php
/**
 * Clean up Sports Player Platform after a WordPress uninstall.
 *
 * @package Sports_Player_Platform
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/database/class-spp-db-schema.php';

global $wpdb;

$spp_settings = get_option( 'spp_settings', array() );
if ( ! empty( $spp_settings['delete_data_on_uninstall'] ) && $wpdb->has_cap( 'identifier_placeholders' ) ) {
	foreach ( array_reverse( array_keys( SPP_DB_Schema::definitions() ) ) as $spp_table_suffix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicitly opted-in uninstall cleanup.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'spp_' . $spp_table_suffix ) );
	}
}

foreach ( array( 'spp_db_version', 'spp_db_phase1_conversation_cursor', 'spp_settings', 'spp_pages_version', 'spp_roles_version' ) as $spp_option ) {
	delete_option( $spp_option );
}

do_action( 'spp_uninstalled' );
