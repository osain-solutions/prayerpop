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
			'popup_intro_image_id',
			esc_html__( 'Welcome Image', 'prayerpop' ),
			array( $this, 'popup_intro_image_callback' ),
			'prayer-pop-settings-general',
			'prayer_pop_general_section'
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

		$popup_intro_image_id                    = isset( $input['popup_intro_image_id'] ) ? absint( $input['popup_intro_image_id'] ) : 0;
		$sanitized['show_prayer_pop_bubble']      = isset( $input['show_prayer_pop_bubble'] ) ? 1 : 0;
		$sanitized['popup_intro_image_id']        = $popup_intro_image_id && wp_attachment_is_image( $popup_intro_image_id ) ? $popup_intro_image_id : 0;
		$sanitized['allow_anonymous']             = isset( $input['allow_anonymous'] ) ? 1 : 0;
		$sanitized['retention_period']            = isset( $input['retention_period'] ) ? absint( $input['retention_period'] ) : 0;
		$sanitized['require_admin_approval']      = 1;

		return $sanitized;
	}

	/** Render the single Free welcome-image control. */
	public function popup_intro_image_callback() {
		$options  = get_option( 'prayer_pop_general_settings', array() );
		$image_id = isset( $options['popup_intro_image_id'] ) ? absint( $options['popup_intro_image_id'] ) : 0;
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="prayer-pop-free-intro-image-field">
			<input type="hidden" id="prayer_pop_general_settings_popup_intro_image_id" name="prayer_pop_general_settings[popup_intro_image_id]" value="<?php echo esc_attr( $image_id ); ?>">
			<div id="prayer-pop-free-intro-image-preview" class="prayer-pop-free-intro-image-preview<?php echo $image_url ? '' : ' is-empty'; ?>" data-empty-label="<?php esc_attr_e( 'No image selected', 'prayerpop' ); ?>">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="">
				<?php else : ?>
					<span><?php esc_html_e( 'No image selected', 'prayerpop' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="prayer-pop-free-intro-image-actions">
				<button type="button" class="button" id="prayer-pop-select-free-intro-image" data-frame-title="<?php esc_attr_e( 'Choose popup welcome image', 'prayerpop' ); ?>" data-frame-button="<?php esc_attr_e( 'Use this image', 'prayerpop' ); ?>"><?php esc_html_e( 'Choose Image', 'prayerpop' ); ?></button>
				<button type="button" class="button" id="prayer-pop-remove-free-intro-image"<?php echo $image_url ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove Image', 'prayerpop' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Choose the image shown behind the popup welcome text. A wide image works best.', 'prayerpop' ); ?></p>
		</div>
		<?php
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
		$options   = get_option( 'prayer_pop_general_settings', array() );
		$retention = isset( $options['retention_period'] ) ? absint( $options['retention_period'] ) : 0;
		$periods   = array(
			'7'   => esc_html__( '7 days', 'prayerpop' ),
			'30'  => esc_html__( '30 days', 'prayerpop' ),
			'90'  => esc_html__( '90 days', 'prayerpop' ),
			'180' => esc_html__( '180 days', 'prayerpop' ),
			'360' => esc_html__( '360 days', 'prayerpop' ),
			'720' => esc_html__( '720 days', 'prayerpop' ),
		);

		// Keep an existing custom period intact until the site owner deliberately chooses a standard option.
		if ( $retention && ! isset( $periods[ (string) $retention ] ) ) {
			$periods[ (string) $retention ] = sprintf(
				/* translators: %d: custom retention period in days. */
				esc_html__( '%d days (custom)', 'prayerpop' ),
				$retention
			);
		}

		$periods['0'] = esc_html__( 'Forever', 'prayerpop' );

		echo '<select name="prayer_pop_general_settings[retention_period]">';
		foreach ( $periods as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $retention, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Sets how long submissions are kept before automatic cleanup. Approved items are archived first, then archived items are removed after the retention window. Choose "Forever" to disable automatic deletion.', 'prayerpop' ) . '</p>';
	}
}
