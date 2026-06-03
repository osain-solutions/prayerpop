<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General settings.
 */
class Prayer_Pop_Settings_General {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'prayer_pop_settings_group', 'prayer_pop_general_settings', array( $this, 'sanitize_settings' ) );

		add_settings_section(
			'prayer_pop_general_section',
			'<span class="dashicons dashicons-admin-generic"></span> ' . esc_html__( 'General Settings', 'prayerpop' ),
			null,
			'prayer-pop-settings-general'
		);

		add_settings_field(
			'show_prayer_pop_bubble',
			esc_html__( 'Show PrayerPop Bubble', 'prayerpop' ),
			array( $this, 'toggle_callback' ),
			'prayer-pop-settings-general',
			'prayer_pop_general_section',
			array(
				'id'          => 'show_prayer_pop_bubble',
				'default'     => 1,
				'description' => esc_html__( 'Show the PrayerPop bubble at the bottom right corner of the site.', 'prayerpop' ),
			)
		);

		add_settings_field(
			'allow_anonymous',
			esc_html__( 'Anonymous Submissions', 'prayerpop' ),
			array( $this, 'toggle_callback' ),
			'prayer-pop-settings-general',
			'prayer_pop_general_section',
			array(
				'id'          => 'allow_anonymous',
				'default'     => 1,
				'description' => esc_html__( 'Allow users to submit without providing a name', 'prayerpop' ),
			)
		);

		add_settings_field(
			'admin_approval_note',
			esc_html__( 'Admin Approval', 'prayerpop' ),
			array( $this, 'admin_approval_note_callback' ),
			'prayer-pop-settings-general',
			'prayer_pop_general_section'
		);

		add_settings_field(
			'retention_period',
			esc_html__( 'Retention Period', 'prayerpop' ),
			array( $this, 'retention_period_callback' ),
			'prayer-pop-settings-general',
			'prayer_pop_general_section'
		);
	}

	/**
	 * Sanitize settings.
	 */
	public function sanitize_settings( $input ) {
		$input     = wp_unslash( $input );
		$existing  = get_option( 'prayer_pop_general_settings', array() );
		$sanitized = is_array( $existing ) ? $existing : array();

		$sanitized['show_prayer_pop_bubble']      = isset( $input['show_prayer_pop_bubble'] ) ? 1 : 0;
		$sanitized['allow_anonymous']             = isset( $input['allow_anonymous'] ) ? 1 : 0;
		$sanitized['retention_period']            = isset( $input['retention_period'] ) ? absint( $input['retention_period'] ) : 0;
		$sanitized['require_admin_approval']      = 1;

		return $sanitized;
	}

	/**
	 * Render fixed admin-approval note.
	 */
	public function admin_approval_note_callback() {
		echo '<p class="description">' . esc_html__( 'Every prayer request starts in Pending Action and requires manual admin review.', 'prayerpop' ) . '</p>';
	}

	/**
	 * Toggle callback.
	 */
	public function toggle_callback( $args ) {
		$options       = get_option( 'prayer_pop_general_settings', array() );
		$default_value = isset( $args['default'] ) ? (int) $args['default'] : 0;
		$value         = isset( $options[ $args['id'] ] ) ? (int) $options[ $args['id'] ] : $default_value;
		$input_id      = 'prayer_pop_general_settings_' . sanitize_key( $args['id'] );

		echo '<div class="prayer-pop-toggle-wrapper">';
		echo '<label class="prayer-pop-toggle-switch">';
		echo '<input type="checkbox" id="' . esc_attr( $input_id ) . '" name="prayer_pop_general_settings[' . esc_attr( $args['id'] ) . ']" value="1" ' . checked( 1, $value, false ) . '>';
		echo '<span class="prayer-pop-toggle-slider"></span>';
		echo '<span class="toggle-status">' . ( $value ? esc_html__( 'On', 'prayerpop' ) : esc_html__( 'Off', 'prayerpop' ) ) . '</span>';
		echo '</label>';

		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Retention field.
	 */
	public function retention_period_callback() {
		$options = get_option( 'prayer_pop_general_settings', array() );
		$value   = isset( $options['retention_period'] ) ? absint( $options['retention_period'] ) : 0;

		echo '<input type="number" min="0" step="1" class="small-text" name="prayer_pop_general_settings[retention_period]" value="' . esc_attr( $value ) . '"> ';
		echo esc_html__( 'days', 'prayerpop' );
		echo '<p class="description">' . esc_html__( '0 disables automatic retention cleanup.', 'prayerpop' ) . '</p>';
	}
}
