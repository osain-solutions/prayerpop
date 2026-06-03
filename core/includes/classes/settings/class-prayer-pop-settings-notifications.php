<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Settings_Notifications
 *
 * Handles the Notification Settings section.
 */
class Prayer_Pop_Settings_Notifications {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Use the updated option name in the update hook.
		add_action( 'update_option_prayer_pop_notification_settings', array( $this, 'schedule_notifications' ), 10, 2 );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Use a unique option name for notifications settings.
		register_setting( 'prayer_pop_settings_group', 'prayer_pop_notification_settings', array( $this, 'sanitize_settings' ) );

		add_settings_section(
			'prayer_pop_notification_section',
			esc_html__( 'Notification Settings', 'prayerpop' ),
			null,
			'prayer-pop-settings-notifications'
		);

		add_settings_section(
			'prayer_pop_notification_debug_section',
			'',
			'__return_empty_string',
			'prayer-pop-settings-notifications'
		);

		add_settings_field(
			'enable_notifications',
			esc_html__( 'Enable Email Notifications', 'prayerpop' ),
			array( $this, 'enable_notifications_callback' ),
			'prayer-pop-settings-notifications',
			'prayer_pop_notification_section'
		);

		add_settings_field(
			'notification_email',
			esc_html__( 'Notification Email', 'prayerpop' ),
			array( $this, 'notification_email_callback' ),
			'prayer-pop-settings-notifications',
			'prayer_pop_notification_section'
		);

		add_settings_field(
			'notification_frequency',
			esc_html__( 'Notification Frequency', 'prayerpop' ),
			array( $this, 'notification_frequency_callback' ),
			'prayer-pop-settings-notifications',
			'prayer_pop_notification_section'
		);

		add_settings_field(
			'notification_time',
			esc_html__( 'Notification Time', 'prayerpop' ),
			array( $this, 'notification_time_callback' ),
			'prayer-pop-settings-notifications',
			'prayer_pop_notification_section'
		);

		add_settings_field(
			'notification_day',
			esc_html__( 'Notification Day', 'prayerpop' ),
			array( $this, 'notification_day_callback' ),
			'prayer-pop-settings-notifications',
			'prayer_pop_notification_section'
		);
		
