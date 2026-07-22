<?php
/**
 * Simple, self-contained PrayerPop Chat for the WordPress.org package.
 *
 * @package PrayerPop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prayer_Pop_Chat {
	const SETTINGS_OPTION = 'prayer_pop_chat_settings';
	const SCHEMA_OPTION   = 'prayer_pop_chat_schema_version';
	const SCHEMA_VERSION  = '1';
	const COOKIE_NAME     = 'prayerpop_chat_token';
	const RATE_LIMIT      = 10;

	/** @var Prayer_Pop_Chat|null */
	private static $instance = null;

	/** Initialize the singleton. */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ), 4 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 30 );
		add_action( 'wp_footer', array( $this, 'render_frontend' ), 35 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );
	}

	/** Default operational settings. */
	public static function defaults() {
		return array(
			'enabled'            => 0,
			'team_name'          => __( 'PrayerPop', 'prayerpop' ),
			'notification_email' => sanitize_email( get_option( 'admin_email' ) ),
		);
	}

	/** Sanitized settings merged with defaults. */
	public static function settings() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/** Create or repair the minimal compatible chat tables. */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset       = $wpdb->get_charset_collate();
		$conversations = esc_sql( self::conversations_table() );
		$messages      = esc_sql( self::messages_table() );

		dbDelta( "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_token_hash char(64) NOT NULL,
			visitor_token_expires_at datetime NOT NULL,
			visitor_name varchar(100) NOT NULL DEFAULT '',
			visitor_email varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'open',
			visitor_unread int(10) unsigned NOT NULL DEFAULT 0,
			admin_unread int(10) unsigned NOT NULL DEFAULT 0,
			last_sender varchar(20) NOT NULL DEFAULT '',
			last_message_excerpt varchar(255) NOT NULL DEFAULT '',
			last_message_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY visitor_token_hash (visitor_token_hash),
			KEY status_last_message (status,last_message_at),
			KEY visitor_email (visitor_email)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			sender_type varchar(20) NOT NULL,
			sender_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			read_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY conversation_messages (conversation_id,id),
			KEY unread_messages (conversation_id,read_at)
		) {$charset};" );

		add_option( self::SETTINGS_OPTION, self::defaults(), '', false );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/** Repair the schema after an update. */
	public static function maybe_install() {
		if ( self::SCHEMA_VERSION !== (string) get_option( self::SCHEMA_OPTION, '0' ) ) {
			self::install();
		}
	}

	private static function conversations_table() {
		global $wpdb;
		return $wpdb->prefix . 'prayerpop_chat_conversations';
	}

	private static function messages_table() {
		global $wpdb;
		return $wpdb->prefix . 'prayerpop_chat_messages';
	}

	/** Register the three intentionally small settings. */
	public function register_settings() {
		register_setting( 'prayer_pop_chat_settings_group', self::SETTINGS_OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$email = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		return array(
			'enabled'            => empty( $input['enabled'] ) ? 0 : 1,
			'team_name'          => isset( $input['team_name'] ) ? self::truncate( sanitize_text_field( $input['team_name'] ), 100 ) : __( 'PrayerPop', 'prayerpop' ),
			'notification_email' => is_email( $email ) ? $email : sanitize_email( get_option( 'admin_email' ) ),
		);
	}

	/** Explain the locally stored Chat data to site owners. */
	public function add_privacy_policy_content() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'PrayerPop Chat', 'prayerpop' ),
				wp_kses_post( __( 'When PrayerPop Chat is enabled, the visitor name, email address, messages, conversation status, and message timestamps are stored in this website’s WordPress database. A random private browser token is stored as an HttpOnly cookie so the visitor can return to the conversation. New-message and reply notifications are sent through the website’s configured WordPress email system.', 'prayerpop' ) )
			);
		}
	}

	public function register_privacy_exporter( $exporters ) {
		$exporters['prayerpop-chat'] = array(
			'exporter_friendly_name' => __( 'PrayerPop Chat', 'prayerpop' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	public function register_privacy_eraser( $erasers ) {
		$erasers['prayerpop-chat'] = array(
			'eraser_friendly_name' => __( 'PrayerPop Chat', 'prayerpop' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/** Export conversations associated with an email address. */
	public function export_personal_data( $email_address, $page = 1 ) {
		global $wpdb;
		$email = sanitize_email( $email_address );
		if ( ! is_email( $email ) ) {
			return array( 'data' => array(), 'done' => true );
		}
		$table = esc_sql( self::conversations_table() );
		$offset = max( 0, ( absint( $page ) - 1 ) * 20 );
		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE visitor_email=%s ORDER BY id ASC LIMIT 20 OFFSET %d", $email, $offset );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data = array();
		foreach ( $rows as $row ) {
			$messages = $this->messages_for( $row->id );
			$message_text = implode( "\n\n", array_map( static function( $message ) { return $message['sender_type'] . ': ' . $message['message']; }, $messages ) );
			$data[] = array(
				'group_id'    => 'prayerpop-chat',
				'group_label' => __( 'PrayerPop Chat conversations', 'prayerpop' ),
				'item_id'     => 'prayerpop-chat-' . (int) $row->id,
				'data'        => array(
					array( 'name' => __( 'Name', 'prayerpop' ), 'value' => $row->visitor_name ),
					array( 'name' => __( 'Email', 'prayerpop' ), 'value' => $row->visitor_email ),
					array( 'name' => __( 'Status', 'prayerpop' ), 'value' => $row->status ),
					array( 'name' => __( 'Created', 'prayerpop' ), 'value' => $row->created_at ),
					array( 'name' => __( 'Messages', 'prayerpop' ), 'value' => $message_text ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $rows ) < 20 );
	}

	/** Permanently erase conversations associated with an email address. */
	public function erase_personal_data( $email_address, $page = 1 ) {
		global $wpdb;
		$email = sanitize_email( $email_address );
		if ( ! is_email( $email ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$table = esc_sql( self::conversations_table() );
		$sql = $wpdb->prepare( "SELECT id FROM {$table} WHERE visitor_email=%s ORDER BY id ASC LIMIT 20", $email );
		$ids = array_map( 'absint', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $ids as $id ) {
			$wpdb->delete( self::messages_table(), array( 'conversation_id' => $id ), array( '%d' ) );
			$wpdb->delete( self::conversations_table(), array( 'id' => $id ), array( '%d' ) );
		}
		return array( 'items_removed' => ! empty( $ids ), 'items_retained' => false, 'messages' => array(), 'done' => count( $ids ) < 20 );
	}

	/** Add the shared inbox below PrayerPop. */
	public function register_admin_page() {
		add_submenu_page(
			'prayer-pop',
			__( 'PrayerPop Chat', 'prayerpop' ),
			__( 'Chat', 'prayerpop' ),
			'manage_options',
			'prayer-pop-chat',
			array( $this, 'render_admin_page' )
		);
	}

	/** Load inbox assets only on its screen. */
	public function enqueue_admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'prayer-pop-chat' !== $page ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		$css_path = PRAYERPOP_PLUGIN_DIR . 'assets/css/prayer-pop-chat-admin.css';
		$layout_css_path = PRAYERPOP_PLUGIN_DIR . 'assets/css/prayer-pop-chat-admin-layout.css';
		$js_path  = PRAYERPOP_PLUGIN_DIR . 'assets/js/prayer-pop-chat-admin.js';
		wp_enqueue_style( 'prayer-pop-chat-admin', PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-chat-admin.css', array(), file_exists( $css_path ) ? (string) filemtime( $css_path ) : PRAYERPOP_VERSION );
		wp_enqueue_style( 'prayer-pop-chat-admin-layout', PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-chat-admin-layout.css', array( 'prayer-pop-chat-admin' ), file_exists( $layout_css_path ) ? (string) filemtime( $layout_css_path ) : PRAYERPOP_VERSION );
		wp_enqueue_script( 'prayer-pop-chat-admin', PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-chat-admin.js', array(), file_exists( $js_path ) ? (string) filemtime( $js_path ) : PRAYERPOP_VERSION, true );
		wp_localize_script(
			'prayer-pop-chat-admin',
			'PrayerPopChatAdmin',
			array(
				'root'  => esc_url_raw( rest_url( 'prayerpop/v1/chat/admin/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'empty'          => __( 'No conversations yet.', 'prayerpop' ),
					'newMessages'    => __( 'New website messages will appear here.', 'prayerpop' ),
					'choose'         => __( 'Choose a conversation to read and reply.', 'prayerpop' ),
					'deleteConversation' => __( 'Delete conversation', 'prayerpop' ),
					'deleteConfirm'  => __( 'Delete this conversation permanently?', 'prayerpop' ),
					'visitorDetails' => __( 'Visitor details will appear here.', 'prayerpop' ),
					'yourMessages'   => __( 'Your messages', 'prayerpop' ),
					'contactDetails' => __( 'Contact details', 'prayerpop' ),
					'status'         => __( 'Status', 'prayerpop' ),
					'started'        => __( 'Started', 'prayerpop' ),
					'lastMessage'    => __( 'Last message', 'prayerpop' ),
					'reply'          => __( 'Write a reply…', 'prayerpop' ),
					'send'           => __( 'Send reply', 'prayerpop' ),
					'close'          => __( 'Close conversation', 'prayerpop' ),
					'reopen'         => __( 'Reopen conversation', 'prayerpop' ),
					'error'          => __( 'Something went wrong.', 'prayerpop' ),
					'closed'         => __( 'Closed', 'prayerpop' ),
					'open'           => __( 'Open', 'prayerpop' ),
				),
			)
		);
	}

	/** Render settings and the deliberately simple inbox. */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'prayerpop' ) );
		}
		$settings      = self::settings();
		$settings_open = isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) );
		?>
		<div class="wrap ppm-admin-wrap">
			<header class="ppm-workspace-header">
				<div><span class="ppm-workspace-mark"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span></span><div><h1><?php esc_html_e( 'PrayerPop Chat', 'prayerpop' ); ?></h1><p><?php esc_html_e( 'Talk with your website visitors in one place.', 'prayerpop' ); ?></p></div></div>
				<button type="button" class="ppm-settings-link" id="ppfc-settings-toggle" aria-controls="ppfc-settings" aria-expanded="<?php echo $settings_open ? 'true' : 'false'; ?>"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Chat settings', 'prayerpop' ); ?></button>
			</header>
			<form method="post" action="options.php" class="ppfc-settings" id="ppfc-settings"<?php echo $settings_open ? '' : ' hidden'; ?>>
				<?php settings_fields( 'prayer_pop_chat_settings_group' ); ?>
				<label class="ppfc-enable"><input type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <span><?php esc_html_e( 'Enable Chat', 'prayerpop' ); ?></span></label>
				<label><span><?php esc_html_e( 'Team name', 'prayerpop' ); ?></span><input type="text" maxlength="100" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[team_name]" value="<?php echo esc_attr( $settings['team_name'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Notification email', 'prayerpop' ); ?></span><input type="email" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>"></label>
				<?php submit_button( __( 'Save Chat settings', 'prayerpop' ), 'secondary', 'submit', false ); ?>
			</form>
			<div class="ppm-inbox ppfc-free-inbox">
				<aside class="ppm-chat-list"><div class="ppm-list-heading"><h2><?php esc_html_e( 'Chats', 'prayerpop' ); ?></h2><span id="ppfc-count">0</span></div><div id="ppfc-conversations" class="ppm-conversations"><div class="ppm-list-loading"><span class="spinner is-active"></span><?php esc_html_e( 'Loading conversations…', 'prayerpop' ); ?></div></div></aside>
				<section id="ppfc-thread"><div class="ppm-thread-empty"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><h2><?php esc_html_e( 'Your messages', 'prayerpop' ); ?></h2><p><?php esc_html_e( 'Choose a conversation from the left to read and reply.', 'prayerpop' ); ?></p></div></section>
				<aside id="ppfc-contact-panel"><div class="ppm-contact-empty"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><p><?php esc_html_e( 'Visitor details will appear here.', 'prayerpop' ); ?></p></div></aside>
			</div>
		</div>
		<?php
	}

	/** Whether frontend chat is enabled. */
	private function enabled() {
		return ! empty( self::settings()['enabled'] );
	}

	/** Return a consistent response when public Chat access is disabled. */
	private function disabled_error() {
		return new WP_Error( 'chat_disabled', __( 'Chat is unavailable.', 'prayerpop' ), array( 'status' => 404 ) );
	}

	/** Load frontend assets only while enabled. */
	public function enqueue_frontend_assets() {
		if ( ! $this->enabled() ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'prayer-pop-free-chat', PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-chat.css', array( 'prayer-pop-style' ), PRAYERPOP_VERSION );
		wp_enqueue_script( 'prayer-pop-free-chat', PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-chat.js', array(), PRAYERPOP_VERSION, true );
		wp_localize_script(
			'prayer-pop-free-chat',
			'PrayerPopChat',
			array(
				'root' => esc_url_raw( rest_url( 'prayerpop/v1/chat/' ) ),
				'i18n' => array(
					'error'   => __( 'Something went wrong. Please try again.', 'prayerpop' ),
					'closed'  => __( 'This conversation is closed.', 'prayerpop' ),
					'sending' => __( 'Sending…', 'prayerpop' ),
				),
			)
		);
	}

	/** Render one classic Chat panel. */
	public function render_frontend() {
		if ( ! $this->enabled() ) {
			return;
		}
		$settings = self::settings();
		$styles = Prayer_Pop_Defaults::get_styles();
		$general = Prayer_Pop_Defaults::get_settings();
		$position = isset( $styles['bubble_position'] ) && 'left' === $styles['bubble_position'] ? 'left' : 'right';
		$with_bubble = ! empty( $general['show_prayer_pop_bubble'] );
		?>
		<button type="button" class="ppfc-trigger<?php echo $with_bubble ? ' ppfc-with-bubble' : ''; ?>" data-position="<?php echo esc_attr( $position ); ?>" aria-controls="ppfc-panel" aria-expanded="false"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><span><?php esc_html_e( 'Chat', 'prayerpop' ); ?></span></button>
		<section id="ppfc-panel" class="ppfc-panel<?php echo $with_bubble ? ' ppfc-with-bubble' : ''; ?>" data-position="<?php echo esc_attr( $position ); ?>" role="dialog" aria-modal="false" aria-labelledby="ppfc-title" hidden>
			<header><div><strong id="ppfc-title"><?php echo esc_html( $settings['team_name'] ); ?></strong><small><?php esc_html_e( 'The team can also help', 'prayerpop' ); ?></small></div><button type="button" class="ppfc-close" aria-label="<?php esc_attr_e( 'Close Chat', 'prayerpop' ); ?>">&times;</button></header>
			<div class="ppfc-start">
				<p><?php esc_html_e( 'Leave a message and we will reply here and by email.', 'prayerpop' ); ?></p>
				<form class="ppfc-start-form">
					<label><?php esc_html_e( 'Your name', 'prayerpop' ); ?><input name="name" maxlength="100" autocomplete="name" required></label>
					<label><?php esc_html_e( 'Your email', 'prayerpop' ); ?><input name="email" type="email" maxlength="190" autocomplete="email" required></label>
					<label><?php esc_html_e( 'How can we help?', 'prayerpop' ); ?><textarea name="message" maxlength="2000" rows="4" required></textarea></label>
					<input class="ppfc-website" name="website" tabindex="-1" autocomplete="off"><input name="started_at" type="hidden" value="<?php echo esc_attr( time() ); ?>">
					<button type="submit"><?php esc_html_e( 'Send message', 'prayerpop' ); ?></button>
				</form>
			</div>
			<div class="ppfc-conversation" hidden><div class="ppfc-messages" aria-live="polite"></div><p class="ppfc-closed" hidden><?php esc_html_e( 'This conversation is closed.', 'prayerpop' ); ?> <button type="button"><?php esc_html_e( 'Start a new conversation', 'prayerpop' ); ?></button></p><form class="ppfc-composer"><textarea rows="1" maxlength="2000" required placeholder="<?php esc_attr_e( 'Write a message…', 'prayerpop' ); ?>"></textarea><button type="submit" aria-label="<?php esc_attr_e( 'Send message', 'prayerpop' ); ?>">&uarr;</button></form></div>
			<p class="ppfc-error" role="alert" hidden></p>
		</section>
		<?php
	}

	/** Register public session routes and capability-protected inbox routes. */
	public function register_routes() {
		$public = array( 'permission_callback' => '__return_true' );
		$admin  = array( 'permission_callback' => static function() { return current_user_can( 'manage_options' ); } );
		register_rest_route( 'prayerpop/v1', '/chat/conversation', array( array_merge( $public, array( 'methods' => 'GET', 'callback' => array( $this, 'visitor_get' ) ) ) ) );
		register_rest_route( 'prayerpop/v1', '/chat/conversations', array( array_merge( $public, array( 'methods' => 'POST', 'callback' => array( $this, 'visitor_create' ) ) ) ) );
		register_rest_route( 'prayerpop/v1', '/chat/messages', array(
			array_merge( $public, array( 'methods' => 'GET', 'callback' => array( $this, 'visitor_messages' ) ) ),
			array_merge( $public, array( 'methods' => 'POST', 'callback' => array( $this, 'visitor_send' ) ) ),
		) );
		register_rest_route( 'prayerpop/v1', '/chat/read', array( array_merge( $public, array( 'methods' => 'POST', 'callback' => array( $this, 'visitor_read' ) ) ) ) );
		register_rest_route( 'prayerpop/v1', '/chat/admin/conversations', array( array_merge( $admin, array( 'methods' => 'GET', 'callback' => array( $this, 'admin_list' ) ) ) ) );
		register_rest_route( 'prayerpop/v1', '/chat/admin/conversations/(?P<id>\d+)', array(
			array_merge( $admin, array( 'methods' => 'GET', 'callback' => array( $this, 'admin_get' ) ) ),
			array_merge( $admin, array( 'methods' => 'DELETE', 'callback' => array( $this, 'admin_delete' ) ) ),
		) );
		register_rest_route( 'prayerpop/v1', '/chat/admin/conversations/(?P<id>\d+)/messages', array( array_merge( $admin, array( 'methods' => 'POST', 'callback' => array( $this, 'admin_send' ) ) ) ) );
		register_rest_route( 'prayerpop/v1', '/chat/admin/conversations/(?P<id>\d+)/read', array( array_merge( $admin, array( 'methods' => 'POST', 'callback' => array( $this, 'admin_read' ) ) ) ) );
		register_rest_route( 'prayerpop/v1', '/chat/admin/conversations/(?P<id>\d+)/status', array( array_merge( $admin, array( 'methods' => 'POST', 'callback' => array( $this, 'admin_status' ) ) ) ) );
	}

	private function visitor_token() {
		$token = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';
		return preg_match( '/^[a-f0-9]{64}$/', $token ) ? $token : '';
	}

	private function visitor_conversation( $id = 0 ) {
		global $wpdb;
		$token = $this->visitor_token();
		if ( ! $token ) {
			return null;
		}
		$table = esc_sql( self::conversations_table() );
		if ( $id ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND visitor_token_hash=%s AND visitor_token_expires_at>%s", absint( $id ), hash( 'sha256', $token ), current_time( 'mysql', true ) );
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE visitor_token_hash=%s AND visitor_token_expires_at>%s ORDER BY last_message_at DESC LIMIT 1", hash( 'sha256', $token ), current_time( 'mysql', true ) );
		}
		return $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function limited() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'prayerpop_chat_rate_' . substr( hash( 'sha256', $address . wp_salt( 'nonce' ) ), 0, 20 );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return true;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	private function request_message( WP_REST_Request $request ) {
		$message = trim( sanitize_textarea_field( (string) $request->get_param( 'message' ) ) );
		return self::truncate( $message, 2000 );
	}

	/** Safely limit user-facing Unicode text. */
	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	public function visitor_create( WP_REST_Request $request ) {
		if ( ! $this->enabled() ) {
			return $this->disabled_error();
		}
		if ( $this->limited() || $request->get_param( 'website' ) || absint( $request->get_param( 'started_at' ) ) > time() - 2 ) {
			return new WP_Error( 'chat_limited', __( 'Please wait a moment and try again.', 'prayerpop' ), array( 'status' => 429 ) );
		}
		$name      = self::truncate( sanitize_text_field( (string) $request->get_param( 'name' ) ), 100 );
		$email     = sanitize_email( (string) $request->get_param( 'email' ) );
		$message   = $this->request_message( $request );
		if ( '' === $name || ! is_email( $email ) || '' === $message ) {
			return new WP_Error( 'invalid_chat', __( 'Enter your name, a valid email address, and a message.', 'prayerpop' ), array( 'status' => 400 ) );
		}
		$token = $this->visitor_token();
		if ( ! $token ) {
			$token = hash( 'sha256', wp_generate_password( 64, true, true ) . wp_rand() );
		}
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert( self::conversations_table(), array(
			'visitor_token_hash'       => hash( 'sha256', $token ),
			'visitor_token_expires_at' => gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ),
			'visitor_name'             => $name,
			'visitor_email'            => $email,
			'status'                   => 'open',
			'admin_unread'             => 1,
			'last_sender'              => 'visitor',
			'last_message_excerpt'     => self::truncate( $message, 255 ),
			'last_message_at'          => $now,
			'created_at'               => $now,
			'updated_at'               => $now,
		) );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return new WP_Error( 'chat_storage', __( 'The conversation could not be created.', 'prayerpop' ), array( 'status' => 500 ) );
		}
		if ( ! $this->insert_message( $id, 'visitor', 0, $message ) ) {
			$wpdb->delete( self::conversations_table(), array( 'id' => $id ), array( '%d' ) );
			return new WP_Error( 'chat_storage', __( 'The message could not be saved.', 'prayerpop' ), array( 'status' => 500 ) );
		}
		$this->email_admin( $name, $message, $id );
		$conversation = $this->get_conversation( $id );
		$response = rest_ensure_response( array( 'conversation' => $this->format_conversation( $conversation ), 'messages' => $this->messages_for( $id ) ) );
		$response->header( 'Set-Cookie', $this->cookie_header( $token ) );
		return $response;
	}

	private function cookie_header( $token ) {
		$parts = array( rawurlencode( self::COOKIE_NAME ) . '=' . rawurlencode( $token ), 'Expires=' . gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS ) . ' GMT', 'Max-Age=' . YEAR_IN_SECONDS, 'Path=' . ( COOKIEPATH ?: '/' ), 'HttpOnly', 'SameSite=Lax' );
		if ( is_ssl() ) {
			$parts[] = 'Secure';
		}
		return implode( '; ', $parts );
	}

	public function visitor_get() {
		if ( ! $this->enabled() ) {
			return $this->disabled_error();
		}
		$conversation = $this->visitor_conversation();
		return rest_ensure_response( array( 'conversation' => $conversation ? $this->format_conversation( $conversation ) : null ) );
	}

	public function visitor_messages( WP_REST_Request $request ) {
		if ( ! $this->enabled() ) {
			return $this->disabled_error();
		}
		$conversation = $this->visitor_conversation( absint( $request->get_param( 'conversation_id' ) ) );
		if ( ! $conversation ) {
			return new WP_Error( 'forbidden', __( 'Conversation not found.', 'prayerpop' ), array( 'status' => 403 ) );
		}
		return rest_ensure_response( array( 'conversation' => $this->format_conversation( $conversation ), 'messages' => $this->messages_for( $conversation->id, absint( $request->get_param( 'after_id' ) ) ) ) );
	}

	public function visitor_send( WP_REST_Request $request ) {
		if ( ! $this->enabled() ) {
			return $this->disabled_error();
		}
		$conversation = $this->visitor_conversation( absint( $request->get_param( 'conversation_id' ) ) );
		$message = $this->request_message( $request );
		if ( ! $conversation || 'open' !== $conversation->status ) {
			return new WP_Error( 'closed', __( 'This conversation is closed.', 'prayerpop' ), array( 'status' => 409 ) );
		}
		if ( '' === $message || $this->limited() ) {
			return new WP_Error( 'invalid_message', __( 'Enter a message and try again.', 'prayerpop' ), array( 'status' => 400 ) );
		}
		if ( ! $this->insert_message( $conversation->id, 'visitor', 0, $message ) ) {
			return new WP_Error( 'chat_storage', __( 'The message could not be saved.', 'prayerpop' ), array( 'status' => 500 ) );
		}
		$this->touch( $conversation->id, 'visitor', $message );
		$this->email_admin( $conversation->visitor_name, $message, $conversation->id );
		return rest_ensure_response( array( 'conversation' => $this->format_conversation( $this->get_conversation( $conversation->id ) ), 'messages' => $this->messages_for( $conversation->id ) ) );
	}

	public function visitor_read( WP_REST_Request $request ) {
		if ( ! $this->enabled() ) {
			return $this->disabled_error();
		}
		$conversation = $this->visitor_conversation( absint( $request->get_param( 'conversation_id' ) ) );
		if ( ! $conversation ) {
			return new WP_Error( 'forbidden', '', array( 'status' => 403 ) );
		}
		global $wpdb;
		$table = esc_sql( self::messages_table() );
		$sql = $wpdb->prepare( "UPDATE {$table} SET read_at=%s WHERE conversation_id=%d AND sender_type='admin' AND read_at IS NULL", current_time( 'mysql', true ), $conversation->id );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->update( self::conversations_table(), array( 'visitor_unread' => 0 ), array( 'id' => $conversation->id ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	private function insert_message( $id, $type, $user_id, $message ) {
		global $wpdb;
		$wpdb->insert( self::messages_table(), array( 'conversation_id' => absint( $id ), 'sender_type' => $type, 'sender_user_id' => absint( $user_id ), 'message' => $message, 'created_at' => current_time( 'mysql', true ) ) );
		return (int) $wpdb->insert_id;
	}

	private function touch( $id, $sender, $message ) {
		global $wpdb;
		$table  = esc_sql( self::conversations_table() );
		$column = 'admin' === $sender ? 'visitor_unread' : 'admin_unread';
		$now = current_time( 'mysql', true );
		$sql = $wpdb->prepare( "UPDATE {$table} SET {$column}={$column}+1,last_sender=%s,last_message_excerpt=%s,last_message_at=%s,updated_at=%s WHERE id=%d", $sender, self::truncate( $message, 255 ), $now, $now, absint( $id ) );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function messages_for( $id, $after = 0 ) {
		global $wpdb;
		$table = esc_sql( self::messages_table() );
		$sql = $wpdb->prepare( "SELECT id,sender_type,sender_user_id,message,read_at,created_at FROM {$table} WHERE conversation_id=%d AND id>%d AND sender_type IN ('visitor','admin') ORDER BY id ASC LIMIT 200", absint( $id ), absint( $after ) );
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( static function( $row ) {
			return array( 'id' => (int) $row->id, 'sender_type' => $row->sender_type, 'sender_user_id' => (int) $row->sender_user_id, 'message' => $row->message, 'read_at' => $row->read_at, 'created_at' => $row->created_at );
		}, $rows );
	}

	private function get_conversation( $id ) {
		global $wpdb;
		$table = esc_sql( self::conversations_table() );
		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $id ) );
		return $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function format_conversation( $conversation ) {
		return array(
			'id' => (int) $conversation->id, 'visitor_name' => $conversation->visitor_name, 'visitor_email' => $conversation->visitor_email,
			'status' => $conversation->status, 'visitor_unread' => (int) $conversation->visitor_unread, 'admin_unread' => (int) $conversation->admin_unread,
			'last_sender' => $conversation->last_sender, 'last_message_excerpt' => $conversation->last_message_excerpt,
			'last_message_at' => $conversation->last_message_at, 'created_at' => $conversation->created_at,
		);
	}

	public function admin_list() {
		global $wpdb;
		$table = esc_sql( self::conversations_table() );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_message_at DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return rest_ensure_response( array_map( array( $this, 'format_conversation' ), $rows ) );
	}

	public function admin_get( WP_REST_Request $request ) {
		$conversation = $this->get_conversation( $request['id'] );
		if ( ! $conversation ) {
			return new WP_Error( 'not_found', __( 'Conversation not found.', 'prayerpop' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'conversation' => $this->format_conversation( $conversation ), 'messages' => $this->messages_for( $conversation->id, absint( $request->get_param( 'after_id' ) ) ) ) );
	}

	public function admin_send( WP_REST_Request $request ) {
		$conversation = $this->get_conversation( $request['id'] );
		$message = $this->request_message( $request );
		if ( ! $conversation || 'open' !== $conversation->status || '' === $message ) {
			return new WP_Error( 'invalid_message', __( 'Reopen the conversation and enter a reply.', 'prayerpop' ), array( 'status' => 400 ) );
		}
		if ( ! $this->insert_message( $conversation->id, 'admin', get_current_user_id(), $message ) ) {
			return new WP_Error( 'chat_storage', __( 'The reply could not be saved.', 'prayerpop' ), array( 'status' => 500 ) );
		}
		$this->touch( $conversation->id, 'admin', $message );
		$this->email_visitor( $conversation, $message );
		return rest_ensure_response( array( 'conversation' => $this->format_conversation( $this->get_conversation( $conversation->id ) ), 'messages' => $this->messages_for( $conversation->id ) ) );
	}

	public function admin_read( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		if ( ! $this->get_conversation( $id ) ) {
			return new WP_Error( 'not_found', __( 'Conversation not found.', 'prayerpop' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$table = esc_sql( self::messages_table() );
		$sql = $wpdb->prepare( "UPDATE {$table} SET read_at=%s WHERE conversation_id=%d AND sender_type='visitor' AND read_at IS NULL", current_time( 'mysql', true ), $id );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->update( self::conversations_table(), array( 'admin_unread' => 0 ), array( 'id' => $id ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function admin_status( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! $this->get_conversation( $id ) || ! in_array( $status, array( 'open', 'closed' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Choose a valid conversation status.', 'prayerpop' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		$wpdb->update( self::conversations_table(), array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		return rest_ensure_response( array( 'conversation' => $this->format_conversation( $this->get_conversation( $id ) ) ) );
	}

	/** Permanently delete a conversation and all of its messages. */
	public function admin_delete( WP_REST_Request $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		if ( ! $this->get_conversation( $id ) ) {
			return new WP_Error( 'not_found', __( 'Conversation not found.', 'prayerpop' ), array( 'status' => 404 ) );
		}
		$wpdb->delete( self::messages_table(), array( 'conversation_id' => $id ), array( '%d' ) );
		$deleted = $wpdb->delete( self::conversations_table(), array( 'id' => $id ), array( '%d' ) );
		if ( false === $deleted ) {
			return new WP_Error( 'delete_failed', __( 'The conversation could not be deleted.', 'prayerpop' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	private function email_html( $title, $name, $message, $url, $button ) {
		$team        = self::settings()['team_name'];
		$styles      = Prayer_Pop_Defaults::get_styles();
		$brand_color = isset( $styles['global_bg_color'] ) ? sanitize_hex_color( $styles['global_bg_color'] ) : '';
		$brand_color = $brand_color ? $brand_color : '#365bb2';
		return '<div style="background:#f4f5f7;padding:32px;font-family:Arial,sans-serif;color:#172033"><div style="max-width:560px;margin:auto;background:#fff;border-radius:18px;padding:28px"><strong>' . esc_html( $team ) . '</strong><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $name ) . '</p><div style="display:inline-block;background:#eef1f5;border-radius:18px;padding:14px 18px;white-space:pre-wrap">' . esc_html( $message ) . '</div><p style="margin-top:24px"><a href="' . esc_url( $url ) . '" style="background:' . esc_attr( $brand_color ) . ';color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;display:inline-block">' . esc_html( $button ) . '</a></p><p style="font-size:12px;color:#6b7280">' . esc_html__( 'Continue the conversation in PrayerPop Chat. Replies to this email are not added to the chat.', 'prayerpop' ) . '</p></div></div>';
	}

	private function send_html_mail( $to, $subject, $html ) {
		add_filter( 'wp_mail_content_type', array( $this, 'html_mail_type' ) );
		$sent = wp_mail( $to, $subject, $html );
		remove_filter( 'wp_mail_content_type', array( $this, 'html_mail_type' ) );
		return $sent;
	}

	public function html_mail_type() {
		return 'text/html';
	}

	private function email_admin( $name, $message, $id ) {
		$email = self::settings()['notification_email'];
		if ( is_email( $email ) ) {
			$this->send_html_mail( $email, __( 'New PrayerPop Chat message', 'prayerpop' ), $this->email_html( __( 'New message', 'prayerpop' ), $name, $message, admin_url( 'admin.php?page=prayer-pop-chat&conversation=' . absint( $id ) ), __( 'Open Chat', 'prayerpop' ) ) );
		}
	}

	private function email_visitor( $conversation, $message ) {
		if ( is_email( $conversation->visitor_email ) ) {
			$this->send_html_mail( $conversation->visitor_email, __( 'We replied to your message', 'prayerpop' ), $this->email_html( __( 'We’re here to help', 'prayerpop' ), self::settings()['team_name'], $message, add_query_arg( 'prayerpop_chat', 'open', home_url( '/' ) ), __( 'Open PrayerPop Chat', 'prayerpop' ) ) );
		}
	}
}
