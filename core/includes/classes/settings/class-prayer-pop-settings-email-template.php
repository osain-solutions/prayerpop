<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Settings_Email_Template
 *
 * Handles the Email Template Settings section.
 */
class Prayer_Pop_Settings_Email_Template {
	/**
	 * Get the human-readable description for each placeholder token, used as a tooltip.
	 *
	 * @return array<string,string>
	 */
	private function get_placeholder_descriptions() {
		return array(
			'{type}'          => __( 'Type of submission (Prayer Request or Testimony)', 'prayerpop' ),
			'{name}'          => __( 'Name of the person who submitted (or Anonymous)', 'prayerpop' ),
			'{message}'       => __( 'The submitted message content', 'prayerpop' ),
			'{pending_count}' => __( 'Number of submissions currently waiting for admin action/review', 'prayerpop' ),
			'{admin_url}'     => __( 'Direct link to manage submissions in admin', 'prayerpop' ),
			'{site_url}'      => __( 'Your website homepage URL', 'prayerpop' ),
			'{site_name}'     => __( 'Your website name', 'prayerpop' ),
		);
	}

	/**
	 * Get the very short, one-line summary of each placeholder for the combined tooltip.
	 *
	 * @return array<string,string>
	 */
	private function get_placeholder_short_descriptions() {
		return array(
			'{type}'          => __( 'Prayer Request or Testimony', 'prayerpop' ),
			'{name}'          => __( "submitter's name", 'prayerpop' ),
			'{message}'       => __( 'the message text', 'prayerpop' ),
			'{pending_count}' => __( '# waiting for review', 'prayerpop' ),
			'{admin_url}'     => __( 'link to admin', 'prayerpop' ),
			'{site_url}'      => __( 'your homepage URL', 'prayerpop' ),
			'{site_name}'     => __( 'your site name', 'prayerpop' ),
		);
	}

	/**
	 * Render a single info-tooltip icon summarizing every placeholder token.
	 *
	 * @return void
	 */
	private function render_placeholder_summary_tooltip() {
		$lines = array();
		foreach ( $this->get_placeholder_short_descriptions() as $placeholder => $summary ) {
			$lines[] = $placeholder . ' — ' . $summary;
		}
		?>
		<span class="prayer-pop-info-tooltip-wrap">
			<button type="button" class="prayer-pop-info-tooltip-button" aria-label="<?php esc_attr_e( 'What each placeholder means', 'prayerpop' ); ?>">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			</button>
			<span class="prayer-pop-info-tooltip-content prayer-pop-placeholder-summary-tooltip" role="tooltip"><?php echo esc_html( implode( "\n", $lines ) ); ?></span>
		</span>
		<?php
	}

	/**
	 * Render clickable placeholder buttons for a target field.
	 *
	 * @param string $target_field_id Field ID.
	 * @return void
	 */
	private function render_placeholder_insert_buttons( $target_field_id ) {
		$target_field_id = sanitize_key( (string) $target_field_id );
		$descriptions    = $this->get_placeholder_descriptions();
		?>
		<div class="prayer-pop-placeholder-insert-row">
			<span class="prayer-pop-placeholder-insert-label"><?php esc_html_e( 'Click to insert:', 'prayerpop' ); ?></span>
			<div class="prayer-pop-placeholder-chips">
				<?php foreach ( $descriptions as $placeholder => $description ) : ?>
					<button
						type="button"
						class="button button-secondary prayer-pop-insert-placeholder"
						data-target="#<?php echo esc_attr( $target_field_id ); ?>"
						data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
						title="<?php echo esc_attr( $description ); ?>"
					>
						<code><?php echo esc_html( $placeholder ); ?></code>
					</button>
				<?php endforeach; ?>
				<?php $this->render_placeholder_summary_tooltip(); ?>
			</div>
		</div>
		<?php
	}

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
		register_setting( 'prayer_pop_settings_group', 'prayer_pop_email_template', array( $this, 'sanitize_email_template' ) );

		add_settings_section(
			'prayer_pop_email_template_section',
			'',
			'__return_empty_string',
			'prayer-pop-settings-email-template'
		);

		add_settings_field(
			'email_subject',
			esc_html__( 'Email Subject', 'prayerpop' ),
			array( $this, 'email_subject_callback' ),
			'prayer-pop-settings-email-template',
			'prayer_pop_email_template_section'
		);

