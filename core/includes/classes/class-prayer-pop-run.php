<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Run
 *
 * This class contains the core functionality of the plugin.
 *
 * @package     PRAYERPOP
 * @since       1.0.0
 */
class Prayer_Pop_Run {
	/**
	 * Maximum number of legacy rows to migrate per admin request.
	 *
	 * @var int
	 */
	const LEGACY_MIGRATION_BATCH_SIZE = 25;

	/**
	 * Default capability required to manage submissions.
	 *
	 * @var string
	 */
	const MANAGE_SUBMISSIONS_CAPABILITY = 'manage_options';

	/**
	 * Post meta key used to track when a submission was archived.
	 *
	 * @var string
	 */
	const ARCHIVED_AT_META_KEY = 'prayer_pop_archived_at';

	/**
	 * Post meta key used to track the previous status before archiving.
	 *
	 * @var string
	 */
	const PRE_ARCHIVE_STATUS_META_KEY = 'prayer_pop_pre_archive_status';

	/**
	 * Post meta key that marks a private pending submission as reviewed.
	 *
	 * @var string
	 */
	const PRIVATE_REVIEWED_META_KEY = 'prayer_pop_private_reviewed';

	/**
	 * Post meta key that stores an optional answer message for an answered prayer.
	 *
	 * @var string
	 */
	const ANSWERED_MESSAGE_META_KEY = 'prayer_pop_answered_message';

	/**
	 * Post meta key that stores when a prayer was marked as answered.
	 *
	 * @var string
	 */
	const ANSWERED_AT_META_KEY = 'prayer_pop_answered_at';

	/**
	 * User meta key for the dismissed dashboard pending-widget state.
	 *
	 * @var string
	 */
	const DASHBOARD_WIDGET_DISMISSED_META_KEY = 'prayer_pop_pending_dashboard_widget_dismissed';

	/**
	 * Tracks whether bubble markup has already been rendered during this request.
	 *
	 * @var bool
	 */
	private static $module_rendered = false;

    /**
     * Custom statuses registered for prayer requests.
     *
     * @var array
     */
	    private $custom_statuses = array(
	        'approved' => 'Approved',
	        'answered' => 'Answered',
	        'declined' => 'Declined',
	        'archived' => 'Archived',
	    );

	/**
	 * Return the capability used for PrayerPop submission management.
	 *
	 * @return string
	 */
	public static function get_manage_submissions_capability() {
		$capability = apply_filters( 'prayer_pop_manage_submissions_capability', self::MANAGE_SUBMISSIONS_CAPABILITY );
		$capability = sanitize_key( (string) $capability );
		return '' !== $capability ? $capability : self::MANAGE_SUBMISSIONS_CAPABILITY;
	}

	/**
	 * Check whether current user can manage PrayerPop submissions.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_submissions() {
		return current_user_can( self::get_manage_submissions_capability() );
	}

    /**
     * Constructor
     */
    public function __construct() {
        $this->add_hooks();
    }

