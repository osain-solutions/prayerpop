<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Prayer_Pop' ) ) :

/**
 * Main Prayer_Pop Class.
 *
 * @package     PRAYERPOP
 * @since       1.0.0
 */
final class Prayer_Pop {

	/**
	 * The single instance of Prayer_Pop.
	 *
	 * @var     Prayer_Pop
	 * @access  private
	 */
	private static $instance;

	/**
	 * Plugin settings.
	 *
	 * @var     Prayer_Pop_Settings
	 * @access  public
	 */
	public $settings;

	/**
	 * Main Prayer_Pop Instance.
	 *
	 * Ensures only one instance of Prayer_Pop is loaded or can be loaded.
	 *
	 * @static
	 * @return Prayer_Pop Main instance.
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Prayer_Pop ) ) {
			self::$instance = new Prayer_Pop();
			self::$instance->includes();
			self::$instance->init_hooks();
		}
		return self::$instance;
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/class-prayer-pop-defaults.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/class-prayer-pop-settings.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/class-prayer-pop-run.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'core/includes/classes/class-prayer-pop-ajax.php';
		require_once PRAYERPOP_PLUGIN_DIR . 'src/Admin/ListTable.php';
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks() {
		// plugins_loaded has already fired when this class is instantiated.
		// Load textdomain immediately for the current request.
		$this->load_textdomain();

		// Initialize plugin settings.
		$this->settings = new Prayer_Pop_Settings();

		// Run the plugin.
		new Prayer_Pop_Run();

		// Initialize admin list table only in admin context.
		add_action( 'admin_init', array( $this, 'init_admin_components' ) );

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'template_redirect', array( $this, 'render_frontend_preview_page' ) );

		// Register custom post type is handled by Prayer_Pop_Run class.

                // Admin columns are now handled by PrayerPop_Admin_List_Table.
                // add_filter( 'manage_edit-prayer_request_columns', array( $this, 'custom_columns' ) );
                // add_action( 'manage_prayer_request_posts_custom_column', array( $this, 'custom_columns_content' ), 10, 2 );
	}

	/**
	 * Initialize admin-only components.
	 */
	public function init_admin_components() {
		if ( is_admin() ) {
			new \PrayerPop\Admin\ListTable();
		}
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_textdomain() {
		// WordPress.org loads translations automatically for hosted plugins.
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		// Register all handles first, then enqueue conditionally.
		wp_register_script( 'prayer-pop-script', PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop.js', array( 'jquery' ), PRAYERPOP_VERSION, true );
		wp_register_style( 'prayer-pop-style', PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop.css', array(), PRAYERPOP_VERSION );
		wp_register_style( 'prayer-pop-form-style', PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-form.css', array(), PRAYERPOP_VERSION );
		wp_register_script( 'prayer-pop-form-script', PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-form.js', array( 'jquery' ), PRAYERPOP_VERSION, true );

		// Retrieve styles once and conditionally load icon dependencies.
		$styles    = Prayer_Pop_Defaults::get_styles();
		$icon_type = isset( $styles['bubble_icon_type'] ) ? sanitize_key( $styles['bubble_icon_type'] ) : 'none';
		if ( 'dashicon' === $icon_type ) {
			wp_enqueue_style( 'dashicons' );
		}

		wp_enqueue_script( 'prayer-pop-script' );
		wp_enqueue_style( 'prayer-pop-style' );

		// Always enqueue form assets because this build is bubble-first.
		wp_enqueue_style( 'prayer-pop-form-style' );
		wp_enqueue_script( 'prayer-pop-form-script' );

		// Retrieve the selected animation from settings using cache.
		$selected_animation = isset( $styles['bubble_animation'] ) ? $styles['bubble_animation'] : 'fade-in';
		if ( ! in_array( $selected_animation, array( 'none', 'fade-in', 'slide-up', 'bounce-in' ), true ) ) {
			$selected_animation = 'fade-in';
		}

		// Get texts from settings using cache.
		$texts = Prayer_Pop_Defaults::get_texts();
		$new_request_button_text = Prayer_Pop_Defaults::get_text( 'text_new_request_button', esc_html__( 'Send One More', 'prayerpop' ) );
		$success_message = Prayer_Pop_Defaults::get_text( 'text_success_message', esc_html__( 'Thank you for your submission!', 'prayerpop' ) );
		$list_view_text = Prayer_Pop_Defaults::get_text( 'text_list_view', esc_html__( 'List View', 'prayerpop' ) );
		$grid_view_text = Prayer_Pop_Defaults::get_text( 'text_grid_view', esc_html__( 'Grid View', 'prayerpop' ) );
		$anonymous_text = Prayer_Pop_Defaults::get_text( 'text_anonymous', esc_html__( 'Anonymous', 'prayerpop' ) );

			// Get settings using cache.
			$settings = Prayer_Pop_Defaults::get_settings();
			$allow_anonymous = isset( $settings['allow_anonymous'] ) ? (bool) $settings['allow_anonymous'] : true;
			$name_placeholder = $allow_anonymous ? $texts['text_name_placeholder'] : $texts['text_name_placeholder_required'];

			wp_localize_script( 'prayer-pop-script', 'prayerPopAjax', array(
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'nonce'                   => wp_create_nonce( 'prayer_pop_nonce' ),
				'selected_animation'      => esc_js( $selected_animation ),
				'error_message'           => esc_html( $texts['text_error_message'] ),
				'new_request_button'      => esc_html( $new_request_button_text ),
				'success_message'         => esc_html( $success_message ),
				'anonymous_name'          => esc_html( $anonymous_text ),
				'headers'                 => array(
					'prayer_request' => array(
						'header' => $texts['text_prayer_request_header'],
						'description' => $texts['text_prayer_request_description'],
					),
				),
				'config'                  => array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'prayer_pop_nonce' ),
					'enableLastSubmissionTime' => false,
					'defaultType' => 'prayer_request',
					'timezoneOffset' => intval( get_option( 'gmt_offset' ) * 3600 ),
					'timeUnits' => array(
						'second_singular' => $texts['text_time_unit_second_singular'],
						'second_plural'   => $texts['text_time_unit_second_plural'],
						'minute_singular' => $texts['text_time_unit_minute_singular'],
						'minute_plural'   => $texts['text_time_unit_minute_plural'],
						'hour_singular'   => $texts['text_time_unit_hour_singular'],
						'hour_plural'     => $texts['text_time_unit_hour_plural'],
						'day_singular'    => $texts['text_time_unit_day_singular'],
						'day_plural'      => $texts['text_time_unit_day_plural'],
					),
					'messages' => array(
						'error'      => $texts['text_error_message'],
						'success'    => $texts['text_success_message'],
						'newRequest' => $texts['text_new_request_button'],
						'submitting' => $texts['text_submitting_button'],
					),
				),
				'preview'                 => array(
					'backButton'            => $texts['text_back_button'],
					'prayerLabel'           => $texts['text_prayer_request_label'],
					'messagePlaceholder'    => $texts['text_message_placeholder'],
					'namePlaceholder'       => $name_placeholder,
					'submitButton'          => $texts['text_submit_button'],
					'bubbleLabel'           => $texts['text_bubble_label'],
				),
			) );

			// Localize form config.
				wp_localize_script( 'prayer-pop-form-script', 'prayerPopFormConfig', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'prayer_pop_nonce' ),
			'enableLastSubmissionTime' => false,
			'timezoneOffset' => intval( get_option( 'gmt_offset' ) * 3600 ),
			'anonymousName' => esc_html( $anonymous_text ),
			'timeUnits' => array(
				'second_singular' => $texts['text_time_unit_second_singular'],
				'second_plural'   => $texts['text_time_unit_second_plural'],
				'minute_singular' => $texts['text_time_unit_minute_singular'],
				'minute_plural'   => $texts['text_time_unit_minute_plural'],
				'hour_singular'   => $texts['text_time_unit_hour_singular'],
				'hour_plural'     => $texts['text_time_unit_hour_plural'],
				'day_singular'    => $texts['text_time_unit_day_singular'],
				'day_plural'      => $texts['text_time_unit_day_plural'],
			),
			'messages' => array(
				'error'      => $texts['text_error_message'],
				'success'    => $texts['text_success_message'],
				'newRequest' => $texts['text_new_request_button'],
				'submitting' => $texts['text_submitting_button'],
			),
			'lastTimes' => array(),
			) );
		}

		/**
		 * Build a canonical last-submission payload for the bubble form.
		 *
		 * @param array $texts Text customization values.
		 * @return array<string,array<string,int|string>>
		 */
	private function get_last_submission_times_payload( $texts ) {
		$last_prayer_time_message = isset( $texts['text_last_prayer_time_message'] )
			? (string) $texts['text_last_prayer_time_message']
			: __( 'Last prayer request was submitted {time_ago} ago.', 'prayerpop' );

		return array(
			'prayer_request' => array(
				'timestamp' => $this->get_last_submission_timestamp_for_type( 'prayer_request' ),
				'message'   => $last_prayer_time_message,
				),
			);
		}

		/**
		 * Return normalized "last public submission" timestamp for a specific type.
		 * Falls back to querying the latest publicly visible post when option value is invalid.
		 *
		 * @param string $type Submission type.
		 * @return int
		 */
	private function get_last_submission_timestamp_for_type( $type ) {
		$option_key = 'prayer_pop_last_prayer_time';
		$query = new WP_Query(
				array(
					'post_type'              => 'prayer_request',
					'post_status'            => array( 'approved', 'answered' ),
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						'relation' => 'AND',
						array(
							'key'   => 'prayer_pop_public',
							'value' => '1',
						),
						array(
							'key'   => 'prayer_pop_type',
							'value' => $type,
						),
					),
				)
			);

			if ( ! empty( $query->posts ) ) {
				$submitted_timestamp = $this->normalize_last_submission_timestamp( get_post_time( 'U', true, (int) $query->posts[0] ) );
				if ( $submitted_timestamp > 0 ) {
					update_option( $option_key, $submitted_timestamp );
					return $submitted_timestamp;
				}
			}

			// Keep legacy option only as a final fallback when no canonical public post exists.
			return $this->normalize_last_submission_timestamp( get_option( $option_key, 0 ) );
		}

