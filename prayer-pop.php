<?php
/**
 * Plugin Name: PrayerPop
 * Plugin URI: https://prayerpop.eu/
 * Update URI: https://wordpress.org/plugins/prayerpop/
 * Description: Prayer request workflow and simple visitor chat with frontend tools, WordPress inboxes, and email notifications.
 * Version: 1.6.0
 * Author: Ösain OÜ
 * Author URI: https://osain.ee/
 * Text Domain: prayerpop
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
if ( ! defined( 'PRAYERPOP_VERSION' ) ) {
	define( 'PRAYERPOP_VERSION', '1.6.0' );
}
if ( ! defined( 'PRAYERPOP_PLUGIN_DIR' ) ) {
	define( 'PRAYERPOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'PRAYERPOP_PLUGIN_URL' ) ) {
	define( 'PRAYERPOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'PRAYERPOP_PLUGIN_FILE' ) ) {
	define( 'PRAYERPOP_PLUGIN_FILE', __FILE__ );
}

/** Whether the current request is rendering a PrayerPop-owned admin screen. */
function prayer_pop_is_admin_screen() {
	$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	return 0 === strpos( $page, 'prayer-pop' ) || 'prayerpop-print' === $page || 'prayer_request' === $post_type;
}

/** Resolve the source file for a registered notice callback when possible. */
function prayer_pop_notice_callback_file( $callback ) {
	try {
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$reflection = new ReflectionMethod( $callback[0], $callback[1] );
		} elseif ( $callback instanceof Closure || is_string( $callback ) ) {
			$reflection = new ReflectionFunction( $callback );
		} elseif ( is_object( $callback ) && is_callable( $callback ) ) {
			$reflection = new ReflectionMethod( $callback, '__invoke' );
		} else {
			return '';
		}
		return (string) $reflection->getFileName();
	} catch ( ReflectionException $exception ) {
		return '';
	}
}

/** Hide notices supplied by unrelated plugins on PrayerPop screens only. */
function prayer_pop_remove_other_plugin_notices() {
	if ( ! prayer_pop_is_admin_screen() ) {
		return;
	}

	global $wp_filter;
	$plugins_root = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
	$our_root     = trailingslashit( wp_normalize_path( PRAYERPOP_PLUGIN_DIR ) );
	$notice_hooks = array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' );

	foreach ( $notice_hooks as $hook_name ) {
		if ( empty( $wp_filter[ $hook_name ] ) || empty( $wp_filter[ $hook_name ]->callbacks ) ) {
			continue;
		}
		foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_data ) {
				$file = wp_normalize_path( prayer_pop_notice_callback_file( $callback_data['function'] ) );
				if ( $file && 0 === strpos( $file, $plugins_root ) && 0 !== strpos( $file, $our_root ) ) {
					remove_filter( $hook_name, $callback_data['function'], $priority );
				}
			}
		}
	}
}
add_action( 'admin_init', 'prayer_pop_remove_other_plugin_notices', PHP_INT_MAX );
add_action( 'in_admin_header', 'prayer_pop_remove_other_plugin_notices', 0 );

// Include the defaults class first (needed for activation)
require_once __DIR__ . '/core/includes/classes/class-prayer-pop-defaults.php';
require_once __DIR__ . '/core/includes/classes/class-prayer-pop-notification-scheduler.php';
require_once __DIR__ . '/core/includes/classes/class-prayer-pop-chat.php';
require_once __DIR__ . '/core/includes/classes/class-prayer-pop-upgrades.php';

Prayer_Pop_Upgrades::init();

// Include the main plugin class
require_once __DIR__ . '/core/class-prayer-pop.php';

/**
 * Returns the main instance of Prayer_Pop.
 */
function PRAYERPOP() {
	return Prayer_Pop::instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'PRAYERPOP');
add_action( 'plugins_loaded', array( 'Prayer_Pop_Chat', 'init' ) );

// Activation Hook
register_activation_hook(__FILE__, 'prayer_pop_activate');

// Deactivation Hook
register_deactivation_hook(__FILE__, 'prayer_pop_deactivate');

// Redirect old post type slugs to the new one in the admin
add_action( 'admin_init', 'prayer_pop_redirect_old_slug' );
add_action( 'admin_init', 'prayer_pop_maybe_redirect_to_welcome' );

// Add rewrite rule for legacy URLs
add_action( 'init', 'prayer_pop_add_rewrite_rule' );

