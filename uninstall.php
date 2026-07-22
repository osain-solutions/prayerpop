<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uninstall script for the Pray Box plugin.
 *
 * This file is executed when the plugin is uninstalled (deleted)
 * via the WordPress dashboard.
 *
 * @package Prayer_Pop
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Determine whether PrayerPop Pro is still installed.
 *
 * Free and Pro intentionally share option names so Pro can pick up Free
 * settings during an upgrade. Do not delete shared data while Pro exists.
 *
 * @return bool
 */
function prayer_pop_pro_installed_for_uninstall() {
	$plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( __DIR__ );
	return file_exists( trailingslashit( $plugins_dir ) . 'prayerpop-pro/prayer-pop.php' );
}

$prayer_pop_preserve_shared_data = prayer_pop_pro_installed_for_uninstall();

// This marker belongs only to the Free package and is never shared with Pro.
delete_option( 'prayer_pop_free_schema_version' );

$prayer_pop_options_to_delete = array(
	'prayer_pop_texts',
	'prayer_pop_styles',
	'prayer_pop_general_settings',
	'prayer_pop_notification_settings',
	'prayer_pop_email_template',
	'prayer_pop_custom_buttons',
	'prayer_pop_last_prayer_time',
	'prayer_pop_last_notification_time',
	'prayer_pop_migrated_post_type',
	'prayer_pop_chat_settings',
);

if ( ! $prayer_pop_preserve_shared_data ) {
	delete_option( 'prayer_pop_chat_schema_version' );
	foreach ( $prayer_pop_options_to_delete as $prayer_pop_option ) {
		delete_option( $prayer_pop_option );
	}
}


if ( ! $prayer_pop_preserve_shared_data ) {
	global $wpdb;
	$prayer_pop_chat_messages_table = esc_sql( $wpdb->prefix . 'prayerpop_chat_messages' );
	$prayer_pop_chat_conversations_table = esc_sql( $wpdb->prefix . 'prayerpop_chat_conversations' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table names are derived only from the WordPress prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$prayer_pop_chat_messages_table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table names are derived only from the WordPress prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$prayer_pop_chat_conversations_table}" );

	// Clear shared scheduled hooks only when no other PrayerPop edition remains.
	wp_clear_scheduled_hook( 'prayer_pop_send_daily_notifications' );
	wp_clear_scheduled_hook( 'prayer_pop_send_weekly_notifications' );
	wp_clear_scheduled_hook( 'prayer_pop_cleanup_event' );
}

// Flush rewrite rules
flush_rewrite_rules();
