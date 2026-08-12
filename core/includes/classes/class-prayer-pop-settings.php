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
		add_action( 'admin_menu', array( $this, 'reorder_admin_menu_items' ), 999 );
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_workspace_notice_styles' ) );
		add_action( 'admin_head', array( $this, 'suppress_divi_supreme_license_notice' ), 0 );
		add_action('admin_footer-prayer-pop_page_prayer-pop-settings', array($this, 'render_frontend_overlay_preview'));
		add_action('admin_notices', array($this, 'show_settings_messages'));
		add_action('admin_post_prayer_pop_submit_feedback', array($this, 'handle_submit_feedback'));
	}

	/**
	 * Whether the current WordPress admin screen belongs to PrayerPop.
	 *
	 * @return bool
	 */
	private function is_prayerpop_workspace() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen && isset( $screen->id ) ? (string) $screen->id : '';
		$post_type = $screen && isset( $screen->post_type ) ? (string) $screen->post_type : '';
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return
			'toplevel_page_prayer-pop' === $screen_id ||
			0 === strpos( $screen_id, 'prayer-pop_page_' ) ||
			'prayer_request' === $post_type ||
			false !== strpos( $screen_id, 'prayer_request' ) ||
			0 === strpos( $page, 'prayer-pop' );
	}

	/**
	 * Keep third-party and WordPress notices out of focused PrayerPop workspaces.
	 *
	 * PrayerPop action notices use the prayerpop-admin-notice class and stay
	 * visible. Notices rendered inside a PrayerPop page wrapper are unaffected.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_workspace_notice_styles( $hook ) {
		if ( ! $this->is_prayerpop_workspace() ) {
			return;
		}

		$notice_css_path    = PRAYERPOP_PLUGIN_DIR . 'assets/css/prayer-pop-admin-notices.css';
		$notice_css_version = file_exists( $notice_css_path ) ? (string) filemtime( $notice_css_path ) : PRAYERPOP_VERSION;
		wp_enqueue_style(
			'prayer-pop-admin-notices',
			PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-admin-notices.css',
			array(),
			$notice_css_version
		);
	}

	/**
	 * Keep Divi Supreme's missing-license reminder off PrayerPop workspaces.
	 *
	 * The reminder remains available on Divi Supreme and every other WordPress
	 * admin screen. Removing its registered callback is intentionally narrower
	 * than hiding arbitrary notices with CSS.
	 *
	 * @return void
	 */
	public function suppress_divi_supreme_license_notice() {
		if ( ! $this->is_prayerpop_workspace() ) {
			return;
		}

		global $wp_filter;
		if ( empty( $wp_filter['admin_notices'] ) || empty( $wp_filter['admin_notices']->callbacks ) ) {
			return;
		}

		foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $registered_callback ) {
				$callback = isset( $registered_callback['function'] ) ? $registered_callback['function'] : null;
				if (
					is_array( $callback ) &&
					isset( $callback[0], $callback[1] ) &&
					is_object( $callback[0] ) &&
					'DiviSupreme\\Core\\Settings' === get_class( $callback[0] ) &&
					'dsm_render_missing_license_notice' === $callback[1]
				) {
					remove_action( 'admin_notices', $callback, $priority );
				}
			}
		}
	}

	/** Keep Chat in the same relative submenu position as PrayerPop Pro. */
	public function reorder_admin_menu_items() {
		global $submenu;

		if ( ! isset( $submenu['prayer-pop'] ) || ! is_array( $submenu['prayer-pop'] ) ) {
			return;
		}

		$desired_order = array(
			'edit.php?post_type=prayer_request',
			'prayer-pop-chat',
			'prayer-pop-feedback',
			'prayer-pop-settings',
		);
		$ordered_items = array();
		$remaining     = $submenu['prayer-pop'];

		foreach ( $desired_order as $slug ) {
			foreach ( $remaining as $index => $item ) {
				if ( isset( $item[2] ) && $item[2] === $slug ) {
					$ordered_items[] = $item;
					unset( $remaining[ $index ] );
					break;
				}
			}
		}

		$submenu['prayer-pop'] = array_values( array_merge( $ordered_items, $remaining ) );
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
		$aliases = array(
			'general'       => 'popup',
			'submissions'   => 'popup',
			'notifications' => 'notifications-email',
			'style'         => 'design',
			'text'          => 'language-text',
		);

		$tab = sanitize_key( (string) $tab );
		if ( isset( $aliases[ $tab ] ) ) {
			$tab = $aliases[ $tab ];
		}

		$allowed_tabs = array(
			'popup',
			'notifications-email',
			'design',
			'language-text',
			'data',
			'documentation',
		);

		return in_array( $tab, $allowed_tabs, true ) ? $tab : 'popup';
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
		wp_enqueue_media();

		// Enqueue admin styles
		wp_enqueue_style(
			'prayer-pop-admin',
			PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-admin.css',
			array(),
			$admin_css_version
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
		$active_tab = $this->sanitize_active_tab( get_option( 'prayer_pop_active_tab', 'popup' ) );
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
				'textImport' => array(
					'nonce'             => wp_create_nonce( 'prayer_pop_import_texts' ),
					'selectFile'        => __( 'Please select a file to import.', 'prayerpop' ),
					'selectValidJson'   => __( 'Please select a valid JSON file.', 'prayerpop' ),
					'importing'         => __( 'Importing...', 'prayerpop' ),
					'importSuccess'     => __( 'Text fields imported successfully!', 'prayerpop' ),
					'importFailedPrefix' => __( 'Import failed: ', 'prayerpop' ),
					'unknownError'      => __( 'Unknown error.', 'prayerpop' ),
					'importFailedRetry' => __( 'Import failed. Please try again.', 'prayerpop' ),
				),
				'feedbackForm' => array(
					'bugTitle'              => __( 'Bug summary', 'prayerpop' ),
					'bugTitlePlaceholder'   => __( 'A short summary of the problem', 'prayerpop' ),
					'bugDescription'        => __( 'What happened? What did you expect?', 'prayerpop' ),
					'bugDescriptionPlaceholder' => __( 'Describe what went wrong and what you expected to happen.', 'prayerpop' ),
					'featureTitle'          => __( 'Feature idea', 'prayerpop' ),
					'featureTitlePlaceholder' => __( 'A short name for your idea', 'prayerpop' ),
					'featureDescription'    => __( 'What would you like PrayerPop to do?', 'prayerpop' ),
					'featureDescriptionPlaceholder' => __( 'Describe the feature and how it would help you.', 'prayerpop' ),
					'questionTitle'         => __( 'Question', 'prayerpop' ),
					'questionTitlePlaceholder' => __( 'What is your question about?', 'prayerpop' ),
					'questionDescription'   => __( 'How can we help?', 'prayerpop' ),
					'questionDescriptionPlaceholder' => __( 'Write your question and include any helpful details.', 'prayerpop' ),
				),
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
		$active_tab = $this->sanitize_active_tab( get_option( 'prayer_pop_active_tab', 'popup' ) );
		
		// Check for settings update
		$settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : '';
		if ( '' !== $settings_updated ) {
			if ( 'true' === $settings_updated ) {
				$tab_label = ucfirst(str_replace('-', ' ', $active_tab));
				echo '<div class="notice prayerpop-admin-notice notice-success is-dismissible"><p>' .
					sprintf(
						/* translators: %s: settings tab label. */
						esc_html__( '%s settings updated successfully.', 'prayerpop' ),
						esc_html( $tab_label )
					) . 
					'</p></div>';
			} elseif ( 'false' === $settings_updated ) {
				echo '<div class="notice prayerpop-admin-notice notice-error is-dismissible"><p>' .
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

		$menu_hook = add_menu_page(
			esc_html__('PrayerPop', 'prayerpop' ),
			esc_html__('PrayerPop', 'prayerpop' ),
			'manage_options',
			$menu_slug,
			array( $this, 'render_settings_page' ),
			$menu_icon,
			60
		);
		add_action( 'load-' . $menu_hook, array( $this, 'redirect_to_submissions' ) );

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

	/** Send the top-level Free menu directly to its primary submissions workflow. */
	public function redirect_to_submissions() {
		wp_safe_redirect( admin_url( 'edit.php?post_type=prayer_request' ) );
		exit;
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
		$current_user = wp_get_current_user();
		$current_user_email = isset( $current_user->user_email ) ? sanitize_email( $current_user->user_email ) : '';
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
								<th scope="row"><label id="prayer-pop-feedback-title-label" for="prayer-pop-feedback-title"><?php esc_html_e( 'Bug summary', 'prayerpop' ); ?></label></th>
								<td><input type="text" class="regular-text" id="prayer-pop-feedback-title" name="feedback_title" placeholder="<?php esc_attr_e( 'A short summary', 'prayerpop' ); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="prayer-pop-feedback-email"><?php esc_html_e( 'Your email', 'prayerpop' ); ?></label></th>
								<td>
									<input type="email" class="regular-text" id="prayer-pop-feedback-email" name="feedback_email" value="<?php echo esc_attr( $current_user_email ); ?>" autocomplete="email">
									<p class="description"><?php esc_html_e( 'Used only so we can reply to this message. If left empty, your WordPress account email will be included.', 'prayerpop' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label id="prayer-pop-feedback-description-label" for="prayer-pop-feedback-description"><?php esc_html_e( 'What happened? What did you expect?', 'prayerpop' ); ?></label></th>
								<td><textarea id="prayer-pop-feedback-description" name="feedback_description" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'Describe the issue or the feature you have in mind.', 'prayerpop' ); ?>" required></textarea></td>
							</tr>
							<tr id="prayer-pop-feedback-steps-row">
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
		?>

					<?php
					$this->render_admin_footer_row(
						'<button type="submit" class="button button-primary button-large">' . esc_html__( 'Send', 'prayerpop' ) . '</button>'
					);
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page with tabs.
	 */
	public function render_settings_page() {
		// Use the same task-oriented settings shell as Pro, populated only with Free fields.
		$active_tab = isset( $_GET['tab'] )
			? $this->sanitize_active_tab( wp_unslash( $_GET['tab'] ) )
			: $this->sanitize_active_tab( get_option( 'prayer_pop_active_tab', 'popup' ) );
		$show_welcome_modal = isset( $_GET['prayer_pop_welcome'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['prayer_pop_welcome'] ) );
		update_option( 'prayer_pop_active_tab', $active_tab );

		$tabs = array(
			'popup'              => array( 'icon' => 'welcome-widgets-menus', 'label' => __( 'Popup & Submissions', 'prayerpop' ) ),
			'notifications-email'=> array( 'icon' => 'email', 'label' => __( 'Notifications & Email', 'prayerpop' ) ),
			'design'             => array( 'icon' => 'admin-appearance', 'label' => __( 'Design', 'prayerpop' ) ),
			'language-text'      => array( 'icon' => 'translation', 'label' => __( 'Language & Text', 'prayerpop' ) ),
			'data'               => array( 'icon' => 'database', 'label' => __( 'Data', 'prayerpop' ) ),
			'documentation'      => array( 'icon' => 'book', 'label' => __( 'Documentation', 'prayerpop' ) ),
		);

		$tab_descriptions = array(
			'popup'               => __( 'Choose the visitor experience: private submissions only, or submissions with PrayerPop Chat.', 'prayerpop' ),
				'notifications-email' => __( 'Set who receives prayer request and testimony alerts, when they are sent, and how the email is written.', 'prayerpop' ),
			'design'              => __( 'Customize the PrayerPop color, typography, position, and bubble icon.', 'prayerpop' ),
			'language-text'       => __( 'Search and customize all visitor-facing wording included in PrayerPop Free.', 'prayerpop' ),
				'data'                => __( 'Choose how long PrayerPop Free keeps submissions and Chat conversations.', 'prayerpop' ),
			'documentation'       => __( 'Browse setup, usage, styling, wording, notification, and troubleshooting guidance.', 'prayerpop' ),
		);

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

			<div class="prayer-pop-settings-search" role="search">
				<label for="prayer-pop-settings-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Search all settings', 'prayerpop' ); ?></span></label>
				<input type="search" id="prayer-pop-settings-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search all settings…', 'prayerpop' ); ?>" autocomplete="off">
				<button type="button" class="button" id="prayer-pop-settings-search-clear" hidden><?php esc_html_e( 'Clear', 'prayerpop' ); ?></button>
			</div>
			<div id="prayer-pop-settings-search-results" class="prayer-pop-settings-search-results" aria-live="polite" hidden></div>

			<h2 class="nav-tab-wrapper">
				<?php
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

					// Render every tab once for instant switching and cross-tab search.
					foreach ($tabs as $tab_id => $tab) {
						$display = ($active_tab === $tab_id) ? 'block' : 'none';
						echo '<div id="' . esc_attr($tab_id) . '" class="tab-content" data-tab-label="' . esc_attr( $tab['label'] ) . '" style="display: ' . esc_attr($display) . ';">';
						$tab_description = isset( $tab_descriptions[ $tab_id ] ) ? $tab_descriptions[ $tab_id ] : '';
						$this->render_tab_intro( $tab['label'], $tab['icon'], $tab_description );
						$this->render_settings_tab_content( $tab_id );
						echo '</div>';
					}

				$footer_classes = 'prayer-pop-settings-save-row';
				if ( 'documentation' === $active_tab ) {
					$footer_classes .= ' is-hidden';
				}
				$this->render_admin_footer_row( get_submit_button( null, 'primary', 'submit', false ), $footer_classes );
				?>
				</form>
					</div>
					<?php $this->render_feature_rail(); ?>
				</div>

			<?php $this->render_welcome_modal( $show_welcome_modal ); ?>
		</div>
		<?php
		}

	/**
	 * Render one task-oriented Free settings tab.
	 *
	 * @param string $tab_id Tab identifier.
	 * @return void
	 */
	private function render_settings_tab_content( $tab_id ) {
		switch ( $tab_id ) {
			case 'popup':
				$this->render_field_card( __( 'Bubble', 'prayerpop' ), __( 'Choose whether the PrayerPop entry point is visible on the front end.', 'prayerpop' ), 'prayer-pop-settings-general', 'prayer_pop_general_section', array( 'show_prayer_pop_bubble' ) );
				$this->render_field_card( __( 'PrayerPop Chat', 'prayerpop' ), __( 'Enable conversations when your team wants to reply to visitors. Turn this off to offer only the enabled submission forms.', 'prayerpop' ), 'prayer-pop-settings-general', 'prayer_pop_general_section', array( 'enable_prayerpop_chat' ) );
				$this->render_field_card( __( 'Submission rules', 'prayerpop' ), __( 'Choose which submissions visitors can send and whether the welcome header appears. PrayerPop Free holds every new item for manual admin approval before it is actioned.', 'prayerpop' ), 'prayer-pop-settings-general', 'prayer_pop_general_section', array( 'show_prayer_request_button', 'show_testimony_button', 'popup_intro_enabled', 'allow_anonymous' ) );
				break;

			case 'notifications-email':
				$this->render_field_card( __( 'Submission alerts', 'prayerpop' ), __( 'Set recipients and delivery timing for new prayer request alerts.', 'prayerpop' ), 'prayer-pop-settings-notifications', 'prayer_pop_notification_section', array( 'enable_notifications', 'notification_email', 'notification_frequency', 'notification_time', 'notification_day' ) );
				?>
				<section class="prayer-pop-subsection-card prayer-pop-email-template-card">
					<h3 class="prayer-pop-subsection-title"><span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'Submission email template', 'prayerpop' ); ?></h3>
					<p class="prayer-pop-subsection-description"><?php esc_html_e( 'Set the subject and body, then send a test to the submission-alert recipient.', 'prayerpop' ); ?></p>
					<?php do_settings_sections( 'prayer-pop-settings-email-template' ); ?>
					<?php $this->email_template_settings->render_section_description(); ?>
				</section>
				<details class="prayer-pop-advanced-panel"><summary><?php esc_html_e( 'Advanced diagnostics', 'prayerpop' ); ?></summary><div class="prayer-pop-advanced-panel__content"><table class="form-table" role="presentation"><?php $this->render_settings_field_rows( 'prayer-pop-settings-notifications', 'prayer_pop_notification_debug_section', array( 'show_debug_info' ) ); ?></table></div></details>
				<?php
				break;

			case 'design':
				$this->render_field_card( __( 'Popup welcome image', 'prayerpop' ), __( 'Add the image used by the Free popup welcome panel.', 'prayerpop' ), 'prayer-pop-settings-general', 'prayer_pop_general_section', array( 'popup_intro_image_id' ) );
				$this->render_standard_settings_sections( 'prayer-pop-settings-style' );
				break;

			case 'language-text':
				$this->text_settings->render_tab_content();
				break;

			case 'data':
				$this->render_field_card( __( 'Data retention', 'prayerpop' ), __( 'Choose how long submitted prayer requests are retained.', 'prayerpop' ), 'prayer-pop-settings-general', 'prayer_pop_general_section', array( 'retention_period' ) );
				$this->render_field_card( __( 'Chat data retention', 'prayerpop' ), __( 'Choose how long inactive Chat conversations are retained.', 'prayerpop' ), 'prayer-pop-settings-general', 'prayer_pop_general_section', array( 'chat_conversation_retention' ) );
				break;

			case 'documentation':
				?><section class="prayer-pop-subsection-card prayer-pop-documentation-card"><?php $this->render_documentation_tab(); ?></section><?php
				break;
		}
	}

	/** Render a settings card containing selected registered fields. */
	private function render_field_card( $title, $description, $page, $section, $field_ids ) {
		?>
		<section class="prayer-pop-subsection-card">
			<h3 class="prayer-pop-subsection-title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( '' !== $description ) : ?><p class="prayer-pop-subsection-description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
			<table class="form-table" role="presentation"><?php $this->render_settings_field_rows( $page, $section, $field_ids ); ?></table>
		</section>
		<?php
	}

	/** Render selected fields from WordPress' registered settings-field registry. */
	private function render_settings_field_rows( $page, $section, $field_ids ) {
		global $wp_settings_fields;
		if ( empty( $wp_settings_fields[ $page ][ $section ] ) ) {
			return;
		}

		foreach ( $field_ids as $field_id ) {
			if ( empty( $wp_settings_fields[ $page ][ $section ][ $field_id ] ) ) {
				continue;
			}
			$field = $wp_settings_fields[ $page ][ $section ][ $field_id ];
			echo '<tr';
			if ( ! empty( $field['args']['class'] ) ) {
				echo ' class="' . esc_attr( $field['args']['class'] ) . '"';
			}
			echo ' data-setting-id="' . esc_attr( $field_id ) . '">';
			if ( ! empty( $field['args']['label_for'] ) ) {
				echo '<th scope="row"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . wp_kses_post( $field['title'] ) . '</label></th>';
			} else {
				echo '<th scope="row">' . wp_kses_post( $field['title'] ) . '</th>';
			}
			echo '<td>';
			call_user_func( $field['callback'], $field['args'] );
			echo '</td></tr>';
		}
	}

	/** Render all registered sections for a settings page as cards. */
	private function render_standard_settings_sections( $page ) {
		global $wp_settings_sections;
		if ( empty( $wp_settings_sections[ $page ] ) ) {
			return;
		}

		foreach ( $wp_settings_sections[ $page ] as $section ) {
			echo '<section class="prayer-pop-subsection-card" data-settings-section="' . esc_attr( $section['id'] ) . '">';
			if ( $section['title'] ) {
				echo '<h3 class="prayer-pop-subsection-title">' . wp_kses_post( $section['title'] ) . '</h3>';
			}
			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}
			echo '<table class="form-table" role="presentation">';
			do_settings_fields( $page, $section['id'] );
			echo '</table></section>';
		}
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
	 * Render the PrayerPop Pro upsell sidebar beside Free settings.
	 *
	 * This contains marketing links only; no Pro implementation is bundled.
	 *
	 * @return void
	 */
	private function render_feature_rail() {
		$features_url = 'https://prayerpop.eu/features';
		?>
		<aside class="prayer-pop-feature-rail" aria-label="<?php esc_attr_e( 'PrayerPop Pro feature highlights', 'prayerpop' ); ?>">
			<div class="prayer-pop-feature-card prayer-pop-feature-card-intro">
				<h3><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Reasons to go Pro', 'prayerpop' ); ?></h3>
				<p><?php esc_html_e( 'Add public-facing prayer content, deeper moderation, and more design control when your prayer workflow grows.', 'prayerpop' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( $features_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Pro Features', 'prayerpop' ); ?></a>
			</div>

			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-superhero" aria-hidden="true"></span><?php esc_html_e( 'AI-Assisted Moderation', 'prayerpop' ); ?></h3>
				<p><?php esc_html_e( 'Reduce manual review work with optional AI checks for public submissions and a clearer moderation flow.', 'prayerpop' ); ?></p>
			</div>

			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Pro Content Tools', 'prayerpop' ); ?></h3>
				<ul class="prayer-pop-feature-list">
					<li><strong><?php esc_html_e( 'Prayer Campaigns', 'prayerpop' ); ?></strong><span><?php esc_html_e( 'Create focused campaigns with public pages, progress, and clear calls to action.', 'prayerpop' ); ?></span></li>
					<li><strong><?php esc_html_e( 'Testimonies and FAQ', 'prayerpop' ); ?></strong><span><?php esc_html_e( 'Show answered prayers and helpful explanations beside the prayer experience.', 'prayerpop' ); ?></span></li>
					<li><strong><?php esc_html_e( 'Custom Link Buttons', 'prayerpop' ); ?></strong><span><?php esc_html_e( 'Guide visitors to calendars, groups, giving pages, or other next steps.', 'prayerpop' ); ?></span></li>
				</ul>
			</div>

			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><?php esc_html_e( 'Public Submissions Wall', 'prayerpop' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Public submissions wall', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Social media sharing', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Reactions such as “I Prayed” and “Celebrate”', 'prayerpop' ); ?></li>
				</ul>
			</div>

			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-layout" aria-hidden="true"></span><?php esc_html_e( 'Builder Modules', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'Divi 5 PrayerPop modules', 'prayerpop' ); ?></strong> <?php esc_html_e( 'let you place and style forms, submissions, campaigns, and campaign cards directly inside the builder.', 'prayerpop' ); ?></p>
			</div>

			<div class="prayer-pop-feature-card">
				<h3><span class="dashicons dashicons-art" aria-hidden="true"></span><?php esc_html_e( 'Extra Styling Options', 'prayerpop' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'More layout controls for walls and cards', 'prayerpop' ); ?></li>
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
				<p><?php esc_html_e( 'PrayerPop is bubble-first. Follow these steps to collect and review prayer requests and testimonies.', 'prayerpop' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Open General settings and confirm Show PrayerPop Bubble is enabled.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Choose whether to enable PrayerPop Chat. Leave it off when you want visitors to use prayer-request submissions only.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Choose whether visitors may leave the name field empty.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Set the retention period if old approved or answered requests should be archived and later cleaned up.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'In Notifications, set your recipient email and schedule, then send a test email.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'In Style, adjust the bubble color, icon, position, animation, and font.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'In Text Customization, edit the visible prayer request and testimony labels and messages if needed.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Visit the frontend of your site, click the bubble, and submit a test prayer request or testimony.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Open PrayerPop -> Submissions and process the test submission.', 'prayerpop' ); ?></li>
				</ol>
				<div class="prayer-pop-doc-note is-tip"><strong><?php esc_html_e( 'Note:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'With PrayerPop Chat turned off, the global bubble offers the enabled prayer request and testimony forms. When only one form is enabled, it opens straight away. With Chat turned on, visitors can also start a conversation.', 'prayerpop' ); ?></div>
			</section>

			<hr />

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-bubble">
				<h2><?php esc_html_e( 'Using the PrayerPop Bubble', 'prayerpop' ); ?></h2>
				<p><?php esc_html_e( 'The bubble is the frontend entry point. When enabled, it offers the prayer request and testimony forms you have enabled. If you enable PrayerPop Chat, it also gives visitors a way to start a conversation.', 'prayerpop' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'With Chat turned off, visitors choose an enabled prayer request or testimony form. If only one is enabled, it opens directly.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'With Chat turned on, visitors can send messages to your team as well as submit enabled prayer requests or testimonies.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Submissions are saved in WordPress admin with their prayer request or testimony type.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Every new request starts in Pending Action so an admin can review it.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'The form includes honeypot, minimum-submit-time, rate-limit, and cooldown protection.', 'prayerpop' ); ?></li>
				</ul>
				<div class="prayer-pop-doc-note"><strong><?php esc_html_e( 'Bubble setup:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Use the global bubble setting to make the form available on the frontend.', 'prayerpop' ); ?></div>
			</section>

			<hr />

			<section class="prayer-pop-doc-section" id="prayer-pop-doc-managing-submissions">
				<h2><?php esc_html_e( 'Managing Submissions', 'prayerpop' ); ?></h2>
				<p><?php esc_html_e( 'This is where your church team will spend most time. New submissions appear in WordPress -> Submissions.', 'prayerpop' ); ?></p>
				<p><?php esc_html_e( 'Usually WordPress administrators handle this work in PrayerPop Free.', 'prayerpop' ); ?></p>

				<h3><?php esc_html_e( 'What happens when someone submits', 'prayerpop' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'The item is saved as a prayer request or testimony submission.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'The submission starts as Pending Action.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'An admin reviews the submission and chooses the next action.', 'prayerpop' ); ?></li>
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
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Controls the prayer request, testimony, and Chat workflow.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want to show or hide the bubble, choose submissions only or Chat, or allow anonymous names.', 'prayerpop' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Show PrayerPop Bubble:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Shows the floating frontend bubble. It opens the enabled prayer request and testimony forms and, when enabled, PrayerPop Chat.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Enable Prayer Requests / Enable Testimonies:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Choose which private submissions visitors can send. If both are disabled, the frontend bubble is hidden.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Enable PrayerPop Chat:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Lets visitors start Chat conversations. Turn it off to offer only the enabled submission forms. Existing Chat records remain stored until you delete them or the Chat retention rule removes them.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Anonymous submissions:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Allows visitors to leave the name field empty.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Admin review:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Every request starts in Pending Action for review.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Retention period:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Older approved/answered submissions move to archive first. Later, archived submissions can be auto-deleted based on this time window.', 'prayerpop' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Notifications', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Sends email alerts when prayer requests or testimonies arrive.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want your team notified automatically instead of checking manually.', 'prayerpop' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Set recipient and frequency (immediate, daily, weekly).', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Set the subject and body in Email Template, then verify delivery with a real notification.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'PrayerPop queues immediate alerts and retries failed work. It cannot fix an unavailable mail service, so keep your WordPress mail service working and send a test after changes.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Daily and weekly alerts run through WordPress cron. On a low-traffic site, ask your host or developer to run WordPress cron from the server so scheduled alerts run on time.', 'prayerpop' ); ?></li>
				</ul>

				<h3><?php esc_html_e( 'Style Tab', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Controls how PrayerPop looks on your website.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want to adjust colors, typography, bubble design, icon, position, animation, and spacing.', 'prayerpop' ); ?></p>

				<h3><?php esc_html_e( 'Text Customization Tab', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Lets you rewrite visible prayer request and testimony form text, labels, and messages.', 'prayerpop' ); ?></p>
				<p><strong><?php esc_html_e( 'Use it when:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'You want your own tone, wording, or single-language translation.', 'prayerpop' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Edit text directly in fields.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Export text fields to JSON as backup.', 'prayerpop' ); ?></li>
					<li><?php esc_html_e( 'Import JSON back after edits or translation updates.', 'prayerpop' ); ?></li>
				</ol>

				<h3><?php esc_html_e( 'Data Tab', 'prayerpop' ); ?></h3>
				<p><strong><?php esc_html_e( 'What it does:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Controls how long submission records and inactive Chat conversations are retained.', 'prayerpop' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Chat conversation retention:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Permanently removes inactive Chat conversations after the chosen period. The default is one year. Choose Keep indefinitely only when your data-retention policy requires it.', 'prayerpop' ); ?></li>
				</ul>

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
					<li><strong><?php esc_html_e( 'Email delivery issue:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Check Notifications and Email Template settings, then verify WordPress mail delivery with your hosting provider.', 'prayerpop' ); ?></li>
					<li><strong><?php esc_html_e( 'Chat is not available to visitors:', 'prayerpop' ); ?></strong> <?php esc_html_e( 'Open Settings, turn on Enable PrayerPop Chat, save the setting, clear page or CDN caches, and reload the website.', 'prayerpop' ); ?></li>
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
							<li><?php esc_html_e( 'When PrayerPop Chat is enabled, it stores visitor names, optional email addresses, messages, conversation status, and message times in WordPress database tables. It sends new-message and reply notices through your WordPress email service.', 'prayerpop' ); ?></li>
							<li><?php esc_html_e( 'Use Tools -> Export Personal Data or Tools -> Erase Personal Data for a visitor who gave an email address. PrayerPop can export or erase matching Chat conversations.', 'prayerpop' ); ?></li>
							<li><?php esc_html_e( 'No external analytics or ad trackers are added by PrayerPop itself.', 'prayerpop' ); ?></li>
						</ul>

					<h3><?php esc_html_e( 'Cookies & Browser Storage Used by PrayerPop', 'prayerpop' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Local anti-spam and cooldown tracking is used to limit repeated submissions.', 'prayerpop' ); ?></li>
						<li><code>prayerpop_chat_token</code> - <?php esc_html_e( 'A private HttpOnly, SameSite browser credential lets a visitor return to a Chat conversation. WordPress stores only a hash of this credential.', 'prayerpop' ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'Retention & Deletion (Practical)', 'prayerpop' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Approved/Answered items older than the retention window are archived first.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Archived items are deleted only after they have also stayed archived for the full retention window.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Retention set to Forever (0) keeps submissions stored during automatic cleanup.', 'prayerpop' ); ?></li>
						<li><?php esc_html_e( 'Chat retention is separate from submission retention. PrayerPop permanently removes each inactive Chat conversation after the Chat retention period you choose.', 'prayerpop' ); ?></li>
					</ul>

					<h3><?php esc_html_e( 'Hosting Behind a Reverse Proxy', 'prayerpop' ); ?></h3>
					<p><?php esc_html_e( 'PrayerPop uses the direct connection address for rate limiting by default. If your site runs behind a reverse proxy or CDN, ask your developer to add only your trusted proxy ranges through the prayer_pop_trusted_proxy_ranges filter before PrayerPop uses forwarded visitor IP headers.', 'prayerpop' ); ?></p>

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

	/**
	 * Render the shared admin footer row.
	 *
	 * @param string $actions_html Left-side actions markup.
	 * @param string $classes      Optional extra classes.
	 * @return void
	 */
	private function render_admin_footer_row( $actions_html = '', $classes = '' ) {
		$row_classes = trim( 'prayer-pop-save-row ' . $classes );
		$allowed_actions_html = array(
			'button' => array(
				'class' => true,
				'id'    => true,
				'name'  => true,
				'type'  => true,
				'value' => true,
			),
			'input'  => array(
				'class' => true,
				'id'    => true,
				'name'  => true,
				'type'  => true,
				'value' => true,
			),
			'span'   => array(
				'class'       => true,
				'aria-hidden' => true,
			),
		);
		?>
		<div class="<?php echo esc_attr( $row_classes ); ?>">
			<div class="prayer-pop-save-row__actions">
				<?php echo wp_kses( $actions_html, $allowed_actions_html ); ?>
			</div>
			<?php $this->render_brand_footer_logo(); ?>
		</div>
		<?php
	}

	/**
	 * Render the shared PrayerPop brand footer logo.
	 *
	 * @return void
	 */
	private function render_brand_footer_logo() {
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
		$email       = isset( $_POST['feedback_email'] ) ? sanitize_email( wp_unslash( $_POST['feedback_email'] ) ) : '';
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

		if ( ! is_email( $email ) ) {
			$current_user = wp_get_current_user();
			$email = isset( $current_user->user_email ) ? sanitize_email( $current_user->user_email ) : '';
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
		$message .= 'Contact email: ' . ( is_email( $email ) ? $email : 'unavailable' ) . "\n\n";
		$message .= "Description:\n{$description}\n\n";
		if ( 'bug' === strtolower( $type ) && '' !== trim( $steps ) ) {
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
		if ( is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $email;
		}
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