// Add plugin action links (Settings link on plugins page)
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'prayer_pop_action_links' );
add_filter( 'plugin_row_meta', 'prayer_pop_plugin_row_meta', 10, 4 );
add_filter( 'plugins_api', 'prayer_pop_plugins_api', 20, 3 );
add_action( 'load-plugins.php', 'prayer_pop_block_pro_activation_attempt', 1 );
add_action( 'admin_notices', 'prayer_pop_activation_blocked_notice' );

/**
 * Plugin activation function
 */
function prayer_pop_activate() {
	
	// Set default options using centralized defaults
	$default_texts = Prayer_Pop_Defaults::get_default_texts_raw();
	
	add_option( 'prayer_pop_texts', $default_texts );
	add_option(
		'prayer_pop_general_settings',
		array(
			'show_prayer_pop_bubble'    => 1,
			'allow_anonymous'           => 1,
			'require_admin_approval'    => 1,
			'retention_period'          => 0,
		)
	);
	Prayer_Pop_Chat::install();
	add_option(
		'prayer_pop_notification_settings',
		array(
			'enable_notifications' => 0,
			'show_debug_info'      => 0,
			'notification_email'   => get_option( 'admin_email' ),
			'notification_frequency' => 'immediately',
			'notification_time'    => '08:00',
			'notification_day'     => 'Monday',
		)
	);
	add_option(
		'prayer_pop_styles',
		array(
			'bubble_icon_type'   => 'dashicon',
			'bubble_dashicon'    => 'prayerpop',
			'bubble_design_mode' => 'fixed_circle',
			'bubble_layout'      => 'icon',
			'bubble_position'    => 'right',
			'bubble_offset_x'    => '0px',
			'bubble_offset_y'    => '0px',
			'bubble_icon_color'  => '#ffffff',
			'bubble_icon_size'   => 170,
		)
	);

	// Restore saved digest schedules after activation or reactivation.
	Prayer_Pop_Upgrades::activate();

	// Schedule daily cleanup of old submissions (only if not already scheduled)
	if ( ! wp_next_scheduled( 'prayer_pop_cleanup_event' ) ) {
		wp_schedule_event( time(), 'daily', 'prayer_pop_cleanup_event' );
	}

	// Migrate old prayer request post type slug
	prayer_pop_migrate_post_type();

	// Register plugin routes before flushing so they are persisted on activation.
	prayer_pop_register_rewrite_dependencies();
	flush_rewrite_rules();

	// Show one-time welcome modal on first settings visit after activation.
	update_option( 'prayer_pop_show_welcome_modal', 1, false );
}

/**
 * Plugin deactivation function
 */
function prayer_pop_deactivate() {
	// Clear any scheduled hooks
	wp_clear_scheduled_hook('prayer_pop_send_daily_notifications');
	wp_clear_scheduled_hook('prayer_pop_send_weekly_notifications');
	wp_clear_scheduled_hook('prayer_pop_cleanup_event');
	
	// Flush rewrite rules
	flush_rewrite_rules();
}

/**
 * Register custom post types and rewrite rules before an activation flush.
 *
 * WordPress does not run the normal init lifecycle before activation hooks, so
 * rewrite rules must be registered explicitly before flush_rewrite_rules().
 */
function prayer_pop_register_rewrite_dependencies() {
	require_once __DIR__ . '/core/includes/classes/class-prayer-pop-run.php';

	static $runner = null;

	if ( null === $runner ) {
		$runner = new Prayer_Pop_Run();
	}

	$runner->register_prayer_request_cpt();
	prayer_pop_add_rewrite_rule();
}

/**
 * Block activating PrayerPop Pro while PrayerPop is active and redirect back to plugins page.
 *
 * @return void
 */
function prayer_pop_block_pro_activation_attempt() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$other_plugin = 'prayerpop-pro/prayer-pop.php';
	$should_block = false;

	if ( isset( $_GET['action'], $_GET['plugin'] ) ) {
		$action = sanitize_key( (string) wp_unslash( $_GET['action'] ) );
		$plugin = sanitize_text_field( (string) wp_unslash( $_GET['plugin'] ) );
		if ( 'activate' === $action && $other_plugin === $plugin ) {
			check_admin_referer( 'activate-plugin_' . $other_plugin );
			$should_block = true;
		}
	}

	if ( ! $should_block && isset( $_REQUEST['action'] ) ) {
			$bulk_action = sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) );
			if ( 'activate-selected' === $bulk_action ) {
				check_admin_referer( 'bulk-plugins' );
				$checked = isset( $_REQUEST['checked'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_REQUEST['checked'] ) ) : array();
				$should_block = in_array( $other_plugin, $checked, true );
			}
	}

	if ( ! $should_block ) {
		return;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'prayerpop_notice' => 'standard_blocks_pro_activation',
			),
			admin_url( 'plugins.php' )
		)
	);
	exit;
}

