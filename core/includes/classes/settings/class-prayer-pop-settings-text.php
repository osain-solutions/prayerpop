<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Settings_Text
 *
 * Handles the Text Customization section.
 */
class Prayer_Pop_Settings_Text {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_export_import' ) );
		add_action( 'wp_ajax_prayer_pop_import_translations', array( $this, 'ajax_import_translations' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'prayer_pop_settings_group', 'prayer_pop_texts', array( $this, 'sanitize_texts' ) );

		// Main Section
		add_settings_section(
			'prayer_pop_text_section',
			esc_html__( 'Text Tools', 'prayerpop' ),
			array( $this, 'render_section_description' ),
			'prayer-pop-settings-text'
		);
		$this->register_content_section( 'bubble', esc_html__( 'Bubble & Navigation', 'prayerpop' ), esc_html__( 'Text shown on the floating bubble and its primary navigation.', 'prayerpop' ) );
		$this->register_content_section( 'form', esc_html__( 'Prayer Request Form', 'prayerpop' ), esc_html__( 'Headings, fields, buttons, and accessibility labels used by the request form.', 'prayerpop' ) );
		$this->register_content_section( 'messages', esc_html__( 'Confirmations & Errors', 'prayerpop' ), esc_html__( 'Success, validation, preview, and error messages shown to visitors.', 'prayerpop' ) );
		$this->register_content_section( 'activity', esc_html__( 'Activity & Time', 'prayerpop' ), esc_html__( 'Recent-submission wording and the time units used in relative dates.', 'prayerpop' ) );

		// Bubble and Main Menu
		$this->add_text_field( 'text_bubble_label', esc_html__( 'Bubble Label', 'prayerpop' ), 'PrayerPop' );
		$this->add_text_field( 'text_bubble_icon_alt', esc_html__( 'Bubble Icon Alt Text', 'prayerpop' ), 'PrayerPop icon' );
		$this->add_text_field( 'text_prayer_request_label', esc_html__( 'Prayer Request Button', 'prayerpop' ), 'Prayer Request' );
		$this->add_text_field( 'text_back_button', esc_html__( 'Back Button', 'prayerpop' ), 'Back' );

		// Headers and Descriptions
		$this->add_text_field( 'text_prayer_request_header', esc_html__( 'Prayer Request Header', 'prayerpop' ), 'Submit a Prayer Request' );
		$this->add_text_field( 'text_prayer_request_description', esc_html__( 'Prayer Request Description', 'prayerpop' ), 'Please fill out the form below to submit your prayer request.' );

		// Form Fields
		$this->add_text_field( 'text_message_placeholder', esc_html__( 'Message Placeholder', 'prayerpop' ), 'Enter your message...' );
		$this->add_text_field( 'text_name_placeholder', esc_html__( 'Name Placeholder (Optional)', 'prayerpop' ), 'Your Name (optional)' );
		$this->add_text_field( 'text_name_placeholder_required', esc_html__( 'Name Placeholder (Required)', 'prayerpop' ), 'Your Name' );
		$this->add_text_field( 'text_submit_button', esc_html__( 'Submit Button', 'prayerpop' ), 'Submit' );
		$this->add_text_field( 'text_submitting_button', esc_html__( 'Submitting Button', 'prayerpop' ), 'Sending...' );
		$this->add_text_field( 'text_anonymous', esc_html__( 'Anonymous Text', 'prayerpop' ), 'Anonymous' );
		$this->add_text_field( 'text_honeypot_label', esc_html__( 'Honeypot Accessibility Label', 'prayerpop' ), 'Leave this field empty' );

		// Messages
		$this->add_text_field( 'text_success_message', esc_html__( 'Success Message', 'prayerpop' ), 'Thank you for your submission!' );
		$this->add_text_field( 'text_error_message', esc_html__( 'Error Message', 'prayerpop' ), 'There was an error processing your request.' );
		$this->add_text_field( 'text_error_rate_limit', esc_html__( 'Rate Limit Message', 'prayerpop' ), 'Too many submissions right now. Please wait a few minutes and try again.' );
		$this->add_text_field( 'text_error_invalid_name', esc_html__( 'Invalid Name Message', 'prayerpop' ), 'Please use only a real person name in the name field.' );
		$this->add_text_field( 'text_new_request_button', esc_html__( 'New Request Button', 'prayerpop' ), 'Send One More' );
		$this->add_text_field( 'text_required_field', esc_html__( 'Required Field Message', 'prayerpop' ), esc_html__( 'Please fill out this field', 'prayerpop' ) );
		$this->add_text_field( 'text_preview_permission_error', esc_html__( 'Preview Permission Error', 'prayerpop' ), 'You do not have permission to view this preview.' );
		$this->add_text_field( 'text_preview_invalid_token', esc_html__( 'Preview Invalid Token Error', 'prayerpop' ), 'Invalid preview token.' );

		// Last Time Messages
		$this->add_text_field( 'text_last_prayer_time_message', esc_html__( 'Last Prayer Time Message', 'prayerpop' ), 'Last prayer request was submitted {time_ago} ago' );

		// Time Units
		$this->add_text_field( 'text_time_unit_second_singular', esc_html__( '"Second" (Singular)', 'prayerpop' ), 'second' );
		$this->add_text_field( 'text_time_unit_second_plural', esc_html__( '"Seconds" (Plural)', 'prayerpop' ), 'seconds' );
		$this->add_text_field( 'text_time_unit_minute_singular', esc_html__( '"Minute" (Singular)', 'prayerpop' ), 'minute' );
		$this->add_text_field( 'text_time_unit_minute_plural', esc_html__( '"Minutes" (Plural)', 'prayerpop' ), 'minutes' );
		$this->add_text_field( 'text_time_unit_hour_singular', esc_html__( '"Hour" (Singular)', 'prayerpop' ), 'hour' );
		$this->add_text_field( 'text_time_unit_hour_plural', esc_html__( '"Hours" (Plural)', 'prayerpop' ), 'hours' );
		$this->add_text_field( 'text_time_unit_day_singular', esc_html__( '"Day" (Singular)', 'prayerpop' ), 'day' );
		$this->add_text_field( 'text_time_unit_day_plural', esc_html__( '"Days" (Plural)', 'prayerpop' ), 'days' );

		// Admin answered-prayer note.
		$this->add_text_field( 'text_answered_message_label', esc_html__( 'Answered Message Label', 'prayerpop' ), 'Answer Update' );
	}

