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

$prayer_pop_options_to_delete = array(
	'prayer_pop_texts',
	'prayer_pop_styles',
	'prayer_pop_general_settings',
	'prayer_pop_notification_settings',
	'prayer_pop_email_template',
	'prayer_pop_custom_buttons',
	'prayer_pop_last_prayer_time',
	'prayer_pop_last_notification_time',
	'prayer_pop_migrated_post_type'
);

if ( ! $prayer_pop_preserve_shared_data ) {
	foreach ( $prayer_pop_options_to_delete as $prayer_pop_option ) {
		delete_option( $prayer_pop_option );
	}
}


if ( ! $prayer_pop_preserve_shared_data ) {
	// Clear shared scheduled hooks only when no other PrayerPop edition remains.
	wp_clear_scheduled_hook( 'prayer_pop_send_daily_notifications' );
	wp_clear_scheduled_hook( 'prayer_pop_send_weekly_notifications' );
	wp_clear_scheduled_hook( 'prayer_pop_cleanup_event' );
}

// Flush rewrite rules
flush_rewrite_rules();