/**
 * Show plugins-page warning after a blocked PrayerPop Pro activation attempt.
 *
 * @return void
 */
function prayer_pop_activation_blocked_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$notice = isset( $_GET['prayerpop_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['prayerpop_notice'] ) ) : '';
	if ( 'standard_blocks_pro_activation' !== $notice ) {
		return;
	}

	echo '<div class="notice notice-warning is-dismissible"><p>' .
		esc_html__( 'PrayerPop Pro cannot be activated while PrayerPop is active. Please deactivate PrayerPop first.', 'prayerpop' ) .
		'</p></div>';
}

/**
 * Migrate legacy post type slug to the new one.
 */
function prayer_pop_migrate_post_type() {
        if ( get_option( 'prayer_pop_migrated_post_type', false ) ) {
                return;
        }

        global $wpdb;
        // Intentional one-time migration query on core posts table.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
                $wpdb->posts,
                array( 'post_type' => 'prayer_request' ),
                array( 'post_type' => 'pray_request' ),
                array( '%s' ),
                array( '%s' )
        );
        update_option( 'prayer_pop_migrated_post_type', 1 );
}

/**
 * Redirect admin requests using the old post type slug.
 */
function prayer_pop_redirect_old_slug() {
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
        if ( 'pray_request' === $post_type ) {
                wp_safe_redirect( admin_url( 'edit.php?post_type=prayer_request' ) );
                exit;
        }
}

/**
 * Redirect to settings with welcome modal after activation.
 *
 * @return void
 */
function prayer_pop_maybe_redirect_to_welcome() {
	if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! get_option( 'prayer_pop_show_welcome_modal', false ) ) {
		return;
	}

	// Skip auto-redirect on bulk activation.
	if ( isset( $_GET['activate-multi'] ) ) {
		return;
	}

	$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'prayer-pop-settings' === $current_page && isset( $_GET['prayer_pop_welcome'] ) ) {
		delete_option( 'prayer_pop_show_welcome_modal' );
		return;
	}

	delete_option( 'prayer_pop_show_welcome_modal' );
	$redirect_url = add_query_arg(
		array(
			'page'                => 'prayer-pop-settings',
			'tab'                 => 'general',
			'prayer_pop_welcome'  => '1',
		),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Add rewrite rule for legacy URLs.
 */
function prayer_pop_add_rewrite_rule() {
        add_rewrite_rule( '^pray_request/?$', 'index.php?post_type=prayer_request', 'top' );
}

/**
 * Add plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function prayer_pop_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=prayer-pop-settings' ) ) . '">' . esc_html__( 'Settings', 'prayerpop' ) . '</a>';
	$docs_link     = '<a href="' . esc_url( admin_url( 'admin.php?page=prayer-pop-settings&tab=documentation' ) ) . '">' . esc_html__( 'Docs', 'prayerpop' ) . '</a>';
	array_unshift( $links, $settings_link );
	array_unshift( $links, $docs_link );
	return $links;
}

/**
 * Add "View details" link in plugin row meta (opens WP plugin modal).
 *
 * @param string[] $links Existing plugin meta links.
 * @param string   $file  Plugin file basename.
 * @return string[]
 */
function prayer_pop_plugin_row_meta( $links, $file, $plugin_data = array(), $status = '' ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}

	$slug = dirname( plugin_basename( __FILE__ ) );
	if ( '.' === $slug || '' === $slug ) {
		$slug = 'prayerpop';
	}

	$details_url = add_query_arg(
		array(
			'tab'       => 'plugin-information',
			'plugin'    => $slug,
			'TB_iframe' => 'true',
			'width'     => '600',
			'height'    => '550',
		),
		admin_url( 'plugin-install.php' )
	);

	$plugin_name = ! empty( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : 'PrayerPop';
	$links[]     = sprintf(
		'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s" data-title="%3$s">%4$s</a>',
		esc_url( $details_url ),
		esc_attr(
			sprintf(
				/* translators: %s: Plugin name. */
					__( 'More information about %s', 'prayerpop' ),
				$plugin_name
			)
		),
		esc_attr( $plugin_name ),
			esc_html__( 'View details', 'prayerpop' )
	);
	return $links;
}

/**
 * Provide plugin info modal content for PrayerPop.
 *
 * @param false|object|array $result Existing API result.
 * @param string             $action API action.
 * @param object             $args   Request args.
 * @return false|object|array
 */
function prayer_pop_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) ) {
		return $result;
	}

	$slug = dirname( plugin_basename( __FILE__ ) );
	if ( '.' === $slug || '' === $slug ) {
		$slug = 'prayerpop';
	}

	if ( $slug !== (string) $args->slug ) {
		return $result;
	}

	$readme_headers = prayer_pop_get_readme_headers();
	$sections       = prayer_pop_get_plugin_information_sections();

	return (object) array(
		'name'          => 'PrayerPop',
		'slug'          => $slug,
		'version'       => (string) PRAYERPOP_VERSION,
		'author'        => '<a href="https://osain.ee/">Ösain OÜ</a>',
		'homepage'      => 'https://prayerpop.eu/',
		'requires'      => ! empty( $readme_headers['requires_at_least'] ) ? $readme_headers['requires_at_least'] : '5.8',
		'tested'        => ! empty( $readme_headers['tested_up_to'] ) ? $readme_headers['tested_up_to'] : '',
		'requires_php'  => ! empty( $readme_headers['requires_php'] ) ? $readme_headers['requires_php'] : '7.2',
		'last_updated'  => gmdate( 'Y-m-d', (int) filemtime( PRAYERPOP_PLUGIN_FILE ) ),
		'external'      => true,
		'download_link' => '',
		'sections'      => $sections,
		'banners'       => array(
			'low'  => PRAYERPOP_PLUGIN_URL . 'assets/images/prayer-pop-cover.jpg',
			'high' => PRAYERPOP_PLUGIN_URL . 'assets/images/prayer-pop-cover.jpg',
		),
		'icons'         => array(
			'1x'  => PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-favicon-512x512.png',
			'2x'  => PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-favicon-512x512.png',
			'svg' => PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-icon.svg',
		),
	);
}

