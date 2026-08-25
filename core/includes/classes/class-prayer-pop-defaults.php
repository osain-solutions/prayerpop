<?php
/**
 * Prayer_Pop_Defaults Class
 *
 * Centralized default values and caching for PrayerPop plugin.
 * Single source of truth for all default text strings and settings.
 *
 * @package     PRAYERPOP
 * @since       1.5.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Defaults
 *
 * Provides default values and caching for plugin options.
 */
class Prayer_Pop_Defaults {
	/**
	 * Canonical stored value for anonymous names.
	 */
	const ANONYMOUS_NAME_MARKER = '__prayer_pop_anonymous__';

	/**
	 * Meta key storing whether a submission is anonymous.
	 */
	const ANONYMOUS_FLAG_META_KEY = 'prayer_pop_is_anonymous';

	/**
	 * Cached texts to avoid repeated get_option calls.
	 *
	 * @var array|null
	 */
	private static $texts_cache = null;

	/**
	 * Cached general settings to avoid repeated get_option calls.
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	/**
	 * Cached style settings to avoid repeated get_option calls.
	 *
	 * @var array|null
	 */
	private static $styles_cache = null;

	/**
	 * Get default text strings.
	 *
	 * Single source of truth for all translatable text strings.
	 *
	 * @return array Default text strings with translation functions.
	 * @since 1.5.1
	 */
	public static function get_default_texts() {
		return array(
			// Bubble and Main Menu
			'text_bubble_label'               => __( 'PrayerPop', 'prayerpop' ),
			'text_bubble_icon_alt'            => __( 'PrayerPop icon', 'prayerpop' ),
			'text_prayer_request_label'       => __( 'Prayer request', 'prayerpop' ),
			'text_testimony_label'            => __( 'Share a testimony', 'prayerpop' ),
			'text_chat_button'                => __( 'Send us a message', 'prayerpop' ),
			'text_back_button'                => __( 'Back', 'prayerpop' ),
			'text_chat_team_help'             => __( 'The team can also help', 'prayerpop' ),
			'text_chat_reply_time'            => __( 'Replies within a day', 'prayerpop' ),
			'text_chat_step_one'              => __( '1 of 3', 'prayerpop' ),
			'text_chat_step_two'              => __( '2 of 3', 'prayerpop' ),
			'text_chat_step_three'            => __( '3 of 3', 'prayerpop' ),
			'text_chat_name_prompt'           => __( 'Before we start, what’s your name?', 'prayerpop' ),
			'text_chat_name_placeholder'      => __( 'Type your name', 'prayerpop' ),
			'text_chat_thanks'                => __( 'Thanks,', 'prayerpop' ),
			'text_chat_email_prompt'          => __( 'Where should we send reply notifications?', 'prayerpop' ),
			'text_chat_email_placeholder'     => __( 'Email address (optional)', 'prayerpop' ),
			'text_chat_skip'                  => __( 'Skip for now', 'prayerpop' ),
			'text_chat_message_prompt'        => __( 'How can we help?', 'prayerpop' ),
			'text_chat_message_placeholder'   => __( 'Write your message…', 'prayerpop' ),
			'text_chat_continue_label'        => __( 'Continue', 'prayerpop' ),
			'text_chat_send_label'            => __( 'Send message', 'prayerpop' ),
			'text_chat_closed'                => __( 'This conversation is closed.', 'prayerpop' ),
			'text_chat_new_conversation'       => __( 'Start a new conversation', 'prayerpop' ),

			// Headers and Descriptions
			'text_popup_intro_title'          => __( 'Hi there, prayer warrior!', 'prayerpop' ),
			'text_popup_intro_description'    => __( 'Send us a message and we will get back to you.', 'prayerpop' ),
			'text_prayer_request_header'      => __( 'Submit a Prayer Request', 'prayerpop' ),
			'text_prayer_request_description' => __( 'Please fill out the form below to submit your prayer request.', 'prayerpop' ),
			'text_testimony_header'            => __( 'Share a testimony', 'prayerpop' ),
			'text_testimony_description'       => __( 'We would love to hear what God has done in your life.', 'prayerpop' ),

			// Form Fields
			'text_message_placeholder'        => __( 'Enter your message...', 'prayerpop' ),
			'text_testimony_message_placeholder' => __( 'Share your testimony…', 'prayerpop' ),
			'text_name_placeholder'           => __( 'Your Name (optional)', 'prayerpop' ),
			'text_name_placeholder_required'  => __( 'Your Name', 'prayerpop' ),
			'text_submit_button'              => __( 'Submit', 'prayerpop' ),
			'text_testimony_submit_button'    => __( 'Share testimony', 'prayerpop' ),
			'text_submitting_button'          => __( 'Sending...', 'prayerpop' ),
			'text_anonymous'                  => __( 'Anonymous', 'prayerpop' ),
			'text_honeypot_label'             => __( 'Leave this field empty', 'prayerpop' ),

			// Messages
			'text_success_message'            => __( 'Thank you for your submission!', 'prayerpop' ),
			'text_testimony_success_message'  => __( 'Thank you for sharing your testimony!', 'prayerpop' ),
			'text_error_message'              => __( 'There was an error processing your request.', 'prayerpop' ),
			'text_error_rate_limit'           => __( 'Too many submissions right now. Please wait a few minutes and try again.', 'prayerpop' ),
			'text_error_invalid_name'         => __( 'Please use only a real person name in the name field.', 'prayerpop' ),
			'text_new_request_button'         => __( 'Send One More', 'prayerpop' ),
			'text_required_field'             => __( 'Please fill out this field', 'prayerpop' ),
			'text_preview_permission_error'   => __( 'You do not have permission to view this preview.', 'prayerpop' ),
			'text_preview_invalid_token'      => __( 'Invalid preview token.', 'prayerpop' ),

			// Last Time Messages
			'text_last_prayer_time_message'   => __( 'Last prayer request: {time_ago} ago', 'prayerpop' ),

			// Time Units
			'text_time_unit_second_singular'  => __( 'second', 'prayerpop' ),
			'text_time_unit_second_plural'    => __( 'seconds', 'prayerpop' ),
			'text_time_unit_minute_singular'  => __( 'minute', 'prayerpop' ),
			'text_time_unit_minute_plural'    => __( 'minutes', 'prayerpop' ),
			'text_time_unit_hour_singular'    => __( 'hour', 'prayerpop' ),
			'text_time_unit_hour_plural'      => __( 'hours', 'prayerpop' ),
			'text_time_unit_day_singular'     => __( 'day', 'prayerpop' ),
			'text_time_unit_day_plural'       => __( 'days', 'prayerpop' ),

			'text_answered_message_label'     => __( 'Answer Update', 'prayerpop' ),
		);
	}

