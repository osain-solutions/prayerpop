<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps scheduled notification digests aligned with the WordPress timezone.
 */
class Prayer_Pop_Notification_Scheduler {
	const DAILY_HOOK  = 'prayer_pop_send_daily_notifications';
	const WEEKLY_HOOK = 'prayer_pop_send_weekly_notifications';
	const IMMEDIATE_HOOK = 'prayer_pop_send_immediate_notification';
	const OUTBOX_WORKER_HOOK = 'prayer_pop_process_notification_outbox';
	const OUTBOX_STATE_META = '_prayer_pop_immediate_notification_state';
	const OUTBOX_NEXT_ATTEMPT_META = '_prayer_pop_immediate_notification_next_attempt';

	/** Queue an immediate submission notification in durable post meta. */
	public static function queue_immediate_notification( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error( 'invalid_submission', __( 'The submission could not be queued for notification.', 'prayerpop' ) );
		}

		update_post_meta( $post_id, self::OUTBOX_STATE_META, 'pending' );
		update_post_meta( $post_id, self::OUTBOX_NEXT_ATTEMPT_META, time() );
		delete_post_meta( $post_id, '_prayer_pop_immediate_notification_last_error' );
		self::ensure_outbox_worker_scheduled();

		$result = wp_schedule_single_event( time(), self::IMMEDIATE_HOOK, array( $post_id ), true );
		if ( is_wp_error( $result ) || ! $result ) {
			$error = is_wp_error( $result ) ? $result->get_error_message() : __( 'WordPress could not schedule the notification event.', 'prayerpop' );
			update_post_meta( $post_id, '_prayer_pop_immediate_notification_last_error', $error );
			return new WP_Error( 'notification_schedule_failed', $error );
		}

		return true;
	}

	/** Ensure an hourly worker can recover queued or retried notifications. */
	public static function ensure_outbox_worker_scheduled() {
		if ( wp_next_scheduled( self::OUTBOX_WORKER_HOOK ) ) {
			return true;
		}

		return (bool) wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::OUTBOX_WORKER_HOOK );
	}

	/**
	 * Replace existing notification events with the next event requested by settings.
	 *
	 * @param array $settings Notification settings.
	 * @return bool True when no event is needed or the requested event was scheduled.
	 */
	public static function sync( $settings ) {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_clear_scheduled_hook( self::WEEKLY_HOOK );
		self::ensure_outbox_worker_scheduled();

		return self::ensure_scheduled( $settings );
	}

	/**
	 * Ensure the next configured digest event exists.
	 *
	 * Single events are used so each subsequent occurrence is recalculated in the
	 * site timezone. This prevents digest times drifting when daylight saving changes.
	 *
	 * @param array $settings Notification settings.
	 * @return bool True when no event is needed or the requested event exists.
	 */
	public static function ensure_scheduled( $settings ) {
		if ( empty( $settings['enable_notifications'] ) ) {
			return true;
		}

		$frequency = isset( $settings['notification_frequency'] ) ? sanitize_key( (string) $settings['notification_frequency'] ) : 'immediately';
		if ( ! in_array( $frequency, array( 'daily', 'weekly' ), true ) ) {
			return true;
		}

		$hook = 'daily' === $frequency ? self::DAILY_HOOK : self::WEEKLY_HOOK;
		if ( wp_next_scheduled( $hook ) ) {
			return true;
		}

		$timestamp = self::get_next_timestamp( $settings, $frequency );
		if ( $timestamp <= time() ) {
			return false;
		}

		return (bool) wp_schedule_single_event( $timestamp, $hook );
	}

	/**
	 * Calculate the next digest timestamp in the configured WordPress timezone.
	 *
	 * @param array  $settings  Notification settings.
	 * @param string $frequency Daily or weekly.
	 * @return int UTC Unix timestamp.
	 */
	private static function get_next_timestamp( $settings, $frequency ) {
		$time = isset( $settings['notification_time'] ) ? (string) $settings['notification_time'] : '08:00';
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$time = '08:00';
		}

		list( $hour, $minute ) = array_map( 'intval', explode( ':', $time ) );
		$timezone              = wp_timezone();
		$now                   = new DateTimeImmutable( 'now', $timezone );
		$target                = $now->setTime( $hour, $minute, 0 );

		if ( 'daily' === $frequency ) {
			if ( $target <= $now ) {
				$target = $target->modify( '+1 day' );
			}

			return $target->getTimestamp();
		}

		$days = array(
			'Monday'    => 1,
			'Tuesday'   => 2,
			'Wednesday' => 3,
			'Thursday'  => 4,
			'Friday'    => 5,
			'Saturday'  => 6,
			'Sunday'    => 7,
		);
		$day = isset( $settings['notification_day'] ) && isset( $days[ $settings['notification_day'] ] )
			? $settings['notification_day']
			: 'Monday';
		$days_ahead = ( $days[ $day ] - (int) $now->format( 'N' ) + 7 ) % 7;

		if ( $days_ahead > 0 ) {
			$target = $target->modify( '+' . $days_ahead . ' days' );
		} elseif ( $target <= $now ) {
			$target = $target->modify( '+7 days' );
		}

		return $target->getTimestamp();
	}
}