		add_settings_field(
			'email_body',
			esc_html__( 'Email Body', 'prayerpop' ),
			array( $this, 'email_body_callback' ),
			'prayer-pop-settings-email-template',
			'prayer_pop_email_template_section'
		);

		add_settings_field(
			'email_preview',
			esc_html__( 'Preview', 'prayerpop' ),
			array( $this, 'email_preview_callback' ),
			'prayer-pop-settings-email-template',
			'prayer_pop_email_template_section'
		);

		add_settings_field(
			'email_test_button',
			esc_html__( 'Send test', 'prayerpop' ),
			array( $this, 'email_test_button_callback' ),
			'prayer-pop-settings-email-template',
			'prayer_pop_email_template_section'
		);
	}

	/**
	 * Render test email button below the input fields.
	 */
	public function email_test_button_callback() {
		?>
		<button type="button" class="button button-secondary" id="prayer-pop-send-test-email"><?php esc_html_e( 'Send Test Email', 'prayerpop' ); ?></button>
		<p class="description" id="prayer-pop-test-email-result" aria-live="polite"></p>
		<?php
	}

	/**
	 * Sanitize email template.
	 */
	public function sanitize_email_template( $input ) {
		$reset_action = isset( $_POST['prayer_pop_reset_action'] )
			? sanitize_key( wp_unslash( $_POST['prayer_pop_reset_action'] ) )
			: '';
		if ( 'translations' === $reset_action ) {
			$existing = get_option( 'prayer_pop_email_template', array() );
			return is_array( $existing ) ? $existing : array();
		}

		$input = wp_unslash( $input );
		$sanitized = array();
		$sanitized['email_subject'] = isset( $input['email_subject'] ) ? sanitize_text_field( $input['email_subject'] ) : '';
		$sanitized['email_body']    = isset( $input['email_body'] ) ? wp_kses_post( $input['email_body'] ) : '';
		return $sanitized;
	}

	/**
	 * Sample values used to render the live preview with realistic data.
	 *
	 * @return array<string,string>
	 */
	private function get_preview_sample_values() {
		return array(
			'{type}'          => __( 'Prayer Request', 'prayerpop' ),
			'{name}'          => 'Maria',
			'{message}'       => __( "Please pray for my mother's surgery on Thursday.", 'prayerpop' ),
			'{pending_count}' => '3',
			'{admin_url}'     => __( '(link to submissions in admin)', 'prayerpop' ),
			'{site_url}'      => home_url( '/' ),
			'{site_name}'     => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Email subject callback.
	 */
	public function email_subject_callback() {
		$options = get_option( 'prayer_pop_email_template', array() );
		$subject = isset( $options['email_subject'] ) ? $options['email_subject'] : __( 'New PrayerPop Submission', 'prayerpop' );
		?>
			<input type="text" name="prayer_pop_email_template[email_subject]" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" id="email_subject_field">
		<?php $this->render_placeholder_insert_buttons( 'email_subject_field' ); ?>
		<?php
	}

	/**
	 * Email body callback.
	 */
	public function email_body_callback() {
		$options = get_option( 'prayer_pop_email_template', array() );
		$body = isset( $options['email_body'] ) ? $options['email_body'] : __( "Type: {type}\nName: {name}\nMessage:\n{message}", 'prayerpop' );
		?>
		<details class="prayer-pop-advanced-panel">
			<summary><?php esc_html_e( 'Customize email body', 'prayerpop' ); ?></summary>
			<div class="prayer-pop-advanced-panel__content">
				<textarea name="prayer_pop_email_template[email_body]" rows="10" class="large-text code" id="email_body_field"><?php echo esc_textarea( $body ); ?></textarea>
				<?php $this->render_placeholder_insert_buttons( 'email_body_field' ); ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Combined live preview of the subject and body, rendered with sample data and kept in sync by JS.
	 */
	public function email_preview_callback() {
		?>
		<div
			class="prayer-pop-email-preview"
			data-preview-subject="#email_subject_field"
			data-preview-body="#email_body_field"
			data-preview-values="<?php echo esc_attr( wp_json_encode( $this->get_preview_sample_values() ) ); ?>"
		></div>
		<p class="description"><?php esc_html_e( 'Shown with sample data so you can see how it reads before sending.', 'prayerpop' ); ?></p>
		<?php
	}
}