	/**
	 * Get text strings with caching.
	 *
	 * Retrieves custom texts merged with defaults. Uses caching to avoid
	 * repeated database queries within the same request.
	 *
	 * @return array Merged text strings (custom overrides defaults).
	 * @since 1.5.1
	 */
	public static function get_texts() {
		if ( self::$texts_cache === null ) {
			$custom_texts = get_option( 'prayer_pop_texts', array() );
			if ( isset( $custom_texts['text_prayer_request_label'] ) && 'Prayer Request' === $custom_texts['text_prayer_request_label'] ) {
				$custom_texts['text_prayer_request_label'] = __( 'Prayer request', 'prayerpop' );
			}
			if ( isset( $custom_texts['text_last_prayer_time_message'] ) && in_array( $custom_texts['text_last_prayer_time_message'], array( 'Last prayer request was submitted {time_ago} ago', 'Last prayer request was submitted {time_ago} ago.' ), true ) ) {
				$custom_texts['text_last_prayer_time_message'] = __( 'Last prayer request: {time_ago} ago', 'prayerpop' );
			}
			$defaults     = self::get_default_texts();
			self::$texts_cache = wp_parse_args( $custom_texts, $defaults );
		}
		return self::$texts_cache;
	}

	/**
	 * Get a single text string.
	 *
	 * @param string $key     The text key to retrieve.
	 * @param string $default Optional fallback if key doesn't exist.
	 * @return string The text string.
	 * @since 1.5.1
	 */
	public static function get_text( $key, $default = '' ) {
		$texts = self::get_texts();
		return isset( $texts[ $key ] ) ? $texts[ $key ] : $default;
	}

	/**
	 * Get the currently configured anonymous label.
	 *
	 * @return string
	 */
	public static function get_anonymous_text() {
		return (string) self::get_text( 'text_anonymous', esc_html__( 'Anonymous', 'prayerpop' ) );
	}