    /**
     * Add WordPress hooks
     */
    private function add_hooks() {
        // Display prayerpop on front-end
        add_action( 'wp_footer', array( $this, 'display_prayer_box' ) );

        // AJAX handler for form submission is registered in Prayer_Pop_Ajax

        // Register custom post type
        add_action( 'init', array( $this, 'register_prayer_request_cpt' ) );

        // Customize admin columns handled by PrayerPop_Admin_List_Table.
        // The default hooks are disabled to avoid conflicts.
        // add_filter( 'manage_prayer_request_posts_columns', array( $this, 'set_custom_columns' ) );
        // add_action( 'manage_prayer_request_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );

        // Make columns sortable - handled in PrayerPop_Admin_List_Table.
        // add_filter( 'manage_edit-prayer_request_sortable_columns', array( $this, 'sortable_columns' ) );
        // add_action( 'pre_get_posts', array( $this, 'orderby_columns' ) );

        // Register custom post statuses
		add_action( 'init', array( $this, 'register_custom_statuses' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate_publish_status' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate_viewed_status' ) );
		add_action( 'admin_init', array( $this, 'block_submission_edit_screen_access' ) );
		add_action( 'admin_init', array( $this, 'handle_dashboard_widget_dismissal' ) );

        // Bulk actions
        add_filter( 'bulk_actions-edit-prayer_request', array( $this, 'bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-prayer_request', array( $this, 'handle_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        // Filter posts by status and type
        add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
        add_filter( 'pre_get_posts', array( $this, 'filter_posts_by_status' ) );

        // Output custom styles
	        add_action( 'wp_enqueue_scripts', array( $this, 'output_custom_styles' ), 20 );

        // Schedule cleanup event
        add_action( 'prayer_pop_cleanup_event', array( $this, 'cleanup_old_submissions' ) );

        // Notification events
		add_action( 'prayer_pop_send_daily_notifications', array( $this, 'send_daily_notifications' ) );
		add_action( 'prayer_pop_send_weekly_notifications', array( $this, 'send_weekly_notifications' ) );
		add_action( 'prayer_pop_send_immediate_notification', array( $this, 'send_immediate_notification' ), 10, 4 );
		add_action( 'transition_post_status', array( $this, 'refresh_last_public_submission_time_on_status_change' ), 10, 3 );
        
        // Register custom cron schedules
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

        // Pending count indicator
        add_action( 'admin_menu', array( $this, 'add_pending_count_bubble' ), 99 );
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );


	        // Single item actions
	        add_action( 'admin_post_prayer_pop_approve', array( $this, 'admin_post_prayer_pop_approve' ) );
	        add_action( 'admin_post_prayer_pop_mark_viewed', array( $this, 'admin_post_prayer_pop_mark_viewed' ) );
	        add_action( 'admin_post_prayer_pop_decline', array( $this, 'admin_post_prayer_pop_decline' ) );
		add_action( 'admin_post_prayer_pop_mark_answered', array( $this, 'admin_post_prayer_pop_mark_answered' ) );
		add_action( 'admin_post_prayer_pop_mark_unanswered', array( $this, 'admin_post_prayer_pop_mark_unanswered' ) );
	        add_action( 'admin_post_prayer_pop_trash', array( $this, 'admin_post_prayer_pop_trash' ) );
	        add_action( 'admin_post_prayer_pop_restore', array( $this, 'admin_post_prayer_pop_restore' ) );
	        add_action( 'admin_post_prayer_pop_archive', array( $this, 'admin_post_prayer_pop_archive' ) );
	        add_action( 'admin_post_prayer_pop_unarchive', array( $this, 'admin_post_prayer_pop_unarchive' ) );

	        // Answered-prayer note editing on classic WordPress edit screen.
	        add_action( 'add_meta_boxes_prayer_request', array( $this, 'add_submission_content_metabox' ), 5 );
	        add_action( 'add_meta_boxes_prayer_request', array( $this, 'replace_submission_side_metabox' ), 99 );
	        add_action( 'add_meta_boxes_prayer_request', array( $this, 'add_answered_note_metabox' ) );
	        add_action( 'save_post_prayer_request', array( $this, 'save_submission_content_metabox' ), 20, 3 );
	        add_action( 'save_post_prayer_request', array( $this, 'save_answered_note_metabox' ), 10, 3 );

	        // Streamline the classic edit screen for submissions.
	        add_action( 'edit_form_top', array( $this, 'render_submission_edit_back_button' ) );
	        add_filter( 'wp_editor_settings', array( $this, 'filter_submission_editor_settings' ), 10, 2 );
	        add_filter( 'wp_insert_post_data', array( $this, 'preserve_submission_status_on_save' ), 10, 2 );
	        add_filter( 'post_updated_messages', array( $this, 'filter_submission_updated_messages' ) );
	        add_filter( 'screen_options_show_screen', array( $this, 'maybe_hide_submission_screen_options' ), 10, 2 );
	        add_filter( 'get_user_option_screen_layout_prayer_request', array( $this, 'force_submission_screen_layout' ) );
	    }

	/**
	 * Refresh "last submission" timestamps when an item becomes publicly visible.
	 *
	 * @param string       $new_status New post status.
	 * @param string       $old_status Previous post status.
	 * @param WP_Post|null $post       Current post object.
	 * @return void
	 */
	public function refresh_last_public_submission_time_on_status_change( $new_status, $old_status, $post ) {
		if ( ! ( $post instanceof WP_Post ) || 'prayer_request' !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		if ( ! in_array( $new_status, array( 'approved', 'answered' ), true ) ) {
			return;
		}

		// Ignore moves inside already-public statuses (approved <-> answered).
		if ( in_array( $old_status, array( 'approved', 'answered' ), true ) ) {
			return;
		}

		// Only refresh when this item is entering public visibility from a moderation queue/status.
		if ( ! in_array( $old_status, array( 'pending', 'declined', 'archived', 'draft', 'new' ), true ) ) {
			return;
		}

		if ( '1' !== (string) get_post_meta( $post->ID, 'prayer_pop_public', true ) ) {
			return;
		}

		$type      = (string) get_post_meta( $post->ID, 'prayer_pop_type', true );
		$timestamp = absint( get_post_time( 'U', true, $post ) );
		if ( 'prayer_request' === $type ) {
			update_option( 'prayer_pop_last_prayer_time', $timestamp );
		}
	}

	    /**
	     * Add answered-message metabox only for answered submissions.
	     *
	     * @param WP_Post $post Current post.
	     * @return void
	     */
	    public function add_answered_note_metabox( $post ) {
	        if ( ! $post || ! isset( $post->ID ) ) {
	            return;
	        }

	        if ( 'answered' !== get_post_status( $post->ID ) ) {
	            return;
	        }

	        if ( 'prayer_request' !== get_post_meta( $post->ID, 'prayer_pop_type', true ) ) {
	            return;
	        }

	        add_meta_box(
	            'prayer_pop_answered_note',
	            esc_html__( 'Answered Prayer Message', 'prayerpop' ),
	            array( $this, 'render_answered_note_metabox' ),
	            'prayer_request',
	            'normal',
	            'default'
	        );
	    }

	/**
	 * Replace the default title/editor area with a plugin-specific content metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function add_submission_content_metabox( $post ) {
		if ( ! $post || ! isset( $post->ID ) ) {
			return;
		}

		remove_meta_box( 'slugdiv', 'prayer_request', 'normal' );
		add_meta_box(
			'prayer_pop_submission_content',
			esc_html__( 'Submission Content', 'prayerpop' ),
			array( $this, 'render_submission_content_metabox' ),
			'prayer_request',
			'normal',
			'high'
		);
	}

	/**
	 * Replace the default Publish box with plugin-specific details/actions.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function replace_submission_side_metabox( $post ) {
		if ( ! $post || ! isset( $post->ID ) ) {
			return;
		}

		remove_meta_box( 'submitdiv', 'prayer_request', 'side' );
		add_meta_box(
			'prayer_pop_submission_details',
			esc_html__( 'Submission Details', 'prayerpop' ),
			array( $this, 'render_submission_details_metabox' ),
			'prayer_request',
			'side',
			'high'
		);
	}

	/**
	 * Render plugin-specific editable fields for submission content.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_submission_content_metabox( $post ) {
		if ( ! $post || ! isset( $post->ID ) ) {
			return;
		}

		$raw_name      = (string) get_post_meta( $post->ID, 'prayer_pop_name', true );
		$is_anonymous  = \Prayer_Pop_Defaults::is_anonymous_submission_name( $post->ID, $raw_name );
		$display_name  = $is_anonymous ? '' : $raw_name;
		$content_value = (string) get_post_field( 'post_content', $post->ID );

		wp_nonce_field( 'prayer_pop_save_submission_content', 'prayer_pop_submission_content_nonce' );
		?>
		<div class="prayer-pop-submission-edit-fields">
			<p>
				<label for="prayer_pop_submission_name"><strong><?php esc_html_e( 'Name', 'prayerpop' ); ?></strong></label><br>
				<input type="text" id="prayer_pop_submission_name" name="prayer_pop_submission_name" class="widefat" value="<?php echo esc_attr( $display_name ); ?>" maxlength="120" autocomplete="off">
				<span class="description"><?php esc_html_e( 'Leave empty to keep this submission anonymous.', 'prayerpop' ); ?></span>
			</p>
			<p>
				<label for="prayer_pop_submission_content"><strong><?php esc_html_e( 'Submission Text', 'prayerpop' ); ?></strong></label><br>
				<textarea id="prayer_pop_submission_content" name="prayer_pop_submission_content" rows="8" class="widefat"><?php echo esc_textarea( $content_value ); ?></textarea>
			</p>
		</div>
		<?php
	}

	/**
	 * Render custom side metabox with submission timestamps and save actions.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_submission_details_metabox( $post ) {
		if ( ! $post || ! isset( $post->ID ) ) {
			return;
		}

		$date_format   = trim( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		$submitted_at  = wp_date( $date_format, (int) get_post_time( 'U', false, $post ) );
		$modified_at   = wp_date( $date_format, (int) get_post_modified_time( 'U', false, $post ) );
		$trash_url     = get_delete_post_link( $post->ID );
		?>
		<div class="submitbox" id="submitpost">
			<div id="minor-publishing">
				<div class="misc-pub-section">
					<strong><?php esc_html_e( 'Submission entered at:', 'prayerpop' ); ?></strong><br>
					<span><?php echo esc_html( $submitted_at ); ?></span>
				</div>
				<div class="misc-pub-section">
					<strong><?php esc_html_e( 'Last modified at:', 'prayerpop' ); ?></strong><br>
					<span><?php echo esc_html( $modified_at ); ?></span>
				</div>
			</div>
			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php if ( $trash_url ) : ?>
						<a class="submitdelete deletion" href="<?php echo esc_url( $trash_url ); ?>"><?php esc_html_e( 'Move to Trash', 'prayerpop' ); ?></a>
					<?php endif; ?>
				</div>
				<div id="publishing-action">
					<span class="spinner"></span>
					<input type="submit" name="save" id="publish" class="button button-primary button-large" value="<?php echo esc_attr__( 'Save Changes', 'prayerpop' ); ?>">
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save plugin-specific submission content fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Current post object.
	 * @param bool    $update  Whether this is an existing post update.
	 * @return void
	 */
	public function save_submission_content_metabox( $post_id, $post, $update ) {
		unset( $update );

		if ( empty( $post ) || ! isset( $post->post_type ) || 'prayer_request' !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST['prayer_pop_submission_content_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['prayer_pop_submission_content_nonce'] ) ), 'prayer_pop_save_submission_content' )
		) {
			return;
		}

		$name_input    = isset( $_POST['prayer_pop_submission_name'] ) ? sanitize_text_field( wp_unslash( $_POST['prayer_pop_submission_name'] ) ) : '';
		$content_input = isset( $_POST['prayer_pop_submission_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prayer_pop_submission_content'] ) ) : '';

		$name_input    = trim( $name_input );
		$content_input = trim( $content_input );

		$is_anonymous = ( '' === $name_input );
		if ( $is_anonymous ) {
			update_post_meta( $post_id, 'prayer_pop_name', \Prayer_Pop_Defaults::ANONYMOUS_NAME_MARKER );
			update_post_meta( $post_id, \Prayer_Pop_Defaults::ANONYMOUS_FLAG_META_KEY, '1' );
		} else {
			update_post_meta( $post_id, 'prayer_pop_name', $name_input );
			update_post_meta( $post_id, \Prayer_Pop_Defaults::ANONYMOUS_FLAG_META_KEY, '0' );
		}

		$title_value = $is_anonymous ? \Prayer_Pop_Defaults::get_anonymous_text() : $name_input;

		remove_action( 'save_post_prayer_request', array( $this, 'save_submission_content_metabox' ), 20 );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title_value,
				'post_content' => $content_input,
			)
		);
		add_action( 'save_post_prayer_request', array( $this, 'save_submission_content_metabox' ), 20, 3 );
	}

	    /**
	     * Render answered-message metabox.
	     *
	     * @param WP_Post $post Current post.
	     * @return void
	     */
	    public function render_answered_note_metabox( $post ) {
	        if ( ! $post || ! isset( $post->ID ) ) {
	            return;
	        }

	        $value = (string) get_post_meta( $post->ID, self::ANSWERED_MESSAGE_META_KEY, true );
	        wp_nonce_field( 'prayer_pop_save_answered_note', 'prayer_pop_answered_note_nonce' );

	        echo '<p class="description">' . esc_html__( 'Optional message shown in the answered section on the front end.', 'prayerpop' ) . '</p>';
	        echo '<textarea id="prayer_pop_answered_message" name="prayer_pop_answered_message" rows="8" class="widefat">' . esc_textarea( $value ) . '</textarea>';
	    }

	    /**
	     * Save answered-message metabox value.
	     *
	     * @param int     $post_id Post ID.
	     * @param WP_Post $post    Current post object.
	     * @param bool    $update  Whether this is an existing post being updated.
	     * @return void
	     */
	    public function save_answered_note_metabox( $post_id, $post, $update ) {
	        unset( $update ); // Unused, kept for hook signature clarity.

	        if ( empty( $post ) || ! isset( $post->post_type ) || 'prayer_request' !== $post->post_type ) {
	            return;
	        }

	        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	            return;
	        }

	        if ( ! current_user_can( 'edit_post', $post_id ) ) {
	            return;
	        }

	        if (
	            ! isset( $_POST['prayer_pop_answered_note_nonce'] ) ||
	            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['prayer_pop_answered_note_nonce'] ) ), 'prayer_pop_save_answered_note' )
	        ) {
	            return;
	        }

	        if ( 'answered' !== get_post_status( $post_id ) ) {
	            return;
	        }

	        if ( 'prayer_request' !== get_post_meta( $post_id, 'prayer_pop_type', true ) ) {
	            return;
	        }

	        $answered_message = isset( $_POST['prayer_pop_answered_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prayer_pop_answered_message'] ) ) : '';
	        $answered_message = trim( $answered_message );

	        if ( '' === $answered_message ) {
	            delete_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY );
	            return;
	        }

	        update_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY, $answered_message );
	    }

	/**
	 * Check whether current admin request is the single edit screen for prayer submissions.
	 *
	 * @param WP_Post|null $post Optional post object.
	 * @return bool
	 */
	private function is_prayer_submission_edit_screen( $post = null ) {
		global $pagenow;

		if ( 'post.php' !== $pagenow ) {
			return false;
		}

		if ( $post instanceof WP_Post ) {
			return 'prayer_request' === $post->post_type;
		}

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( $post_id <= 0 ) {
			return false;
		}

		return 'prayer_request' === get_post_type( $post_id );
	}

	/**
	 * Disable direct single-item edit screen for submissions.
	 *
	 * Submissions are managed inline in the list table.
	 *
	 * @return void
	 */
	public function block_submission_edit_screen_access() {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;
		if ( 'post.php' !== $pagenow ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( 'prayer_request' !== get_post_type( $post_id ) ) {
			return;
		}

		$redirect_to = add_query_arg(
			array(
				'post_type'        => 'prayer_request',
				'prayer_pop_notice' => 'edit_disabled',
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Add a dedicated back button on submission edit screen.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_submission_edit_back_button( $post ) {
		if ( ! $this->is_prayer_submission_edit_screen( $post ) ) {
			return;
		}

		echo '<p class="prayer-pop-edit-back-link"><a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request' ) ) . '" class="button button-secondary">← ' . esc_html__( 'Back to Submissions', 'prayerpop' ) . '</a></p>';
	}

	/**
	 * Keep main content editor and answered note editor aligned and simplified.
	 *
	 * @param array  $settings  Editor settings.
	 * @param string $editor_id Editor ID.
	 * @return array
	 */
	public function filter_submission_editor_settings( $settings, $editor_id ) {
		if ( ! $this->is_prayer_submission_edit_screen() || 'content' !== $editor_id ) {
			return $settings;
		}

		$settings['media_buttons'] = false;
		$settings['tinymce']       = false;
		$settings['quicktags']     = false;
		$settings['textarea_rows'] = 8;
		$settings['teeny']         = false;

		return $settings;
	}

	/**
	 * Prevent classic "Publish" button from forcing submissions into WP core status values.
	 * Keeps plugin workflow status unchanged on edit/save.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw posted data.
	 * @return array
	 */
	public function preserve_submission_status_on_save( $data, $postarr ) {
		if ( ! is_admin() ) {
			return $data;
		}

		if ( ! isset( $data['post_type'] ) || 'prayer_request' !== $data['post_type'] ) {
			return $data;
		}

		if ( ! isset( $postarr['ID'] ) || absint( $postarr['ID'] ) <= 0 ) {
			return $data;
		}

		if ( ! isset( $_POST['action'] ) || 'editpost' !== sanitize_key( wp_unslash( $_POST['action'] ) ) ) {
			return $data;
		}

		$post_id = absint( $postarr['ID'] );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $data;
		}

		$current_status = get_post_status( $post_id );
		if ( ! in_array( $current_status, array( 'pending', 'approved', 'answered', 'declined', 'archived' ), true ) ) {
			return $data;
		}

		if ( isset( $data['post_status'] ) && 'trash' === sanitize_key( (string) $data['post_status'] ) ) {
			return $data;
		}

		$data['post_status'] = $current_status;
		return $data;
	}

	/**
	 * Use plugin wording in save notice messages.
	 *
	 * @param array $messages Existing post updated messages.
	 * @return array
	 */
	public function filter_submission_updated_messages( $messages ) {
		if ( ! isset( $messages['prayer_request'] ) || ! is_array( $messages['prayer_request'] ) ) {
			return $messages;
		}

		$changes_saved = esc_html__( 'Changes saved.', 'prayerpop' );
		for ( $i = 1; $i <= 10; $i++ ) {
			$messages['prayer_request'][ $i ] = $changes_saved;
		}
		return $messages;
	}

	/**
	 * Hide Screen Options panel on submission edit view.
	 *
	 * @param bool      $show_screen Existing value.
	 * @param WP_Screen $screen      Current screen.
	 * @return bool
	 */
	public function maybe_hide_submission_screen_options( $show_screen, $screen ) {
		if ( $screen instanceof WP_Screen && 'prayer_request' === (string) $screen->post_type && 'post' === (string) $screen->base ) {
			return false;
		}

		return $show_screen;
	}

	/**
	 * Force two-column edit layout for prayer submission screen.
	 *
	 * @param mixed $layout Current stored layout option.
	 * @return int
	 */
	public function force_submission_screen_layout( $layout ) {
		unset( $layout );
		return 2;
	}

	/**
	 * Mark bubble module as rendered for current request.
	 *
	 * @return void
	 */
	public static function mark_module_rendered() {
		self::$module_rendered = true;
	}

	/**
	 * Check whether bubble module has already been rendered.
	 *
	 * @return bool
	 */
	public static function has_module_rendered() {
		return self::$module_rendered;
	}

    /**
     * Output custom styles
     */
	    public function output_custom_styles() {
		if ( ! wp_style_is( 'prayer-pop-style', 'enqueued' ) ) {
			return;
		}

		static $styles_printed = false;
		if ( $styles_printed ) {
			return;
		}
		$styles_printed = true;

		$options      = Prayer_Pop_Defaults::get_styles();
		$declarations = array();

		if ( is_array( $options ) ) {
			foreach ( $options as $key => $value ) {
				$value = $this->sanitize_css_custom_property_value( $key, $value );
				if ( '' === $value ) {
					continue;
				}

				$css_variable  = '--' . str_replace( '_', '-', sanitize_key( $key ) );
				$declarations[] = $css_variable . ':' . $value;
			}
		}

		$bubble_size = isset( $options['bubble_size'] ) ? max( 50, min( 150, absint( $options['bubble_size'] ) ) ) : 100;
		$declarations[] = '--prayerpop-bubble-scale:' . number_format( $bubble_size / 100, 2, '.', '' );
		$declarations[] = '--prayerpop-bubble-hover-scale:' . number_format( ( $bubble_size / 100 ) * 1.03, 4, '.', '' );

		$declarations_safe = safecss_filter_attr( implode( ';', $declarations ) );
		$custom_css_safe   = ':root{' . $declarations_safe . '}';

		if ( ':root{}' !== $custom_css_safe ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are type-validated above and the completed declaration list is filtered by safecss_filter_attr().
			wp_add_inline_style( 'prayer-pop-style', $custom_css_safe );
		}
    }

    /**
     * Display the prayerpop on the front-end
     */
    public function display_prayer_box() {
		static $bubble_printed = false;
		if ( $bubble_printed ) {
			return;
		}

		$force_render = defined( 'PRAYERPOP_FORCE_BUBBLE' ) && PRAYERPOP_FORCE_BUBBLE;
		$settings     = Prayer_Pop_Defaults::get_settings();
		$show_bubble  = isset( $settings['show_prayer_pop_bubble'] ) ? (bool) $settings['show_prayer_pop_bubble'] : true;
		if ( ! $show_bubble && ! $force_render ) {
			return;
		}

		// Avoid duplicate markup if module already rendered on the same page.
		if ( self::has_module_rendered() && ! $force_render ) {
			return;
		}

		$bubble_printed = true;
		self::mark_module_rendered();

        // Get the selected animation from styles using cache
        $styles = Prayer_Pop_Defaults::get_styles();
        $selected_animation = isset( $styles['bubble_animation'] ) ? $styles['bubble_animation'] : 'fade-in';
        if ( ! in_array( $selected_animation, array( 'none', 'fade-in', 'slide-up', 'bounce-in' ), true ) ) {
            $selected_animation = 'fade-in';
        }

        // Pass the animation to the template
        include PRAYERPOP_PLUGIN_DIR . 'templates/prayer-pop-front-end.php';
    }


    

    /**
     * Register custom post type for prayer requests
     */
    public function register_prayer_request_cpt() {
        // Define the main menu slug
        $menu_slug = 'prayer-pop';

        $labels = array(
            'name'               => _x( 'Submissions', 'post type general name', 'prayerpop' ),
            'singular_name'      => _x( 'Submission', 'post type singular name', 'prayerpop' ),
            'menu_name'          => _x( 'Submissions', 'admin menu', 'prayerpop' ),
            'name_admin_bar'     => _x( 'Submission', 'add new on admin bar', 'prayerpop' ),
            'add_new'            => _x( 'Add New', 'pray request', 'prayerpop' ),
            'add_new_item'       => __( 'Add New Submission', 'prayerpop' ),
            'new_item'           => __( 'New Submission', 'prayerpop' ),
            'edit_item'          => __( 'Edit Submission', 'prayerpop' ),
            'view_item'          => __( 'View Submission', 'prayerpop' ),
            'all_items'          => __( 'Submissions', 'prayerpop' ),
            'search_items'       => __( 'Search Submissions', 'prayerpop' ),
            'parent_item_colon'  => __( 'Parent Submissions:', 'prayerpop' ),
            'not_found'          => __( 'No submissions found.', 'prayerpop' ),
            'not_found_in_trash' => __( 'No submissions found in Trash.', 'prayerpop' )
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => $menu_slug, // Use main menu slug here
            'menu_icon'          => 'dashicons-heart',
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'prayer_request' ),
            'capability_type'    => 'post',
            'capabilities'       => array(
                'create_posts' => 'do_not_allow', // Disable "Add New" button
            ),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array( 'title', 'editor' ),
        );

        register_post_type( 'prayer_request', $args );
    }

    /**
     * Register custom post statuses
     */
    public function register_custom_statuses() {
        foreach ( $this->custom_statuses as $status => $label ) {
            register_post_status( $status, array(
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => $this->get_status_label_count_noop( $status ),
            ) );
        }
    }

    /**
     * Return a translatable status label count pair for register_post_status().
     *
     * @param string $status Status key.
     * @return array{0:string,1:string,2:?string}
     */
    private function get_status_label_count_noop( $status ) {
        switch ( $status ) {
            case 'approved':
                /* translators: %s: Number of submissions in this status. */
                return _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'prayerpop' );
            case 'answered':
                /* translators: %s: Number of submissions in this status. */
                return _n_noop( 'Answered <span class="count">(%s)</span>', 'Answered <span class="count">(%s)</span>', 'prayerpop' );
            case 'declined':
                /* translators: %s: Number of submissions in this status. */
                return _n_noop( 'Declined <span class="count">(%s)</span>', 'Declined <span class="count">(%s)</span>', 'prayerpop' );
            case 'archived':
                /* translators: %s: Number of submissions in this status. */
                return _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'prayerpop' );
            default:
                /* translators: %s: Number of submissions in this status. */
                return _n_noop( 'Submissions <span class="count">(%s)</span>', 'Submissions <span class="count">(%s)</span>', 'prayerpop' );
        }
    }
    /**
     * One-time migration from legacy "viewed" status to private-reviewed meta.
     *
     * @return void
     */
    public function maybe_migrate_viewed_status() {
        if ( ! $this->should_run_legacy_status_migration() ) {
            return;
        }

        if ( get_option( 'prayer_pop_migrated_viewed_status', false ) ) {
            return;
        }

		$batch_size  = $this->get_legacy_migration_batch_size();
		$viewed_posts = get_posts(
			array(
				'post_type'              => 'prayer_request',
				'post_status'            => 'viewed',
				'fields'                 => 'ids',
				'posts_per_page'         => $batch_size,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $viewed_posts ) ) {
			update_option( 'prayer_pop_migrated_viewed_status', 1, false );
			return;
		}

		foreach ( $viewed_posts as $post_id ) {
			$is_public = get_post_meta( $post_id, 'prayer_pop_public', true );
			if ( '1' === $is_public ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'approved',
					)
				);
				delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
			} else {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'pending',
					)
				);
				update_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY, '1' );
			}
		}

		if ( count( $viewed_posts ) < $batch_size ) {
			$remaining_ids = get_posts(
				array(
					'post_type'              => 'prayer_request',
					'post_status'            => 'viewed',
					'fields'                 => 'ids',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( empty( $remaining_ids ) ) {
				update_option( 'prayer_pop_migrated_viewed_status', 1, false );
			}
		}
    }

    /**
     * One-time migration from legacy core "publish" status to "approved".
     *
     * @return void
     */
    public function maybe_migrate_publish_status() {
        if ( ! $this->should_run_legacy_status_migration() ) {
            return;
        }

        if ( get_option( 'prayer_pop_migrated_publish_status', false ) ) {
            return;
        }

		$batch_size   = $this->get_legacy_migration_batch_size();
		$publish_posts = get_posts(
			array(
				'post_type'              => 'prayer_request',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $batch_size,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $publish_posts ) ) {
			update_option( 'prayer_pop_migrated_publish_status', 1, false );
			return;
		}

		foreach ( $publish_posts as $post_id ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'approved',
				)
			);
		}

		if ( count( $publish_posts ) < $batch_size ) {
			$remaining_ids = get_posts(
				array(
					'post_type'              => 'prayer_request',
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( empty( $remaining_ids ) ) {
				update_option( 'prayer_pop_migrated_publish_status', 1, false );
			}
		}
    }

	/**
	 * Resolve a safe batch size for legacy status migrations.
	 *
	 * @return int
	 */
	private function get_legacy_migration_batch_size() {
		$batch_size = absint( apply_filters( 'prayer_pop_legacy_migration_batch_size', self::LEGACY_MIGRATION_BATCH_SIZE ) );
		return $batch_size > 0 ? $batch_size : self::LEGACY_MIGRATION_BATCH_SIZE;
	}

	/**
	 * Decide whether legacy migrations should run on this admin request.
	 *
	 * @return bool
	 */
	private function should_run_legacy_status_migration() {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( ! self::current_user_can_manage_submissions() ) {
			return false;
		}

		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';
		if ( 'prayer_request' === $post_type ) {
			return true;
		}

		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		return in_array( $page, array( 'prayer-pop', 'prayer-pop-settings' ), true );
	}

    /**
     * Customize admin columns.
     *
     * @deprecated 1.5.0 Use PrayerPop_Admin_List_Table instead.
     * @param array $columns Existing columns.
     * @return array Columns unchanged.
     */
    public function set_custom_columns( $columns ) {
        // Deprecated: Column management moved to PrayerPop_Admin_List_Table.
        // Kept for backward compatibility with potential extensions.
        return $columns;
    }

    /**
     * Display custom column content.
     *
     * @deprecated 1.5.0 Use PrayerPop_Admin_List_Table instead.
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function custom_column_content( $column, $post_id ) {
        // Deprecated: Column display moved to PrayerPop_Admin_List_Table.
        // Kept for backward compatibility with potential extensions.
    }

    /**
     * Make columns sortable.
     *
     * @deprecated 1.5.0 Use PrayerPop_Admin_List_Table instead.
     * @param array $columns Sortable columns.
     * @return array Columns unchanged.
     */
    public function sortable_columns( $columns ) {
        // Deprecated: Sortable columns handled in PrayerPop_Admin_List_Table.
        // Kept for backward compatibility with potential extensions.
        return $columns;
    }

    /**
     * Orderby columns.
     *
     * @deprecated 1.5.0 Use PrayerPop_Admin_List_Table instead.
     * @param WP_Query $query WordPress query object.
     */
    public function orderby_columns( $query ) {
        // Deprecated: Column ordering handled in PrayerPop_Admin_List_Table.
        // Kept for backward compatibility with potential extensions.
    }

    /**
     * Add custom bulk actions
     */
	public function bulk_actions( $bulk_actions ) {
	        if ( ! self::current_user_can_manage_submissions() ) {
	            return $bulk_actions;
	        }

		$ordered_actions = array(
			'send_via_email'   => __( 'Send via Email', 'prayerpop' ),
			'approve_selected' => __( 'Approve selected', 'prayerpop' ),
			'decline_selected' => __( 'Decline selected', 'prayerpop' ),
			'mark_as_answered' => __( 'Mark prayer as answered', 'prayerpop' ),
			'bulk_edit'        => __( 'Edit selected', 'prayerpop' ),
			'mark_as_archived' => __( 'Archive', 'prayerpop' ),
		);

	        // Keep default actions at the end in a predictable order.
	        if ( isset( $bulk_actions['trash'] ) ) {
	            $ordered_actions['trash'] = $bulk_actions['trash'];
	        }

	        // Preserve any other actions that may be added by WordPress/plugins.
	        foreach ( $bulk_actions as $action_key => $action_label ) {
		            if ( in_array( $action_key, array( 'edit', 'print_submissions' ), true ) ) {
		                continue;
		            }
	            if ( ! isset( $ordered_actions[ $action_key ] ) ) {
	                $ordered_actions[ $action_key ] = $action_label;
	            }
	        }

        return $ordered_actions;
    }

	/**
	 * Query args that should be stripped so notices do not stack between actions.
	 *
	 * @return array<int, string>
	 */
	private function get_submission_notice_query_args() {
		return array(
			'_wpnonce',
			'action',
			'action2',
			'post',
			'ids',
			'locked',
			'trashed',
			'untrashed',
			'deleted',
			'message',
			'updated',
			'bulk_approved',
			'bulk_declined',
			'bulk_answered',
			'bulk_marked_archived',
			'bulk_sent_via_email',
			'bulk_edited',
			'prayer_pop_notice',
			'redirect_to',
			'answered_message',
			'prayer_pop_bulk_answered_message',
			'prayer_pop_bulk_answered_messages',
			'prayer_pop_bulk_edit_payload',
		);
	}

	/**
	 * Read current submissions-table filter context from request.
	 *
	 * Used as a fallback for action redirects when WordPress supplies a base redirect URL.
	 *
	 * @return array<string, string|int>
	 */
	private function get_submission_redirect_context_args() {
		$context = array(
			'post_type' => 'prayer_request',
		);

		$allowed_statuses = array( 'pending', 'approved', 'answered', 'declined', 'archived', 'trash' );
		$key_filters      = array(
			'post_status',
			'prayer_request_status',
			'prayer_pop_visibility',
			'prayer_pop_type',
			'prayer_pop_stage_ready',
			'orderby',
			'order',
		);

		foreach ( $key_filters as $key ) {
			if ( ! isset( $_REQUEST[ $key ] ) ) {
				continue;
			}

			$value = sanitize_key( wp_unslash( $_REQUEST[ $key ] ) );
			if ( '' === $value ) {
				continue;
			}

			if ( in_array( $key, array( 'post_status', 'prayer_request_status' ), true ) && ! in_array( $value, $allowed_statuses, true ) ) {
				continue;
			}

			$context[ $key ] = $value;
		}

		$int_filters = array( 'm', 'paged' );
		foreach ( $int_filters as $key ) {
			if ( ! isset( $_REQUEST[ $key ] ) ) {
				continue;
			}
			$value = absint( wp_unslash( $_REQUEST[ $key ] ) );
			if ( $value > 0 ) {
				$context[ $key ] = $value;
			}
		}

		if ( isset( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			if ( '' !== $search ) {
				$context['s'] = $search;
			}
		}

		return $context;
	}

	/**
	 * Return submissions-list redirect URL with stale notice/action args removed.
	 *
	 * @param string $url Source URL.
	 * @return string
	 */
	private function clean_submission_redirect_url( $url = '' ) {
		$url = (string) $url;
		$default_url = admin_url( 'edit.php?post_type=prayer_request' );

		if ( '' === $url && isset( $_REQUEST['redirect_to'] ) ) {
			$url = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
		}
		if ( '' === $url ) {
			$url = (string) wp_get_referer();
		}
		if ( '' === $url ) {
			$url = $default_url;
		}
		$url = wp_validate_redirect( $url, $default_url );

		$parsed_url = wp_parse_url( $url );
		$path       = isset( $parsed_url['path'] ) ? (string) $parsed_url['path'] : '';
		$query_args = array();
		if ( ! empty( $parsed_url['query'] ) ) {
			parse_str( (string) $parsed_url['query'], $query_args );
		}

		$request_context = $this->get_submission_redirect_context_args();
		if ( ! empty( $request_context ) ) {
			// Keep explicit redirect_to query args when present, but backfill missing filter context from request.
			$query_args = array_merge( $request_context, $query_args );
		}

		$current_post_type = isset( $query_args['post_type'] ) ? sanitize_key( (string) $query_args['post_type'] ) : '';
		if ( '' !== $current_post_type && 'prayer_request' !== $current_post_type ) {
			$url = $default_url;
		} elseif ( false !== strpos( $path, '/edit.php' ) || 'edit.php' === basename( $path ) ) {
			if ( '' === $current_post_type ) {
				$query_args['post_type'] = 'prayer_request';
			}
			$url = add_query_arg( $query_args, admin_url( 'edit.php' ) );
		} elseif ( '' === $current_post_type ) {
			$url = add_query_arg( $query_args, admin_url( 'edit.php' ) );
		}

		return remove_query_arg( $this->get_submission_notice_query_args(), $url );
	}

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
	        $redirect_to = $this->clean_submission_redirect_url( $redirect_to );
	        $doaction = sanitize_key( (string) $doaction );
		$privileged_actions = array( 'approve_selected', 'decline_selected', 'mark_as_archived', 'mark_as_answered', 'bulk_edit', 'send_via_email' );
	        if ( in_array( $doaction, $privileged_actions, true ) && ! self::current_user_can_manage_submissions() ) {
	            return $redirect_to;
	        }
		if ( in_array( $doaction, $privileged_actions, true ) ) {
			$bulk_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $bulk_nonce, 'bulk-posts' ) ) {
				return add_query_arg( 'prayer_pop_notice', 'security_failed', $redirect_to );
			}
		}
	        $post_ids = $this->get_authorized_bulk_post_ids( $post_ids );

	        if ( $doaction === 'approve_selected' ) {
	            $approved_count = 0;
	            foreach ( $post_ids as $post_id ) {
	                if ( ! $this->is_public_submission( $post_id ) ) {
	                    continue;
	                }

	                wp_update_post(
	                    array(
	                        'ID'          => $post_id,
	                        'post_status' => 'approved',
	                    )
	                );
	                delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
	                delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
	                delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
	                delete_post_meta( $post_id, self::ANSWERED_AT_META_KEY );
	                delete_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY );
	                $approved_count++;
	            }
	            $redirect_to = add_query_arg( 'bulk_approved', $approved_count, $redirect_to );
	            return $redirect_to;
	        }

	        if ( $doaction === 'decline_selected' ) {
	            $declined_count = 0;
	            foreach ( $post_ids as $post_id ) {
	                if ( ! $this->is_public_submission( $post_id ) ) {
	                    continue;
	                }

	                wp_update_post(
	                    array(
	                        'ID'          => $post_id,
	                        'post_status' => 'declined',
	                    )
	                );
	                delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
	                delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
	                delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
	                delete_post_meta( $post_id, self::ANSWERED_AT_META_KEY );
	                delete_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY );
	                $declined_count++;
	            }
	            $redirect_to = add_query_arg( 'bulk_declined', $declined_count, $redirect_to );
	            return $redirect_to;
	        }

	        if ( $doaction === 'mark_as_answered' ) {
	            $has_bulk_answered_message = isset( $_REQUEST['prayer_pop_bulk_answered_message'] );
	            $bulk_answered_message     = '';
	            if ( $has_bulk_answered_message ) {
	                $bulk_answered_message = sanitize_textarea_field( wp_unslash( $_REQUEST['prayer_pop_bulk_answered_message'] ) );
	                $bulk_answered_message = trim( $bulk_answered_message );
	            }
		            $bulk_answered_messages_map = array();
		            if ( isset( $_REQUEST['prayer_pop_bulk_answered_messages'] ) ) {
		                $raw_answered_messages = sanitize_textarea_field( wp_unslash( $_REQUEST['prayer_pop_bulk_answered_messages'] ) );
		                $decoded_map           = json_decode( $raw_answered_messages, true );
		                if ( is_array( $decoded_map ) ) {
	                    foreach ( $decoded_map as $raw_post_id => $raw_message ) {
	                        $map_post_id = absint( $raw_post_id );
	                        if ( $map_post_id <= 0 ) {
	                            continue;
	                        }
	                        $map_message = sanitize_textarea_field( (string) $raw_message );
	                        $map_message = trim( $map_message );
	                        if ( '' === $map_message ) {
	                            continue;
	                        }
	                        $bulk_answered_messages_map[ $map_post_id ] = $map_message;
	                    }
	                }
	            }

	            $answered_count = 0;
	            foreach ( $post_ids as $post_id ) {
	                if ( ! $this->is_public_submission( $post_id ) ) {
	                    continue;
	                }

	                if ( 'prayer_request' !== get_post_meta( $post_id, 'prayer_pop_type', true ) ) {
	                    continue;
	                }

	                if ( 'approved' !== get_post_status( $post_id ) ) {
	                    continue;
	                }

	                wp_update_post(
	                    array(
	                        'ID'          => $post_id,
	                        'post_status' => 'answered',
	                    )
	                );
	                update_post_meta( $post_id, self::ANSWERED_AT_META_KEY, current_time( 'timestamp' ) );
	                if ( isset( $bulk_answered_messages_map[ $post_id ] ) ) {
	                    update_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY, $bulk_answered_messages_map[ $post_id ] );
	                } elseif ( $has_bulk_answered_message && '' !== $bulk_answered_message ) {
	                    update_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY, $bulk_answered_message );
	                }
	                delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
	                delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
	                delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
	                $answered_count++;
	            }
	            $redirect_to = add_query_arg( 'bulk_answered', $answered_count, $redirect_to );
	            return $redirect_to;
	        }

		        if ( 'bulk_edit' === $doaction ) {
		            $bulk_edit_payload = array();
		            if ( isset( $_REQUEST['prayer_pop_bulk_edit_payload'] ) ) {
		                $raw_bulk_edit_payload = sanitize_textarea_field( wp_unslash( $_REQUEST['prayer_pop_bulk_edit_payload'] ) );
		                $decoded_payload       = json_decode( $raw_bulk_edit_payload, true );
		                if ( is_array( $decoded_payload ) ) {
	                    $bulk_edit_payload = $decoded_payload;
	                }
	            }

	            $updated_count = 0;
	            foreach ( $post_ids as $post_id ) {
	                if ( ! isset( $bulk_edit_payload[ $post_id ] ) || ! is_array( $bulk_edit_payload[ $post_id ] ) ) {
	                    continue;
	                }

	                $payload_row = $bulk_edit_payload[ $post_id ];
	                $name_input  = isset( $payload_row['name'] ) ? trim( sanitize_text_field( (string) $payload_row['name'] ) ) : '';
	                $text_input  = isset( $payload_row['submission'] ) ? trim( sanitize_textarea_field( (string) $payload_row['submission'] ) ) : '';

	                $is_anonymous = ( '' === $name_input );
	                if ( $is_anonymous ) {
	                    update_post_meta( $post_id, 'prayer_pop_name', \Prayer_Pop_Defaults::ANONYMOUS_NAME_MARKER );
	                    update_post_meta( $post_id, \Prayer_Pop_Defaults::ANONYMOUS_FLAG_META_KEY, '1' );
	                    $title_value = \Prayer_Pop_Defaults::get_anonymous_text();
	                } else {
	                    update_post_meta( $post_id, 'prayer_pop_name', $name_input );
	                    update_post_meta( $post_id, \Prayer_Pop_Defaults::ANONYMOUS_FLAG_META_KEY, '0' );
	                    $title_value = $name_input;
	                }

	                $post_update = array(
	                    'ID'         => $post_id,
	                    'post_title' => $title_value,
	                );
	                if ( '' !== $text_input ) {
	                    $post_update['post_content'] = $text_input;
	                }

	                wp_update_post( $post_update );
	                $updated_count++;
	            }

	            $redirect_to = add_query_arg( 'bulk_edited', $updated_count, $redirect_to );
	            return $redirect_to;
	        }

        if ( $doaction === 'mark_as_archived' ) {
            $archived_count = 0;
            foreach ( $post_ids as $post_id ) {
                if ( $this->archive_submission( $post_id ) ) {
                    $archived_count++;
                }
            }
            $redirect_to = add_query_arg( 'bulk_marked_archived', $archived_count, $redirect_to );
            return $redirect_to;
        }

        if ( $doaction === 'send_via_email' ) {
            $recipient_email = isset( $_REQUEST['prayer_pop_bulk_email'] ) ? sanitize_email( wp_unslash( $_REQUEST['prayer_pop_bulk_email'] ) ) : '';

            if ( ! is_email( $recipient_email ) ) {
                $redirect_to = add_query_arg( 'prayer_pop_notice', 'bulk_email_invalid', $redirect_to );
                return $redirect_to;
            }

            if ( empty( $post_ids ) ) {
                $redirect_to = add_query_arg( 'prayer_pop_notice', 'bulk_email_none', $redirect_to );
                return $redirect_to;
            }

            $count = $this->send_submissions_via_email( $post_ids, $recipient_email );
            if ( $count > 0 ) {
                $redirect_to = add_query_arg( 'bulk_sent_via_email', $count, $redirect_to );
            } else {
                $redirect_to = add_query_arg( 'prayer_pop_notice', 'bulk_email_failed', $redirect_to );
            }
            return $redirect_to;
        }

        return $redirect_to;
    }

    /**
     * Send submissions via email
     */
    private function send_submissions_via_email( $post_ids, $recipient_email = '' ) {
        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            return 0;
        }

        $recipient_email = sanitize_email( (string) $recipient_email );

        // Backward-compatible fallback if no recipient was provided.
        if ( ! is_email( $recipient_email ) ) {
            $current_user = wp_get_current_user();
            $recipient_email = isset( $current_user->user_email ) ? sanitize_email( $current_user->user_email ) : '';
        }

        if ( ! is_email( $recipient_email ) ) {
            return 0;
        }

        // Get email template
        $email_template = get_option( 'prayer_pop_email_template', array() );
        $subject        = isset( $email_template['email_subject'] ) && ! empty( $email_template['email_subject'] ) ? $email_template['email_subject'] : __( 'New PrayerPop Submission', 'prayerpop' );
        $body_template  = isset( $email_template['email_body'] ) && ! empty( $email_template['email_body'] ) ? $email_template['email_body'] : __( "Type: {type}\nName: {name}\nMessage:\n{message}", "prayerpop" );

        $message = '';

        $processed_count = 0;

        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || 'prayer_request' !== $post->post_type ) {
                continue;
            }

            $type  = sanitize_key( (string) get_post_meta( $post_id, 'prayer_pop_type', true ) );
            $name  = sanitize_text_field( \Prayer_Pop_Defaults::get_submission_display_name( $post_id ) );
            $label = __( 'Prayer Request', 'prayerpop' );

            // Prepare placeholders
            $placeholders = array(
                '{type}'    => $label,
                '{name}'    => $name,
                '{message}' => wp_strip_all_tags( (string) $post->post_content ),
            );

            // Replace placeholders in body template
            $body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body_template );

            $message .= $body . "\n\n----------------------------------------\n\n";
            $processed_count++;
        }

        if ( 0 === $processed_count ) {
            return 0;
        }

        $mail_sent = wp_mail( $recipient_email, $subject, $message );

        return $mail_sent ? $processed_count : 0;
    }

    /**
     * Approve a single submission
     */
	    public function admin_post_prayer_pop_approve() {
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
        }

        if ( ! self::current_user_can_manage_submissions() ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! $this->is_public_submission( $post_id ) ) {
            $redirect_to = $this->clean_submission_redirect_url();
            $redirect_to = add_query_arg( 'prayer_pop_notice', 'private_no_approval', $redirect_to );
            wp_safe_redirect( $redirect_to );
            exit;
        }

	        wp_update_post(
	            array(
	                'ID'          => $post_id,
	                'post_status' => 'approved',
	            )
	        );
	        delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
	        delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
	        delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
	        delete_post_meta( $post_id, self::ANSWERED_AT_META_KEY );
	        delete_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY );

	        $redirect_to = $this->clean_submission_redirect_url();
	        $redirect_to = add_query_arg( 'prayer_pop_notice', 'approved', $redirect_to );
	        wp_safe_redirect( $redirect_to );
	        exit;
	    }

    /**
     * Mark a private pending submission as reviewed.
     */
    public function admin_post_prayer_pop_mark_viewed() {
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
        }

        if ( ! self::current_user_can_manage_submissions() ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( 'pending' === get_post_status( $post_id ) ) {
            $is_public = get_post_meta( $post_id, 'prayer_pop_public', true );
            if ( '1' !== $is_public ) {
                update_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY, '1' );
            }
        }

        $redirect_to = $this->clean_submission_redirect_url();
        $redirect_to = add_query_arg( 'prayer_pop_notice', 'reviewed', $redirect_to );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    /**
     * Decline a single submission
     */
	    public function admin_post_prayer_pop_decline() {
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
        }

        if ( ! self::current_user_can_manage_submissions() ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! $this->is_public_submission( $post_id ) ) {
            $redirect_to = $this->clean_submission_redirect_url();
            $redirect_to = add_query_arg( 'prayer_pop_notice', 'private_no_approval', $redirect_to );
            wp_safe_redirect( $redirect_to );
            exit;
        }

	        wp_update_post(
	            array(
	                'ID'          => $post_id,
	                'post_status' => 'declined',
	            )
	        );
	        delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
	        delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
	        delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
	        delete_post_meta( $post_id, self::ANSWERED_AT_META_KEY );
	        delete_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY );

	        $redirect_to = $this->clean_submission_redirect_url();
	        $redirect_to = add_query_arg( 'prayer_pop_notice', 'declined', $redirect_to );
	        wp_safe_redirect( $redirect_to );
	        exit;
	    }

	    /**
	     * Mark an approved public prayer request as answered.
	     */
	    public function admin_post_prayer_pop_mark_answered() {
	        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	        $post_id = isset( $_REQUEST['post'] ) ? absint( wp_unslash( $_REQUEST['post'] ) ) : 0;
	        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
	            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
	        }

	        if ( ! self::current_user_can_manage_submissions() ) {
	            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
	        }

	        if ( ! current_user_can( 'edit_post', $post_id ) ) {
	            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
	        }

	        if ( ! $this->is_public_submission( $post_id ) || 'prayer_request' !== get_post_meta( $post_id, 'prayer_pop_type', true ) ) {
	            $redirect_to = $this->clean_submission_redirect_url();
	            $redirect_to = add_query_arg( 'prayer_pop_notice', 'answered_invalid', $redirect_to );
	            wp_safe_redirect( $redirect_to );
	            exit;
	        }

	        if ( 'approved' !== get_post_status( $post_id ) && 'answered' !== get_post_status( $post_id ) ) {
	            $redirect_to = $this->clean_submission_redirect_url();
	            $redirect_to = add_query_arg( 'prayer_pop_notice', 'answered_invalid', $redirect_to );
	            wp_safe_redirect( $redirect_to );
	            exit;
	        }

	        if ( isset( $_REQUEST['answered_message'] ) ) {
	            $answered_message = sanitize_textarea_field( wp_unslash( $_REQUEST['answered_message'] ) );
	            $answered_message = trim( $answered_message );
	            if ( '' === $answered_message ) {
	                delete_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY );
	            } else {
	                update_post_meta( $post_id, self::ANSWERED_MESSAGE_META_KEY, $answered_message );
	            }
	        }

	        wp_update_post(
	            array(
	                'ID'          => $post_id,
	                'post_status' => 'answered',
	            )
	        );
	        update_post_meta( $post_id, self::ANSWERED_AT_META_KEY, current_time( 'timestamp' ) );
	        delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
	        delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
	        delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );

	        $redirect_to = $this->clean_submission_redirect_url();
	        $redirect_to = add_query_arg( 'prayer_pop_notice', 'answered', $redirect_to );
	        wp_safe_redirect( $redirect_to );
	        exit;
	    }

	    /**
	     * Move an answered public prayer request back to approved.
	     */
	    public function admin_post_prayer_pop_mark_unanswered() {
	        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	        $post_id = isset( $_REQUEST['post'] ) ? absint( wp_unslash( $_REQUEST['post'] ) ) : 0;
	        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
	            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
	        }

	        if ( ! self::current_user_can_manage_submissions() ) {
	            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
	        }

	        if ( ! current_user_can( 'edit_post', $post_id ) ) {
	            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
	        }

	        if (
	            $this->is_public_submission( $post_id ) &&
	            'prayer_request' === get_post_meta( $post_id, 'prayer_pop_type', true ) &&
	            'answered' === get_post_status( $post_id )
	        ) {
	            wp_update_post(
	                array(
	                    'ID'          => $post_id,
	                    'post_status' => 'approved',
	                )
	            );
	            delete_post_meta( $post_id, self::ANSWERED_AT_META_KEY );
	        }

	        $redirect_to = $this->clean_submission_redirect_url();
	        $redirect_to = add_query_arg( 'prayer_pop_notice', 'unanswered', $redirect_to );
	        wp_safe_redirect( $redirect_to );
	        exit;
	    }

	/**
	 * Move a single submission to Trash from actions column.
	 *
	 * @return void
	 */
	public function admin_post_prayer_pop_trash() {
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
		}

		if ( ! self::current_user_can_manage_submissions() || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
		}

		if ( 'prayer_request' !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Invalid submission type.', 'prayerpop' ) );
		}

		wp_trash_post( $post_id );

		$redirect_to = $this->clean_submission_redirect_url();
		$redirect_to = add_query_arg( 'prayer_pop_notice', 'trashed_single', $redirect_to );
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Restore a single trashed submission from actions column.
	 *
	 * @return void
	 */
	public function admin_post_prayer_pop_restore() {
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
		}

		if ( ! self::current_user_can_manage_submissions() || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
		}

		if ( 'prayer_request' !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Invalid submission type.', 'prayerpop' ) );
		}

		$previous_status = sanitize_key( (string) get_post_meta( $post_id, '_wp_trash_meta_status', true ) );
		$is_public       = '1' === (string) get_post_meta( $post_id, 'prayer_pop_public', true );
		wp_untrash_post( $post_id );
		$current_status = sanitize_key( (string) get_post_status( $post_id ) );
		$allowed_statuses = array( 'pending', 'approved', 'answered', 'declined', 'archived' );

		$normalize_status = static function( $status ) use ( $allowed_statuses, $is_public ) {
			$status = sanitize_key( (string) $status );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				return $status;
			}
			if ( 'publish' === $status ) {
				return 'approved';
			}
			if ( 'viewed' === $status ) {
				return $is_public ? 'approved' : 'pending';
			}
			if ( in_array( $status, array( 'draft', 'auto-draft', 'future', 'private', 'inherit', 'new', 'trash' ), true ) ) {
				return 'pending';
			}
			return '';
		};

		$target_status = $normalize_status( $current_status );
		if ( '' === $target_status ) {
			$target_status = $normalize_status( $previous_status );
		}
		if ( '' === $target_status ) {
			$target_status = 'pending';
		}

		if ( $target_status !== $current_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $target_status,
				)
			);
		}

		if ( 'viewed' === $previous_status && ! $is_public ) {
			update_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY, '1' );
		} elseif ( $is_public ) {
			delete_post_meta( $post_id, self::PRIVATE_REVIEWED_META_KEY );
		}

		$redirect_to = $this->clean_submission_redirect_url();
		$redirect_to = add_query_arg( 'prayer_pop_notice', 'restored_single', $redirect_to );
		wp_safe_redirect( $redirect_to );
		exit;
	}

    /**
     * Archive a single submission.
     */
    public function admin_post_prayer_pop_archive() {
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
        }

        if ( ! self::current_user_can_manage_submissions() ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        $this->archive_submission( $post_id );

        $redirect_to = $this->clean_submission_redirect_url();
        $redirect_to = add_query_arg( 'prayer_pop_notice', 'archived', $redirect_to );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    /**
     * Unarchive a single submission and restore its previous status.
     */
    public function admin_post_prayer_pop_unarchive() {
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
        if ( empty( $post_id ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'prayer_pop_manage_request' ) ) {
            wp_die( esc_html__( 'Invalid request.', 'prayerpop' ) );
        }

        if ( ! self::current_user_can_manage_submissions() ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'prayerpop' ) );
        }

        if ( 'archived' === get_post_status( $post_id ) ) {
            $is_public = get_post_meta( $post_id, 'prayer_pop_public', true );
            $fallback_status = ( '1' === $is_public ) ? 'approved' : 'pending';
	            $previous_status = sanitize_key( (string) get_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY, true ) );
	            $allowed_statuses = array( 'pending', 'approved', 'answered', 'declined' );
            $restore_status = in_array( $previous_status, $allowed_statuses, true ) ? $previous_status : $fallback_status;

            wp_update_post(
                array(
                    'ID'          => $post_id,
                    'post_status' => $restore_status,
                )
            );
            delete_post_meta( $post_id, self::ARCHIVED_AT_META_KEY );
            delete_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY );
        }

        $redirect_to = $this->clean_submission_redirect_url();
        $redirect_to = add_query_arg( 'prayer_pop_notice', 'unarchived', $redirect_to );
        wp_safe_redirect( $redirect_to );
        exit;
    }

	    

	    /**
	     * Display admin notices
     */
	    public function admin_notices() {
	        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	        if ( ! $screen ) {
	            return;
	        }

	        $screen_id = isset( $screen->id ) ? (string) $screen->id : '';
	        $screen_post_type = isset( $screen->post_type ) ? (string) $screen->post_type : '';
	        $is_prayer_screen = ( 'prayer_request' === $screen_post_type ) || ( false !== strpos( $screen_id, 'prayer_request' ) );
	        $is_submissions_list_screen = ( 'edit-prayer_request' === $screen_id );
	        if ( ! $is_prayer_screen ) {
	            return;
	        }

		$action_notice_class = '';
		$action_notice_text  = '';

		if ( ! empty( $_REQUEST['bulk_marked_archived'] ) ) {
			$count               = absint( wp_unslash( $_REQUEST['bulk_marked_archived'] ) );
			$action_notice_class = 'notice-success';
			/* translators: %s: number of archived submissions. */
				$action_notice_text  = sprintf( esc_html( _n( '%s submission archived.', '%s submissions archived.', $count, 'prayerpop' ) ), number_format_i18n( $count ) );
		} elseif ( ! empty( $_REQUEST['bulk_sent_via_email'] ) ) {
			$count               = absint( wp_unslash( $_REQUEST['bulk_sent_via_email'] ) );
			$action_notice_class = 'notice-success';
			/* translators: %s: number of submissions sent by email. */
				$action_notice_text  = sprintf( esc_html( _n( '%s submission sent via email.', '%s submissions sent via email.', $count, 'prayerpop' ) ), number_format_i18n( $count ) );
		} elseif ( ! empty( $_REQUEST['bulk_approved'] ) ) {
			$count               = absint( wp_unslash( $_REQUEST['bulk_approved'] ) );
			$action_notice_class = 'notice-success';
			/* translators: %s: number of approved submissions. */
				$action_notice_text  = sprintf( esc_html( _n( '%s submission approved.', '%s submissions approved.', $count, 'prayerpop' ) ), number_format_i18n( $count ) );
		} elseif ( ! empty( $_REQUEST['bulk_declined'] ) ) {
			$count               = absint( wp_unslash( $_REQUEST['bulk_declined'] ) );
			$action_notice_class = 'notice-success';
			/* translators: %s: number of declined submissions. */
				$action_notice_text  = sprintf( esc_html( _n( '%s submission declined.', '%s submissions declined.', $count, 'prayerpop' ) ), number_format_i18n( $count ) );
		} elseif ( ! empty( $_REQUEST['bulk_answered'] ) ) {
			$count               = absint( wp_unslash( $_REQUEST['bulk_answered'] ) );
			$action_notice_class = 'notice-success';
			/* translators: %s: number of prayers marked as answered. */
				$action_notice_text  = sprintf( esc_html( _n( '%s prayer marked as answered.', '%s prayers marked as answered.', $count, 'prayerpop' ) ), number_format_i18n( $count ) );
		} elseif ( ! empty( $_REQUEST['bulk_edited'] ) ) {
			$count               = absint( wp_unslash( $_REQUEST['bulk_edited'] ) );
			$action_notice_class = 'notice-success';
			/* translators: %s: number of updated submissions. */
				$action_notice_text  = sprintf( esc_html( _n( '%s submission updated.', '%s submissions updated.', $count, 'prayerpop' ) ), number_format_i18n( $count ) );
		} else {
			$notice = isset( $_GET['prayer_pop_notice'] ) ? sanitize_key( wp_unslash( $_GET['prayer_pop_notice'] ) ) : '';
			if ( 'approved' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission approved.', 'prayerpop' );
			} elseif ( 'reviewed' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission marked as reviewed.', 'prayerpop' );
			} elseif ( 'declined' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission declined.', 'prayerpop' );
			} elseif ( 'answered' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Prayer marked as answered.', 'prayerpop' );
			} elseif ( 'unanswered' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Prayer moved back to approved.', 'prayerpop' );
			} elseif ( 'answered_invalid' === $notice ) {
				$action_notice_class = 'notice-warning';
				$action_notice_text  = esc_html__( 'Only approved public prayer requests can be marked as answered.', 'prayerpop' );
			} elseif ( 'archived' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission archived.', 'prayerpop' );
			} elseif ( 'unarchived' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission unarchived.', 'prayerpop' );
			} elseif ( 'private_no_approval' === $notice ) {
				$action_notice_class = 'notice-warning';
				$action_notice_text  = esc_html__( 'Private submissions cannot be approved or declined.', 'prayerpop' );
				} elseif ( 'bulk_email_invalid' === $notice ) {
				$action_notice_class = 'notice-error';
				$action_notice_text  = esc_html__( 'Please enter a valid recipient email before sending.', 'prayerpop' );
			} elseif ( 'bulk_email_none' === $notice ) {
				$action_notice_class = 'notice-warning';
				$action_notice_text  = esc_html__( 'No submissions were selected to send via email.', 'prayerpop' );
			} elseif ( 'bulk_email_failed' === $notice ) {
				$action_notice_class = 'notice-error';
				$action_notice_text  = esc_html__( 'Could not send submissions via email. Please try again.', 'prayerpop' );
			} elseif ( 'edit_disabled' === $notice ) {
				$action_notice_class = 'notice-info';
				$action_notice_text  = esc_html__( 'Submission edit page is disabled. Use inline editing directly in the Submissions table.', 'prayerpop' );
			} elseif ( 'action_unavailable' === $notice ) {
				$action_notice_class = 'notice-info';
				$action_notice_text  = esc_html__( 'This action is not available for the current submissions workflow.', 'prayerpop' );
			} elseif ( 'trashed_single' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission moved to Trash.', 'prayerpop' );
			} elseif ( 'restored_single' === $notice ) {
				$action_notice_class = 'notice-success';
				$action_notice_text  = esc_html__( 'Submission restored from Trash.', 'prayerpop' );
			}
		}

		if ( '' !== $action_notice_text ) {
			echo '<div class="notice ' . esc_attr( $action_notice_class ) . ' is-dismissible"><p>' . esc_html( $action_notice_text ) . '</p></div>';
		}
    }

    /**
     * Add filters to manage posts
     */
    public function restrict_manage_posts() {
        global $typenow;
        if ( $typenow == 'prayer_request' ) {
            $this->filter_by_status();
        }
    }

    /**
     * Add dropdown to filter by status
     */
    private function filter_by_status() {
        $selected = isset( $_GET['prayer_request_status'] ) ? sanitize_key( wp_unslash( $_GET['prayer_request_status'] ) ) : '';
        ?>
	        <select name="prayer_request_status">
	            <option value=""><?php esc_html_e( 'All Statuses', 'prayerpop' ); ?></option>
	            <option value="pending" <?php selected( $selected, 'pending' ); ?>><?php esc_html_e( 'Pending (Action/Review)', 'prayerpop' ); ?></option>
	            <option value="approved" <?php selected( $selected, 'approved' ); ?>><?php esc_html_e( 'Approved', 'prayerpop' ); ?></option>
	            <option value="answered" <?php selected( $selected, 'answered' ); ?>><?php esc_html_e( 'Answered', 'prayerpop' ); ?></option>
	            <option value="declined" <?php selected( $selected, 'declined' ); ?>><?php esc_html_e( 'Declined', 'prayerpop' ); ?></option>
	            <option value="archived" <?php selected( $selected, 'archived' ); ?>><?php esc_html_e( 'Archived', 'prayerpop' ); ?></option>
	        </select>
        <?php
    }

    /**
     * Filter posts by status on the admin screen
     */
    public function filter_posts_by_status( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'prayer_request' !== $query->get( 'post_type' ) ) {
            return;
        }

        // Resolve status from explicit dropdown first, then quick-filter tab.
	        $allowed_statuses = array( 'pending', 'approved', 'answered', 'declined', 'archived', 'trash' );
        $status_dropdown  = isset( $_GET['prayer_request_status'] ) ? sanitize_key( wp_unslash( $_GET['prayer_request_status'] ) ) : '';
        $status_quicktab  = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
        $custom_status    = '';

        if ( in_array( $status_dropdown, $allowed_statuses, true ) ) {
            // Explicit valid dropdown filter overrides quick-tab status.
            $custom_status = $status_dropdown;
        } elseif ( in_array( $status_quicktab, $allowed_statuses, true ) ) {
            $custom_status = $status_quicktab;
        }

        if ( ! empty( $custom_status ) ) {
            $query->set( 'post_status', $custom_status );
        } else {
            // Default "All" view keeps the active queue visible and hides declined/archived.
			$query->set( 'post_status', array( 'pending', 'approved', 'answered' ) );
        }

        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

		$meta_query[] = array(
			'key'     => 'prayer_pop_type',
			'value'   => 'prayer_request',
			'compare' => '=',
		);
		$meta_query[] = array(
			'key'     => 'prayer_pop_public',
			'value'   => '1',
			'compare' => '=',
		);

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }

	    /**
	     * Build shared meta query clause for unresolved pending queue rows.
	     *
	     * @param int $now Optional unix timestamp.
	     * @return array
	     */
	    public static function build_pending_queue_meta_clause( $now = 0 ) {
	        $now = absint( $now );
	        if ( $now <= 0 ) {
	            $now = time();
	        }

	        return array(
	            'relation' => 'OR',
	            array(
	                'relation' => 'AND',
	                array(
	                    'key'     => 'prayer_pop_public',
	                    'value'   => '1',
	                    'compare' => '=',
	                ),
	            ),
	            array(
	                'relation' => 'AND',
	                array(
	                    'relation' => 'OR',
	                    array(
	                        'key'     => 'prayer_pop_public',
	                        'value'   => '0',
	                        'compare' => '=',
	                    ),
	                    array(
	                        'key'     => 'prayer_pop_public',
	                        'compare' => 'NOT EXISTS',
	                    ),
	                ),
	                array(
	                    'relation' => 'OR',
	                    array(
	                        'key'     => self::PRIVATE_REVIEWED_META_KEY,
	                        'value'   => '1',
	                        'compare' => '!=',
	                    ),
	                    array(
	                        'key'     => self::PRIVATE_REVIEWED_META_KEY,
	                        'compare' => 'NOT EXISTS',
	                    ),
	                ),
	            ),
	        );
	    }

	    /**
	     * Meta query clause for the pending moderation queue.
	     *
	     * Includes:
	     * - all public pending items
	     * - only private pending items that are not marked reviewed
	     *
	     * @return array
	     */
	    private function get_pending_queue_meta_clause() {
	        return self::build_pending_queue_meta_clause();
	    }

    /**
     * Check if the user requested bulk action for all posts across pages.
     *
     * @return bool
     */
    private function is_all_posts_selected() {
        return ! empty( sanitize_text_field( wp_unslash( $_REQUEST['all_posts'] ?? '' ) ) );
    }

    /**
     * Get all submission IDs that match the current admin filters.
     *
     * @return int[]
     */
	    private function get_all_submission_ids_for_print() {
	        $allowed_statuses = array( 'pending', 'approved', 'answered', 'declined', 'archived', 'trash' );
        $status_dropdown  = isset( $_REQUEST['prayer_request_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['prayer_request_status'] ) ) : '';
        $status_quicktab  = isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : '';
        $post_status      = '';

        if ( in_array( $status_dropdown, $allowed_statuses, true ) ) {
            $post_status = $status_dropdown;
        } elseif ( in_array( $status_quicktab, $allowed_statuses, true ) ) {
            $post_status = $status_quicktab;
        }

        $args = array(
            'post_type'              => 'prayer_request',
            'posts_per_page'         => 500,
            'fields'                 => 'ids',
            'orderby'                => 'post_date',
            'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
        );

	        if ( ! empty( $post_status ) && in_array( $post_status, $allowed_statuses, true ) ) {
	            $args['post_status'] = $post_status;
	        } else {
	            // Mirror list "All" quick filter behavior.
	            $args['post_status'] = array( 'pending', 'approved', 'answered' );
	        }

        if ( ! empty( $_REQUEST['s'] ) ) {
            $args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
        }

	        if ( ! empty( $_REQUEST['m'] ) ) {
	            $args['m'] = preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_REQUEST['m'] ) ) );
	        }

        $type = isset( $_REQUEST['prayer_pop_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['prayer_pop_type'] ) ) : '';
        if ( 'prayer_request' === $type ) {
            $args['meta_query'] = array(
                array(
                    'key'     => 'prayer_pop_type',
                    'value'   => $type,
                    'compare' => '=',
                ),
            );
        }

		$stage_ready_filter = isset( $_REQUEST['prayer_pop_stage_ready'] ) ? sanitize_key( wp_unslash( $_REQUEST['prayer_pop_stage_ready'] ) ) : '';
		if ( in_array( $stage_ready_filter, array( 'ready', 'not_ready' ), true ) ) {
			if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}

			$args['meta_query'][] = array(
				'key'     => 'prayer_pop_type',
				'value'   => 'prayer_request',
				'compare' => '=',
			);

			if ( 'ready' === $stage_ready_filter ) {
				$args['meta_query'][] = array(
					'key'     => 'prayer_pop_ready_to_share',
					'value'   => '1',
					'compare' => '=',
				);
			} else {
				$args['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key'     => 'prayer_pop_ready_to_share',
						'value'   => '1',
						'compare' => '!=',
					),
					array(
						'key'     => 'prayer_pop_ready_to_share',
						'compare' => 'NOT EXISTS',
					),
				);
			}
		}

        $visibility_filter = isset( $_REQUEST['prayer_pop_visibility'] ) ? sanitize_key( wp_unslash( $_REQUEST['prayer_pop_visibility'] ) ) : '';
        if ( 'public' === $visibility_filter ) {
            if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
                $args['meta_query'] = array();
            }

            if ( 'public' === $visibility_filter ) {
                $args['meta_query'][] = array(
                    'key'     => 'prayer_pop_public',
                    'value'   => '1',
                    'compare' => '=',
                );
            } else {
                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'prayer_pop_public',
                        'value'   => '0',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => 'prayer_pop_public',
                        'compare' => 'NOT EXISTS',
                    ),
                );
            }
        }

        if ( 'pending' === $post_status ) {
            if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
                $args['meta_query'] = array();
            }
            $args['meta_query'][] = $this->get_pending_queue_meta_clause();
        }

        // Printing from admin follows the current public prayer request workflow.

		$post_ids = $this->collect_post_ids_in_batches( $args, 500, 40 );

        return $this->get_authorized_bulk_post_ids( $post_ids );
    }

	/**
	 * Collect matching post IDs in batches to avoid unbounded single queries.
	 *
	 * @param array $args      Base query args.
	 * @param int   $per_page  Page size.
	 * @param int   $max_pages Max pages to fetch.
	 * @return int[]
	 */
	private function collect_post_ids_in_batches( $args, $per_page = 500, $max_pages = 40 ) {
		$ids       = array();
		$per_page  = max( 1, absint( $per_page ) );
		$max_pages = max( 1, absint( $max_pages ) );
		$base_args = (array) $args;
		$per_page  = max( 1, absint( apply_filters( 'prayer_pop_batch_collect_per_page', $per_page, $base_args ) ) );
		$max_pages = max( 1, absint( apply_filters( 'prayer_pop_batch_collect_max_pages', $max_pages, $base_args ) ) );

		$base_args['fields']                 = 'ids';
		$base_args['posts_per_page']         = $per_page;
		$base_args['no_found_rows']          = true;
		$base_args['update_post_meta_cache'] = false;
		$base_args['update_post_term_cache'] = false;

		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$page_args          = $base_args;
			$page_args['paged'] = $page;
			$page_ids           = get_posts( $page_args );
			if ( empty( $page_ids ) ) {
				break;
			}

			$ids = array_merge( $ids, array_map( 'absint', $page_ids ) );
			if ( count( $page_ids ) < $per_page ) {
				break;
			}
		}

		return array_values( array_filter( $ids ) );
	}

    /**
     * Sanitize and authorize post IDs for bulk actions.
     *
     * @param array $post_ids Candidate post IDs.
     * @return int[]
     */
    private function get_authorized_bulk_post_ids( $post_ids ) {
        $validated = array();

        foreach ( (array) $post_ids as $post_id ) {
            $post_id = absint( $post_id );
            if ( ! $post_id ) {
                continue;
            }

            if ( 'prayer_request' !== get_post_type( $post_id ) ) {
                continue;
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                continue;
            }

            $validated[] = $post_id;
        }

        return array_values( array_unique( $validated ) );
    }

    /**
     * Check whether a submission is public.
     *
     * @param int $post_id Post ID.
     * @return bool True when submission is public.
     */
    private function is_public_submission( $post_id ) {
        return '1' === get_post_meta( absint( $post_id ), 'prayer_pop_public', true );
    }

    /**
     * Parse printable post IDs from request/fallback storage.
     *
     * @param string $nonce Verified print nonce.
     * @return int[]
     */
    private function parse_print_post_ids_from_request( $nonce ) {
        $post_ids = array();

        if ( ! wp_verify_nonce( $nonce, 'prayer_pop_print_submissions' ) ) {
            return $post_ids;
        }

        if ( isset( $_GET['prayer_pop_ids'] ) ) {
            $post_ids = explode( ',', sanitize_text_field( wp_unslash( $_GET['prayer_pop_ids'] ) ) );
            $post_ids = array_map( 'absint', $post_ids );
            $post_ids = array_values( array_filter( $post_ids ) );
        } elseif ( isset( $_GET['ids'] ) ) {
            // Backward compatibility for old print URLs.
            $post_ids = explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) );
            $post_ids = array_map( 'absint', $post_ids );
            $post_ids = array_values( array_filter( $post_ids ) );
        } else {
            $post_ids = $this->get_stored_print_ids( $nonce );
            if ( empty( $post_ids ) ) {
                $post_ids = $this->get_last_stored_print_ids();
            }
        }

        return $this->get_authorized_bulk_post_ids( $post_ids );
    }

    /**
     * Render print document and exit.
     *
     * @param int[]  $post_ids Authorized post IDs.
     * @param string $nonce    Print nonce.
     * @return void
     */
    private function render_print_submissions_document( $post_ids, $nonce ) {
	        $submissions = get_posts(
	            array(
	                'post_type'      => 'prayer_request',
	                'post_status'    => array( 'pending', 'approved', 'answered', 'declined', 'archived' ),
	                'post__in'       => $post_ids,
	                'posts_per_page' => -1,
	                'orderby'        => 'post__in',
            )
        );

	        header( 'Content-Type: text/html; charset=utf-8' );
	        $site_name        = wp_strip_all_tags( get_bloginfo( 'name' ) );
	        $submission_count = count( $submissions );
	        $printed_at       = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html__( 'Print Submissions', 'prayerpop' ); ?></title>
	</head>
	<body>
	    <main class="pp-print-shell">
	        <div class="pp-print-actions pp-print-no-print">
	            <a class="pp-print-button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=prayer_request' ) ); ?>"><?php echo esc_html__( 'Back to Submissions', 'prayerpop' ); ?></a>
	            <button class="pp-print-button pp-print-button-primary" type="button" onclick="window.print()"><?php echo esc_html__( 'Print', 'prayerpop' ); ?></button>
	        </div>
	        <header class="pp-print-header">
	            <p class="pp-print-kicker"><?php echo esc_html( $site_name ); ?></p>
	            <h1><?php echo esc_html__( 'Selected Submissions', 'prayerpop' ); ?></h1>
	            <p class="pp-print-summary">
	                <?php
	                printf(
	                    /* translators: 1: number of submissions, 2: print date. */
	                    esc_html( _n( '%1$s submission · Prepared %2$s', '%1$s submissions · Prepared %2$s', $submission_count, 'prayerpop' ) ),
	                    esc_html( number_format_i18n( $submission_count ) ),
	                    esc_html( $printed_at )
	                );
	                ?>
	            </p>
	        </header>
	        <?php foreach ( $submissions as $index => $post ) : ?>
	            <?php
	            $type_key       = sanitize_key( (string) get_post_meta( $post->ID, 'prayer_pop_type', true ) );
	            $type_label     = 'testimony' === $type_key ? __( 'Testimony', 'prayerpop' ) : __( 'Prayer Request', 'prayerpop' );
	            $status_key     = sanitize_key( (string) $post->post_status );
	            $status_object  = get_post_status_object( $status_key );
	            $status_label   = $status_object ? $status_object->label : ucwords( str_replace( '_', ' ', $status_key ) );
	            $name           = \Prayer_Pop_Defaults::get_submission_display_name( $post->ID );
	            $date           = get_the_date( get_option( 'date_format' ) . ' · ' . get_option( 'time_format' ), $post->ID );
	            $answer_message = get_post_meta( $post->ID, self::ANSWERED_MESSAGE_META_KEY, true );
	            ?>
	            <article class="pp-print-submission">
	                <header class="pp-print-submission-header">
	                    <div class="pp-print-heading">
	                        <span class="pp-print-number"><?php echo esc_html( number_format_i18n( $index + 1 ) ); ?></span>
	                        <div>
	                            <h2><?php echo esc_html( $name ); ?></h2>
	                            <p class="pp-print-reference">
	                                <?php
	                                /* translators: 1: submission ID, 2: submission date. */
	                                printf( esc_html__( 'Submission #%1$s · %2$s', 'prayerpop' ), esc_html( $post->ID ), esc_html( $date ) );
	                                ?>
	                            </p>
	                        </div>
	                    </div>
	                    <div class="pp-print-badges">
	                        <span class="pp-print-badge pp-print-type-<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></span>
	                        <span class="pp-print-badge pp-print-status-<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></span>
	                    </div>
	                </header>
	                <div class="pp-print-body">
	                    <p class="pp-print-label"><?php echo esc_html__( 'Message', 'prayerpop' ); ?></p>
	                    <p class="pp-print-message"><?php echo nl2br( esc_html( $post->post_content ) ); ?></p>
	                    <?php if ( '' !== trim( (string) $answer_message ) ) : ?>
	                        <div class="pp-print-answer">
	                            <p class="pp-print-label"><?php echo esc_html__( 'Answer Note', 'prayerpop' ); ?></p>
	                            <p class="pp-print-message"><?php echo nl2br( esc_html( $answer_message ) ); ?></p>
	                        </div>
	                    <?php endif; ?>
	                </div>
	            </article>
	        <?php endforeach; ?>
	    </main>
	</body>
</html><?php
        $this->delete_stored_print_ids( $nonce );
        exit;
    }

    /**
     * Build transient key for stored print IDs.
     *
     * @param string $nonce Print nonce.
     * @return string
     */
    private function get_print_transient_key( $nonce ) {
        $user_id = get_current_user_id();
        return 'prayer_pop_print_ids_' . absint( $user_id ) . '_' . md5( (string) $nonce );
    }

    /**
     * Persist print IDs briefly to survive URL arg stripping.
     *
     * @param array  $post_ids Post IDs.
     * @param string $nonce    Print nonce.
     * @return void
     */
    private function store_print_ids( $post_ids, $nonce ) {
        $nonce = sanitize_text_field( (string) $nonce );
        if ( '' === $nonce ) {
            return;
        }

        $authorized_ids = $this->get_authorized_bulk_post_ids( (array) $post_ids );
        if ( empty( $authorized_ids ) ) {
            return;
        }

        set_transient( $this->get_print_transient_key( $nonce ), $authorized_ids, 10 * MINUTE_IN_SECONDS );

        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            update_user_meta( $user_id, 'prayer_pop_last_print_ids', $authorized_ids );
            update_user_meta( $user_id, 'prayer_pop_last_print_saved', time() );
        }
    }

    /**
     * Retrieve stored print IDs for nonce.
     *
     * @param string $nonce Print nonce.
     * @return int[]
     */
    private function get_stored_print_ids( $nonce ) {
        $nonce = sanitize_text_field( (string) $nonce );
        if ( '' === $nonce ) {
            return array();
        }

        $stored_ids = get_transient( $this->get_print_transient_key( $nonce ) );
        if ( ! is_array( $stored_ids ) ) {
            return array();
        }

        return array_map( 'absint', $stored_ids );
    }

    /**
     * Retrieve most recent print IDs for current user.
     *
     * @return int[]
     */
    private function get_last_stored_print_ids() {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return array();
        }

        $saved_at = absint( get_user_meta( $user_id, 'prayer_pop_last_print_saved', true ) );
        if ( $saved_at > 0 && ( time() - $saved_at ) > ( 10 * MINUTE_IN_SECONDS ) ) {
            return array();
        }

        $stored_ids = get_user_meta( $user_id, 'prayer_pop_last_print_ids', true );
        if ( ! is_array( $stored_ids ) ) {
            return array();
        }

        return array_map( 'absint', $stored_ids );
    }

    /**
     * Delete stored print IDs for nonce after use.
     *
     * @param string $nonce Print nonce.
     * @return void
     */
    private function delete_stored_print_ids( $nonce ) {
        $nonce = sanitize_text_field( (string) $nonce );
        if ( '' === $nonce ) {
            return;
        }

        delete_transient( $this->get_print_transient_key( $nonce ) );
    }

    /**
     * Cleanup old submissions
     */
    public function cleanup_old_submissions() {
        // Use general settings for retention period using cache.
        $options        = Prayer_Pop_Defaults::get_settings();
        $retention_days = isset( $options['retention_period'] ) ? intval( $options['retention_period'] ) : 90;
        $now_timestamp  = current_time( 'timestamp' );
        $batch_size     = 200;

        if ( 0 === $retention_days ) {
            return; // Keep submissions forever when retention is disabled.
        }

        // Archive approved/answered posts older than the configured retention window.
        $approved_args = array(
            'post_type'               => 'prayer_request',
            'post_status'             => array( 'approved', 'answered' ),
            'date_query'              => array(
                array(
                    'column' => 'post_date',
                    'before' => $retention_days . ' days ago',
                ),
            ),
            'fields'                  => 'ids',
            'posts_per_page'          => $batch_size,
            'orderby'                 => 'ID',
            'order'                   => 'ASC',
            'no_found_rows'           => true,
            'update_post_meta_cache'  => false,
            'update_post_term_cache'  => false,
            'suppress_filters'        => false,
        );

        // Fetch first batch repeatedly; archived items leave this query automatically.
        while ( true ) {
            $approved_posts = get_posts( $approved_args );
            if ( empty( $approved_posts ) ) {
                break;
            }

            foreach ( $approved_posts as $post_id ) {
                $this->archive_submission( $post_id, $now_timestamp );
            }
        }

        // Delete archived posts only after they have remained archived for the full retention window.
        // This prevents approved items from being archived and deleted in the same cleanup run.
        $archive_cutoff = $now_timestamp - ( $retention_days * DAY_IN_SECONDS );

        // Phase 1: initialize missing archive timestamps from older data.
        // These posts leave this query after they get ARCHIVED_AT meta.
        $missing_archive_meta_args = array(
            'post_type'               => 'prayer_request',
            'post_status'             => 'archived',
            'fields'                  => 'ids',
            'posts_per_page'          => $batch_size,
            'orderby'                 => 'ID',
            'order'                   => 'ASC',
            'no_found_rows'           => true,
            'update_post_meta_cache'  => false,
            'update_post_term_cache'  => false,
            'suppress_filters'        => false,
            'meta_query'              => array(
                array(
                    'key'     => self::ARCHIVED_AT_META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        while ( true ) {
            $missing_timestamp_posts = get_posts( $missing_archive_meta_args );
            if ( empty( $missing_timestamp_posts ) ) {
                break;
            }

            foreach ( $missing_timestamp_posts as $post_id ) {
                update_post_meta( $post_id, self::ARCHIVED_AT_META_KEY, $now_timestamp );
            }
        }

        // Phase 2: delete archived posts that have exceeded the archive cutoff window.
        $delete_archived_args = array(
            'post_type'               => 'prayer_request',
            'post_status'             => 'archived',
            'fields'                  => 'ids',
            'posts_per_page'          => $batch_size,
            'orderby'                 => 'ID',
            'order'                   => 'ASC',
            'no_found_rows'           => true,
            'update_post_meta_cache'  => false,
            'update_post_term_cache'  => false,
            'suppress_filters'        => false,
            'meta_query'              => array(
                array(
                    'key'     => self::ARCHIVED_AT_META_KEY,
                    'value'   => $archive_cutoff,
                    'type'    => 'NUMERIC',
                    'compare' => '<=',
                ),
            ),
        );

        while ( true ) {
            $archived_posts = get_posts( $delete_archived_args );
            if ( empty( $archived_posts ) ) {
                break;
            }

            foreach ( $archived_posts as $post_id ) {
                wp_delete_post( $post_id, true );
            }
        }
    }

    /**
     * Archive a submission while preserving its previous status.
     *
     * @param int      $post_id       Submission ID.
     * @param int|null $archived_time Optional archive timestamp.
     * @return bool True when archived state was applied, false otherwise.
     */
    private function archive_submission( $post_id, $archived_time = null ) {
        $post_id = absint( $post_id );
        if ( $post_id <= 0 ) {
            return false;
        }

        $current_status = get_post_status( $post_id );
        if ( empty( $current_status ) || in_array( $current_status, array( 'archived', 'trash' ), true ) ) {
            return false;
        }

        update_post_meta( $post_id, self::PRE_ARCHIVE_STATUS_META_KEY, sanitize_key( $current_status ) );

        wp_update_post(
            array(
                'ID'          => $post_id,
                'post_status' => 'archived',
            )
        );

        $archive_timestamp = null === $archived_time ? current_time( 'timestamp' ) : absint( $archived_time );
        update_post_meta( $post_id, self::ARCHIVED_AT_META_KEY, $archive_timestamp );
        return true;
    }

    /**
     * Send immediate notification (called by scheduled event).
     */
    public function send_immediate_notification( $post_id, $type, $name, $message ) {
        // Additional security check: verify post exists and user can manage options
        if ( ! get_post( $post_id ) ) {
            return;
        }

        // Get notification settings
        $notification_options = get_option( 'prayer_pop_notification_settings', array() );
        
        // Double-check notifications are still enabled
        if ( ! isset( $notification_options['enable_notifications'] ) || ! $notification_options['enable_notifications'] ) {
            return;
        }

        if ( ! isset( $notification_options['notification_frequency'] ) || $notification_options['notification_frequency'] !== 'immediately' ) {
            return;
        }

        // Get email settings with validation
        $admin_email = ! empty( $notification_options['notification_email'] ) ? 
            sanitize_email( $notification_options['notification_email'] ) : 
            get_option( 'admin_email' );

        // Validate email address
        if ( ! is_email( $admin_email ) ) {
            return;
        }

        // Get email template
        $email_template = get_option( 'prayer_pop_email_template', array() );
        $subject = isset( $email_template['email_subject'] ) && ! empty( $email_template['email_subject'] ) ? 
            $email_template['email_subject'] : 
            __( 'New PrayerPop Submission', 'prayerpop' );

        $body_template = isset( $email_template['email_body'] ) && ! empty( $email_template['email_body'] ) ? 
            $email_template['email_body'] : 
            __( "Type: {type}\nName: {name}\nMessage:\n{message}", "prayerpop" );

        $pending_count = $this->get_pending_submission_count();

        // Prepare placeholders with enhanced variables and additional security
        $placeholders = array(
            '{type}' => $type === 'prayer_request' ? __( 'Prayer Request', 'prayerpop' ) : __( 'Testimony', 'prayerpop' ),
            '{name}' => $name ?: __( 'Anonymous', 'prayerpop' ),
            '{message}' => $message,
            '{pending_count}' => absint( $pending_count ),
            '{admin_url}' => admin_url( 'edit.php?post_type=prayer_request' ),
            '{site_url}' => home_url(),
            '{site_name}' => wp_strip_all_tags( get_bloginfo( 'name' ) )
        );

        // Replace placeholders with sanitized content
        $subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
        $body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body_template );

        // Additional security: limit subject and body length to prevent potential issues
        $subject = substr( $subject, 0, 998 ); // Email subject length limit
        $body = substr( $body, 0, 50000 ); // Reasonable email body limit

        // Send email with error handling
        $mail_sent = wp_mail( $admin_email, $subject, $body );
        
	    }

    /**
     * Send daily notifications
     */
	public function send_daily_notifications() {
		$options = get_option( 'prayer_pop_notification_settings', array() );

		if ( isset( $options['enable_notifications'], $options['notification_frequency'] ) && $options['enable_notifications'] && 'daily' === $options['notification_frequency'] ) {
			$this->send_scheduled_notifications( 'daily' );
		}

		Prayer_Pop_Notification_Scheduler::ensure_scheduled( is_array( $options ) ? $options : array() );
	}

    /**
     * Send weekly notifications
     */
	public function send_weekly_notifications() {
		$options = get_option( 'prayer_pop_notification_settings', array() );

		if ( isset( $options['enable_notifications'], $options['notification_frequency'] ) && $options['enable_notifications'] && 'weekly' === $options['notification_frequency'] ) {
			$this->send_scheduled_notifications( 'weekly' );
		}

		Prayer_Pop_Notification_Scheduler::ensure_scheduled( is_array( $options ) ? $options : array() );
	}

    /**
     * Common function to send scheduled notifications
     */
	    public function send_scheduled_notifications( $frequency ) {
	        $options      = get_option( 'prayer_pop_notification_settings', array() );
        $admin_email  = ! empty( $options['notification_email'] ) ? sanitize_email( $options['notification_email'] ) : get_option( 'admin_email' );
        $email_template = get_option( 'prayer_pop_email_template', array() );
        $subject        = isset( $email_template['email_subject'] ) && ! empty( $email_template['email_subject'] ) ? $email_template['email_subject'] : __( 'Scheduled PrayerPop Submissions', 'prayerpop' );
        $body_template  = isset( $email_template['email_body'] ) && ! empty( $email_template['email_body'] ) ? $email_template['email_body'] : __( "Type: {type}\nName: {name}\nMessage:\n{message}", "prayerpop" );

	        // Get pending submissions since last notification.
        $last_sent = get_option( 'prayer_pop_last_notification_time', 0 );
        $base_args = array(
            'post_type'              => 'prayer_request',
            'post_status'            => 'pending', // Only pending submissions for scheduled notifications.
            'meta_query'             => array( $this->get_pending_queue_meta_clause() ),
            'date_query'             => array(
                'after' => $last_sent ? gmdate( 'Y-m-d H:i:s', $last_sent ) : ( $frequency === 'daily' ? '1 day ago' : '1 week ago' ),
            ),
            'fields'                 => 'ids',
            'orderby'                => 'date',
            'order'                  => 'ASC',
            'posts_per_page'         => 200,
            'paged'                  => 1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        
			$submission_ids = array();
		$max_pages      = 25; // Safety guard: up to 5000 IDs per run.
		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$page_args         = $base_args;
			$page_args['paged'] = $page;
			$page_ids          = get_posts( $page_args );
			if ( empty( $page_ids ) ) {
				break;
			}

			$submission_ids = array_merge( $submission_ids, array_map( 'absint', $page_ids ) );
			if ( count( $page_ids ) < (int) $base_args['posts_per_page'] ) {
				break;
			}
		}
        
	        if ( ! empty( $submission_ids ) ) {
            $message = '';

            $pending_count = $this->get_pending_submission_count();

            // Add header with pending count
            /* translators: %d: number of pending submissions. */
            $message .= sprintf( __( "Total pending submissions: %d\n\n", "prayerpop" ), $pending_count );

            foreach ( $submission_ids as $submission_id ) {
				$submission_id = absint( $submission_id );
				if ( $submission_id <= 0 ) {
					continue;
				}

				$content = (string) get_post_field( 'post_content', $submission_id );
                $type = get_post_meta( $submission_id, 'prayer_pop_type', true );
                $name = \Prayer_Pop_Defaults::get_submission_display_name( $submission_id );

                // Prepare placeholders with enhanced variables
                $placeholders = array(
                    '{type}'    => $type === 'prayer_request' ? __( 'Prayer Request', 'prayerpop' ) : __( 'Testimony', 'prayerpop' ),
                    '{name}'    => $name,
                    '{message}' => $content,
                    '{pending_count}' => $pending_count,
                    '{admin_url}' => admin_url( 'edit.php?post_type=prayer_request' ),
                    '{site_url}' => home_url(),
                    '{site_name}' => get_bloginfo( 'name' )
                );

                // Replace placeholders in body template
                $body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $body_template );

                $message .= $body . "\n\n----------------------------------------\n\n";
            }

            // Add footer with admin link
            /* translators: %s: admin URL to manage submissions. */
            $message .= sprintf( __( "\nManage submissions: %s", "prayerpop" ), admin_url( 'edit.php?post_type=prayer_request' ) );

	            wp_mail( $admin_email, $subject, $message );
	        }

        // Update last notification time (using local timezone)
        update_option( 'prayer_pop_last_notification_time', current_time( 'timestamp' ) );
        
	    }
    
    /**
     * Add custom cron schedules.
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['prayer_pop_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'prayerpop' )
        );
        return $schedules;
    }

    /**
     * Add pending submission count bubble to the admin menu.
     */
    public function add_pending_count_bubble() {
        $settings = Prayer_Pop_Defaults::get_settings();
        if ( empty( $settings['require_admin_approval'] ) ) {
            return;
        }

        $count = $this->get_pending_submission_count();
        
        if ( $count > 0 ) {
            global $menu, $submenu;
            
            // Add to main menu item (PrayerPop)
            if ( isset( $menu ) ) {
                foreach ( $menu as $index => $item ) {
                    if ( isset( $item[2] ) && $item[2] === 'prayer-pop' ) {
                        $menu[ $index ][0] .= ' <span class="awaiting-mod update-plugins count-' . intval( $count ) . '"><span class="plugin-count">' . intval( $count ) . '</span></span>';
                        break;
                    }
                }
            }
            
            // Add to submenu item (Prayer Requests)
            if ( isset( $submenu['prayer-pop'] ) ) {
                foreach ( $submenu['prayer-pop'] as $index => $item ) {
                    if ( isset( $item[2] ) && $item[2] === 'edit.php?post_type=prayer_request' ) {
                        $submenu['prayer-pop'][ $index ][0] .= ' <span class="awaiting-mod update-plugins count-' . intval( $count ) . '"><span class="plugin-count">' . intval( $count ) . '</span></span>';
                        break;
                    }
                }
            }
        }
    }

    /**
     * Register PrayerPop dashboard widget.
     *
     * @return void
     */
    public function register_dashboard_widget() {
        if ( ! self::current_user_can_manage_submissions() ) {
            return;
        }

        $settings = Prayer_Pop_Defaults::get_settings();
        if ( empty( $settings['require_admin_approval'] ) ) {
            return;
        }

        $state = $this->get_pending_dashboard_widget_state();
        if ( $state['count'] < 1 || $this->is_dashboard_widget_dismissed( $state ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'prayer_pop_pending_submissions_widget',
            esc_html__( 'PrayerPop Pending Submissions', 'prayerpop' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render PrayerPop dashboard widget content.
     *
     * @return void
     */
    public function render_dashboard_widget() {
        $state = $this->get_pending_dashboard_widget_state();
        $count = $state['count'];
        $review_url = admin_url( 'edit.php?post_type=prayer_request&post_status=pending' );
        $dismiss_url = wp_nonce_url(
            add_query_arg(
                array(
                    'prayer_pop_dismiss_pending_widget' => '1',
                ),
                admin_url( 'index.php' )
            ),
            'prayer_pop_dismiss_pending_widget'
        );

        if ( $count > 0 ) {
            echo '<p>';
            echo esc_html(
                sprintf(
                    /* translators: %s: number of pending submissions. */
                    _n(
                        '%s submission is waiting for approval.',
                        '%s submissions are waiting for approval.',
                        $count,
                        'prayerpop'
                    ),
                    number_format_i18n( $count )
                )
            );
            echo '</p>';
            echo '<p><a class="button button-primary" href="' . esc_url( $review_url ) . '">' . esc_html__( 'Review Submissions', 'prayerpop' ) . '</a> ';
            echo '<a class="button" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'prayerpop' ) . '</a></p>';
            return;
        }

        echo '<p>' . esc_html__( 'No pending submissions right now.', 'prayerpop' ) . '</p>';
    }

    /**
     * Handle user dismissal for the pending-submissions dashboard widget.
     *
     * @return void
     */
    public function handle_dashboard_widget_dismissal() {
        $dismiss_requested = isset( $_GET['prayer_pop_dismiss_pending_widget'] )
            ? sanitize_text_field( wp_unslash( $_GET['prayer_pop_dismiss_pending_widget'] ) )
            : '';

        if ( '1' !== $dismiss_requested || ! self::current_user_can_manage_submissions() ) {
            return;
        }

        check_admin_referer( 'prayer_pop_dismiss_pending_widget' );

        $state = $this->get_pending_dashboard_widget_state();
        update_user_meta( get_current_user_id(), self::DASHBOARD_WIDGET_DISMISSED_META_KEY, $state );

        $redirect_url = remove_query_arg(
            array( 'prayer_pop_dismiss_pending_widget', '_wpnonce' ),
            wp_get_referer() ? wp_get_referer() : admin_url( 'index.php' )
        );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Check if the current user dismissed the current pending-submission state.
     *
     * @param array $state Current pending-widget state.
     * @return bool
     */
    private function is_dashboard_widget_dismissed( $state ) {
        $dismissed = get_user_meta( get_current_user_id(), self::DASHBOARD_WIDGET_DISMISSED_META_KEY, true );
        if ( ! is_array( $dismissed ) ) {
            return false;
        }

        $dismissed_count = isset( $dismissed['count'] ) ? absint( $dismissed['count'] ) : 0;
        $dismissed_latest_id = isset( $dismissed['latest_id'] ) ? absint( $dismissed['latest_id'] ) : 0;

        return $dismissed_latest_id === absint( $state['latest_id'] ) && $dismissed_count >= absint( $state['count'] );
    }

    /**
     * Get dashboard widget state for pending submissions.
     *
     * @return array{count:int,latest_id:int}
     */
    private function get_pending_dashboard_widget_state() {
        $pending_query = new \WP_Query(
            array(
                'post_type'              => 'prayer_request',
                'post_status'            => 'pending',
                'meta_query'             => array( $this->get_pending_queue_meta_clause() ),
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        return array(
            'count'     => (int) $pending_query->found_posts,
            'latest_id' => ! empty( $pending_query->posts[0] ) ? absint( $pending_query->posts[0] ) : 0,
        );
    }

    /**
     * Get the count of pending submissions (public + private).
     *
     * @return int
     */
	    private function get_pending_submission_count() {
	        $pending_query = new \WP_Query(
	            array(
	                'post_type'              => 'prayer_request',
	                'post_status'            => 'pending',
                    'meta_query'             => array( $this->get_pending_queue_meta_clause() ),
	                'posts_per_page'         => 1,
	                'fields'                 => 'ids',
	                'no_found_rows'          => false,
	                'update_post_meta_cache' => false,
	                'update_post_term_cache' => false,
	            )
	        );

	        return (int) $pending_query->found_posts;
	    }

	    /**
	     * Sanitize a CSS custom property value before injecting to inline style.
	     *
	     * @param string $key   Option key.
	     * @param mixed  $value Raw option value.
	     * @return string
	     */
	    private function sanitize_css_custom_property_value( $key, $value ) {
	        if ( ! is_scalar( $value ) ) {
	            return '';
	        }

	        $value = trim( (string) $value );
	        if ( '' === $value ) {
	            return '';
	        }

		$color_keys = array(
			'global_bg_color',
			'bubble_bg_color',
			'bubble_icon_color',
			'global_font_color',
			'global_label_color',
			'global_button_hover_color',
			'global_textarea_bg_color',
			'global_border_color',
		);
	        if ( in_array( $key, $color_keys, true ) ) {
	            $color = sanitize_hex_color( $value );
	            return $color ? $color : '';
	        }

		$dimension_keys = array(
			'global_font_size',
			'heading_font_size',
			'global_padding',
			'global_margin',
			'global_border_radius',
			'bubble_border_radius',
			'bubble_padding',
			'bubble_margin',
			'bubble_height',
			'bubble_offset_x',
			'bubble_offset_y',
			'checkbox_margin',
		);
		if ( in_array( $key, $dimension_keys, true ) ) {
			return preg_match( '/^(?:0|[0-9]+(?:\.[0-9]+)?(?:%|px|em|rem))$/', $value ) ? $value : '';
	        }

		if ( in_array( $key, array( 'bubble_size', 'bubble_icon_size' ), true ) ) {
			return (string) absint( $value );
		}

		if ( in_array( $key, array( 'heading_font_weight', 'global_bold_font_weight' ), true ) ) {
			return preg_match( '/^(?:normal|bold|[1-9]00)$/', $value ) ? $value : '';
		}

		if ( in_array( $key, array( 'global_font_family', 'heading_font_family' ), true ) ) {
			$allowed_fonts = array(
				'system-ui',
				'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
				'Georgia, serif',
				'"Helvetica Neue", Helvetica, Arial, sans-serif',
				'Times, "Times New Roman", serif',
				'Arial, Helvetica, sans-serif',
				'Tahoma, Geneva, sans-serif',
				'Verdana, Geneva, sans-serif',
				'"Trebuchet MS", Helvetica, sans-serif',
				'Impact, Charcoal, sans-serif',
				'"Courier New", Courier, monospace',
			);

			return in_array( $value, $allowed_fonts, true ) ? $value : '';
		}

	        return '';
	    }
}
