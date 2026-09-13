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
	const SCHEMA_VERSION = '1.6.1';

	/**
	 * Register the lightweight upgrade check for administrator requests.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'cleanup_legacy_options' ) );
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

	/**
	 * TEMPORARY (remove in the next update): retire abandoned pre-1.7 data.
	 *
	 * Folds the legacy notification subject/body into prayer_pop_texts and
	 * collapses the unused multi-recipient keys (Free supports one recipient).
	 * Self-gating and idempotent: it only acts while legacy data exists, so it
	 * is safe to run on every admin request until this method and its hook in
	 * init() are deleted.
	 */
	public static function cleanup_legacy_options() {
		$notifications = get_option( 'prayer_pop_notification_settings', array() );
		if ( is_array( $notifications ) && array_key_exists( 'notification_emails', $notifications ) ) {
			unset( $notifications['notification_emails'] );
			update_option( 'prayer_pop_notification_settings', $notifications );
		}

		$chat = get_option( Prayer_Pop_Chat::SETTINGS_OPTION, array() );
		if ( is_array( $chat ) && array_key_exists( 'notification_emails', $chat ) ) {
			$emails = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $chat['notification_emails'] ) ) );
			if ( empty( $chat['notification_email'] ) && ! empty( $emails ) ) {
				$chat['notification_email'] = reset( $emails );
			}
			unset( $chat['notification_emails'] );
			update_option( Prayer_Pop_Chat::SETTINGS_OPTION, $chat );
		}

		$legacy = get_option( 'prayer_pop_email_template' );
		if ( ! is_array( $legacy ) ) {
			return;
		}

		$defaults  = Prayer_Pop_Defaults::get_default_texts_raw();
		$texts     = get_option( 'prayer_pop_texts', array() );
		$texts     = is_array( $texts ) ? $texts : array();
		// The legacy option also served the scheduled-alert texts, so seed those targets too.
		$map       = array(
			'email_subject' => array( 'text_email_submission_subject', 'text_email_scheduled_subject' ),
			'email_body'    => array( 'text_email_submission_body', 'text_email_scheduled_body' ),
		);
		$normalize = static function ( $value ) {
			// Saved single-line inputs store the default with literal "\n" sequences,
			// so compare whitespace-insensitively before treating a value as customized.
			return preg_replace( '/\s+|\\\\[nrt]/', '', (string) $value );
		};

		foreach ( $map as $legacy_key => $text_keys ) {
			$value = isset( $legacy[ $legacy_key ] ) ? trim( (string) $legacy[ $legacy_key ] ) : '';
			if ( '' === $value ) {
				continue;
			}
			foreach ( $text_keys as $text_key ) {
				$current = isset( $texts[ $text_key ] ) ? trim( (string) $texts[ $text_key ] ) : '';
				$default = isset( $defaults[ $text_key ] ) ? trim( (string) $defaults[ $text_key ] ) : '';
				// Only take over when the new field was never customized.
				if ( '' === $current || $normalize( $current ) === $normalize( $default ) ) {
					$texts[ $text_key ] = $value;
				}
			}
		}

		update_option( 'prayer_pop_texts', $texts );
		delete_option( 'prayer_pop_email_template' );
	}
}