		/**
		 * Normalize stored last-submission timestamp values.
		 *
		 * @param mixed $value Raw timestamp value.
		 * @return int
		 */
		private function normalize_last_submission_timestamp( $value ) {
			if ( is_numeric( $value ) ) {
				$timestamp = (int) floor( (float) $value );
			} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
				$timestamp = strtotime( $value );
				$timestamp = false !== $timestamp ? (int) $timestamp : 0;
			} else {
				$timestamp = 0;
			}

			// Convert millisecond timestamps to seconds.
			if ( $timestamp > 9999999999 ) {
				$timestamp = (int) floor( $timestamp / 1000 );
			}

			$now = time();
			// Reject clearly invalid/ancient values (pre-2000) and far-future values.
			if ( $timestamp < 946684800 || $timestamp > ( $now + DAY_IN_SECONDS ) ) {
				return 0;
			}

			return $timestamp;
		}

		/**
		 * Render isolated frontend preview page for admin settings iframe.
		 *
		 * @return void
		 */
		public function render_frontend_preview_page() {
			$preview = isset( $_GET['prayer_pop_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['prayer_pop_preview'] ) ) : '';
			if ( '1' !== $preview ) {
				return;
			}

			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				status_header( 403 );
				wp_die( esc_html( Prayer_Pop_Defaults::get_text( 'text_preview_permission_error', esc_html__( 'You do not have permission to view this preview.', 'prayerpop' ) ) ) );
			}

			$nonce = isset( $_GET['_pp_preview_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_pp_preview_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'prayer_pop_frontend_preview' ) ) {
				status_header( 403 );
				wp_die( esc_html( Prayer_Pop_Defaults::get_text( 'text_preview_invalid_token', esc_html__( 'Invalid preview token.', 'prayerpop' ) ) ) );
			}

			if ( ! defined( 'PRAYERPOP_FORCE_BUBBLE' ) ) {
				define( 'PRAYERPOP_FORCE_BUBBLE', true );
			}

			nocache_headers();
			?><!doctype html>
			<html class="prayer-pop-preview-document" <?php language_attributes(); ?>>
			<head>
				<meta charset="<?php bloginfo( 'charset' ); ?>">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<?php wp_head(); ?>
			</head>
			<body class="prayer-pop-preview-page">
				<?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>
				<?php wp_footer(); ?>
			</body>
			</html><?php
			exit;
		}

	// Post type registration removed - handled by Prayer_Pop_Run class

	/**
	 * Customize admin columns.
	 *
	 * @deprecated 1.5.0 Use PrayerPop_Admin_List_Table instead.
	 * @param array $columns Existing columns.
	 * @return array Columns unchanged.
	 */
        public function custom_columns( $columns ) {
                // Deprecated: columns handled by PrayerPop_Admin_List_Table.
                // Kept for backward compatibility with potential extensions.
                return $columns;
        }

	/**
	 * Populate custom admin columns.
	 *
	 * @deprecated 1.5.0 Use PrayerPop_Admin_List_Table instead.
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
        public function custom_columns_content( $column, $post_id ) {
                // Deprecated: output handled by PrayerPop_Admin_List_Table.
                // Kept for backward compatibility with potential extensions.
        }

}

endif;