/**
 * Parse readme header fields used by the plugin information modal.
 *
 * @return array
 */
function prayer_pop_get_readme_headers() {
	$content = prayer_pop_get_readme_content();
	$headers = array();
	if ( '' === $content ) {
		return $headers;
	}

	$map = array(
		'requires_at_least' => 'Requires at least',
		'tested_up_to'      => 'Tested up to',
		'requires_php'      => 'Requires PHP',
	);

	foreach ( $map as $key => $label ) {
		if ( preg_match( '/^' . preg_quote( $label, '/' ) . ':\s*(.+)$/mi', $content, $matches ) ) {
			$headers[ $key ] = sanitize_text_field( trim( (string) $matches[1] ) );
		}
	}

	return $headers;
}

/**
 * Build WordPress plugin-information modal sections from readme.txt.
 *
 * @return array
 */
function prayer_pop_get_plugin_information_sections() {
	$readme_sections = prayer_pop_get_readme_sections();
	$sections        = array(
		'description'  => prayer_pop_format_plugin_information_content(
			$readme_sections['Description'] ?? __( 'PrayerPop helps churches collect and moderate prayer requests with a simple frontend bubble and admin workflow.', 'prayerpop' )
		),
		'installation' => prayer_pop_format_plugin_information_content(
			$readme_sections['Quick Start'] ?? __( 'Install and activate PrayerPop, then open PrayerPop -> Settings to configure the bubble, notifications, styling, and text.', 'prayerpop' )
		),
		'faq'          => prayer_pop_format_plugin_information_content( $readme_sections['Frequently Asked Questions'] ?? '' ),
		'screenshots'  => prayer_pop_get_plugin_information_screenshots_section(),
		'changelog'    => prayer_pop_format_plugin_information_content(
			$readme_sections['Changelog'] ?? __( 'See readme.txt for release notes.', 'prayerpop' )
		),
	);

	$other_notes = prayer_pop_get_plugin_information_other_notes( $readme_sections );
	if ( '' !== $other_notes ) {
		$sections['other_notes'] = $other_notes;
	}

	return array_filter( $sections );
}

/**
 * Read local readme.txt.
 *
 * @return string
 */
function prayer_pop_get_readme_content() {
	$readme = PRAYERPOP_PLUGIN_DIR . 'readme.txt';
	if ( ! file_exists( $readme ) || ! is_readable( $readme ) ) {
		return '';
	}

	return (string) file_get_contents( $readme );
}

