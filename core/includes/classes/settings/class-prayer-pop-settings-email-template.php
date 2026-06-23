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
	 * Get supported placeholder tokens.
	 *
	 * @return string[]
	 */
	private function get_available_placeholders() {
		return array( '{type}', '{name}', '{message}', '{pending_count}', '{admin_url}', '{site_url}', '{site_name}' );
	}

	/**
	 * Render clickable placeholder buttons for a target field.
	 *
	 * @param string $target_field_id Field ID.
	 * @return void
	 */
	private function render_placeholder_insert_buttons( $target_field_id ) {
		$target_field_id = sanitize_key( (string) $target_field_id );
		$placeholders    = $this->get_available_placeholders();
		?>
		<div class="prayer-pop-placeholder-insert-row">
			<span class="prayer-pop-placeholder-insert-label"><?php esc_html_e( 'Click to insert:', 'prayerpop' ); ?></span>
			<div class="prayer-pop-placeholder-chips">
				<?php foreach ( $placeholders as $placeholder ) : ?>
					<button
						type="button"
						class="button button-secondary prayer-pop-insert-placeholder"
						data-target="#<?php echo esc_attr( $target_field_id ); ?>"
						data-placeholder="<?php echo esc_attr( $placeholder ); ?>"
					>
						<code><?php echo esc_html( $placeholder ); ?></code>
					</button>
				<?php endforeach; ?>
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
			'email_test_button',
			'',
			array( $this, 'email_test_button_callback' ),
			'prayer-pop-settings-email-template',
			'prayer_pop_email_template_section'
		);

	}

	/**
	 * Render section description.
	 */
	public function render_section_description() {
		?>
		<div class="prayer-pop-email-template-info">
			<p><?php esc_html_e( 'Customize the email notifications sent when new prayer requests are submitted. Available placeholders:', 'prayerpop' ); ?></p>
			<table class="widefat striped prayer-pop-placeholder-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Placeholder', 'prayerpop' ); ?></th>
						<th><?php esc_html_e( 'Description', 'prayerpop' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>{type}</code></td>
						<td><?php esc_html_e( 'Type of submission.', 'prayerpop' ); ?></td>
					</tr>
					<tr>
						<td><code>{name}</code></td>
						<td><?php esc_html_e( 'Name of the person who submitted (or Anonymous)', 'prayerpop' ); ?></td>
					</tr>
					<tr>
						<td><code>{message}</code></td>
						<td><?php esc_html_e( 'The submitted message content', 'prayerpop' ); ?></td>
					</tr>
					<tr>
						<td><code>{pending_count}</code></td>
						<td><?php esc_html_e( 'Number of submissions currently waiting for admin action/review', 'prayerpop' ); ?></td>
					</tr>
					<tr>
						<td><code>{admin_url}</code></td>
						<td><?php esc_html_e( 'Direct link to manage submissions in admin', 'prayerpop' ); ?></td>
					</tr>
					<tr>
						<td><code>{site_url}</code></td>
						<td><?php esc_html_e( 'Your website homepage URL', 'prayerpop' ); ?></td>
					</tr>
					<tr>
						<td><code>{site_name}</code></td>
						<td><?php esc_html_e( 'Your website name', 'prayerpop' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render test email button below the input fields.
	 */
	public function email_test_button_callback() {
		?>
		<p><button type="button" class="button" id="prayer-pop-send-test-email"><?php esc_html_e( 'Send Test Email', 'prayerpop' ); ?></button></p>
		<?php
	}

	/**
	 * Sanitize email template.
	 */
	public function sanitize_email_template( $input ) {
		$input = wp_unslash( $input );
		$sanitized = array();
		$sanitized['email_subject'] = isset( $input['email_subject'] ) ? sanitize_text_field( $input['email_subject'] ) : '';
		$sanitized['email_body']    = isset( $input['email_body'] ) ? wp_kses_post( $input['email_body'] ) : '';
		return $sanitized;
	}

	/**
	 * Email subject callback.
	 */
	public function email_subject_callback() {
		$options = get_option( 'prayer_pop_email_template', array() );
		$subject = isset( $options['email_subject'] ) ? $options['email_subject'] : esc_html__( 'New PrayerPop Submission', 'prayerpop' );
		?>
			<input type="text" name="prayer_pop_email_template[email_subject]" value="<?php echo esc_attr( $subject ); ?>" class="regular-text" id="email_subject_field">
		<p class="description"><?php esc_html_e( 'Available placeholders: {type}, {name}, {message}, {pending_count}, {admin_url}, {site_url}, {site_name}', 'prayerpop' ); ?></p>
		<?php
	}

	/**
	 * Email body callback.
	 */
	public function email_body_callback() {
		$options = get_option( 'prayer_pop_email_template', array() );
		$body = isset( $options['email_body'] ) ? $options['email_body'] : esc_html__( "Type: {type}\nName: {name}\nMessage:\n{message}", "prayerpop" );
		?>
			<textarea name="prayer_pop_email_template[email_body]" rows="10" class="large-text code" id="email_body_field"><?php echo esc_textarea( $body ); ?></textarea>
		<?php $this->render_placeholder_insert_buttons( 'email_body_field' ); ?>
		<p class="description"><?php esc_html_e( 'Available placeholders: {type}, {name}, {message}, {pending_count}, {admin_url}, {site_url}, {site_name}', 'prayerpop' ); ?></p>
		<?php
	}
}
