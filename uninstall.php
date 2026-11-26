<?php
/**
 * Uninstall Sports Player Platform
 *
 * Runs when the plugin is deleted from WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$settings = get_option('spp_settings', []);
$delete_data = !empty($settings['delete_data_on_uninstall']);

// Always clean up plugin options
delete_option('spp_db_version');
delete_option('spp_settings');

// Optionally drop custom tables if explicitly enabled in settings
if ($delete_data) {
    $tables = [
        'spp_players',
        'spp_teams',
        'spp_rankings',
        'spp_rankings_history',
        'spp_ranking_categories',
        'spp_memberships',
        'spp_conversations',
        'spp_messages',
        'spp_recruiting_lists',
        'spp_recruiting_players',
        'spp_player_interest',
        'spp_saved_players',
        'spp_notifications',
        'spp_player_views',
        'spp_payment_logs',
        'spp_email_logs',
    ];

    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}

do_action('spp_uninstalled');