/**
 * Split readme.txt into top-level sections.
 *
 * @return array
 */
function prayer_pop_get_readme_sections() {
	$content = prayer_pop_get_readme_content();
	if ( '' === $content ) {
		return array();
	}

	$sections = array();
	if ( preg_match_all( '/^==\s*(.+?)\s*==\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[1] as $index => $match ) {
			$title = trim( (string) $match[0] );
			$start = (int) $matches[0][ $index ][1] + strlen( (string) $matches[0][ $index ][0] );
			$end   = isset( $matches[0][ $index + 1 ] ) ? (int) $matches[0][ $index + 1 ][1] : strlen( $content );
			$sections[ $title ] = trim( substr( $content, $start, $end - $start ) );
		}
	}

	return $sections;
}

/**
 * Convert simple readme markup into modal-safe HTML.
 *
 * @param string $content Readme section content.
 * @return string
 */
function prayer_pop_format_plugin_information_content( $content ) {
	$content = trim( (string) $content );
	if ( '' === $content ) {
		return '';
	}

	$lines   = preg_split( '/\R/', $content );
	$html    = '';
	$list    = '';
	foreach ( $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			if ( '' !== $list ) {
				$html .= '</' . $list . '>';
				$list = '';
			}
			continue;
		}

		if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $matches ) ) {
			if ( '' !== $list ) {
				$html .= '</' . $list . '>';
				$list = '';
			}
			$html .= '<h4>' . prayer_pop_format_plugin_information_line( $matches[1] ) . '</h4>';
			continue;
		}

		if ( preg_match( '/^\*\s+(.+)$/', $line, $matches ) ) {
			if ( 'ul' !== $list ) {
				if ( '' !== $list ) {
					$html .= '</' . $list . '>';
				}
				$html .= '<ul>';
				$list = 'ul';
			}
			$html .= '<li>' . prayer_pop_format_plugin_information_line( $matches[1] ) . '</li>';
			continue;
		}

		if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $matches ) ) {
			if ( 'ol' !== $list ) {
				if ( '' !== $list ) {
					$html .= '</' . $list . '>';
				}
				$html .= '<ol>';
				$list = 'ol';
			}
			$html .= '<li>' . prayer_pop_format_plugin_information_line( $matches[1] ) . '</li>';
			continue;
		}

		if ( '' !== $list ) {
			$html .= '</' . $list . '>';
			$list = '';
		}
		$html .= '<p>' . prayer_pop_format_plugin_information_line( $line ) . '</p>';
	}

	if ( '' !== $list ) {
		$html .= '</' . $list . '>';
	}

	return wp_kses_post( $html );
}

/**
 * Escape a readme line and preserve inline code formatting.
 *
 * @param string $line Text line.
 * @return string
 */
function prayer_pop_format_plugin_information_line( $line ) {
	$line = esc_html( (string) $line );
	$line = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $line );
	$line = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line );
	$line = preg_replace_callback(
		'/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/',
		static function ( $matches ) {
			return sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $matches[2] ),
				$matches[1]
			);
		},
		$line
	);

	return $line;
}

/**
 * Build a screenshots tab for the modal.
 *
 * @return string
 */
function prayer_pop_get_plugin_information_screenshots_section() {
	return wp_kses_post(
		'<p>' . esc_html__( 'PrayerPop includes a focused admin workflow, a frontend prayer request bubble, notification settings, style controls, and built-in documentation.', 'prayerpop' ) . '</p>' .
		'<p><img src="' . esc_url( PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-favicon-512x512.png' ) . '" class="screenshot" width="320" height="320" alt="' . esc_attr__( 'PrayerPop app icon', 'prayerpop' ) . '" /></p>'
	);
}

/**
 * Combine remaining readme sections into Other Notes.
 *
 * @param array $readme_sections Parsed readme sections.
 * @return string
 */
function prayer_pop_get_plugin_information_other_notes( $readme_sections ) {
	$wanted = array( 'Frontend Usage', 'Admin Workflow', 'Notifications', 'Text Customization', 'Privacy & Data Handling' );
	$html   = '';
	foreach ( $wanted as $title ) {
		if ( empty( $readme_sections[ $title ] ) ) {
			continue;
		}
		$html .= '<h3>' . esc_html( $title ) . '</h3>' . prayer_pop_format_plugin_information_content( $readme_sections[ $title ] );
	}

	return wp_kses_post( $html );
}