	/**
	 * Determine if a stored name value should be treated as anonymous.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $raw_name  Stored raw name value.
	 * @return bool
	 */
	public static function is_anonymous_submission_name( $post_id, $raw_name ) {
		$raw_name = trim( (string) $raw_name );

		if ( '' === $raw_name || self::ANONYMOUS_NAME_MARKER === $raw_name ) {
			return true;
		}

		if ( $post_id > 0 && '1' === (string) get_post_meta( $post_id, self::ANONYMOUS_FLAG_META_KEY, true ) ) {
			return true;
		}

		$current_anonymous = self::get_anonymous_text();
		$defaults_raw      = self::get_default_texts_raw();
		$default_anonymous = isset( $defaults_raw['text_anonymous'] ) ? (string) $defaults_raw['text_anonymous'] : 'Anonymous';

		return in_array(
			$raw_name,
			array(
				$current_anonymous,
				$default_anonymous,
				(string) esc_html__( 'Anonymous', 'prayerpop' ),
			),
			true
		);
	}

	/**
	 * Resolve the display name for a submission using current text customization.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $raw_name Optional raw name value to avoid a second query.
	 * @return string
	 */
	public static function get_submission_display_name( $post_id, $raw_name = '' ) {
		if ( '' === $raw_name ) {
			$raw_name = (string) get_post_meta( $post_id, 'prayer_pop_name', true );
		}

		if ( self::is_anonymous_submission_name( $post_id, $raw_name ) ) {
			return self::get_anonymous_text();
		}

		return (string) $raw_name;
	}

	/**
	 * Get general settings with caching.
	 *
	 * @return array General settings.
	 * @since 1.5.1
	 */
	public static function get_settings() {
		if ( self::$settings_cache === null ) {
			self::$settings_cache = wp_parse_args(
				get_option( 'prayer_pop_general_settings', array() ),
				array(
					'show_prayer_pop_bubble' => 1,
					'show_prayer_request_button' => 1,
					'show_testimony_button'  => 1,
					'popup_intro_enabled'    => 1,
					'popup_intro_image_id'   => 0,
					'allow_anonymous'        => 1,
					'require_admin_approval' => 1,
					'retention_period'       => 0,
				)
			);
		}
		return self::$settings_cache;
	}

	/**
	 * Get style settings with caching.
	 *
	 * @return array Style settings.
	 * @since 1.5.1
	 */
	public static function get_styles() {
		if ( self::$styles_cache === null ) {
			self::$styles_cache = wp_parse_args(
				get_option( 'prayer_pop_styles', array() ),
				array(
					'bubble_icon_type'   => 'dashicon',
					'bubble_dashicon'    => 'prayerpop',
					'bubble_design_mode' => 'fixed_circle',
					'bubble_layout'      => 'icon',
					'bubble_position'    => 'right',
					'bubble_offset_x'    => '0px',
					'bubble_offset_y'    => '0px',
					'bubble_animation'   => 'gentle-rise',
					'bubble_icon_color'  => '#ffffff',
					'bubble_icon_size'   => 170,
				)
			);
		}
		return self::$styles_cache;
	}

	/**
	 * Return the supported popup motion presets.
	 *
	 * @return array<int, string>
	 */
	public static function get_popup_animation_presets() {
		return array( 'none', 'fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in' );
	}

	/**
	 * Return a normalized popup motion preset.
	 *
	 * @param array|null $styles Optional style settings.
	 * @return string
	 */
	public static function get_popup_animation( $styles = null ) {
		$styles    = is_array( $styles ) ? $styles : self::get_styles();
		$animation = isset( $styles['bubble_animation'] ) ? sanitize_key( $styles['bubble_animation'] ) : 'gentle-rise';

		return in_array( $animation, self::get_popup_animation_presets(), true ) ? $animation : 'gentle-rise';
	}

	/** Return safe color tokens for HTML email rendering. */
	public static function get_email_style_tokens() {
		$styles = self::get_styles();
		$color  = static function( $key, $fallback ) use ( $styles ) {
			$value = isset( $styles[ $key ] ) ? sanitize_hex_color( (string) $styles[ $key ] ) : false;
			return $value ? $value : $fallback;
		};

		return array(
			'primary'      => $color( 'global_bg_color', '#2755aa' ),
			'primary_text' => $color( 'global_font_color', '#ffffff' ),
			'border'       => $color( 'global_border_color', '#d7ddea' ),
			'surface'      => $color( 'global_textarea_bg_color', '#f8fafc' ),
			'text'         => $color( 'global_label_color', '#1f2937' ),
			'muted'        => '#5f6b7a',
			'page'         => '#f3f5fb',
			'card'         => '#ffffff',
		);
	}

