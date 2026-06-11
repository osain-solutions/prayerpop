<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Settings
 *
 * Main settings class that includes all settings sections.
 */
class Prayer_Pop_Settings {

	// Declare properties for settings classes.
	private $general_settings;
	private $notification_settings;
	private $email_template_settings;
	private $style_settings;
	private $text_settings;
	private $menu_icon_data_uri = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Include settings classes.
		$this->includes();

		// Instantiate settings classes.
		$this->initialize_settings();

		// Add WordPress hooks.
		$this->add_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/settings/class-prayer-pop-settings-general.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/settings/class-prayer-pop-settings-notifications.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/settings/class-prayer-pop-settings-email-template.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/settings/class-prayer-pop-settings-style.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/settings/class-prayer-pop-settings-text.php';
	}

	/**
	 * Instantiate settings classes.
	 */
	private function initialize_settings() {
		$this->general_settings        = new Prayer_Pop_Settings_General();
		$this->notification_settings   = new Prayer_Pop_Settings_Notifications();
		$this->email_template_settings = new Prayer_Pop_Settings_Email_Template();
		$this->style_settings          = new Prayer_Pop_Settings_Style();
		$this->text_settings           = new Prayer_Pop_Settings_Text();
	}

	/**
	 * Add WordPress hooks.
	 */
	private function add_hooks() {
		add_action('admin_menu', array($this, 'add_settings_page'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		add_action('admin_footer-prayer-pop_page_prayer-pop-settings', array($this, 'render_frontend_overlay_preview'));
		add_action('admin_footer-prayer-pop_page_prayer-pop-feedback', array($this, 'render_feedback_environment_capture_script'));
		add_action('admin_notices', array($this, 'show_settings_messages'));
		add_action('admin_post_prayer_pop_submit_feedback', array($this, 'handle_submit_feedback'));
		add_action('wp_ajax_prayer_pop_send_test_email', array(
			$this, 'handle_send_test_email'
		));
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Register a hidden setting to store the active tab
		register_setting(
			'prayer_pop_settings_group',
			'prayer_pop_active_tab',
			array( $this, 'sanitize_active_tab' )
		);
	}

	/**
	 * Sanitize active settings tab.
	 *
	 * @param string $tab Raw tab value.
	 * @return string
	 */
	public function sanitize_active_tab( $tab ) {
		$allowed_tabs = array(
			'general',
			'notifications',
			'style',
			'text',
			'documentation',
		);

		$tab = sanitize_key( (string) $tab );
		return in_array( $tab, $allowed_tabs, true ) ? $tab : 'general';
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_admin_scripts($hook) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $hook, array( 'prayer-pop_page_prayer-pop-settings', 'prayer-pop_page_prayer-pop-feedback' ), true ) && ! in_array( $page, array( 'prayer-pop-settings', 'prayer-pop-feedback' ), true ) ) {
			return;
		}

		$admin_css_version = file_exists( PRAYERPOP_PLUGIN_DIR . 'assets/css/prayer-pop-admin.css' )
			? (string) filemtime( PRAYERPOP_PLUGIN_DIR . 'assets/css/prayer-pop-admin.css' )
			: PRAYERPOP_VERSION;
		$admin_js_version = file_exists( PRAYERPOP_PLUGIN_DIR . 'assets/js/prayer-pop-admin.js' )
			? (string) filemtime( PRAYERPOP_PLUGIN_DIR . 'assets/js/prayer-pop-admin.js' )
			: PRAYERPOP_VERSION;

		// Enqueue WordPress color picker
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		// Enqueue admin styles
		wp_enqueue_style(
			'prayer-pop-admin',
			PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-admin.css',
			array(),
			$admin_css_version
		);

		wp_add_inline_style(
			'prayer-pop-admin',
			'
			#custom-buttons-table thead th,
			#custom-buttons-table tbody td,
			#custom-buttons-table tfoot td,
			#prayer-pop-faq-items-field .prayer-pop-faq-table th,
			#prayer-pop-faq-items-field .prayer-pop-faq-table td {
				padding: 12px 14px !important;
			}
			#custom-buttons-table thead th,
			#prayer-pop-faq-items-field .prayer-pop-faq-table th {
				width: auto !important;
			}
			'
		);

		// Enqueue admin script
		wp_enqueue_script(
			'prayer-pop-admin',
			PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-admin.js',
			array('jquery', 'wp-color-picker'),
			$admin_js_version,
			true
		);

		// Pass the current active tab to JavaScript
		$active_tab = get_option( 'prayer_pop_active_tab', 'general' );
		$layout_defaults = method_exists( $this->style_settings, 'get_layout_defaults' )
			? $this->style_settings->get_layout_defaults()
			: array();
		$style_customization_defaults = method_exists( $this->style_settings, 'get_style_customization_defaults' )
			? $this->style_settings->get_style_customization_defaults()
			: array();
		wp_localize_script(
			'prayer-pop-admin',
			'prayerPopAdmin',
			array(
				'activeTab' => $active_tab,
				'nonce' => wp_create_nonce( 'prayer_pop_admin_actions' ),
				'resetDefaults' => array(
					'layout' => $layout_defaults,
					'styleCustomization' => $style_customization_defaults,
					'translations' => Prayer_Pop_Defaults::get_default_texts_raw(),
				),
			)
		);
	}

	/**
	 * Show settings messages.
	 */
	public function show_settings_messages() {
		$screen = get_current_screen();
		if ( ! $screen || 'prayer-pop_page_prayer-pop-settings' !== $screen->id ) {
			return;
		}

		// Get the active tab
		$active_tab = get_option('prayer_pop_active_tab', 'general');
		
		// Check for settings update
		$settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : '';
		if ( '' !== $settings_updated ) {
			if ( 'true' === $settings_updated ) {
				$tab_label = ucfirst(str_replace('-', ' ', $active_tab));
				echo '<div class="notice notice-success is-dismissible"><p>' . 
					sprintf(
						/* translators: %s: settings tab label. */
						esc_html__( '%s settings updated successfully.', 'prayerpop' ),
						esc_html( $tab_label )
					) . 
					'</p></div>';
			} elseif ( 'false' === $settings_updated ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . 
					esc_html__('There was an error saving your settings.', 'prayerpop' ) . 
					'</p></div>';
			}
		}
	}

	/**
	 * Add settings page.
	 */
	public function add_settings_page() {
		// Define the main menu slug.
		$menu_slug = 'prayer-pop';
		$menu_icon = $this->get_menu_icon_data_uri();

		add_menu_page(
			esc_html__('PrayerPop', 'prayerpop' ),
			esc_html__('PrayerPop', 'prayerpop' ),
			'manage_options',
			$menu_slug,
			'', // No callback function; we only use submenus.
			$menu_icon,
			60
		);

		add_submenu_page(
			$menu_slug,
			esc_html__('Settings', 'prayerpop' ),
			esc_html__('Settings', 'prayerpop' ),
			'manage_options',
			'prayer-pop-settings',
			array($this, 'render_settings_page')
		);

		add_submenu_page(
			$menu_slug,
			esc_html__( 'Bug, feature, question', 'prayerpop' ),
			esc_html__( 'Bug, feature, question', 'prayerpop' ),
			'manage_options',
			'prayer-pop-feedback',
			array( $this, 'render_feedback_page' )
		);
	}

	/**
	 * Render feedback page.
	 *
	 * @return void
	 */
	public function render_feedback_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'prayerpop' ) );
		}

		$status = isset( $_GET['feedback_status'] ) ? sanitize_key( wp_unslash( $_GET['feedback_status'] ) ) : '';
		$server_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$server_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$server_current_url = '' !== $server_request_uri ? admin_url( ltrim( $server_request_uri, '/' ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bug, feature, question', 'prayerpop' ); ?></h1>
			<?php if ( 'success' === $status ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Your report was sent successfully.', 'prayerpop' ); ?></p></div>
			<?php elseif ( 'error' === $status ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not send your report. Please try again.', 'prayerpop' ); ?></p></div>
			<?php endif; ?>

			<div class="prayer-pop-feedback-page">
				<p class="description">
					<?php esc_html_e( 'Title and description are both required. Found a bug or have a feature idea? Fill this in and it comes to our mailbox.', 'prayerpop' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="prayer_pop_submit_feedback">
					<?php wp_nonce_field( 'prayer_pop_submit_feedback', 'prayer_pop_feedback_nonce' ); ?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="prayer-pop-feedback-type"><?php esc_html_e( 'Type', 'prayerpop' ); ?></label></th>
								<td>
									<select id="prayer-pop-feedback-type" name="feedback_type" required>
										<option value="bug"><?php esc_html_e( 'Bug', 'prayerpop' ); ?></option>
										<option value="feature request"><?php esc_html_e( 'Feature request', 'prayerpop' ); ?></option>
										<option value="question"><?php esc_html_e( 'Question', 'prayerpop' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="prayer-pop-feedback-title"><?php esc_html_e( 'Title', 'prayerpop' ); ?></label></th>
								<td><input type="text" class="regular-text" id="prayer-pop-feedback-title" name="feedback_title" placeholder="<?php esc_attr_e( 'A short summary', 'prayerpop' ); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="prayer-pop-feedback-description"><?php esc_html_e( 'What happened? What did you expect?', 'prayerpop' ); ?></label></th>
								<td><textarea id="prayer-pop-feedback-description" name="feedback_description" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'Describe the issue or the feature you have in mind.', 'prayerpop' ); ?>" required></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="prayer-pop-feedback-steps"><?php esc_html_e( 'Steps to reproduce (bug only)', 'prayerpop' ); ?></label></th>
								<td><textarea id="prayer-pop-feedback-steps" name="feedback_steps" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'One step per line', 'prayerpop' ); ?>"></textarea></td>
							</tr>
						</tbody>
					</table>

					<details>
						<summary><strong><?php esc_html_e( 'Environment included with the report', 'prayerpop' ); ?></strong></summary>
						<pre id="prayer-pop-feedback-env-preview"><?php echo esc_html( $this->get_feedback_environment_preview() ); ?></pre>
					</details>

					<input type="hidden" name="feedback_user_agent" id="prayer-pop-feedback-user-agent" value="<?php echo esc_attr( $server_user_agent ); ?>">
					<input type="hidden" name="feedback_viewport" id="prayer-pop-feedback-viewport" value="">
					<input type="hidden" name="feedback_platform" id="prayer-pop-feedback-platform" value="">
					<input type="hidden" name="feedback_current_url" id="prayer-pop-feedback-current-url" value="<?php echo esc_attr( $server_current_url ); ?>">

					<?php
		$prayer_pop_inline_js = implode( "\n", array(
			'					(function () {',
			'						var userAgentField = document.getElementById(\'prayer-pop-feedback-user-agent\');',
			'						var viewportField = document.getElementById(\'prayer-pop-feedback-viewport\');',
			'						var platformField = document.getElementById(\'prayer-pop-feedback-platform\');',
			'						var currentUrlField = document.getElementById(\'prayer-pop-feedback-current-url\');',
			'						var preview = document.getElementById(\'prayer-pop-feedback-env-preview\');',
			'',
			'						if (userAgentField && navigator.userAgent) {',
			'							userAgentField.value = navigator.userAgent;',
			'						}',
			'						if (viewportField) {',
			'							viewportField.value = (window.innerWidth || 0) + \'x\' + (window.innerHeight || 0);',
			'						}',
			'						if (platformField && navigator.platform) {',
			'							platformField.value = navigator.platform;',
			'						}',
			'						if (currentUrlField && window.location && window.location.href) {',
			'							currentUrlField.value = window.location.href;',
			'						}',
			'',
			'						if (preview) {',
			'							var lines = preview.textContent.split(\'\\n\');',
			'							for (var i = 0; i < lines.length; i++) {',
			'								if (lines[i].indexOf(\'User agent:\') === 0 && userAgentField && userAgentField.value) {',
			'									lines[i] = \'User agent:        \' + userAgentField.value;',
			'								}',
			'								if (lines[i].indexOf(\'Viewport:\') === 0 && viewportField && viewportField.value) {',
			'									lines[i] = \'Viewport:          \' + viewportField.value;',
			'								}',
			'								if (lines[i].indexOf(\'Platform:\') === 0 && platformField && platformField.value) {',
			'									lines[i] = \'Platform:          \' + platformField.value;',
			'								}',
			'								if (lines[i].indexOf(\'Current URL:\') === 0 && currentUrlField && currentUrlField.value) {',
			'									lines[i] = \'Current URL:       \' + currentUrlField.value;',
			'								}',
			'							}',
			'							preview.textContent = lines.join(\'\\n\');',
			'						}',
			'					})();',
		) );
		wp_add_inline_script( 'prayer-pop-admin', $prayer_pop_inline_js );
		?>

					<p>
						<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Send', 'prayerpop' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page with tabs.
	 */
	public function render_settings_page() {
		// Get the active tab from the option, or from URL, or default to 'general'
		$active_tab = isset( $_GET['tab'] )
			? $this->sanitize_active_tab( wp_unslash( $_GET['tab'] ) )
			: $this->sanitize_active_tab( get_option( 'prayer_pop_active_tab', 'general' ) );
		$show_welcome_modal = isset( $_GET['prayer_pop_welcome'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['prayer_pop_welcome'] ) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e('PrayerPop Settings', 'prayerpop' ); ?></h1>

			<?php settings_errors(); ?>

			<!-- Sticky Save Bar -->
			<div class="prayer-pop-sticky-save-bar" id="prayer-pop-sticky-save-bar">
				<div class="sticky-save-content">
					<span class="save-indicator"><?php esc_html_e('You have unsaved changes', 'prayerpop' ); ?></span>
					<button type="button" class="button button-primary save-changes-btn" id="sticky-save-btn">
						<?php esc_html_e('Save Changes', 'prayerpop' ); ?>
					</button>
				</div>
			</div>

				<h2 class="nav-tab-wrapper">
					<?php
					$tabs = array(
						'general' => array('icon' => 'admin-generic', 'label' => __('General', 'prayerpop' )),
						'notifications' => array('icon' => 'email', 'label' => __('Notifications', 'prayerpop' )),
						'style' => array('icon' => 'admin-appearance', 'label' => __('Style', 'prayerpop' )),
						'text' => array('icon' => 'translation', 'label' => __('Text Customization', 'prayerpop' )),
							'documentation' => array('icon' => 'book', 'label' => __('Documentation', 'prayerpop' )),
					);
					$tab_descriptions = array(
						'general'       => __( 'Configure the prayer-request workflow: bubble visibility, anonymous names, required admin review, and retention cleanup.', 'prayerpop' ),
						'notifications' => __( 'Set who gets prayer request alerts, when alerts are sent, and how those alert emails are written.', 'prayerpop' ),
						'style'         => __( 'Customize the PrayerPop bubble, including core colors, typography, size, animation, icon, position, and spacing.', 'prayerpop' ),
						'text'          => __( 'Customize visible prayer request form text, labels, and messages.', 'prayerpop' ),
						'documentation' => __( 'Setup and usage guidance for the PrayerPop bubble, admin submissions, notifications, styling, text customization, and troubleshooting.', 'prayerpop' ),
					);

					foreach ($tabs as $tab_id => $tab) {
						$tab_url = add_query_arg(array(
						'page' => 'prayer-pop-settings',
						'tab' => $tab_id,
					), admin_url('admin.php'));

					$active_class = ($active_tab === $tab_id) ? ' nav-tab-active' : '';
					?>
					<a href="<?php echo esc_url($tab_url); ?>" class="nav-tab<?php echo esc_attr($active_class); ?>" data-tab="<?php echo esc_attr($tab_id); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr($tab['icon']); ?>"></span>
						<?php echo esc_html($tab['label']); ?>
					</a>
					<?php
				}
				?>
				</h2>

					<div class="prayer-pop-settings-layout has-feature-rail">
					<div class="prayer-pop-settings-main">
				<form method="post" action="options.php" id="prayer-pop-settings-form">
					<?php
					settings_fields('prayer_pop_settings_group');
				
				// Add hidden field for active tab
				echo '<input type="hidden" name="prayer_pop_active_tab" value="' . esc_attr($active_tab) . '">';

					// Display the appropriate section based on active tab
					foreach ($tabs as $tab_id => $tab) {
						$display = ($active_tab === $tab_id) ? 'block' : 'none';
						echo '<div id="' . esc_attr($tab_id) . '" class="tab-content" style="display: ' . esc_attr($display) . ';">';
						$tab_description = isset( $tab_descriptions[ $tab_id ] ) ? $tab_descriptions[ $tab_id ] : '';
						$this->render_tab_intro( $tab['label'], $tab['icon'], $tab_description );
						if ($tab_id === 'documentation') {
							$this->render_documentation_tab();
						} elseif ( $tab_id === 'notifications' ) {
							?>
							<div class="prayer-pop-subsection-card prayer-pop-notifications-intent-card">
								<h3 class="prayer-pop-subsection-title">
									<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
									<?php esc_html_e( 'How Notifications Work', 'prayerpop' ); ?>
								</h3>
								<p class="prayer-pop-subsection-description">
									<?php esc_html_e( 'When a new prayer request is submitted, PrayerPop can send an email alert to the address you set below. The Email Template controls exactly how that alert message looks.', 'prayerpop' ); ?>
								</p>
								<ul>
									<li><?php esc_html_e( 'Enable notifications to turn alerts on.', 'prayerpop' ); ?></li>
									<li><?php esc_html_e( 'Choose recipient + timing (instant, daily, weekly).', 'prayerpop' ); ?></li>
									<li><?php esc_html_e( 'Set subject/body in Email Template, then send a test email.', 'prayerpop' ); ?></li>
								</ul>
							</div>
							<?php
							echo '<h2>' . esc_html__( 'Notification Settings', 'prayerpop' ) . '</h2>';
							echo '<table class="form-table" role="presentation">';
							do_settings_fields( 'prayer-pop-settings-notifications', 'prayer_pop_notification_section' );
							echo '</table>';
							?>
							<div class="prayer-pop-subsection-card prayer-pop-email-template-card">
								<h3 class="prayer-pop-subsection-title">
									<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
									<?php esc_html_e( 'Email Template Settings', 'prayerpop' ); ?>
								</h3>
								<p class="prayer-pop-subsection-description">
									<?php esc_html_e( 'This is part of Notifications. Set the subject/body placeholders and send a test email to verify delivery and formatting.', 'prayerpop' ); ?>
								</p>
								<?php do_settings_sections( 'prayer-pop-settings-email-template' ); ?>
								<?php $this->email_template_settings->render_section_description(); ?>
							</div>
							<div class="prayer-pop-notification-debug-settings">
								<h3><?php esc_html_e( 'Advanced Troubleshooting (Optional)', 'prayerpop' ); ?></h3>
								<table class="form-table" role="presentation">
									<?php do_settings_fields( 'prayer-pop-settings-notifications', 'prayer_pop_notification_debug_section' ); ?>
								</table>
							</div>
							<?php
						} else {
							do_settings_sections('prayer-pop-settings-' . $tab_id);
						}
						echo '</div>';
					}

				// Save button is not needed on tabs with custom actions.
				$tabs_with_custom_actions = array( 'documentation' );
				if ( ! in_array( $active_tab, $tabs_with_custom_actions, true ) ) {
					echo '<div class="prayer-pop-save-row">';
					submit_button();
					?>
					<div class="prayer-pop-brand-footer" aria-hidden="true">
						<a class="prayer-pop-brand-logo prayer-pop-brand-logo-full" href="<?php echo esc_url( 'https://prayerpop.eu/' ); ?>" target="_blank" rel="noopener noreferrer">
							<img
								class="prayer-pop-logo-img prayer-pop-logo-img-full"
								src="<?php echo esc_url( PRAYERPOP_PLUGIN_URL . 'assets/images/prayer-pop-logo-full.svg' ); ?>"
								width="180"
								height="46"
								style="width:180px;max-width:180px;height:auto;display:block;"
								alt=""
							/>
						</a>
					</div>
					<?php
					echo '</div>';
				}
				?>
				</form>
					</div>
					<?php $this->render_feature_rail(); ?>
				</div>

			<?php $this->render_welcome_modal( $show_welcome_modal ); ?>
		</div>

		<?php
		$prayer_pop_inline_css = implode( "\n", array(
			'			.nav-tab .dashicons {',
			'				margin-right: 5px;',
			'				line-height: 1.4;',
			'			}',
			'					.prayer-pop-tab-intro {',
			'						background: transparent;',
			'						border: 0;',
			'						padding: 4px 0 10px;',
			'						margin: 0 0 10px;',
			'					}',
			'					.prayer-pop-tab-intro h2 {',
			'						display: flex;',
			'						align-items: center;',
			'						gap: 8px;',
			'						margin: 0 0 8px;',
			'						font-size: 22px;',
			'					}',
			'				.prayer-pop-tab-intro h2 .dashicons {',
			'					color: #0073aa;',
			'				}',
			'				.prayer-pop-tab-intro p {',
			'					margin: 0;',
			'					color: #50575e;',
			'				}',
			'						.tab-content {',
			'							margin-top: 12px;',
			'							max-width: 1240px;',
			'							background: #fff;',
			'							border: 1px solid #dcdcde;',
			'							border-radius: 10px;',
			'							padding: 18px 22px;',
			'						}',
			'						.prayer-pop-save-row {',
			'							width: 100%;',
			'							max-width: 100%;',
			'							margin-top: 16px;',
			'							padding: 18px 22px;',
			'							background: #fff;',
			'							border: 1px solid #dcdcde;',
			'							border-radius: 10px;',
			'							box-sizing: border-box;',
			'						}',
			'						.prayer-pop-settings-layout {',
			'							display: block;',
			'						}',
			'						.prayer-pop-settings-layout.has-feature-rail {',
			'							display: grid;',
			'							grid-template-columns: minmax(0, 1fr) 320px;',
			'							gap: 22px;',
			'							align-items: start;',
			'						}',
			'						.prayer-pop-settings-main {',
			'							min-width: 0;',
			'						}',
			'						.prayer-pop-feature-rail {',
			'							position: sticky;',
			'							top: 64px;',
			'						}',
			'						.prayer-pop-feature-card {',
			'							margin: 12px 0 0;',
			'							background: #fff;',
			'							border: 1px solid #dcdcde;',
			'							border-left: 4px solid #2271b1;',
			'							border-radius: 10px;',
			'							padding: 14px 14px 12px;',
			'						}',
			'						.prayer-pop-feature-card:first-child {',
			'							margin-top: 12px;',
			'						}',
			'						.prayer-pop-feature-card h3 {',
			'							margin: 0 0 8px;',
			'							font-size: 15px;',
			'							line-height: 1.35;',
			'							display: flex;',
			'							align-items: center;',
			'							gap: 7px;',
			'						}',
			'						.prayer-pop-feature-card h3 .dashicons {',
			'							color: #2271b1;',
			'						}',
			'						.prayer-pop-feature-card p {',
			'							margin: 0 0 10px;',
			'							color: #50575e;',
			'							font-size: 13px;',
			'							line-height: 1.5;',
			'						}',
			'						.prayer-pop-feature-card ul {',
			'							margin: 0 0 10px 18px;',
			'						}',
			'						.prayer-pop-feature-card li {',
			'							margin-bottom: 4px;',
			'							font-size: 12px;',
			'						}',
			'						.prayer-pop-feature-card .button {',
			'							width: 100%;',
			'							text-align: center;',
			'						}',
			'',
			'			/* Documentation tab */',
			'			.prayer-pop-docs {',
			'				max-width: 1400px;',
			'			}',
			'			.prayer-pop-doc-start {',
			'				background: #fff;',
			'				border: 1px solid #dcdcde;',
			'				border-left: 4px solid #2271b1;',
			'				padding: 14px 16px;',
			'				margin: 0 0 18px;',
			'			}',
			'			.prayer-pop-doc-start h3 {',
			'				margin: 0 0 8px;',
			'				font-size: 16px;',
			'			}',
			'			.prayer-pop-doc-start ol,',
			'			.prayer-pop-doc-start ul {',
			'				margin: 0 0 0 18px;',
			'			}',
			'			.prayer-pop-doc-note {',
			'				margin: 12px 0;',
			'				padding: 10px 12px;',
			'				border-radius: 6px;',
			'				border: 1px solid #c3d7ea;',
			'				background: #f0f6fc;',
			'				color: #1d2327;',
			'			}',
			'			.prayer-pop-doc-note strong {',
			'				font-weight: 700;',
			'			}',
			'			.prayer-pop-doc-note.is-tip {',
			'				border-color: #cde5c2;',
			'				background: #f4fbf0;',
			'			}',
			'			.prayer-pop-doc-section h2 {',
			'				margin: 14px 0 12px;',
			'				font-size: 21px;',
			'				line-height: 1.25;',
			'				font-weight: 700;',
			'				color: #1d2327;',
			'			}',
			'			.prayer-pop-doc-section,',
			'			#prayer-pop-doc-ai-workflow {',
			'				scroll-margin-top: 96px;',
			'			}',
			'			@keyframes prayer-pop-doc-target-flash {',
			'				0% {',
			'					background-color: transparent;',
			'					box-shadow: inset 0 0 0 transparent;',
			'					color: #1d2327;',
			'				}',
			'				30% {',
			'					background-color: rgba(255, 224, 138, 0.98);',
			'					box-shadow: inset 0 -4px 0 #dba617;',
			'					color: #0a4b78;',
			'				}',
			'				100% {',
			'					background-color: transparent;',
			'					box-shadow: inset 0 0 0 transparent;',
			'					color: #1d2327;',
			'				}',
			'			}',
			'			.prayer-pop-doc-section:target > h2,',
			'			#prayer-pop-doc-ai-workflow:target {',
			'				animation: prayer-pop-doc-target-flash 1.25s ease;',
			'			}',
			'			.prayer-pop-docs h3 {',
			'				margin-top: 24px;',
			'				margin-bottom: 12px;',
			'			}',
			'			.prayer-pop-docs hr {',
			'				margin: 28px 0;',
			'				border: 0;',
			'				border-top: 2px solid #c3c4c7;',
			'			}',
			'			.prayer-pop-doc-advanced {',
			'				margin-top: 24px;',
			'				border: 1px solid #c3c4c7;',
			'				border-radius: 6px;',
			'				background: #fff;',
			'			}',
			'			.prayer-pop-doc-advanced > summary {',
			'				padding: 14px 16px;',
			'				cursor: pointer;',
			'				font-size: 15px;',
			'				font-weight: 600;',
			'			}',
			'			.prayer-pop-doc-advanced[open] > summary {',
			'				border-bottom: 1px solid #dcdcde;',
			'				background: #f6f7f7;',
			'			}',
			'			.prayer-pop-doc-advanced__content {',
			'				padding: 16px;',
			'			}',
			'			.prayer-pop-subsection-card {',
			'				margin-top: 18px;',
			'				background: transparent;',
			'				border: 0;',
			'				border-top: 1px solid #e2e5e9;',
			'				padding: 18px 0 0;',
			'			}',
			'			.prayer-pop-subsection-card:first-child {',
			'				margin-top: 0;',
			'				border-top: 0;',
			'				padding-top: 0;',
			'			}',
			'			.prayer-pop-subsection-title {',
			'				display: flex;',
			'				align-items: center;',
			'				gap: 8px;',
			'				margin: 0 0 6px;',
			'				font-size: 16px;',
			'				line-height: 1.3;',
			'			}',
			'			.prayer-pop-subsection-title .dashicons {',
			'				color: #2271b1;',
			'			}',
			'			.prayer-pop-subsection-description {',
			'				margin: 0 0 12px;',
			'				color: #50575e;',
			'			}',
			'			.tab-content .form-table {',
			'				margin-top: 0;',
			'			}',
			'			.tab-content .form-table th {',
			'				width: 220px;',
			'				padding: 10px 12px 10px 0;',
			'			}',
			'			.tab-content .form-table td {',
			'				padding: 8px 0 10px;',
			'			}',
			'			.prayer-pop-email-template-card .form-table {',
			'				margin-top: 0;',
			'			}',
			'			.prayer-pop-notification-debug-settings {',
			'				margin-top: 18px;',
			'			}',
			'			.prayer-pop-notification-debug-settings h3 {',
			'				margin: 0 0 10px;',
			'				font-size: 14px;',
			'				color: #50575e;',
			'			}',
			'			#custom-buttons .prayer-pop-settings-block,',
			'			#custom-buttons .prayer-pop-wp-card {',
			'				margin: 0 0 24px;',
			'				background: transparent !important;',
			'				border: 0 !important;',
			'				border-top: 1px solid #e2e5e9 !important;',
			'				border-radius: 0 !important;',
			'				box-shadow: none !important;',
			'				padding-top: 18px;',
			'			}',
			'			#custom-buttons .prayer-pop-settings-block:first-child,',
			'			#custom-buttons .prayer-pop-wp-card:first-child {',
			'				border-top: 0 !important;',
			'				padding-top: 0;',
			'			}',
			'			#custom-buttons .prayer-pop-wp-card__body {',
			'				padding: 0 !important;',
			'			}',
			'			',
			'			/* Sticky Save Bar Styles */',
			'			.prayer-pop-sticky-save-bar {',
			'				position: fixed;',
			'			top: 32px; /* Account for WP admin bar */',
			'			right: 20px;',
			'			background: #ffffff;',
			'			border: 1px solid #c3c4c7;',
			'			border-radius: 4px;',
			'			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);',
			'			z-index: 1000;',
			'			display: none; /* Initially hidden */',
			'			padding: 0;',
			'			min-width: 200px;',
			'		}',
			'		',
			'		.sticky-save-content {',
			'			display: flex;',
			'			align-items: center;',
			'			gap: 10px;',
			'			padding: 10px 15px;',
			'		}',
			'		',
			'		.save-indicator {',
			'			font-size: 12px;',
			'			color: #d63638;',
			'			font-weight: 500;',
			'		}',
			'		',
			'		.save-changes-btn {',
			'			white-space: nowrap;',
			'		}',
			'		',
			'		/* Show sticky bar when form has changes */',
			'		.prayer-pop-sticky-save-bar.show {',
			'			display: block;',
			'		}',
			'		',
			'		/* Responsive adjustments */',
			'			@media screen and (max-width: 782px) {',
			'				.prayer-pop-sticky-save-bar {',
			'					top: 46px; /* Adjust for mobile admin bar */',
			'					right: 10px;',
			'					min-width: 160px;',
			'			}',
			'			',
			'			.sticky-save-content {',
			'				padding: 8px 12px;',
			'			}',
			'			',
			'				.save-indicator {',
			'					font-size: 11px;',
			'				}',
			'				.prayer-pop-settings-layout.has-feature-rail {',
			'					grid-template-columns: 1fr;',
			'					gap: 14px;',
			'				}',
			'				.prayer-pop-feature-rail {',
			'					position: static;',
			'				}',
			'			}',
			'		.prayer-pop-welcome-modal {',
			'			position: fixed;',
			'			inset: 0;',
			'			display: none;',
			'			z-index: 100000;',
			'		}',
			'		.prayer-pop-welcome-modal.is-open {',
			'			display: block;',
			'		}',
			'		.prayer-pop-welcome-modal__backdrop {',
			'			position: absolute;',
			'			inset: 0;',
			'			background: rgba(17, 24, 39, 0.55);',
			'		}',
			'		.prayer-pop-welcome-modal__dialog {',
			'			position: relative;',
			'			z-index: 1;',
			'			max-width: 760px;',
			'			margin: 8vh auto 0;',
			'			background: #fff;',
			'			border: 1px solid #dcdcde;',
			'			border-radius: 8px;',
			'			box-shadow: 0 24px 48px rgba(0, 0, 0, 0.18);',
			'			padding: 24px;',
			'		}',
			'		.prayer-pop-welcome-step {',
			'			display: none;',
			'		}',
			'		.prayer-pop-welcome-modal__dialog:not([data-welcome-step]) .prayer-pop-welcome-step-onboarding {',
			'			display: block;',
			'		}',
			'		.prayer-pop-welcome-modal__dialog[data-welcome-step="onboarding"] .prayer-pop-welcome-step-onboarding {',
			'			display: block;',
			'		}',
			'		.prayer-pop-welcome-modal__close {',
			'			position: absolute;',
			'			top: 12px;',
			'			right: 12px;',
			'			z-index: 2;',
			'			cursor: pointer;',
			'		}',
			'		.prayer-pop-welcome-modal__dialog h2 {',
			'			margin: 0 0 10px;',
			'			font-size: 26px;',
			'			line-height: 1.2;',
			'		}',
			'		.prayer-pop-welcome-modal__dialog p {',
			'			margin: 0 0 12px;',
			'			color: #2c3338;',
			'			line-height: 1.55;',
			'		}',
			'		.prayer-pop-welcome-modal__quicklist {',
			'			margin: 0 0 16px 18px;',
			'			line-height: 1.55;',
			'		}',
			'		.prayer-pop-welcome-modal__quicklist li {',
			'			margin-bottom: 6px;',
			'		}',
			'		.prayer-pop-welcome-modal__actions {',
			'			display: flex;',
			'			flex-wrap: wrap;',
			'			gap: 12px;',
			'			margin-top: 14px;',
			'		}',
			'		.prayer-pop-welcome-modal__action {',
			'			flex: 1 1 220px;',
			'			min-width: 220px;',
			'			border: 1px solid #dcdcde;',
			'			border-radius: 8px;',
			'			padding: 10px;',
			'			background: #fff;',
			'		}',
			'		.prayer-pop-welcome-modal__action .button {',
			'			display: block;',
			'			width: 100%;',
			'			text-align: center;',
			'		}',
			'		.prayer-pop-welcome-modal__action p {',
			'			margin: 8px 0 0;',
			'			font-size: 12px;',
			'			line-height: 1.45;',
			'			color: #4b5563;',
			'		}',
			'		@media screen and (max-width: 782px) {',
			'			.prayer-pop-welcome-modal__dialog {',
			'				margin: 4vh 16px 0;',
			'				padding: 18px;',
			'			}',
			'			.prayer-pop-welcome-modal__action {',
			'				min-width: 100%;',
			'			}',
			'		}',
		) );
		wp_add_inline_style( 'prayer-pop-admin', $prayer_pop_inline_css );
		?>
			<?php
		}

	/**
	 * Render tab title and description block.
	 *
	 * @param string $title       Tab title.
	 * @param string $icon        Dashicon slug.
	 * @param string $description Description text.
	 * @return void
	 */
	private function render_tab_intro( $title, $icon, $description ) {
		?>
		<div class="prayer-pop-tab-intro">
			<h2>
				<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $title ); ?></span>
			</h2>
			<?php if ( '' !== trim( (string) $description ) ) : ?>
				<p><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render right-side feature rail for settings tabs.
	 *
	 * @return void
	 */
	private function render_feature_rail() {
		$features_url = 'https://prayerpop.eu/features';
		?>
		<aside class="prayer-pop-feature-rail" aria-label="<?php esc_attr_e( 'Pro feature highlights', 'prayerpop' ); ?>">
			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-star-filled" aria-hidden="true"></span> <?php esc_html_e( 'Reasons to go PRO', 'prayerpop' ); ?></h3>
			</div>
			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-superhero" aria-hidden="true"></span> <?php esc_html_e( 'AI-Assisted Moderation', 'prayerpop' ); ?></h3>
				<p><?php esc_html_e( 'Reduce manual review load with optional AI checks for public submissions and clearer moderation flow.', 'prayerpop' ); ?></p>
			</div>
			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-groups" aria-hidden="true"></span> <?php esc_html_e( 'More Functionality', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'Testimonies, FAQ, Custom link buttons', 'prayerpop' ); ?></strong></p>
				<p><strong><?php esc_html_e( 'Divi 5 PrayerPop modules', 'prayerpop' ); ?></strong> <?php esc_html_e( 'and design options', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Social media sharing', 'prayerpop' ); ?></strong></p>
			</div>
			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> <?php esc_html_e( 'Public Submissions Wall', 'prayerpop' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Public submissions wall', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Social media sharing', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Reactions like "I Prayed" and "Celebrate"', 'prayerpop' ); ?></li>
				</ul>
			</div>
			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-art" aria-hidden="true"></span> <?php esc_html_e( 'Extra Styling Options', 'prayerpop' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'More layout controls for wall and cards', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Additional icon and bubble style controls', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Advanced color and spacing customization', 'prayerpop' ); ?></li>
				</ul>
			</div>
			<div class="prayer-pop-feature-card">
				<a class="button button-primary" href="<?php echo esc_url( $features_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Pro Features', 'prayerpop' ); ?></a>
			</div>
		</aside>
		<?php
	}

	/**
	 * Read, sanitize, and render plugin logo SVG.
	 *
	 * @param string $variant full|icon.
	 * @return string
	 */
	private function get_logo_markup( $variant = 'full' ) {
		$file_name = ( 'icon' === $variant ) ? 'prayer-pop-logo-icon.svg' : 'prayer-pop-logo-full.svg';
		$path      = PRAYERPOP_PLUGIN_DIR . 'assets/images/' . $file_name;

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return '';
		}

		$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $svg || '' === trim( $svg ) ) {
			return '';
		}

		if ( preg_match( '/<svg\b[^>]*>[\s\S]*<\/svg>/i', $svg, $matches ) ) {
			$svg = $matches[0];
		}

		$svg = preg_replace( '/<\?xml[\s\S]*?\?>/i', '', $svg );
		$svg = preg_replace( '/<!DOCTYPE[\s\S]*?>/i', '', $svg );
		$svg = preg_replace( '/\s+xmlns:serif="[^"]*"/i', '', $svg );
		$svg = preg_replace( '/\s+xml:space="[^"]*"/i', '', $svg );
		$svg = preg_replace( '/\s+(width|height)="100%"/i', '', $svg );
		$svg = preg_replace( '/\sid="[^"]*"/i', '', $svg );
		$svg = $this->normalize_svg_style_attributes( $svg );

		$svg = wp_kses( $svg, $this->get_svg_allowed_html() );
		if ( '' === trim( $svg ) ) {
			return '';
		}

		$class = ( 'icon' === $variant ) ? 'prayer-pop-logo-svg prayer-pop-logo-svg-icon' : 'prayer-pop-logo-svg prayer-pop-logo-svg-full';

		if ( preg_match( '/<svg\b/i', $svg ) ) {
			$svg = preg_replace( '/<svg\b/', '<svg class="' . esc_attr( $class ) . '" role="img" focusable="false"', $svg, 1 );
		}

		return trim( $svg );
	}

	/**
	 * SVG allow list for safe inline rendering in WP admin.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private function get_svg_allowed_html() {
		$global = array(
			'class'              => true,
			'fill'               => true,
			'stroke'             => true,
			'stroke-width'       => true,
			'stroke-linecap'     => true,
			'stroke-linejoin'    => true,
			'stroke-miterlimit'  => true,
			'fill-rule'          => true,
			'clip-rule'          => true,
			'opacity'            => true,
			'transform'          => true,
			'id'                 => true,
		);

		$allowed = array(
			'svg'      => array_merge(
				$global,
				array(
					'xmlns'               => true,
					'xmlns:xlink'         => true,
					'viewbox'             => true,
					'width'               => true,
					'height'              => true,
					'role'                => true,
					'focusable'           => true,
					'aria-hidden'         => true,
					'preserveaspectratio' => true,
				)
			),
			'g'        => $global,
			'path'     => array_merge( $global, array( 'd' => true ) ),
			'rect'     => array_merge( $global, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ) ),
			'circle'   => array_merge( $global, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
			'ellipse'  => array_merge( $global, array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ) ),
			'line'     => array_merge( $global, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ) ),
			'polygon'  => array_merge( $global, array( 'points' => true ) ),
			'polyline' => array_merge( $global, array( 'points' => true ) ),
			'defs'     => $global,
			'title'    => $global,
			'desc'     => $global,
		);

		return $allowed;
	}

	/**
	 * Build a WP-compatible SVG data URI for admin menu icon.
	 *
	 * @return string
	 */
	private function get_menu_icon_data_uri() {
		if ( null !== $this->menu_icon_data_uri ) {
			return $this->menu_icon_data_uri;
		}

		$icon_svg = 'PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAw' .
			'IDkzNCA5NjQiPiA8cGF0aCBmaWxsPSIjYTJhYWIyIiBkPSJNMzYyLjA5NSw4ODMuNzM3Yy0xNC45MjIsLTE0LjkwNCAtMzUuMTQ5' .
			'LC0yMy4yNzUgLTU2LjIzOSwtMjMuMjc1bC00NC4yMSwwYy0xNDQuNDA2LDAgLTI2MS42NDUsLTExNy4yMzkgLTI2MS42NDUsLTI2' .
			'MS42NDVsMCwtMzM3LjE3MWMwLC0xNDQuNDA2IDExNy4yMzksLTI2MS42NDUgMjYxLjY0NSwtMjYxLjY0NWw0MTAuMjE5LDBjMTQ0' .
			'LjQwNiwwIDI2MS42NDUsMTE3LjIzOSAyNjEuNjQ1LDI2MS42NDVsMCwzMzcuMTcxYzAsMTQ0LjQwNiAtMTE3LjIzOSwyNjEuNjQ1' .
			'IC0yNjEuNjQ1LDI2MS42NDVsLTQ0LjIxLDBjLTIxLjA5LDAgLTQxLjMxNyw4LjM3MSAtNTYuMjM5LDIzLjI3NWwtNjIuMzY3LDYy' .
			'LjI5Yy0yMy4zNjYsMjMuMzM4IC02MS4yMiwyMy4zMzggLTg0LjU4NywwbC02Mi4zNjcsLTYyLjI5Wm0tNDUuOTY2LC0yNzYuNzI2' .
			'Yy03OS41NzEsNC43NTUgLTEyMi41NiwtMjUuNzA3IC0xMjYuNjMzLDE1LjgwNWMtMS4wODksMTEuMDk3IDQuNzE3LDU2Ljk3MiA0' .
			'Ni44MzEsOTEuOTIyYzE2LjM2NiwxMy41ODIgNTYuNzU5LDQyLjQ5NyA3OS4yOTksNDcuMzEzYzQyLjY4MSw5LjEyIDU5LjQ4Nywt' .
			'MzEuMTU2IDc2LjEyLC01Ny40MDRjMTAuOTQ2LC0xNy4yNzIgMTYuNDIyLC05Ljk2OSA0My4wMywtNy4yMTZjMS4yODEsMC4xMzMg' .
			'NzUuMDY3LDEzLjY5MiAxNTAuNzE0LC0yMi45NDVjMzEuMTYyLC0xNS4wOTIgNjMuMzc4LC00Ni40NDUgNjQuOTAxLC00OC4xMzFj' .
			'Mi44NTMsLTMuMTU4IDMyLjEwNywtMzguMzY4IDQzLjMyLC03Ny42NDNjMTcuMzA1LC02MC42MTIgMTEuNTM4LC02MS43MjkgMTgu' .
			'ODQzLC0xMDAuMTkxYzE1LjY1NiwtODIuNDI1IDY0LjEzNiwtNTguMzIxIDY0LjE3NiwtNzUuNDM4YzAuMDEzLC01LjM4NCAtMy4z' .
			'NTQsLTYuNTA1IC04LjEzOCwtOS4xOTFjLTE5LjI0NywtMTAuODA4IC0yNC45MSwtMTIuODM5IC0zMS4xNTQsLTI0LjE0NGMtNS4y' .
			'NTksLTkuNTIxIC0xMS4zNjIsLTE1LjgzOCAtMTcuMzY1LC0yMC4xODFjLTIxLjMxLC0xNS40MTMgLTg4LjkzOSwtMzYuOTAyIC0x' .
			'MzQuMTMsNTMuOTkzYy0yLjIwMiw0LjQyOSAtMTMuODI3LDI3LjgxIC0yNS4yNyw1Ni40MjljLTEuNzc1LDQuNDM4IC0yLjIwMiw0' .
			'LjgwMSAtNy4wNjIsNC42NjZjLTQuNzI1LC0wLjEzMiAtNC42OTIsLTEuMTIzIC01Ljc3MSwtNS43NThjLTcuMDc1LC0zMC4zODIg' .
			'LTE5Ljc0NiwtODAuOTA2IC0xMDEuMTA1LC0xNDkuMDk1Yy00MS40MDMsLTM0LjcwMSAtOTEuNTgzLC02My42ODEgLTk5Ljk4LC02' .
			'OC41M2MtMTMuMjQ2LC03LjY1IC0xMDAuMjgyLC01OS4zNDcgLTExOS4yODMsLTQ2LjQwNWMtMTguNzM3LDEyLjc2MSAtMTYuNTY0' .
			'LDY5LjU2OSAxNC43MjcsMTA3LjA4YzMzLjA1NywzOS42MjkgNTEuMjExLDM1LjkzOSA1OS4xOTksNDEuMzczYzAuNjQ4LDAuNDQx' .
			'IDYuMzk0LDQuMzUgMi4wNDIsOS41ODRjLTcuMjQyLDguNzExIC04MS44NjcsLTMxLjc3OCAtOTEuMTM0LC01LjE2MmMtMS44MTYs' .
			'NS4yMTUgLTYuMTg0LDYyLjMxMiA0Ni41NjgsMTAwLjI1MWMyLjk5LDIuMTUgMTcuMjI3LDEyLjM5IDQ0LjUzOSwxMS42NGMwLjk3' .
			'OSwtMC4wMjcgOC42OTIsLTAuNzQ5IDEyLjI0NiwwLjJjMC45NjEsMC4yNTcgNy4yMjgsNC44NjkgLTAuMzQsMTAuOTkyYy04Ljkx' .
			'Miw3LjIxIC03MS43OTMsNC4wOTEgLTIyLjg5Niw1NS43NzRjMTQuNDMyLDE1LjI1NCAzNS40MTIsMjguOTY0IDM3LjQ0LDI5Ljk5' .
			'M2MxOS4xMDcsOS42OTUgMjUuNDQsMTEuODczIDYxLjA1NiwxNS44MzFjNy4yOTgsMC44MTEgMjEuNzg5LDAuMjMxIDI1LjA3Mywt' .
			'MC4xNzZjNC4zMDIsLTAuNTMzIDguNTI1LC0xLjk3NCAxMi44NiwtMS45MzdjMS4zOTcsMC4wMTIgMTUuNTM0LDIuODgxIC0zLjE4' .
			'MiwxNy4xN2MtMzAuMjIzLDIzLjA3MyAtNTkuNjkyLDQ2LjU1NCAtMTA5LjU0LDQ5LjUzM1ptMzcyLjg0NiwtMjYyLjg3MmM2LjQx' .
			'NCwwIDExLjYyMSw1LjIwNyAxMS42MjEsMTEuNjIxYzAsNi40MTQgLTUuMjA3LDExLjYyMSAtMTEuNjIxLDExLjYyMWMtNi40MTQs' .
			'MCAtMTEuNjIxLC01LjIwNyAtMTEuNjIxLC0xMS42MjFjMCwtNi40MTQgNS4yMDcsLTExLjYyMSAxMS42MjEsLTExLjYyMVptNTEu' .
			'MDM0LC0xNTMuODg3YzEuOTEzLC0wLjYyIDE5LjM5NywtNS45ODcgMjIuODkzLC0xMC4zNjljMy4wMjgsLTMuNzk1IDEwLjA4Nywt' .
			'MTUuMzY2IC0zLjM5MSwtMjYuMjExYy02LjM5MiwtNS4xNDQgLTcuMDYsLTMuNzE1IC0yMS4xMTEsLTguMTE2Yy0zNS4xOTQsLTEx' .
			'LjAyMiAtMjMuODA1LC00Ni42NDggLTQyLjk0MywtNTIuNjUyYy0yMi41MzUsLTcuMDcgLTI4LjMyOSwyNy41NzkgLTMwLjIxLDMy' .
			'LjMxOGMtMTAuNDQ5LDI2LjMzNyAtNTQuMTU2LDE4LjYzIC01My43NjYsNDMuMjgzYzAuMjI1LDE0LjIzOCAxNy42NTksMTguMzA1' .
			'IDMzLjczNCwyNS4wNTljMzAuMjA2LDEyLjY5MSAxNi40NDUsNDMuMjAyIDM3LjQ3NSw1MC4wOTJjMjAuMTQ0LDYuNiAyNi40ODQs' .
			'LTE3Ljg0OSAyNi42NzMsLTE4LjMzOGM4Ljg2OSwtMjIuODg4IDcuOTExLC0yNS41NCAzMC42NDUsLTM1LjA2NloiLz4KPC9zdmc+';

		$this->menu_icon_data_uri = 'data:image/svg+xml;base64,' . $icon_svg;

		return $this->menu_icon_data_uri;
	}

	/**
	 * Convert inline style properties to safe SVG attributes (so colors survive wp_kses).
	 *
	 * @param string $svg Raw svg markup.
	 * @return string
	 */
	private function normalize_svg_style_attributes( $svg ) {
		return preg_replace_callback(
			'/<([a-zA-Z0-9:_-]+)\b([^>]*)\sstyle="([^"]*)"([^>]*?)(\s*\/?)>/',
			static function ( $matches ) {
				$tag         = $matches[1];
				$before      = $matches[2];
				$style_value = $matches[3];
				$after       = $matches[4];
				$self_close  = $matches[5];
				$attrs       = $before . $after;

				$map = array(
					'fill'              => 'fill',
					'stroke'            => 'stroke',
					'stroke-width'      => 'stroke-width',
					'stroke-linecap'    => 'stroke-linecap',
					'stroke-linejoin'   => 'stroke-linejoin',
					'stroke-miterlimit' => 'stroke-miterlimit',
					'fill-rule'         => 'fill-rule',
					'clip-rule'         => 'clip-rule',
					'opacity'           => 'opacity',
				);

				$declarations = array_filter( array_map( 'trim', explode( ';', $style_value ) ) );
				foreach ( $declarations as $declaration ) {
					$parts = array_map( 'trim', explode( ':', $declaration, 2 ) );
					if ( 2 !== count( $parts ) ) {
						continue;
					}
					$prop = strtolower( $parts[0] );
					$val  = $parts[1];
					if ( ! isset( $map[ $prop ] ) || '' === $val ) {
						continue;
					}
					$attr_name = $map[ $prop ];
					if ( preg_match( '/\b' . preg_quote( $attr_name, '/' ) . '\s*=/i', $attrs ) ) {
						continue;
					}
					$attrs .= ' ' . $attr_name . '="' . esc_attr( $val ) . '"';
				}

				return '<' . $tag . $attrs . $self_close . '>';
			},
			$svg
		);
	}

	/**
	 * Render one-time welcome modal shown after activation.
	 *
	 * @param bool $is_open Whether the modal should be visible.
	 * @return void
	 */
	private function render_welcome_modal( $is_open ) {
		$modal_class = $is_open ? 'prayer-pop-welcome-modal is-open' : 'prayer-pop-welcome-modal';
		$submissions_url = add_query_arg(
			array(
				'post_type' => 'prayer_request',
			),
			admin_url( 'edit.php' )
		);
		$settings_url = add_query_arg(
			array(
				'page' => 'prayer-pop-settings',
				'tab'  => 'general',
			),
			admin_url( 'admin.php' )
		);
		$docs_url = add_query_arg(
			array(
				'page' => 'prayer-pop-settings',
				'tab'  => 'documentation',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div id="prayer-pop-welcome-modal" class="<?php echo esc_attr( $modal_class ); ?>" role="dialog" aria-modal="true" aria-labelledby="prayer-pop-welcome-title" aria-hidden="<?php echo $is_open ? 'false' : 'true'; ?>">
			<div class="prayer-pop-welcome-modal__backdrop"></div>
			<div class="prayer-pop-welcome-modal__dialog">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button-link prayer-pop-welcome-modal__close" data-welcome-close="1" aria-label="<?php esc_attr_e( 'Close welcome message', 'prayerpop' ); ?>" role="button">✕</a>
				<div class="prayer-pop-welcome-step prayer-pop-welcome-step-onboarding">
					<h2 id="prayer-pop-welcome-title"><?php esc_html_e( 'Welcome to PrayerPop', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'PrayerPop helps your church receive prayer requests through a simple frontend bubble and review them inside WordPress admin.', 'prayerpop' ); ?></p>
					<p><strong><?php esc_html_e( 'How it works:', 'prayerpop' ); ?></strong></p>
					<p><?php esc_html_e( 'People submit prayer requests through the bubble on your website. Those submissions appear in WordPress admin, where your team can review them, approve or decline them, archive old items, or mark approved prayers as answered.', 'prayerpop' ); ?></p>
					<p><strong><?php esc_html_e( 'Your next steps:', 'prayerpop' ); ?></strong></p>
					<ol class="prayer-pop-welcome-modal__quicklist">
						<li><strong><?php esc_html_e( 'Confirm the bubble is enabled', 'prayerpop' ); ?></strong><br><?php esc_html_e( 'Open General settings and keep Show PrayerPop Bubble turned on.', 'prayerpop' ); ?></li>
						<li><strong><?php esc_html_e( 'Check your submissions', 'prayerpop' ); ?></strong><br><?php esc_html_e( 'Go to Admin Submissions to see incoming prayer requests and manage them.', 'prayerpop' ); ?></li>
						<li><strong><?php esc_html_e( 'Adjust your settings', 'prayerpop' ); ?></strong><br><?php esc_html_e( 'Customize the bubble style, form text, notifications, and retention cleanup.', 'prayerpop' ); ?></li>
					</ol>
					<div class="prayer-pop-welcome-modal__actions">
						<div class="prayer-pop-welcome-modal__action">
							<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Settings & Setup', 'prayerpop' ); ?></a>
							<p><?php esc_html_e( 'Configure bubble behavior, admin approval, style, text, notifications, and retention cleanup.', 'prayerpop' ); ?></p>
						</div>
						<div class="prayer-pop-welcome-modal__action">
							<a href="<?php echo esc_url( $submissions_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Admin Submissions', 'prayerpop' ); ?></a>
							<p><?php esc_html_e( 'Review incoming prayer requests, approve or decline items, archive old items, and mark answered prayers.', 'prayerpop' ); ?></p>
						</div>
						<div class="prayer-pop-welcome-modal__action">
							<a href="<?php echo esc_url( $docs_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Documentation', 'prayerpop' ); ?></a>
							<p><?php esc_html_e( 'Open practical setup guides and workflow explanations for your team.', 'prayerpop' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$prayer_pop_inline_js = implode( "\n", array(
			'		(function(){',
			'			var modal = document.getElementById(\'prayer-pop-welcome-modal\');',
			'			if (!modal) {',
			'				return;',
			'			}',
			'',
			'			function closeModal() {',
			'				modal.classList.remove(\'is-open\');',
			'				modal.setAttribute(\'aria-hidden\', \'true\');',
			'				document.body.style.overflow = \'\';',
			'				try {',
			'					var url = new URL(window.location.href);',
			'					if (url.searchParams.has(\'prayer_pop_welcome\')) {',
			'						url.searchParams.delete(\'prayer_pop_welcome\');',
			'						window.history.replaceState({ path: url.href }, \'\', url.href);',
			'					}',
			'				} catch (e) {}',
			'			}',
			'',
			'			modal.querySelectorAll(\'[data-welcome-close="1"]\').forEach(function(node){',
			'				node.addEventListener(\'click\', function(e){',
			'					e.preventDefault();',
			'					closeModal();',
			'				});',
			'			});',
			'',
			'			document.addEventListener(\'keydown\', function(e){',
			'				var isEscape = (e.key === \'Escape\' || e.keyCode === 27);',
			'				if (isEscape && modal.classList.contains(\'is-open\')) {',
			'					closeModal();',
			'				}',
			'			});',
			'		})();',
		) );
		wp_add_inline_script( 'prayer-pop-admin', $prayer_pop_inline_js );
		?>
		<?php
	}

	/**
	 * Render floating frontend overlay preview outside settings content.
	 *
	 * @return void
	 */
	public function render_frontend_overlay_preview() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'prayer-pop-settings' !== $page ) {
			return;
		}

		$preview_url = add_query_arg(
			array(
				'prayer_pop_preview' => '1',
				'_pp_preview_nonce' => wp_create_nonce( 'prayer_pop_frontend_preview' ),
			),
			home_url( '/' )
		);
		?>
		<div id="prayer-pop-admin-frontend-preview" class="prayer-pop-admin-frontend-preview" aria-hidden="true">
			<div class="prayer-pop-admin-frontend-preview-actions">
				<span class="prayer-pop-admin-frontend-preview-title"><?php esc_html_e( 'Frontend Overlay Preview', 'prayerpop' ); ?></span>
				<button type="button" class="button button-secondary" id="prayer-pop-preview-reload"><?php esc_html_e( 'Reload', 'prayerpop' ); ?></button>
				<a class="button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in New Tab', 'prayerpop' ); ?></a>
			</div>
			<iframe
				id="prayer-pop-frontend-preview-frame"
				class="prayer-pop-frontend-preview-frame"
				src="<?php echo esc_url( $preview_url ); ?>"
				title="<?php esc_attr_e( 'PrayerPop Frontend Preview', 'prayerpop' ); ?>"
				loading="lazy"
			></iframe>
		</div>
		<?php
	}

	/**
	 * Render documentation tab content
	 */
	private function render_documentation_tab() {
		?>
		<div class="prayer-pop-docs">
			<div class="prayer-pop-doc-start">
				<h3><?php esc_html_e( 'Most common tasks', 'prayerpop' ); ?></h3>
				<ul>
					<li><a href="#prayer-pop-doc-quick-start"><?php esc_html_e( 'Get PrayerPop live quickly', 'prayerpop' ); ?></a></li>
					<li><a href="#prayer-pop-doc-bubble"><?php esc_html_e( 'Use the PrayerPop bubble', 'prayerpop' ); ?></a></li>
					<li><a href="#prayer-pop-doc-managing-submissions"><?php esc_html_e( 'Review and process submissions', 'prayerpop' ); ?></a></li>
					<li><a href="#prayer-pop-doc-settings"><?php esc_html_e( 'Understand settings tabs', 'prayerpop' ); ?></a></li>
					<li><a href="#prayer-pop-doc-troubleshooting"><?php esc_html_e( 'Solve common issues fast', 'prayerpop' ); ?></a></li>
				</ul>
			</div>

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-quick-start">
				<h2><?php esc_html_e( 'Quick Start Guide', 'prayerpop' ); ?></h2>
				<p><?php esc_html_e( 'PrayerPop is bubble-first. Follow these steps to collect and review prayer requests.', 'prayerpop' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Open General settings and confirm Show PrayerPop Bubble is enabled.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Choose whether visitors may leave the name field empty.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Set the retention period if old approved or answered requests should be archived and later cleaned up.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'In Notifications, set your recipient email and schedule, then send a test email.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'In Style, adjust the bubble color, icon, position, animation, and font.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'In Text Customization, edit the visible prayer request form labels and messages if needed.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Visit the frontend of your site, click the bubble, and submit a test prayer request.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Open PrayerPop -> Submissions and process the test request.', 'prayerpop' ); ?></li>
				</ol>
				<div class="prayer-pop-doc-note is-tip"><strong><?php esc_html_e( 'Note:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'The global bubble opens the prayer request form on your site.', 'prayerpop' ); ?></div>
			</section>

			<hr />

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-bubble">
				<h2><?php esc_html_e( 'Using the PrayerPop Bubble', 'prayerpop' ); ?></h2>
				<p><?php esc_html_e( 'The bubble is the frontend entry point. When enabled, it appears on your site and opens the prayer request form.', 'prayerpop' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Visitors submit one prayer request message and an optional name.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Submissions are saved as prayer requests in WordPress admin.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Every new request starts in Pending Action so an admin can review it.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'The form includes honeypot, minimum-submit-time, rate-limit, and cooldown protection.', 'prayerpop' ); ?></li>
				</ul>
				<div class="prayer-pop-doc-note"><strong><?php esc_html_e( 'Bubble setup:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Use the global bubble setting to make the form available on the frontend.', 'prayerpop' ); ?></div>
			</section>

			<hr />

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-managing-submissions">
				<h2><?php esc_html_e( 'Managing Submissions', 'prayerpop' ); ?></h2>
				<p><?php esc_html_e( 'This is where your church team will spend most time. New submissions appear in WordPress -> Submissions.', 'prayerpop' ); ?></p>
				<p><?php esc_html_e( 'Usually this is handled by administrators or trusted team members with PrayerPop permissions.', 'prayerpop' ); ?></p>

				<h3><?php esc_html_e( 'What happens when someone submits', 'prayerpop' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'The request is saved as a prayer request submission.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'The request starts as Pending Action.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'An admin reviews the request and chooses the next action.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Approved prayer requests can later be marked as answered with an optional answer update.', 'prayerpop' ); ?></li>
				</ul>
				<div class="prayer-pop-doc-note"><strong><?php esc_html_e( 'Admin review:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Every request starts in Pending Action so your team can review it manually.', 'prayerpop' ); ?></div>

				<h3><?php esc_html_e( 'Typical workflow', 'prayerpop' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Open Submissions in WordPress admin.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Start with pending rows first so new items are handled quickly.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Approve requests you want to keep as accepted.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Decline requests that should be rejected.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Archive requests that should leave the active queue but remain stored.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Mark approved prayer requests as answered when appropriate.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Move spam or unwanted records to Trash.', 'prayerpop' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Filters and Bulk Actions', 'prayerpop' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Use quick filters for queue views such as All, Approved, Answered, Declined, Archived, and Trash.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Use dropdown filters for status, visibility, type, and date when available.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Use bulk actions to send selected via email, approve selected, decline selected, mark prayer requests as answered, edit selected, archive, or trash.', 'prayerpop' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Status labels (plain meaning)', 'prayerpop' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Pending Action:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Waiting for admin decision.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Approved:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Accepted by an admin.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Answered:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Approved prayer request marked as answered.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Declined:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Rejected by an admin.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Archived:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Kept for history, outside your active queue.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Trash:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Moved to trash and restorable from the Trash view.', 'prayerpop' ); ?></li>
				</ul>
			</section>

			<hr />

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-settings">
				<h2><?php esc_html_e( 'What Each Settings Tab Does', 'prayerpop' ); ?></h2>
				<p><?php esc_html_e( 'Use this section to find the right setting quickly. Each tab description explains what it does and when to use it.', 'prayerpop' ); ?></p>

				<h3><?php esc_html_e( 'General Tab', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Controls the prayer request workflow.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want to show or hide the bubble, allow anonymous names, or set the retention period.', 'prayerpop' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Show PrayerPop Bubble:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Shows the floating frontend bubble that opens the prayer request form.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Anonymous submissions:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Allows visitors to leave the name field empty.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Admin review:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Every request starts in Pending Action for review.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Retention period:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Older approved/answered submissions move to archive first. Later, archived submissions can be auto-deleted based on this time window.', 'prayerpop' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Notifications', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Sends email alerts when prayer requests arrive.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want your team notified automatically instead of checking manually.', 'prayerpop' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Set recipient and frequency (immediate, daily, weekly).', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Set subject/body in Email Template and run Send Test Email.', 'prayerpop' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Style Tab', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Controls how PrayerPop looks on your website.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want to adjust colors, typography, bubble design, icon, position, animation, and spacing.', 'prayerpop' ); ?></p>

				<h3><?php esc_html_e( 'Text Customization Tab', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Lets you rewrite visible prayer request form text, labels, and messages.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want your own tone, wording, or single-language translation.', 'prayerpop' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Edit text directly in fields.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Export text fields to JSON as backup.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Import JSON back after edits or translation updates.', 'prayerpop' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Anti-Spam & Cooldown', 'prayerpop' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Current limit:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Maximum 5 submissions from the same source in 5 minutes.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Cooldown:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'If the limit is reached, new submissions are blocked for 3 minutes.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Customization:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Currently fixed. Can be made configurable in a future update if needed.', 'prayerpop' ); ?></li>
				</ul>
			</section>

			<hr />

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-troubleshooting">
				<h2><?php esc_html_e( 'Troubleshooting', 'prayerpop' ); ?></h2>
				<ul>
					<li><strong><?php esc_html_e( 'Email delivery issue:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Check Notifications and Email Template settings, then run Send Test Email.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Bubble visibility issue:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Check General settings, confirm Show PrayerPop Bubble is enabled, then clear cache and reload the frontend.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Website changes are missing:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Clear cache (plugin/server/CDN) and reload the page.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Text import failed:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Use a JSON file exported from PrayerPop that contains top-level "texts" data.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Pending approvals:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Open PrayerPop -> Submissions and approve pending requests manually.', 'prayerpop' ); ?></li>
				</ul>
			</section>

			<details class="prayer-pop-doc-advanced">
				<summary><?php esc_html_e( 'Advanced: Privacy, Data, Cookies, and Open-Source Notices', 'prayerpop' ); ?></summary>
				<div class="prayer-pop-doc-advanced__content">
					<h3><?php esc_html_e( 'Privacy & Data Handling', 'prayerpop' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Submissions are stored as WordPress posts (post type: prayer_request) with related meta fields.', 'prayerpop' ); ?></li>
							<li><?php esc_html_e( 'Main stored fields include: message, name (or anonymous marker), submission type, public marker, moderation status, and answered-prayer note when used.', 'prayerpop' ); ?></li>
							<li><?php esc_html_e( 'Notification settings can store one admin email address for alerts.', 'prayerpop' ); ?></li>
							<li><?php esc_html_e( 'No external analytics or ad trackers are added by PrayerPop itself.', 'prayerpop' ); ?></li>
						</ul>

					<h3><?php esc_html_e( 'Cookies & Browser Storage Used by PrayerPop', 'prayerpop' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Local anti-spam and cooldown tracking is used to limit repeated submissions.', 'prayerpop' ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'Retention & Deletion (Practical)', 'prayerpop' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Approved/Answered items older than the retention window are archived first.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Archived items are deleted only after they have also stayed archived for the full retention window.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Retention set to Forever (0) keeps submissions stored during automatic cleanup.', 'prayerpop' ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'Uninstall Behavior', 'prayerpop' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'On uninstall, plugin options and scheduled cron hooks are removed.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Submission posts remain in WordPress after uninstall unless you delete them manually.', 'prayerpop' ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'Open-Source Components', 'prayerpop' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'WordPress Dashicons (from WordPress core).', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Tabler SVG icon dataset (MIT) bundled locally for icon selection.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'jQuery provided by WordPress core.', 'prayerpop' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'See THIRD_PARTY_NOTICES.txt in the plugin folder for attribution details.', 'prayerpop' ); ?></p>
				</div>
			</details>
		</div>
		<?php
	}

	public function handle_send_test_email() {
		// Verify nonce and capability.
		check_ajax_referer( 'prayer_pop_admin_actions', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Permission denied.', 'prayerpop' ) );
		}

		// Get email settings.
		$notification_options = get_option( 'prayer_pop_notification_settings', array() );
		$email_template       = get_option( 'prayer_pop_email_template', array() );

		$admin_email = ! empty( $notification_options['notification_email'] )
			? sanitize_email( $notification_options['notification_email'] )
			: get_option( 'admin_email' );

		$subject = ( isset( $email_template['email_subject'] ) && $email_template['email_subject'] )
			? $email_template['email_subject']
			: esc_html__( 'Test PrayerPop Email', 'prayerpop' );

		$body = ( isset( $email_template['email_body'] ) && $email_template['email_body'] )
			? $email_template['email_body']
			: esc_html__(
				'This is a test email from PrayerPop. If you received this, email notifications are working!', 'prayerpop' );

		// Replace placeholders with test data.
		$placeholders = array(
			'{type}'    => 'Test',
			'{name}'    => 'Admin',
			'{message}' => 'This is a test message from PrayerPop.',
		);
		$subject      = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
		$body         = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body );

		$sent = wp_mail( $admin_email, $subject, $body );

		if ( $sent ) {
			wp_send_json_success(
				sprintf(
					/* translators: %s: destination email address */
					esc_html__( 'Test email sent to: %s', 'prayerpop' ),
					esc_html( $admin_email )
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'Failed to send test email.', 'prayerpop' ) );
		}
	}

	/**
	 * Populate hidden environment fields on the feedback page.
	 *
	 * @return void
	 */
	public function render_feedback_environment_capture_script() {
		?>
		<?php
		$prayer_pop_inline_js = implode( "\n", array(
			'		(function () {',
			'			var userAgentField = document.getElementById(\'prayer-pop-feedback-user-agent\');',
			'			var viewportField = document.getElementById(\'prayer-pop-feedback-viewport\');',
			'			var platformField = document.getElementById(\'prayer-pop-feedback-platform\');',
			'			var currentUrlField = document.getElementById(\'prayer-pop-feedback-current-url\');',
			'			if (userAgentField) {',
			'				userAgentField.value = navigator.userAgent || \'\';',
			'			}',
			'			if (viewportField) {',
			'				viewportField.value = (window.innerWidth || 0) + \'x\' + (window.innerHeight || 0);',
			'			}',
			'			if (platformField) {',
			'				platformField.value = navigator.platform || \'\';',
			'			}',
			'			if (currentUrlField) {',
			'				currentUrlField.value = window.location.href || \'\';',
			'			}',
			'		})();',
		) );
		wp_add_inline_script( 'prayer-pop-admin', $prayer_pop_inline_js );
		?>
		<?php
	}

	/**
	 * Handle feedback form submission.
	 *
	 * @return void
	 */
	public function handle_submit_feedback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'prayerpop' ) );
		}

		check_admin_referer( 'prayer_pop_submit_feedback', 'prayer_pop_feedback_nonce' );

		$type        = isset( $_POST['feedback_type'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback_type'] ) ) : '';
		$title       = isset( $_POST['feedback_title'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback_title'] ) ) : '';
		$description = isset( $_POST['feedback_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback_description'] ) ) : '';
		$steps       = isset( $_POST['feedback_steps'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback_steps'] ) ) : '';
		$user_agent  = isset( $_POST['feedback_user_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback_user_agent'] ) ) : '';
		$viewport    = isset( $_POST['feedback_viewport'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback_viewport'] ) ) : '';
		$platform    = isset( $_POST['feedback_platform'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback_platform'] ) ) : '';
		$current_url = isset( $_POST['feedback_current_url'] ) ? esc_url_raw( wp_unslash( $_POST['feedback_current_url'] ) ) : '';

		if ( '' === $user_agent && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		if ( '' === $current_url && isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			$scheme = is_ssl() ? 'https://' : 'http://';
			$current_url = esc_url_raw( $scheme . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) ) );
		}

		if ( '' === $title || '' === $description ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'            => 'prayer-pop-feedback',
						'feedback_status' => 'error',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$allowed_types = array( 'bug', 'feature request', 'question' );
		if ( ! in_array( strtolower( $type ), $allowed_types, true ) ) {
			$type = 'question';
		}

		$plugin_version = defined( 'PRAYERPOP_VERSION' ) ? PRAYERPOP_VERSION : 'unknown';
		$wp_version     = get_bloginfo( 'version' );
		$php_version    = function_exists( 'phpversion' ) ? (string) phpversion() : 'unknown';
		$theme          = wp_get_theme();
		$theme_name     = ( $theme instanceof WP_Theme ) ? (string) $theme->get( 'Name' ) : 'unknown';
		$theme_version  = ( $theme instanceof WP_Theme ) ? (string) $theme->get( 'Version' ) : '';
		$theme_label    = '' !== $theme_name ? $theme_name : 'unknown';
		if ( '' !== $theme_version ) {
			$theme_label .= ' ' . $theme_version;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugin_labels = array();
		foreach ( $active_plugins as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$plugin_name = isset( $all_plugins[ $plugin_file ]['Name'] ) ? (string) $all_plugins[ $plugin_file ]['Name'] : $plugin_file;
				$plugin_ver  = isset( $all_plugins[ $plugin_file ]['Version'] ) ? (string) $all_plugins[ $plugin_file ]['Version'] : '';
				$active_plugin_labels[] = '' !== $plugin_ver ? ( $plugin_name . ' ' . $plugin_ver ) : $plugin_name;
			} else {
				$active_plugin_labels[] = $plugin_file;
			}
		}
		$active_plugins_summary = empty( $active_plugin_labels ) ? 'none' : implode( ', ', $active_plugin_labels );

		$subject = sprintf( '[PrayerPop] %s: %s', ucfirst( $type ), $title );
		$message = "Type: {$type}\n";
		$message .= "Title: {$title}\n\n";
		$message .= "Description:\n{$description}\n\n";
		if ( '' !== trim( $steps ) ) {
			$message .= "Steps to reproduce:\n{$steps}\n\n";
		}

		$message .= "Plugin version: {$plugin_version}\n";
		$message .= 'WordPress version: ' . ( '' !== $wp_version ? $wp_version : 'unknown' ) . "\n";
		$message .= 'User agent: ' . ( '' !== $user_agent ? $user_agent : 'unknown' ) . "\n";
		$message .= 'Viewport: ' . ( '' !== $viewport ? $viewport : 'unknown' ) . "\n";
		$message .= 'Platform: ' . ( '' !== $platform ? $platform : 'unknown' ) . "\n";
		$message .= 'Current URL: ' . ( '' !== $current_url ? $current_url : 'unknown' ) . "\n";
		$message .= 'Active theme: ' . ( '' !== $theme_label ? $theme_label : 'unknown' ) . "\n";
		$message .= 'PHP version: ' . ( '' !== $php_version ? $php_version : 'unknown' ) . "\n";
		$message .= 'Active plugins: ' . $active_plugins_summary . "\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( 'info@osain.ee', $subject, $message, $headers );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'prayer-pop-feedback',
					'feedback_status' => $sent ? 'success' : 'error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Build environment preview block for UI.
	 *
	 * @return string
	 */
	private function get_feedback_environment_preview() {
		$plugin_version = defined( 'PRAYERPOP_VERSION' ) ? PRAYERPOP_VERSION : 'unknown';
		$wp_version     = get_bloginfo( 'version' );
		$php_version    = function_exists( 'phpversion' ) ? (string) phpversion() : 'unknown';
		$theme          = wp_get_theme();
		$theme_name     = ( $theme instanceof WP_Theme ) ? (string) $theme->get( 'Name' ) : 'unknown';
		$theme_version  = ( $theme instanceof WP_Theme ) ? (string) $theme->get( 'Version' ) : '';
		$theme_label    = '' !== $theme_name ? $theme_name : 'unknown';
		if ( '' !== $theme_version ) {
			$theme_label .= ' ' . $theme_version;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugin_labels = array();
		foreach ( $active_plugins as $plugin_file ) {
			$plugin_file = (string) $plugin_file;
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$plugin_name = isset( $all_plugins[ $plugin_file ]['Name'] ) ? (string) $all_plugins[ $plugin_file ]['Name'] : $plugin_file;
				$plugin_ver  = isset( $all_plugins[ $plugin_file ]['Version'] ) ? (string) $all_plugins[ $plugin_file ]['Version'] : '';
				$active_plugin_labels[] = '' !== $plugin_ver ? ( $plugin_name . ' ' . $plugin_ver ) : $plugin_name;
			} else {
				$active_plugin_labels[] = $plugin_file;
			}
		}
		$active_plugins_summary = empty( $active_plugin_labels ) ? 'none' : implode( ', ', $active_plugin_labels );
		$server_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$server_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$server_current_url = '' !== $server_request_uri ? admin_url( ltrim( $server_request_uri, '/' ) ) : '';

		$lines          = array(
			'Plugin version:    ' . ( '' !== $plugin_version ? $plugin_version : 'unknown' ),
			'WordPress version: ' . ( '' !== $wp_version ? $wp_version : 'unknown' ),
			'User agent:        ' . ( '' !== $server_user_agent ? $server_user_agent : 'unknown' ),
			'Viewport:          unknown',
			'Platform:          unknown',
			'Current URL:       ' . ( '' !== $server_current_url ? $server_current_url : 'unknown' ),
			'Active theme:      ' . ( '' !== $theme_label ? $theme_label : 'unknown' ),
			'PHP version:       ' . ( '' !== $php_version ? $php_version : 'unknown' ),
			'Active plugins:    ' . $active_plugins_summary,
		);

		return implode( "\n", $lines );
	}
}