		add_settings_field(
			'show_debug_info',
			esc_html__( 'Show Debug Information', 'prayerpop' ),
			array( $this, 'show_debug_info_callback' ),
			'prayer-pop-settings-notifications',
			'prayer_pop_notification_debug_section'
		);
	}

	/**
	 * Sanitize settings.
	 */
	public function sanitize_settings( $input ) {
		if ( is_array( $input ) ) {
			$input = wp_unslash( $input );
		}
		$sanitized = array();
		$sanitized['enable_notifications'] = isset( $input['enable_notifications'] ) ? 1 : 0;
		$sanitized['notification_email']   = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		$allowed_frequencies = array( 'immediately', 'daily', 'weekly' );
		$sanitized['notification_frequency'] = ( isset( $input['notification_frequency'] ) && in_array( $input['notification_frequency'], $allowed_frequencies, true ) ) ? $input['notification_frequency'] : 'immediately';
		$time = isset( $input['notification_time'] ) ? sanitize_text_field( $input['notification_time'] ) : '08:00';
		$sanitized['notification_time'] = preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '08:00';

		$allowed_days = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		$day = isset( $input['notification_day'] ) ? sanitize_text_field( $input['notification_day'] ) : 'Monday';
		$sanitized['notification_day'] = in_array( $day, $allowed_days, true ) ? $day : 'Monday';
		$sanitized['show_debug_info']      = isset( $input['show_debug_info'] ) ? 1 : 0;
		return $sanitized;
	}

	/**
	 * Enable notifications callback.
	 */
	public function enable_notifications_callback() {
		$options = get_option( 'prayer_pop_notification_settings', array() );
		$enabled = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : 0;
		
		echo '<div class="prayer-pop-toggle-wrapper">';
		echo '<label class="prayer-pop-toggle-switch">';
		echo '<input type="checkbox" name="prayer_pop_notification_settings[enable_notifications]" value="1" ' . checked( 1, $enabled, false ) . ' id="enable_notifications_toggle">';
		echo '<span class="prayer-pop-toggle-slider"></span>';
		echo '<span class="toggle-status">' . ( $enabled ? esc_html__( 'On', 'prayerpop' ) : esc_html__( 'Off', 'prayerpop' ) ) . '</span>';
		echo '</label>';
		echo '<p class="description">' . esc_html__('Enable email notifications for new prayer requests', 'prayerpop' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Notification email callback.
	 */
	public function notification_email_callback() {
		$options = get_option( 'prayer_pop_notification_settings', array() );
		$email   = isset( $options['notification_email'] ) ? $options['notification_email'] : get_option( 'admin_email' );
		$enabled = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : 0;
		
		echo '<input type="email" name="prayer_pop_notification_settings[notification_email]" value="' . esc_attr( $email ) . '" class="regular-text notification-field" ' . disabled( ! $enabled, true, false ) . '>';
		echo '<p class="description">' . esc_html__('Email address to receive notifications', 'prayerpop' ) . '</p>';
	}

	/**
	 * Notification frequency callback.
	 */
	public function notification_frequency_callback() {
		$options = get_option( 'prayer_pop_notification_settings', array() );
		$frequency = isset( $options['notification_frequency'] ) ? $options['notification_frequency'] : 'immediately';
		$enabled = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : 0;
		
		$frequencies = array(
			'immediately' => esc_html__( 'Every time there is a request', 'prayerpop' ),
			'daily'       => esc_html__( 'Once a day at selected time', 'prayerpop' ),
			'weekly'      => esc_html__( 'Once a week on selected day and time', 'prayerpop' ),
		);
		echo '<select name="prayer_pop_notification_settings[notification_frequency]" id="prayer_pop_notification_frequency" class="notification-field" ' . disabled( ! $enabled, true, false ) . '>';
		foreach ( $frequencies as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $frequency, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('How often to send notifications', 'prayerpop' ) . '</p>';
	}

	/**
	 * Notification time callback.
	 */
	public function notification_time_callback() {
		$options = get_option( 'prayer_pop_notification_settings', array() );
		$time    = isset( $options['notification_time'] ) ? $options['notification_time'] : '08:00';
		$enabled = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : 0;
		
		echo '<input type="time" name="prayer_pop_notification_settings[notification_time]" value="' . esc_attr( $time ) . '" class="notification-field" ' . disabled( ! $enabled, true, false ) . '>';
		echo '<p class="description">' . esc_html__('Time to send daily/weekly notifications', 'prayerpop' ) . '</p>';
	}

	/**
	 * Notification day callback.
	 */
	public function notification_day_callback() {
		$options = get_option( 'prayer_pop_notification_settings', array() );
		$day     = isset( $options['notification_day'] ) ? $options['notification_day'] : 'Monday';
		$enabled = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : 0;
		
		$days    = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		echo '<select name="prayer_pop_notification_settings[notification_day]" class="notification-field" ' . disabled( ! $enabled, true, false ) . '>';
		foreach ( $days as $d ) {
			echo '<option value="' . esc_attr( $d ) . '" ' . selected( $day, $d, false ) . '>' . esc_html( $d ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__('Day of the week to send weekly notifications', 'prayerpop' ) . '</p>';
	}
	
	/**
	 * Show debug info callback.
	 */
	public function show_debug_info_callback() {
		$options = get_option( 'prayer_pop_notification_settings', array() );
		$show_debug = isset( $options['show_debug_info'] ) ? $options['show_debug_info'] : 0;
		$enabled = isset( $options['enable_notifications'] ) ? $options['enable_notifications'] : 0;
		
		echo '<label>';
		echo '<input type="checkbox" id="prayer_pop_notification_show_debug_info" name="prayer_pop_notification_settings[show_debug_info]" value="1" ' . checked( $show_debug, 1, false ) . ' class="notification-field" ' . disabled( ! $enabled, true, false ) . '>';
		echo ' ' . esc_html__( 'Show technical information for troubleshooting', 'prayerpop' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Enable this to see cron scheduling details and debug information', 'prayerpop' ) . '</p>';

		echo '<div class="prayer-pop-notification-debug-panel" ' . ( $enabled && $show_debug ? '' : 'style="display:none;"' ) . '>';
		if ( $enabled && $show_debug ) {
			$this->render_cron_status_panel();
		}
		echo '</div>';
	}

	/**
	 * Render cron status debug panel.
	 */
	private function render_cron_status_panel() {
		$daily_next = wp_next_scheduled( 'prayer_pop_send_daily_notifications' );
        $weekly_next = wp_next_scheduled( 'prayer_pop_send_weekly_notifications' );
        $last_sent = get_option( 'prayer_pop_last_notification_time', 0 );
        $current_utc = time();
		$current_local = current_time( 'timestamp' );
		$gmt_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		
		echo '<div style="background: #f1f1f1; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">';
		echo '<strong>' . esc_html__( 'Scheduled Events (Local Time):', 'prayerpop' ) . '</strong><br>';
		
		if ( $daily_next ) {
			$daily_local = $daily_next + $gmt_offset;
			$daily_diff = $daily_local - $current_local;
			echo esc_html__( 'Daily:', 'prayerpop' ) . ' ' . esc_html( gmdate( 'Y-m-d H:i:s', $daily_local ) ) . ' (' . esc_html__( 'in', 'prayerpop' ) . ' ' . esc_html( $this->format_time_diff( $daily_diff ) ) . ')<br>';
		} else {
			echo esc_html__( 'Daily: Not scheduled', 'prayerpop' ) . '<br>';
		}
		
		if ( $weekly_next ) {
			$weekly_local = $weekly_next + $gmt_offset;
			$weekly_diff = $weekly_local - $current_local;
			echo esc_html__( 'Weekly:', 'prayerpop' ) . ' ' . esc_html( gmdate( 'Y-m-d H:i:s', $weekly_local ) ) . ' (' . esc_html__( 'in', 'prayerpop' ) . ' ' . esc_html( $this->format_time_diff( $weekly_diff ) ) . ')<br>';
		} else {
			echo esc_html__( 'Weekly: Not scheduled', 'prayerpop' ) . '<br>';
		}
		
		echo '<strong>' . esc_html__( 'Last notification sent:', 'prayerpop' ) . '</strong> ' . esc_html( $last_sent ? gmdate( 'Y-m-d H:i:s', $last_sent + $gmt_offset ) : __( 'Never', 'prayerpop' ) ) . '<br>';
		echo '<strong>' . esc_html__( 'Current time (Local):', 'prayerpop' ) . '</strong> ' . esc_html( current_time( 'mysql' ) ) . '<br>';
		echo '<strong>' . esc_html__( 'Current time (UTC):', 'prayerpop' ) . '</strong> ' . esc_html( gmdate( 'Y-m-d H:i:s', $current_utc ) ) . '<br>';
		echo '<strong>' . esc_html__( 'WordPress cron enabled:', 'prayerpop' ) . '</strong> ' . esc_html( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'NO (disabled)', 'prayerpop' ) : __( 'YES', 'prayerpop' ) ) . '<br>';
		echo '<strong>' . esc_html__( 'GMT Offset:', 'prayerpop' ) . '</strong> ' . esc_html( get_option( 'gmt_offset' ) ) . ' ' . esc_html__( 'hours', 'prayerpop' ) . '<br>';
		echo '</div>';
		echo '<p class="description">' . esc_html__('Debug information about scheduled notifications', 'prayerpop' ) . '</p>';
	}
	
	/**
	 * Format time difference in a readable way.
	 */
	private function format_time_diff( $seconds ) {
		if ( $seconds < 0 ) {
			return sprintf(
				/* translators: %s: human-readable time difference */
				esc_html__( 'OVERDUE by %s', 'prayerpop' ),
				human_time_diff( 0, abs( $seconds ) )
			);
		}
		
		if ( $seconds < 3600 ) {
			return sprintf(
				/* translators: %d: minute count */
				esc_html__( '%d minutes', 'prayerpop' ),
				(int) round( $seconds / 60 )
			);
		} elseif ( $seconds < 86400 ) {
			return sprintf(
				/* translators: %s: hour count */
				esc_html__( '%s hours', 'prayerpop' ),
				(string) round( $seconds / 3600, 1 )
			);
		} else {
			return sprintf(
				/* translators: %s: day count */
				esc_html__( '%s days', 'prayerpop' ),
				(string) round( $seconds / 86400, 1 )
			);
		}
	}

	/**
	 * Schedule notifications when settings are updated.
	 */
	public function schedule_notifications( $old_value, $value ) {
		// Clear any existing scheduled hooks
		wp_clear_scheduled_hook( 'prayer_pop_send_daily_notifications' );
		wp_clear_scheduled_hook( 'prayer_pop_send_weekly_notifications' );

		if ( isset( $value['enable_notifications'] ) && $value['enable_notifications'] ) {
			if ( isset( $value['notification_frequency'] ) && 'daily' === $value['notification_frequency'] ) {
				// Fix: Calculate next occurrence of the specified time using UTC for WordPress cron
				$notification_time = isset( $value['notification_time'] ) ? $value['notification_time'] : '08:00';
				
				// Get current local time and calculate target in local timezone
				$current_local = current_time( 'timestamp' );
				$today_target_local = strtotime( 'today ' . $notification_time, $current_local );
				
				// If time has passed today, schedule for tomorrow
				if ( $today_target_local <= $current_local ) {
					$target_local = strtotime( 'tomorrow ' . $notification_time, $current_local );
				} else {
					$target_local = $today_target_local;
				}
				
				// Convert to UTC for wp_schedule_event (WordPress cron works in UTC)
				$target_utc = $target_local - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
				
				$scheduled = wp_schedule_event( $target_utc, 'daily', 'prayer_pop_send_daily_notifications' );
				
			} elseif ( isset( $value['notification_frequency'] ) && 'weekly' === $value['notification_frequency'] ) {
				// Fix: Calculate next occurrence of the specified day and time using UTC for WordPress cron
				$notification_day = isset( $value['notification_day'] ) ? $value['notification_day'] : 'Monday';
				$notification_time = isset( $value['notification_time'] ) ? $value['notification_time'] : '08:00';
				
				// Get current local time
				$current_local = current_time( 'timestamp' );
				$today = strtolower( gmdate( 'l', $current_local ) );
				$target_day = strtolower( $notification_day );
				
				// Calculate target time in local timezone
				if ( $today === $target_day ) {
					// If it's the same day, check if time has passed
					$today_target_local = strtotime( 'today ' . $notification_time, $current_local );
					if ( $today_target_local > $current_local ) {
						// Time hasn't passed today, use today
						$target_local = $today_target_local;
					} else {
						// Time has passed, schedule for next week
						$target_local = strtotime( 'next ' . $notification_day . ' ' . $notification_time, $current_local );
					}
				} else {
					// Different day, find next occurrence
					$target_local = strtotime( 'next ' . $notification_day . ' ' . $notification_time, $current_local );
				}
				
				// Convert to UTC for wp_schedule_event (WordPress cron works in UTC)
				$target_utc = $target_local - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
				
				$scheduled = wp_schedule_event( $target_utc, 'prayer_pop_weekly', 'prayer_pop_send_weekly_notifications' );
				
			}
		}

		delete_option( 'prayer_pop_last_notification_time' );
	}
}
