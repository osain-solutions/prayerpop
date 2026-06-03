<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Ajax
 *
 * This class handles AJAX requests for the PrayerPop plugin.
 *
 * @package     PRAYERPOP
 * @since       1.0.0
 */
class Prayer_Pop_Ajax {

	/**
	 * Maximum message length in characters.
	 *
	 * @since 1.5.1
	 */
	const MAX_MESSAGE_LENGTH = 5000;

	/**
	 * Maximum name length in characters.
	 *
	 * @since 1.5.1
	 */
	const MAX_NAME_LENGTH = 24;

	/**
	 * Minimum time in seconds before allowing submission (spam protection).
	 *
	 * @since 1.5.1
	 */
	const MIN_SUBMISSION_TIME = 2;

	/**
	 * Maximum number of submissions allowed per rate-limit window.
	 *
	 * @since 1.5.1
	 */
	const SUBMISSION_RATE_LIMIT_MAX = 5;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @since 1.5.1
	 */
	const SUBMISSION_RATE_LIMIT_WINDOW = 300;

	/**
	 * Cooldown time (seconds) after rate limit is exceeded.
	 *
	 * @since 1.5.1
	 */
	const SUBMISSION_RATE_LIMIT_COOLDOWN = 180;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Handle form submission.
		add_action( 'wp_ajax_prayer_pop_submit', array( $this, 'handle_prayer_submission' ) );
		add_action( 'wp_ajax_nopriv_prayer_pop_submit', array( $this, 'handle_prayer_submission' ) );

