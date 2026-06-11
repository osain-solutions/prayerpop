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
		$prayer_pop_inline_css = implode( "\n", array(
			'		.prayer-pop-email-template-info {',
			'			background: #fff;',
			'			padding: 15px;',
			'			border-left: 4px solid #0073aa;',
			'			margin: 20px 0;',
			'		}',
			'		.prayer-pop-email-template-info code {',
			'			background: #f0f0f1;',
			'			padding: 2px 5px;',
			'			border-radius: 3px;',
			'		}',
			'		.prayer-pop-placeholder-table {',
			'			margin-top: 12px;',
			'			margin-bottom: 14px;',
			'		}',
			'		.prayer-pop-placeholder-table th,',
			'		.prayer-pop-placeholder-table td {',
			'			padding: 10px 12px;',
			'			vertical-align: top;',
			'		}',
			'		.prayer-pop-placeholder-insert-row {',
			'			margin: 8px 0 10px;',
			'		}',
			'		.prayer-pop-placeholder-insert-label {',
			'			display: inline-block;',
			'			margin-right: 8px;',
			'			color: #50575e;',
			'			font-size: 12px;',
			'			vertical-align: middle;',
			'		}',
			'		.prayer-pop-placeholder-chips {',
			'			display: inline-flex;',
			'			flex-wrap: wrap;',
			'			gap: 6px;',
			'			vertical-align: middle;',
			'		}',
			'		.prayer-pop-placeholder-chips .button {',
			'			height: auto;',
			'			min-height: 28px;',
			'			padding: 3px 8px;',
			'			line-height: 1.2;',
			'		}',
			'		.prayer-pop-placeholder-chips code {',
			'			font-size: 12px;',
			'			background: transparent;',
			'			padding: 0;',
			'		}',
		) );
		wp_add_inline_style( 'prayer-pop-admin', $prayer_pop_inline_css );
		?>
		<?php
	}

	/**
	 * Render test email button below the input fields.
	 */
	public function email_test_button_callback() {
		?>
		<p><button type="button" class="button" id="prayer-pop-send-test-email">Send Test Email</button></p>
		<?php
		$prayer_pop_inline_js = implode( "\n", array(
			'		jQuery(document).ready(function($){',
			'		  function insertTextAtCursor($field, text) {',
			'			if (!$field || !$field.length) {',
			'			  return;',
			'			}',
			'			var field = $field.get(0);',
			'			field.focus();',
			'',
			'			if (typeof field.selectionStart === \'number\' && typeof field.selectionEnd === \'number\') {',
			'			  var start = field.selectionStart;',
			'			  var end = field.selectionEnd;',
			'			  var current = $field.val() || \'\';',
			'			  $field.val(current.substring(0, start) + text + current.substring(end));',
			'			  var caret = start + text.length;',
			'			  field.setSelectionRange(caret, caret);',
			'			} else if (document.selection) {',
			'			  field.focus();',
			'			  var range = document.selection.createRange();',
			'			  range.text = text;',
			'			} else {',
			'			  $field.val(($field.val() || \'\') + text);',
			'			}',
			'',
			'			$field.trigger(\'input\').trigger(\'change\');',
			'		  }',
			'',
			'		  $(document).on(\'click\', \'.prayer-pop-insert-placeholder\', function(e){',
			'			e.preventDefault();',
			'			var $button = $(this);',
			'			var selector = $button.attr(\'data-target\');',
			'			var placeholder = $button.attr(\'data-placeholder\') || \'\';',
			'			if (!selector || !placeholder) {',
			'			  return;',
			'			}',
			'			insertTextAtCursor($(selector), placeholder);',
			'		  });',
			'',
			'		  $(\'#prayer-pop-send-test-email\').on(\'click\', function(){',
			'			var $btn = $(this);',
			'			$btn.prop(\'disabled\', true).text(\'Sending...\');',
			'			$.post(ajaxurl, {',
			'			  action: \'prayer_pop_send_test_email\',',
			'			  _wpnonce: (window.prayerPopAdmin && prayerPopAdmin.nonce) ? prayerPopAdmin.nonce : \'\',',
			'			}, function(response){',
			'			  var message = \'Failed to send test email.\';',
			'			  if (response && response.data) {',
			'				message = response.data;',
			'			  }',
			'			  alert(message);',
			'			  $btn.prop(\'disabled\', false).text(\'Send Test Email\');',
			'			}).fail(function(){',
			'			  alert(\'Failed to send test email.\');',
			'			  $btn.prop(\'disabled\', false).text(\'Send Test Email\');',
			'			});',
			'		  });',
			'		});',
		) );
		wp_add_inline_script( 'prayer-pop-admin', $prayer_pop_inline_js );
		?>
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
