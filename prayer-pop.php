<?php
/**
 * Plugin Name: PrayerPop
 * Plugin URI: https://prayerpop.eu/
 * Description: Prayer request workflow plugin with a frontend bubble, admin review, and notifications.
 * Version: 1.5.6
 * Author: Ösain OÜ
 * Author URI: https://www.osain.ee
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
	define( 'PRAYERPOP_VERSION', '1.5.6' );
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

// Include the defaults class first (needed for activation)
require_once __DIR__ . '/core/includes/classes/class-prayer-pop-defaults.php';

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
			'bubble_icon_color'  => '#ffffff',
			'bubble_icon_size'   => 170,
		)
	);

	// Schedule daily cleanup of old submissions (only if not already scheduled)
	if ( ! wp_next_scheduled( 'prayer_pop_cleanup_event' ) ) {
		wp_schedule_event( time(), 'daily', 'prayer_pop_cleanup_event' );
	}

	// Migrate old prayer request post type slug
	prayer_pop_migrate_post_type();

	// Flush rewrite rules
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
			$checked = isset( $_REQUEST['checked'] ) ? (array) wp_unslash( $_REQUEST['checked'] ) : array();
			$checked = array_map( 'sanitize_text_field', $checked );
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

	$links[] = '<a href="' . esc_url( $details_url ) . '" class="thickbox open-plugin-details-modal" aria-label="' . esc_attr__( 'View PrayerPop details', 'prayerpop' ) . '">' . esc_html__( 'View details', 'prayerpop' ) . '</a>';
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

	$changelog = '';
	$readme    = PRAYERPOP_PLUGIN_DIR . 'readme.txt';
	if ( file_exists( $readme ) && is_readable( $readme ) ) {
		$contents = (string) file_get_contents( $readme );
		if ( preg_match( '/==\s*Changelog\s*==(.*)$/si', $contents, $matches ) ) {
			$changelog = trim( (string) $matches[1] );
		}
	}

	$sections = array(
		'description' => wp_kses_post( __( 'PrayerPop helps churches collect and moderate prayer requests with a simple frontend bubble and admin workflow.', 'prayerpop' ) ),
		'changelog'   => '' !== $changelog ? wp_kses_post( nl2br( esc_html( $changelog ) ) ) : wp_kses_post( __( 'See readme.txt for release notes.', 'prayerpop' ) ),
	);

	return (object) array(
		'name'          => 'PrayerPop',
		'slug'          => $slug,
		'version'       => (string) PRAYERPOP_VERSION,
		'author'        => '<a href="https://www.osain.ee">Ösain OÜ</a>',
		'homepage'      => 'https://prayerpop.eu/',
		'requires'      => '5.8',
		'requires_php'  => '7.2',
		'sections'      => $sections,
	);
}