	/**
	 * Render section description.
	 */
	public function render_section_description() {
		?>
		<?php $this->render_export_import_section(); ?>

		<div class="prayer-pop-text-placeholders-help">
			<p><?php esc_html_e( 'Customize text strings used by the PrayerPop bubble and prayer request workflow. Available placeholders:', 'prayerpop' ); ?></p>
			<ul>
				<li><code>{time_ago}</code> - <?php esc_html_e( 'Used in last submission messages to show time', 'prayerpop' ); ?></li>
			</ul>
		</div>

		<div class="prayer-pop-text-reset-block">
			<p class="prayer-pop-reset-wrap">
				<button
					type="submit"
					class="button prayer-pop-reset-submit"
					id="prayer-pop-reset-translations"
					name="prayer_pop_reset_action"
					value="translations"
					formnovalidate
					data-confirm="<?php echo esc_attr__( 'Reset all text customization fields to plugin defaults?', 'prayerpop' ); ?>"
				>
					<?php esc_html_e( 'Reset Text Customization Defaults', 'prayerpop' ); ?>
				</button>
			</p>
			<p class="description"><?php esc_html_e( 'Resets all text customization fields back to the plugin default English text.', 'prayerpop' ); ?></p>
		</div>
			
		<?php
	}

	/**
	 * Helper function to add a text field.
	 */
	private function add_text_field( $id, $label, $default ) {
		add_settings_field(
			$id,
			$label,
			array( $this, 'render_text_field' ),
			'prayer-pop-settings-text',
			$this->get_field_section( $id ),
			array(
				'id' => $id,
				'default' => $default,
				'label_for' => $id
			)
		);
	}

	/**
	 * Helper function to add a textarea field.
	 */
	private function add_textarea_field( $id, $label, $default ) {
		add_settings_field(
			$id,
			$label,
			array( $this, 'render_textarea_field' ),
			'prayer-pop-settings-text',
			$this->get_field_section( $id ),
			array(
				'id' => $id,
				'default' => $default,
				'label_for' => $id
			)
		);
	}

