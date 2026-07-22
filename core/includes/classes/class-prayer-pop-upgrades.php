<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates idempotent PrayerPop data and lifecycle upgrades.
 */
class Prayer_Pop_Upgrades {
	const OPTION_KEY     = 'prayer_pop_free_schema_version';
	const SCHEMA_VERSION = '1.6.0';

	/**
	 * Register the lightweight upgrade check for administrator requests.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Run pending migrations when the stored schema version is old or missing.
	 */
	public static function maybe_upgrade() {
		$installed_version = (string) get_option( self::OPTION_KEY, '0' );
		if ( version_compare( $installed_version, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		self::run( $installed_version );
	}

	/**
	 * Run activation repairs even when the schema version is already current.
	 */
	public static function activate() {
		self::run( (string) get_option( self::OPTION_KEY, '0' ), true );
	}

	/**
	 * Execute versioned, idempotent migrations.
	 *
	 * @param string $installed_version Previously installed schema version.
	 * @param bool   $force_repair      Whether to repair lifecycle state on activation.
	 */
	private static function run( $installed_version, $force_repair = false ) {
		if ( $force_repair || version_compare( $installed_version, '1.5.13', '<' ) ) {
			$settings = get_option( 'prayer_pop_notification_settings', array() );
			Prayer_Pop_Notification_Scheduler::sync( is_array( $settings ) ? $settings : array() );
		}

		if ( $force_repair || version_compare( $installed_version, '1.6.0', '<' ) ) {
			Prayer_Pop_Chat::install();
		}

		update_option( self::OPTION_KEY, self::SCHEMA_VERSION, false );
	}
}