		// This build registers only the prayer request form submission endpoint.
	}

	/**
	 * Handle prayer submission via AJAX.
	 */
	public function handle_prayer_submission() {
		// Verify the nonce for security.
		check_ajax_referer( 'prayer_pop_nonce', 'nonce' );

		// Parse the serialized form payload.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified by check_ajax_referer() above.
		$raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$data     = is_string( $raw_data ) ? $raw_data : '';
		if ( '' === $data ) {
			wp_send_json_error( esc_html__( 'Invalid form data.', 'prayerpop' ) );
		}
		parse_str( $data, $form_data );

		// Basic spam protection: honeypot + time-based check.
		$honeypot   = isset( $form_data['prayer_pop_honeypot'] ) ? trim( (string) $form_data['prayer_pop_honeypot'] ) : '';
		$start_time = isset( $form_data['prayer_pop_start_time'] ) ? intval( $form_data['prayer_pop_start_time'] ) : 0;

		// If honeypot field is filled, treat as spam and fail silently with a generic error.
		if ( '' !== $honeypot ) {
			wp_send_json_error( esc_html__( 'There was an error processing your request.', 'prayerpop' ) );
		}

		// Time-based spam check: submissions that arrive too quickly after the form is shown.
		if ( $start_time > 0 ) {
			// Use a UTC-based timestamp to match the JavaScript Date.now() / 1000 value.
			$now   = current_time( 'timestamp', true );
			$delta = $now - $start_time;

			// Only treat as spam if the difference is non-negative and very small.
			if ( $delta >= 0 && $delta < self::MIN_SUBMISSION_TIME ) {
				wp_send_json_error( esc_html__( 'There was an error processing your request.', 'prayerpop' ) );
			}
		}

		$rate_limit = $this->register_submission_attempt_and_check_limit();
		if ( ! empty( $rate_limit['limited'] ) ) {
			wp_send_json_error(
				Prayer_Pop_Defaults::get_text(
					'text_error_rate_limit',
					esc_html__( 'Too many submissions right now. Please wait a few minutes and try again.', 'prayerpop' )
				)
			);
		}

		// Retrieve and sanitize form data with enhanced security
		$type          = 'prayer_request';
		$message       = isset( $form_data['prayer_pop_message'] ) ? sanitize_textarea_field( $form_data['prayer_pop_message'] ) : '';
		$name          = isset( $form_data['prayer_pop_name'] ) ? sanitize_text_field( $form_data['prayer_pop_name'] ) : '';
		$message       = $this->normalize_utf8_text( $message );
		$name          = $this->normalize_utf8_text( $name );
		$is_anonymous  = Prayer_Pop_Defaults::is_anonymous_submission_name( 0, $name );
		$is_public     = '1';
		$ready_to_share = '0';

		if ( empty( trim( $message ) ) ) {
			wp_send_json_error( esc_html__( 'Message cannot be empty.', 'prayerpop' ) );
		}

		// Limit message length to prevent abuse
		if ( $this->utf8_strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			wp_send_json_error(
				sprintf(
					/* translators: %d: maximum number of allowed characters. */
					esc_html__( 'Message is too long. Maximum %d characters allowed.', 'prayerpop' ),
					self::MAX_MESSAGE_LENGTH
				)
			);
		}

		// Limit name length for display consistency for non-anonymous names.
		if ( ! $is_anonymous && $this->utf8_strlen( $name ) > self::MAX_NAME_LENGTH ) {
			wp_send_json_error(
				sprintf(
					/* translators: %d: maximum number of allowed characters. */
					esc_html__( 'Name is too long. Maximum %d characters allowed.', 'prayerpop' ),
					self::MAX_NAME_LENGTH
				)
			);
		}

		if ( ! $is_anonymous ) {
			$name_validation_error = $this->validate_submission_name( $name );
			if ( is_wp_error( $name_validation_error ) ) {
				wp_send_json_error( $name_validation_error->get_error_message() );
			}
		}

		// Additional sanitization for message content (allow basic formatting but remove scripts)
		$message = wp_kses( $message, array(
			'br' => array(),
			'p' => array(),
			'strong' => array(),
			'em' => array(),
			'b' => array(),
			'i' => array()
		) );

		$anonymous_text = Prayer_Pop_Defaults::get_anonymous_text();
		$name           = $this->normalize_utf8_text( $name );
		$stored_name    = $is_anonymous ? Prayer_Pop_Defaults::ANONYMOUS_NAME_MARKER : $name;
		$display_name   = $is_anonymous ? $anonymous_text : $name;

                // Determine post status based on settings using cache
		$post_status       = 'pending';

                // Create a new custom post with proper timezone handling
                $current_time = current_time( 'mysql' );
                $post_id = wp_insert_post( array(
                        'post_title'   => $display_name,
                        'post_content' => $message,
                        'post_type'    => 'prayer_request',
                        'post_status'  => $post_status,
                        'post_date'    => $current_time,
                        'post_date_gmt' => get_gmt_from_date( $current_time ),
                ) );

		// Check for errors in post creation
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( esc_html__( 'Failed to submit your request. Please try again.', 'prayerpop' ) );
		}

		// Store additional meta data
		update_post_meta( $post_id, 'prayer_pop_name', $stored_name );
		update_post_meta( $post_id, Prayer_Pop_Defaults::ANONYMOUS_FLAG_META_KEY, $is_anonymous ? '1' : '0' );
		update_post_meta( $post_id, 'prayer_pop_public', $is_public );
		update_post_meta( $post_id, 'prayer_pop_type', $type );
		update_post_meta( $post_id, 'prayer_pop_ready_to_share', $ready_to_share );

		// Engagement counters are not used in this build.

		// Only update "last submission" when the item is publicly visible.
		$final_status         = (string) get_post_status( $post_id );
		$is_public_submission = ( '1' === (string) get_post_meta( $post_id, 'prayer_pop_public', true ) );
		$is_publicly_visible  = $is_public_submission && in_array( $final_status, array( 'approved', 'answered' ), true );
		$response_timestamp   = 0;
		if ( $is_publicly_visible ) {
			$response_timestamp = absint( get_post_time( 'U', true, $post_id ) );
			$this->update_last_public_submission_time( $type, $response_timestamp );
		}

		// Get the appropriate message template using centralized texts
		$message_template = Prayer_Pop_Defaults::get_text(
			'text_last_prayer_time_message',
			__( 'Last prayer request was submitted {time_ago} ago', 'prayerpop' )
		);

		// Get success message
		$success_message = Prayer_Pop_Defaults::get_text( 'text_success_message', __('Thank you for your submission!', 'prayerpop' ) );

		// Schedule immediate notification if enabled (asynchronous)
		$this->schedule_immediate_notification( $post_id, $type, $display_name, $message );

		// Send response with all necessary data
		wp_send_json_success( array(
			'message'         => $success_message,
			'timestamp'       => $response_timestamp,
			'type'            => $type,
			'message_template' => $message_template,
			'post_id'         => $post_id
		) );
	}

	/**
	 * Update "last submission" option for the given submission type.
	 *
	 * @param string $type      Submission type.
	 * @param int    $timestamp Local site timestamp.
	 * @return void
	 */
	private function update_last_public_submission_time( $type, $timestamp ) {
		$timestamp = absint( $timestamp );
		if ( $timestamp <= 0 ) {
			return;
		}

		if ( 'prayer_request' === $type ) {
			update_option( 'prayer_pop_last_prayer_time', $timestamp );
		}
	}

	/**
	 * Register current submission attempt and return rate-limit state.
	 *
	 * @return array{limited:bool,retry_after:int}
	 */
	private function register_submission_attempt_and_check_limit() {
		$identity = $this->get_submission_rate_limit_identity();
		if ( '' === $identity ) {
			return array(
				'limited'     => false,
				'retry_after' => 0,
			);
		}

		$key        = 'prayer_pop_submit_rl_' . substr( hash( 'sha256', $identity ), 0, 24 );
		$window      = self::SUBMISSION_RATE_LIMIT_WINDOW;
		$cooldown    = self::SUBMISSION_RATE_LIMIT_COOLDOWN;
		$max_hits    = self::SUBMISSION_RATE_LIMIT_MAX;
		$now         = time();
		$threshold   = $now - $window;
		$cooldown_key  = $key . '_cooldown_until';
		$cooldown_until = absint( get_transient( $cooldown_key ) );
		if ( $cooldown_until > $now ) {
			return array(
				'limited'     => true,
				'retry_after' => $cooldown_until - $now,
			);
		}

		// Cooldown expired: clear counters so a fresh 5-submission window starts.
		if ( $cooldown_until > 0 ) {
			delete_transient( $cooldown_key );
			delete_transient( $key );
		}

		$stored_hits = get_transient( $key );
		$hits       = array();

		if ( is_array( $stored_hits ) ) {
			foreach ( $stored_hits as $hit_ts ) {
				$hit_ts = absint( $hit_ts );
				if ( $hit_ts > $threshold ) {
					$hits[] = $hit_ts;
				}
			}
		}

		if ( count( $hits ) >= $max_hits ) {
			$retry_after = $cooldown;
			set_transient( $cooldown_key, $now + $cooldown, $cooldown );
			set_transient( $key, $hits, $window );
			return array(
				'limited'     => true,
				'retry_after' => $retry_after,
			);
		}

		$hits[] = $now;
		set_transient( $key, $hits, $window );
		return array(
			'limited'     => false,
			'retry_after' => 0,
		);
	}

	/**
	 * Build a stable submission rate-limit identity.
	 *
	 * @return string
	 */
	private function get_submission_rate_limit_identity() {
		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return '';
		}

		return $ip;
	}

	/**
	 * Resolve client IP from trusted server headers.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$candidates = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $candidates as $server_key ) {
			if ( empty( $_SERVER[ $server_key ] ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $server_key ] ) );
			if ( '' === $raw ) {
				continue;
			}

			if ( 'HTTP_X_FORWARDED_FOR' === $server_key ) {
				$parts = explode( ',', $raw );
				$raw   = trim( (string) $parts[0] );
			}

			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return $raw;
			}
		}

		return '';
	}

	/**
	 * Schedule immediate notification if enabled (asynchronous).
	 */
	private function schedule_immediate_notification( $post_id, $type, $name, $message ) {
		// Get notification settings
		$notification_options = get_option( 'prayer_pop_notification_settings', array() );
		
		// Check if notifications are enabled and set to immediate
		if ( ! isset( $notification_options['enable_notifications'] ) || ! $notification_options['enable_notifications'] ) {
			return;
		}

		if ( ! isset( $notification_options['notification_frequency'] ) || $notification_options['notification_frequency'] !== 'immediately' ) {
			return;
		}

		// Schedule the email to be sent asynchronously.
		// Note: wp_schedule_single_event expects a numerically indexed array of arguments
		// that will be passed positionally to the callback (send_immediate_notification).
		wp_schedule_single_event(
			time(),
			'prayer_pop_send_immediate_notification',
			array(
				absint( $post_id ),
				sanitize_text_field( $type ),
				sanitize_text_field( $name ),
				wp_kses_post( $message ),
			)
		);
	}

	/**
	 * UTF-8-safe string length helper.
	 *
	 * @param string $text Input text.
	 * @return int
	 */
	private function utf8_strlen( $text ) {
		$text = (string) $text;
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text, 'UTF-8' );
		}

		return strlen( $text );
	}

	/**
	 * Normalize UTF-8 text to NFC when intl extension is available.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	private function normalize_utf8_text( $text ) {
		$text = (string) $text;
		if ( class_exists( 'Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			if ( false !== $normalized ) {
				return $normalized;
			}
		}

		return $text;
	}

	/**
	 * Validate user-provided submission name.
	 *
	 * Name field must contain a person name only (no sentences, promo text, or famous persona names).
	 *
	 * @param string $name Raw submitted name.
	 * @return true|WP_Error
	 */
	private function validate_submission_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			return true;
		}

		// Allow letters/diacritics, spaces, apostrophes, hyphens, and periods for initials.
		if ( ! preg_match( "/^[\\p{L}\\p{M} .'-]+$/u", $name ) ) {
			return new WP_Error(
				'invalid_name_format',
				$this->get_invalid_name_error_text()
			);
		}

		$comparison = $this->normalize_name_for_comparison( $name );

		$famous_name_patterns = array(
			'/\bvladimir\s+putin\b/u',
			'/\b(?:joseph|joosep)\s*f\s+(?:stalin|sdaalin)\b/u',
			'/\bstalin\p{L}{0,4}\b/u',
			'/\bsdaalin\p{L}{0,4}\b/u',
			'/\bhitler\p{L}{0,4}\b/u',
			'/\bputin\p{L}{0,4}\b/u',
			'/\bmafia\p{L}{0,4}\b/u',
			'/\bcartel\p{L}{0,4}\b/u',
			'/\btaliban\p{L}{0,4}\b/u',
			'/\bisis\p{L}{0,4}\b/u',
			'/\bleonardo\s+dicaprio\b/u',
			'/\bed\s+sheeran\b/u',
		);
		foreach ( $famous_name_patterns as $pattern ) {
			if ( preg_match( $pattern, $comparison ) ) {
				return new WP_Error(
					'invalid_name_famous',
					$this->get_invalid_name_error_text()
				);
			}
		}

		$tokens = preg_split( '/\s+/u', $comparison, -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $tokens ) || count( $tokens ) > 4 ) {
			return new WP_Error(
				'invalid_name_tokens',
				$this->get_invalid_name_error_text()
			);
		}

		$sentence_markers = array(
			'i', 'my', 'me', 'our', 'we', 'please', 'need', 'want', 'pray', 'prayer', 'for', 'because', 'is', 'am', 'and',
			'neighbor', 'neighbour', 'crazy', 'money', 'help',
			'palun', 'palvetage', 'palve', 'tahan', 'vajan', 'minu', 'meie', 'on', 'et', 'aga', 'kuna', 'eest',
		);
		$marker_hits      = array_intersect( $sentence_markers, $tokens );
		if ( count( $marker_hits ) >= 1 && count( $tokens ) >= 2 ) {
			return new WP_Error(
				'invalid_name_content',
				$this->get_invalid_name_error_text()
			);
		}

		$letters_total = preg_match_all( '/\p{L}/u', $name, $all_letters );
		$upper_total   = preg_match_all( '/\p{Lu}/u', $name, $upper_letters );
		if ( $letters_total >= 6 && $upper_total >= 5 && ( $upper_total / $letters_total ) > 0.8 && count( $tokens ) >= 2 ) {
			return new WP_Error(
				'invalid_name_shouting',
				$this->get_invalid_name_error_text()
			);
		}

		return true;
	}

	/**
	 * Normalize name for content checks (lowercase, spaces only).
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	private function normalize_name_for_comparison( $name ) {
		$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $name, 'UTF-8' ) : strtolower( (string) $name );
		$name = preg_replace( "/[.'-]+/u", ' ', $name );
		$name = preg_replace( '/\s+/u', ' ', (string) $name );
		return trim( (string) $name );
	}

	/**
	 * Get customizable invalid-name error text.
	 *
	 * @return string
	 */
	private function get_invalid_name_error_text() {
		return (string) Prayer_Pop_Defaults::get_text(
			'text_error_invalid_name',
			esc_html__( 'Please use only a real person name in the name field.', 'prayerpop' )
		);
	}
}
new Prayer_Pop_Ajax();