	/**
	 * Clear all caches.
	 *
	 * Should be called when options are updated to ensure fresh data.
	 *
	 * @return void
	 * @since 1.5.1
	 */
	public static function clear_cache() {
		self::$texts_cache    = null;
		self::$settings_cache = null;
		self::$styles_cache   = null;
	}

	/**
	 * Get default texts for activation (without translation functions).
	 *
	 * Used during plugin activation when translation may not be loaded yet.
	 *
	 * @return array Default text strings without translation functions.
	 * @since 1.5.1
	 */
	public static function get_default_texts_raw() {
		return array(
			'text_bubble_label'               => 'PrayerPop',
			'text_bubble_icon_alt'            => 'PrayerPop icon',
			'text_prayer_request_label'       => 'Prayer request',
			'text_testimony_label'            => 'Share a testimony',
			'text_chat_button'                => 'Send us a message',
			'text_back_button'                => 'Back',
			'text_chat_team_help'             => 'The team can also help',
			'text_chat_reply_time'            => 'Replies within a day',
			'text_chat_step_one'              => '1 of 3',
			'text_chat_step_two'              => '2 of 3',
			'text_chat_step_three'            => '3 of 3',
			'text_chat_name_prompt'           => 'Before we start, what’s your name?',
			'text_chat_name_placeholder'      => 'Type your name',
			'text_chat_thanks'                => 'Thanks,',
			'text_chat_email_prompt'          => 'Where should we send reply notifications?',
			'text_chat_email_placeholder'     => 'Email address (optional)',
			'text_chat_skip'                  => 'Skip for now',
			'text_chat_message_prompt'        => 'How can we help?',
			'text_chat_message_placeholder'   => 'Write your message…',
			'text_chat_continue_label'        => 'Continue',
			'text_chat_send_label'            => 'Send message',
			'text_chat_closed'                => 'This conversation is closed.',
			'text_chat_new_conversation'       => 'Start a new conversation',
			'text_popup_intro_title'          => 'Hi there, prayer warrior!',
			'text_popup_intro_description'    => 'Send us a message and we will get back to you.',
			'text_prayer_request_header'      => 'Submit a Prayer Request',
			'text_prayer_request_description' => 'Please fill out the form below to submit your prayer request.',
			'text_testimony_header'            => 'Share a testimony',
			'text_testimony_description'       => 'We would love to hear what God has done in your life.',
			'text_message_placeholder'        => 'Enter your message...',
			'text_testimony_message_placeholder' => 'Share your testimony…',
			'text_name_placeholder'           => 'Your Name (optional)',
			'text_name_placeholder_required'  => 'Your Name',
			'text_submit_button'              => 'Submit',
			'text_testimony_submit_button'    => 'Share testimony',
			'text_submitting_button'          => 'Sending...',
			'text_anonymous'                  => 'Anonymous',
			'text_honeypot_label'             => 'Leave this field empty',
			'text_success_message'            => 'Thank you for your submission!',
			'text_testimony_success_message'  => 'Thank you for sharing your testimony!',
			'text_error_message'              => 'There was an error processing your request.',
			'text_error_rate_limit'           => 'Too many submissions right now. Please wait a few minutes and try again.',
			'text_error_invalid_name'         => 'Please use only a real person name in the name field.',
			'text_new_request_button'         => 'Send One More',
			'text_required_field'             => 'Please fill out this field',
			'text_preview_permission_error'   => 'You do not have permission to view this preview.',
			'text_preview_invalid_token'      => 'Invalid preview token.',
			'text_last_prayer_time_message'   => 'Last prayer request: {time_ago} ago',
			'text_time_unit_second_singular'  => 'second',
			'text_time_unit_second_plural'    => 'seconds',
			'text_time_unit_minute_singular'  => 'minute',
			'text_time_unit_minute_plural'    => 'minutes',
			'text_time_unit_hour_singular'    => 'hour',
			'text_time_unit_hour_plural'      => 'hours',
			'text_time_unit_day_singular'     => 'day',
			'text_time_unit_day_plural'       => 'days',
			'text_answered_message_label'     => 'Answer Update',
		);
	}
}