	private function register_content_section( $id, $title, $description ) {
		add_settings_section( 'prayer_pop_text_' . $id . '_section', $title, array( $this, 'render_content_section_description' ), 'prayer-pop-settings-text', array( 'description' => $description ) );
	}

	public function render_content_section_description( $args ) {
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="prayer-pop-text-section-description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	private function get_field_section( $id ) {
		$bubble = array( 'text_bubble_label', 'text_bubble_icon_alt', 'text_prayer_request_label', 'text_back_button' );
		if ( in_array( $id, $bubble, true ) ) {
			return 'prayer_pop_text_bubble_section';
		}
		if ( 0 === strpos( $id, 'text_error_' ) || in_array( $id, array( 'text_success_message', 'text_new_request_button', 'text_required_field', 'text_preview_permission_error', 'text_preview_invalid_token', 'text_answered_message_label' ), true ) ) {
			return 'prayer_pop_text_messages_section';
		}
		if ( 0 === strpos( $id, 'text_last_' ) || 0 === strpos( $id, 'text_time_unit_' ) ) {
			return 'prayer_pop_text_activity_section';
		}
		return 'prayer_pop_text_form_section';
	}

	/**
	 * Render a text field.
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'prayer_pop_texts', array() );
		$id      = $args['id'];
		$default = $args['default'];
		$value   = isset( $options[ $id ] ) ? $options[ $id ] : $default;
		?>
		<input type="text" 
			   id="<?php echo esc_attr( $id ); ?>"
			   name="prayer_pop_texts[<?php echo esc_attr( $id ); ?>]"
			   value="<?php echo esc_attr( $value ); ?>"
			   placeholder="<?php echo esc_attr( $default ); ?>"
			   class="regular-text prayer-pop-text-field-input"
			   style="width:60em;max-width:100%;">
		<?php
	}

	/**
	 * Render a textarea field.
	 */
	public function render_textarea_field( $args ) {
		$options = get_option( 'prayer_pop_texts', array() );
		$id      = $args['id'];
		$default = $args['default'];
		$value   = isset( $options[ $id ] ) ? $options[ $id ] : $default;
		?>
		<textarea id="<?php echo esc_attr( $id ); ?>"
				  name="prayer_pop_texts[<?php echo esc_attr( $id ); ?>]"
				  rows="3"
				  class="large-text"
				  placeholder="<?php echo esc_attr( $default ); ?>"
				  style="width:60em;max-width:100%;"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Sanitize texts.
	 */
	public function sanitize_texts( $input ) {
		$existing_texts = get_option( 'prayer_pop_texts', array() );
		$defaults_raw   = Prayer_Pop_Defaults::get_default_texts_raw();
		$old_anonymous  = isset( $existing_texts['text_anonymous'] ) ? (string) $existing_texts['text_anonymous'] : ( isset( $defaults_raw['text_anonymous'] ) ? (string) $defaults_raw['text_anonymous'] : 'Anonymous' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs during Settings API save request with options.php nonce.
		$reset_action = isset( $_POST['prayer_pop_reset_action'] )
			? sanitize_key( wp_unslash( $_POST['prayer_pop_reset_action'] ) )
			: '';
		if ( 'translations' === $reset_action ) {
			$reset_texts   = Prayer_Pop_Defaults::get_default_texts_raw();
			$new_anonymous = isset( $reset_texts['text_anonymous'] ) ? (string) $reset_texts['text_anonymous'] : 'Anonymous';
			$this->migrate_anonymous_submission_names( $old_anonymous, $new_anonymous );
			return $reset_texts;
		}

		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $key => $value ) {
			$value = $this->normalize_utf8_text( (string) $value );
			if ( strpos( $key, 'description' ) !== false ) {
				$sanitized[ $key ] = wp_kses_post( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
		}
		}

		$new_anonymous = isset( $sanitized['text_anonymous'] ) ? (string) $sanitized['text_anonymous'] : ( isset( $defaults_raw['text_anonymous'] ) ? (string) $defaults_raw['text_anonymous'] : 'Anonymous' );
		$this->migrate_anonymous_submission_names( $old_anonymous, $new_anonymous );

		return $sanitized;
	}

	/**
	 * Convert legacy stored anonymous labels to a canonical marker so
	 * future label changes are reflected on all existing submissions.
	 *
	 * @param string $old_anonymous Previous anonymous text.
	 * @param string $new_anonymous New anonymous text.
	 * @return void
	 */
	private function migrate_anonymous_submission_names( $old_anonymous, $new_anonymous ) {
		$old_anonymous = trim( (string) $old_anonymous );
		$new_anonymous = trim( (string) $new_anonymous );

		if ( '' === $old_anonymous || $old_anonymous === $new_anonymous ) {
			return;
		}

		$max_pages = 40;
		$per_page  = 300;
		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$post_ids = get_posts(
				array(
					'post_type'              => 'prayer_request',
					'post_status'            => array( 'pending', 'approved', 'answered', 'declined', 'archived', 'trash' ),
					'posts_per_page'         => $per_page,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'meta_query'             => array(
						array(
							'key'     => 'prayer_pop_name',
							'value'   => $old_anonymous,
							'compare' => '=',
						),
					),
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
				break;
			}

			foreach ( $post_ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id <= 0 ) {
					continue;
				}

				update_post_meta( $post_id, 'prayer_pop_name', Prayer_Pop_Defaults::ANONYMOUS_NAME_MARKER );
				update_post_meta( $post_id, Prayer_Pop_Defaults::ANONYMOUS_FLAG_META_KEY, '1' );

				$post = get_post( $post_id );
				if ( $post instanceof WP_Post && trim( (string) $post->post_title ) === $old_anonymous ) {
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => $new_anonymous,
						)
					);
				}
			}

			if ( count( $post_ids ) < $per_page ) {
				break;
			}
		}
	}

	/**
	 * Normalize UTF-8 text to NFC when possible so accented characters
	 * (e.g. ü, ä) render consistently across environments.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	private function normalize_utf8_text( $text ) {
		if ( class_exists( 'Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			if ( false !== $normalized ) {
				return $normalized;
			}
		}

		return $text;
	}

	/**
	 * Get all default text values.
	 *
	 * Uses centralized Prayer_Pop_Defaults class.
	 *
	 * @return array Default text strings.
	 */
	private function get_default_texts() {
		return Prayer_Pop_Defaults::get_default_texts();
	}

	/**
	 * Handle export/import actions.
	 */
	public function handle_export_import() {
		// Handle export
		if ( isset( $_GET['prayer_pop_export_texts'] ) && current_user_can( 'manage_options' ) ) {
			$this->export_translations();
		}
	}

	/**
	 * Export text fields to JSON file.
	 */
	private function export_translations() {
		// Verify nonce
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_export_texts' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'prayerpop' ) );
		}

		// Get raw default values (without translation functions)
		$defaults = Prayer_Pop_Defaults::get_default_texts_raw();
		
		// Get saved texts
		$saved_texts = get_option( 'prayer_pop_texts', array() );
		
		// Merge defaults with saved texts (saved texts override defaults)
		$all_texts = array_merge( $defaults, $saved_texts );
		
		// Add metadata
		$export_data = array(
			'plugin' => 'PrayerPop',
			'version' => PRAYERPOP_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'texts' => $all_texts
		);

		$filename = 'prayerpop-text-fields-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
		
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Handle AJAX import text fields.
	 */
	public function ajax_import_translations() {
		// Verify nonce and permissions
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_import_texts' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'prayerpop' ) );
		}

		if ( ! isset( $_FILES['translation_file'] ) || ! is_array( $_FILES['translation_file'] ) ) {
			wp_send_json_error( esc_html__( 'No file selected for import.', 'prayerpop' ) );
		}

		$file = isset( $_FILES['translation_file'] ) && is_array( $_FILES['translation_file'] ) ? $_FILES['translation_file'] : array();

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid uploaded file.', 'prayerpop' ) );
		}

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_send_json_error( esc_html__( 'File upload failed.', 'prayerpop' ) );
		}

		$max_import_size = 5 * MB_IN_BYTES;
		if ( isset( $file['size'] ) && (int) $file['size'] > $max_import_size ) {
			wp_send_json_error( esc_html__( 'File is too large. Maximum size is 5 MB.', 'prayerpop' ) );
		}

		$allowed_mimes = array(
			'json' => 'application/json',
			'txt'  => 'text/plain',
		);
		$filetype      = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
		$extension     = isset( $filetype['ext'] ) ? strtolower( (string) $filetype['ext'] ) : '';
		if ( 'json' !== $extension ) {
			wp_send_json_error( esc_html__( 'Please select a valid JSON file.', 'prayerpop' ) );
		}

		// Read file content
		$content = file_get_contents( $file['tmp_name'] );
		if ( $content === false ) {
			wp_send_json_error( esc_html__( 'Could not read the uploaded file.', 'prayerpop' ) );
		}

		// Parse JSON
		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: JSON parser error message. */
					esc_html__( 'Invalid JSON format: %s', 'prayerpop' ),
					esc_html( json_last_error_msg() )
				)
			);
		}

		// Validate structure
		if ( ! isset( $data['texts'] ) || ! is_array( $data['texts'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid text fields file structure. Expected "texts" array.', 'prayerpop' ) );
		}

		// Get current texts
		$current_texts = get_option( 'prayer_pop_texts', array() );
		
		// Get default texts (raw keys only for validation)
		$defaults = Prayer_Pop_Defaults::get_default_texts_raw();
		
		// Only import valid text keys that exist in defaults
		$imported_texts = array();
		$imported_count = 0;
		foreach ( $data['texts'] as $key => $value ) {
			if ( array_key_exists( $key, $defaults ) ) {
				$imported_texts[ $key ] = $value;
				$imported_count++;
			}
		}
		
		if ( $imported_count === 0 ) {
			wp_send_json_error( esc_html__( 'No valid text fields found in the file.', 'prayerpop' ) );
		}
		
		// Merge with current texts (imported texts override current)
		$merged_texts = array_merge( $current_texts, $imported_texts );

		// Sanitize and save text fields
		$sanitized_texts = $this->sanitize_texts( $merged_texts );
		update_option( 'prayer_pop_texts', $sanitized_texts );

		wp_send_json_success(
			sprintf(
				/* translators: %d: number of imported text fields. */
				esc_html__( 'Successfully imported %d text fields!', 'prayerpop' ),
				$imported_count
			)
		);
	}

	/**
	 * Render export/import section.
	 */
	public function render_export_import_section() {
		$export_url = wp_nonce_url( 
			admin_url( 'admin.php?page=prayer-pop-settings&tab=text&prayer_pop_export_texts=1' ), 
			'prayer_pop_export_texts' 
		);
		?>
				<div class="prayer-pop-export-import-section prayer-pop-text-card">
				<h3><?php esc_html_e( 'Export / Import Text Fields', 'prayerpop' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Export/import text fields as JSON for backup or translation updates. These actions are independent of the main settings form and will not affect your ability to save text customizations.', 'prayerpop' ); ?></p>

				<div class="prayer-pop-export-import-row">
					<div class="prayer-pop-export-section">
						<h4><?php esc_html_e( 'Export Text Fields', 'prayerpop' ); ?></h4>
						<p><?php esc_html_e( 'Download all current text customization fields as a JSON file. This includes all text strings with their current values (custom or default).', 'prayerpop' ); ?></p>
						<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export Text Fields', 'prayerpop' ); ?>
						</a>
					</div>

					<div class="prayer-pop-import-section">
						<h4><?php esc_html_e( 'Import Text Fields', 'prayerpop' ); ?></h4>
						<p><?php esc_html_e( 'Upload a text fields JSON file to update text strings. Imported strings are merged with your current settings.', 'prayerpop' ); ?></p>
						<div class="prayer-pop-import-form">
							<input type="file" id="translation_file" accept=".json">
							<button type="button" id="import_translations_btn" class="button button-primary">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Import Text Fields', 'prayerpop' ); ?>
							</button>
						</div>
						<p class="import-note"><small><?php esc_html_e( 'Note: After importing, the page will reload to show the updated text fields in the form above.', 'prayerpop' ); ?></small></p>
					</div>
				</div>
			</div>

		<?php
	}
}
