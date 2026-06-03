<?php
namespace PrayerPop\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListTable {
    public function __construct() {
        // Register hooks immediately for all admin screens
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks for prayer_request post type
     */
    public function init_hooks() {

        // Ensure our custom ordering runs on the admin screen for prayer_request
        add_filter( 'posts_orderby', array( $this, 'order_admin_list' ), 10, 2 );

        // Display our custom columns on the prayer request list table.
        add_filter( 'manage_edit-prayer_request_columns', array( $this, 'columns' ) );
		add_filter( 'manage_edit-prayer_request_sortable_columns', array( $this, 'sortable_columns' ) );
        add_action( 'manage_prayer_request_posts_custom_column', array( $this, 'column_content' ), 10, 2 );

        // Custom row actions with higher priority to ensure it runs after WordPress defaults
        add_filter( 'post_row_actions', array( $this, 'modify_row_actions' ), 20, 2 );

        // Add custom row classes
        add_filter( 'post_class', array( $this, 'add_row_classes' ), 10, 3 );

        // Load admin styles for the list table.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'manage_posts_extra_tablenav', array( $this, 'render_submissions_footer_logo' ) );

        // Override status filter labels
        add_filter( 'views_edit-prayer_request', array( $this, 'customize_status_filters' ) );

        // Remove WordPress list/excerpt "View mode" toggle from this post type.
        add_filter( 'view_mode_post_types', array( $this, 'disable_view_mode_for_prayer_request' ) );

		// Inline badge editing (type / visibility / status).
		add_action( 'wp_ajax_prayer_pop_inline_update_submission_field', array( $this, 'handle_inline_update_submission_field' ) );

    }

    /**
     * Disable Screen Options "View mode" toggle for prayer_request list.
     *
     * @param array $post_types Post types that support view mode.
     * @return array
     */
    public function disable_view_mode_for_prayer_request( $post_types ) {
        if ( ! is_array( $post_types ) ) {
            return $post_types;
        }

        return array_values( array_diff( $post_types, array( 'prayer_request' ) ) );
    }

    /**
     * Define columns for the prayer request list table.
     *
     * @param array $columns Default columns.
     * @return array Modified columns.
     */
    public function columns( $columns ) {
        return array(
            'cb'              => '<input type="checkbox" />',
            'pp_name'         => esc_html__( 'Submissions', 'prayerpop' ),
            'pp_type'         => esc_html__( 'Type', 'prayerpop' ),
            'pp_visibility'   => esc_html__( 'Visibility', 'prayerpop' ),
            'pp_status'       => esc_html__( 'Status', 'prayerpop' ),
            'pp_actions'      => esc_html__( 'Actions', 'prayerpop' ),
            'date'            => esc_html__( 'Date', 'prayerpop' ),
        );
    }

	/**
	 * Mark custom columns as sortable.
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['pp_type']       = 'pp_type';
		$columns['pp_visibility'] = 'pp_visibility';
		$columns['pp_status']     = 'pp_status';
		$columns['date']          = 'date';
		return $columns;
	}

	/**
	 * Build current submissions list URL so single-item actions return to the same filtered view.
	 *
	 * @return string
	 */
	private function get_submission_list_redirect_url() {
		$args = array(
			'post_type' => 'prayer_request',
		);

		$key_filters = array(
			'post_status',
			'prayer_request_status',
			'prayer_pop_visibility',
			'prayer_pop_type',
			'prayer_pop_stage_ready',
			'orderby',
			'order',
		);

		foreach ( $key_filters as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$value = sanitize_key( wp_unslash( $_GET[ $key ] ) );
				if ( '' !== $value ) {
					$args[ $key ] = $value;
				}
			}
		}

		$int_filters = array( 'm', 'paged' );
		foreach ( $int_filters as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$value = absint( wp_unslash( $_GET[ $key ] ) );
				if ( $value > 0 ) {
					$args[ $key ] = $value;
				}
			}
		}

		if ( isset( $_GET['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			if ( '' !== $search ) {
				$args['s'] = $search;
			}
		}

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

    /**
     * Output column content.
     *
     * @param string $column  Column ID.
     * @param int    $post_id Post ID.
     */
    public function column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'pp_name':
                $raw_name = (string) get_post_meta( $post_id, 'prayer_pop_name', true );
                $name = \Prayer_Pop_Defaults::get_submission_display_name( $post_id, $raw_name );
                $content = (string) get_post_field( 'post_content', $post_id );
                $status  = get_post_status( $post_id );
                $answered_message = trim( (string) get_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY, true ) );
                $can_inline_text_edit = \Prayer_Pop_Run::current_user_can_manage_submissions();

                if ( $can_inline_text_edit ) {
                    echo '<div class="pp-inline-text-editable pp-inline-text-name" data-post-id="' . esc_attr( $post_id ) . '" data-field="name_text" data-multiline="0" data-value="' . esc_attr( trim( $raw_name ) ) . '" data-placeholder="' . esc_attr__( 'Leave empty for Anonymous', 'prayerpop' ) . '" aria-label="' . esc_attr__( 'Edit name', 'prayerpop' ) . '" aria-haspopup="dialog" aria-expanded="false" role="button" tabindex="0"><strong>' . esc_html( $name ) . '</strong></div>';
                } else {
                    echo '<strong>' . esc_html( $name ) . '</strong>';
                }

                // Display the full submitted text under name.
                if ( $can_inline_text_edit ) {
                    echo '<div class="pp-inline-text-editable pp-inline-text-body pp-submission-full" data-post-id="' . esc_attr( $post_id ) . '" data-field="submission_text" data-multiline="1" data-value="' . esc_attr( trim( $content ) ) . '" data-placeholder="' . esc_attr__( 'Add submission text...', 'prayerpop' ) . '" aria-label="' . esc_attr__( 'Edit submission text', 'prayerpop' ) . '" aria-haspopup="dialog" aria-expanded="false" role="button" tabindex="0">';
                    if ( '' !== trim( $content ) ) {
                        echo nl2br( esc_html( $content ) );
                    } else {
                        echo '<span class="pp-inline-text-placeholder">' . esc_html__( 'Add submission text...', 'prayerpop' ) . '</span>';
                    }
                    echo '</div>';
                } elseif ( '' !== trim( $content ) ) {
                    echo '<div class="pp-submission-full">' . nl2br( esc_html( $content ) ) . '</div>';
                }

                // For answered prayers, show the admin answer note in the same column.
                if ( 'answered' === $status ) {
                    $answer_label = \Prayer_Pop_Defaults::get_text( 'text_answered_message_label', esc_html__( 'Answer Update', 'prayerpop' ) );
                    echo '<div class="pp-answered-note">';
                    echo '<div class="pp-answered-note-label">' . esc_html( $answer_label ) . '</div>';
                    if ( $can_inline_text_edit ) {
                        echo '<div class="pp-inline-text-editable pp-inline-text-answer pp-answered-note-text" data-post-id="' . esc_attr( $post_id ) . '" data-field="answered_note" data-multiline="1" data-value="' . esc_attr( $answered_message ) . '" data-placeholder="' . esc_attr__( 'Add answer update...', 'prayerpop' ) . '" aria-label="' . esc_attr__( 'Edit answer update', 'prayerpop' ) . '" aria-haspopup="dialog" aria-expanded="false" role="button" tabindex="0">';
                        if ( '' !== $answered_message ) {
                            echo nl2br( esc_html( $answered_message ) );
                        } else {
                            echo '<span class="pp-inline-text-placeholder">' . esc_html__( 'Add answer update...', 'prayerpop' ) . '</span>';
                        }
                        echo '</div>';
                    } elseif ( '' !== $answered_message ) {
                        echo '<div class="pp-answered-note-text">' . nl2br( esc_html( $answered_message ) ) . '</div>';
                    }
                    echo '</div>';
                }
                break;

            case 'pp_type':
				$labels       = get_option( 'prayer_pop_texts', array() );
				$prayer_label = isset( $labels['text_prayer_request_label'] ) ? $labels['text_prayer_request_label'] : esc_html__( 'Prayer Request', 'prayerpop' );
				echo '<div class="pp-type-cell">';
				echo wp_kses_post( $this->render_inline_badge( $post_id, 'type', 'prayer_request', $prayer_label, 'pp-type-request', $this->get_inline_field_options( 'type', $post_id ) ) );
				echo '</div>';
                break;

            case 'pp_visibility':
			echo wp_kses_post( $this->render_inline_badge( $post_id, 'visibility', 'public', esc_html__( 'Public', 'prayerpop' ), 'pp-visibility-public', $this->get_inline_field_options( 'visibility', $post_id ) ) );
                break;

	            case 'pp_status':
	                $status = get_post_status( $post_id );
	                $is_public = get_post_meta( $post_id, 'prayer_pop_public', true );
	                $answered_message = (string) get_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY, true );
	                $is_private_reviewed = '1' === get_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, true );

				$status_value = '';
				$status_label = '';
				$status_class = '';
				$status_options = $this->get_inline_field_options( 'status', $post_id );
				if ( '1' !== $is_public ) {
					if ( 'pending' === $status && ! $is_private_reviewed ) {
						$status_value = 'pending_private';
						$status_label = esc_html__( 'Pending Review', 'prayerpop' );
						$status_class = 'pp-status-pending';
					} elseif ( 'pending' === $status && $is_private_reviewed ) {
						$status_value = 'reviewed_private';
						$status_label = esc_html__( 'Reviewed', 'prayerpop' );
						$status_class = 'pp-status-private';
					} elseif ( 'archived' === $status ) {
						$status_value = 'archived';
						$status_label = esc_html__( 'Archived', 'prayerpop' );
						$status_class = 'pp-status-archived';
					}
				} elseif ( 'pending' === $status ) {
					$status_value = 'pending';
					$status_label = esc_html__( 'Pending Action', 'prayerpop' );
					$status_class = 'pp-status-pending';
				} else {
					$status_labels = array(
						'approved' => esc_html__( 'Approved', 'prayerpop' ),
						'answered' => esc_html__( 'Answered', 'prayerpop' ),
						'declined' => esc_html__( 'Declined', 'prayerpop' ),
						'archived' => esc_html__( 'Archived', 'prayerpop' ),
					);
					$status_value = (string) $status;
					$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( (string) $status );
					$status_class = 'pp-status-' . sanitize_html_class( (string) $status );
				}

				echo '<span class="pp-status-main">';
				if ( '' !== $status_value && '' !== $status_label && '' !== $status_class ) {
					echo wp_kses_post( $this->render_inline_badge(
						$post_id,
						'status',
						$status_value,
						$status_label,
						$status_class,
						$status_options,
						array(
							'answered-message' => $answered_message,
						)
					) );
				} else {
					echo '<span class="pp-stage-na" aria-hidden="true">—</span>';
				}

				echo '</span>';
                break;

            case 'date':
                $post_date = get_post_time( 'U', false, $post_id );
                $current_time = current_time( 'timestamp' );
                $time_diff = $current_time - $post_date;
                
                // Check if post is within last 24 hours (86400 seconds)
                $is_new = $time_diff <= DAY_IN_SECONDS;
                
                // Always show time format (HH:MM) using WordPress local time
                $formatted_time = get_the_time( 'H:i', $post_id );
                
                if ( $is_new ) {
                    echo '<span class="pp-date-new" title="' . esc_attr__( 'Submitted within last 24 hours', 'prayerpop' ) . '">';
                    echo '<strong>' . esc_html( $formatted_time ) . '</strong>';
                    echo ' <span class="pp-new-badge">' . esc_html__( 'NEW', 'prayerpop' ) . '</span>';
                    echo '</span>';
                } else {
                    echo '<span class="pp-date-normal">' . esc_html( $formatted_time ) . '</span>';
                }
                break;
                
	            case 'pp_actions':
	                $nonce = wp_create_nonce( 'prayer_pop_manage_request' );
		                $status = get_post_status( $post_id );
		                $is_public = get_post_meta( $post_id, 'prayer_pop_public', true );
		                $type = get_post_meta( $post_id, 'prayer_pop_type', true );
		                $is_private_reviewed = '1' === get_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, true );
	                $answered_message = (string) get_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY, true );
		                $can_moderate = \Prayer_Pop_Run::current_user_can_manage_submissions();
	                $redirect_to = $this->get_submission_list_redirect_url();
	                $archive_url = add_query_arg(
	                    array(
                        'action' => 'prayer_pop_archive',
                        'post'   => $post_id,
                        '_wpnonce' => $nonce,
                        'redirect_to' => $redirect_to,
                    ),
                    admin_url( 'admin-post.php' )
                );
                $unarchive_url = add_query_arg(
                    array(
                        'action' => 'prayer_pop_unarchive',
                        'post'   => $post_id,
                        '_wpnonce' => $nonce,
                        'redirect_to' => $redirect_to,
                    ),
                    admin_url( 'admin-post.php' )
                );

                $action_buttons = array();

                // Keep first slot stable: trash/restore.
                if ( $can_moderate && 'trash' === $status ) {
                    $restore_url = add_query_arg(
                        array(
                            'action'      => 'prayer_pop_restore',
                            'post'        => $post_id,
                            '_wpnonce'    => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );
                    $action_buttons[] = $this->render_action_icon_button( $restore_url, 'button button-small pp-restore-btn', 'restore', esc_html__( 'Restore', 'prayerpop' ) );
                } elseif ( $can_moderate ) {
                    $trash_url = add_query_arg(
                        array(
                            'action'      => 'prayer_pop_trash',
                            'post'        => $post_id,
                            '_wpnonce'    => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );
                    $action_buttons[] = $this->render_action_icon_button( $trash_url, 'button button-small pp-trash-btn', 'trash', esc_html__( 'Move to Trash', 'prayerpop' ) );
                }

                // Keep second slot stable: archive/unarchive toggle.
                if ( $can_moderate && 'archived' === $status ) {
                    $action_buttons[] = $this->render_action_icon_button( $unarchive_url, 'button button-small pp-unarchive-btn', 'unarchive', esc_html__( 'Unarchive', 'prayerpop' ) );
                } elseif ( $can_moderate && ! in_array( $status, array( 'archived', 'trash' ), true ) ) {
                    $action_buttons[] = $this->render_action_icon_button( $archive_url, 'button button-small pp-archive-btn', 'archive', esc_html__( 'Archive', 'prayerpop' ) );
                }

                // Context-specific actions start after fixed buttons.
	                if ( $can_moderate && 'pending' === $status && '1' === $is_public ) {
                    $approve_url = add_query_arg(
                        array(
                            'action' => 'prayer_pop_approve',
                            'post'   => $post_id,
                            '_wpnonce' => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );

                    $decline_url = add_query_arg(
                        array(
                            'action' => 'prayer_pop_decline',
                            'post'   => $post_id,
                            '_wpnonce' => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );

                    $action_buttons[] = $this->render_action_icon_button( $approve_url, 'button button-small pp-approve-btn', 'approve', esc_html__( 'Approve', 'prayerpop' ) );
                    $action_buttons[] = $this->render_action_icon_button( $decline_url, 'button button-small pp-decline-btn', 'decline', esc_html__( 'Decline', 'prayerpop' ) );
                } elseif ( $can_moderate && 'pending' === $status && '1' !== $is_public && ! $is_private_reviewed ) {
                    $viewed_url = add_query_arg(
                        array(
                            'action' => 'prayer_pop_mark_viewed',
                            'post'   => $post_id,
                            '_wpnonce' => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );

                    $action_buttons[] = $this->render_action_icon_button( $viewed_url, 'button button-small pp-viewed-btn', 'view', esc_html__( 'Mark as Reviewed', 'prayerpop' ) );
                } elseif ( $can_moderate && 'declined' === $status && '1' === $is_public ) {
                    $approve_url = add_query_arg(
                        array(
                            'action' => 'prayer_pop_approve',
                            'post'   => $post_id,
                            '_wpnonce' => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );

                    $action_buttons[] = $this->render_action_icon_button( $approve_url, 'button button-small pp-approve-btn', 'approve', esc_html__( 'Approve', 'prayerpop' ) );
                }

                if ( $can_moderate && '1' === $is_public && 'prayer_request' === $type && 'approved' === $status ) {
                    $answered_url = add_query_arg(
                        array(
                            'action'   => 'prayer_pop_mark_answered',
                            'post'     => $post_id,
                            '_wpnonce' => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );

                    $action_buttons[] = $this->render_action_icon_button(
                        $answered_url,
                        'button button-small pp-answer-btn pp-mark-answered-btn',
                        'answer',
                        esc_html__( 'Mark as Answered', 'prayerpop' ),
                        'data-current-message="' . esc_attr( $answered_message ) . '" data-mode="' . esc_attr( $status ) . '"'
                    );
                }

                if ( $can_moderate && '1' === $is_public && 'prayer_request' === $type && 'answered' === $status ) {
                    $unanswered_url = add_query_arg(
                        array(
                            'action'   => 'prayer_pop_mark_unanswered',
                            'post'     => $post_id,
                            '_wpnonce' => $nonce,
                            'redirect_to' => $redirect_to,
                        ),
                        admin_url( 'admin-post.php' )
                    );

                    $action_buttons[] = $this->render_action_icon_button( $unanswered_url, 'button button-small pp-unanswer-btn', 'unanswer', esc_html__( 'Remove answered prayer and move back to approved', 'prayerpop' ) );
	                }

	                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Buttons are generated by render_action_icon_button() with escaped URL/attrs; keep inline SVG icons intact.
	                echo '<div class="pp-actions-column">' . implode( '', $action_buttons ) . '</div>';
	                break;
	        }
	    }

    /**
     * Add custom row classes for styling.
     *
     * @param array $classes Post classes.
     * @param string $class Additional class.
     * @param int $post_id Post ID.
     * @return array Modified classes.
     */
    public function add_row_classes( $classes, $class, $post_id ) {
        if ( ! is_admin() || get_post_type( $post_id ) !== 'prayer_request' ) {
            return $classes;
        }

	        $status = get_post_status( $post_id );
	        $type = get_post_meta( $post_id, 'prayer_pop_type', true );
	        $is_public = get_post_meta( $post_id, 'prayer_pop_public', true );
	        $is_private_reviewed = '1' === get_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, true );
        
        // Add status class
        $classes[] = 'status-' . $status;
        
        // Add special class for private posts
	        if ( '1' !== $is_public ) {
	            $classes[] = 'status-private';
	            if ( $is_private_reviewed ) {
	                $classes[] = 'private-reviewed';
	            }
	        }
        
        // Add type class
        return $classes;
    }

    /**
     * Customize row actions for each prayer request.
     *
     * @param array   $actions Existing actions.
     * @param \WP_Post $post    Current post object.
     * @return array Modified actions.
     */
    public function modify_row_actions( $actions, $post ) {
        if ( 'prayer_request' !== $post->post_type ) {
            return $actions;
        }

        if ( ! \Prayer_Pop_Run::current_user_can_manage_submissions() ) {
            return $actions;
        }
        
	        $nonce = wp_create_nonce( 'prayer_pop_manage_request' );
	        $status = get_post_status( $post->ID );
	        $is_public = get_post_meta( $post->ID, 'prayer_pop_public', true );
	        $type = get_post_meta( $post->ID, 'prayer_pop_type', true );
	        $is_private_reviewed = '1' === get_post_meta( $post->ID, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, true );
	        $answered_message = (string) get_post_meta( $post->ID, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY, true );
	        $redirect_to = $this->get_submission_list_redirect_url();

        // Start with existing WordPress actions
        $new_actions = $actions;
        unset( $new_actions['edit'], $new_actions['inline hide'], $new_actions['view'], $new_actions['trash'], $new_actions['untrash'], $new_actions['delete'] );

        // Add custom approve/decline actions for pending posts (public ones only)
	        if ( 'pending' === $status && '1' === $is_public ) {
            $approve_url = add_query_arg(
                array(
                    'action' => 'prayer_pop_approve',
                    'post'   => $post->ID,
                    '_wpnonce' => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );

            $decline_url = add_query_arg(
                array(
                    'action' => 'prayer_pop_decline',
                    'post'   => $post->ID,
                    '_wpnonce' => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );

            // Add approve/decline at the beginning
            $new_actions = array_merge(
                array(
                    'approve' => '<a href="' . esc_url( $approve_url ) . '" class="pp-action-approve">' . esc_html__( 'Approve', 'prayerpop' ) . '</a>',
                    'decline' => '<a href="' . esc_url( $decline_url ) . '" class="pp-action-decline">' . esc_html__( 'Decline', 'prayerpop' ) . '</a>',
                ),
                $new_actions
            );
        }
        // Add "reviewed" action for pending private posts.
        if ( 'pending' === $status && '1' !== $is_public && ! $is_private_reviewed ) {
            $viewed_url = add_query_arg(
                array(
                    'action' => 'prayer_pop_mark_viewed',
                    'post'   => $post->ID,
                    '_wpnonce' => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );

            $new_actions = array_merge(
                array(
                    'reviewed_private' => '<a href="' . esc_url( $viewed_url ) . '" class="pp-action-viewed">' . esc_html__( 'Mark Reviewed', 'prayerpop' ) . '</a>',
                ),
                $new_actions
            );
        }
        
        // Add approve action for declined public posts.
	        if ( 'declined' === $status && '1' === $is_public ) {
	            $approve_url = add_query_arg(
                array(
                    'action' => 'prayer_pop_approve',
                    'post'   => $post->ID,
                    '_wpnonce' => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );

            // Add approve at the beginning
	            $new_actions = array_merge(
	                array(
	                    'approve' => '<a href="' . esc_url( $approve_url ) . '" class="pp-action-approve">' . esc_html__( 'Approve', 'prayerpop' ) . '</a>',
	                ),
	                $new_actions
	            );
	        }

	        if ( '1' === $is_public && 'prayer_request' === $type && 'approved' === $status ) {
	            $answered_url = add_query_arg(
	                array(
	                    'action'   => 'prayer_pop_mark_answered',
	                    'post'     => $post->ID,
	                    '_wpnonce' => $nonce,
	                    'redirect_to' => $redirect_to,
	                ),
	                admin_url( 'admin-post.php' )
	            );
	            $new_actions['mark_answered'] = '<a href="' . esc_url( $answered_url ) . '" class="pp-action-mark-answered pp-mark-answered-btn" data-current-message="' . esc_attr( $answered_message ) . '" data-mode="' . esc_attr( $status ) . '">' . esc_html__( 'Mark Answered', 'prayerpop' ) . '</a>';
	        }

	        if ( '1' === $is_public && 'prayer_request' === $type && 'answered' === $status ) {
	            $unanswered_url = add_query_arg(
	                array(
	                    'action'   => 'prayer_pop_mark_unanswered',
	                    'post'     => $post->ID,
	                    '_wpnonce' => $nonce,
	                    'redirect_to' => $redirect_to,
	                ),
	                admin_url( 'admin-post.php' )
	            );
	            $new_actions['mark_unanswered'] = '<a href="' . esc_url( $unanswered_url ) . '" class="pp-action-unanswered">' . esc_html__( 'Remove answered prayer and move back to approved', 'prayerpop' ) . '</a>';
	        }

	        // Archive action for all non-archived statuses.
	        if ( ! in_array( $status, array( 'archived', 'trash' ), true ) ) {
            $archive_url = add_query_arg(
                array(
                    'action' => 'prayer_pop_archive',
                    'post'   => $post->ID,
                    '_wpnonce' => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );

            $new_actions['archive'] = '<a href="' . esc_url( $archive_url ) . '" class="pp-action-archive">' . esc_html__( 'Archive', 'prayerpop' ) . '</a>';
        }
        // Unarchive action only for archived submissions.
        if ( 'archived' === $status ) {
            $unarchive_url = add_query_arg(
                array(
                    'action' => 'prayer_pop_unarchive',
                    'post'   => $post->ID,
                    '_wpnonce' => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );

            $new_actions['unarchive'] = '<a href="' . esc_url( $unarchive_url ) . '" class="pp-action-unarchive">' . esc_html__( 'Unarchive', 'prayerpop' ) . '</a>';
        }

        if ( 'trash' === $status ) {
            $restore_url = add_query_arg(
                array(
                    'action'      => 'prayer_pop_restore',
                    'post'        => $post->ID,
                    '_wpnonce'    => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );
            $new_actions['restore'] = '<a href="' . esc_url( $restore_url ) . '" class="pp-action-restore">' . esc_html__( 'Restore', 'prayerpop' ) . '</a>';
        } else {
            $trash_url = add_query_arg(
                array(
                    'action'      => 'prayer_pop_trash',
                    'post'        => $post->ID,
                    '_wpnonce'    => $nonce,
                    'redirect_to' => $redirect_to,
                ),
                admin_url( 'admin-post.php' )
            );
            $new_actions['trash'] = '<a href="' . esc_url( $trash_url ) . '" class="pp-action-trash">' . esc_html__( 'Move to Trash', 'prayerpop' ) . '</a>';
        }

        return $new_actions;
    }

    /**
     * Customize status filter labels to use English terms.
     *
     * @param array $views Status filter views.
     * @return array Modified views.
     */
    public function customize_status_filters( $views ) {
        global $wp_list_table;
        
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
        if ( 'prayer_request' !== $post_type ) {
            return $views;
        }

        $current_post_status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
        if ( '' === $current_post_status && isset( $_GET['prayer_request_status'] ) ) {
            $current_post_status = sanitize_key( wp_unslash( $_GET['prayer_request_status'] ) );
        }
        
        // Get post counts for each status
        $post_counts = wp_count_posts( 'prayer_request' );
        
        // Calculate pending counts.
        $all_pending_count = isset( $post_counts->pending ) ? (int) $post_counts->pending : 0;
        $pending_count     = $this->get_pending_count();
        
        // Build custom views with English labels
        $custom_views = array();
        
	        // All posts shown in default queue view (pending + approved + answered).
	        $total_active_posts = 0;
        
        // "All" should match visible list behavior: include all pending.
        $total_active_posts += $all_pending_count;
        
        // Add approved posts
	        if ( isset( $post_counts->approved ) ) {
	            $total_active_posts += $post_counts->approved;
	        }
	        if ( isset( $post_counts->answered ) ) {
	            $total_active_posts += $post_counts->answered;
	        }

        $class = ( '' === $current_post_status ) ? ' class="current"' : '';
        $custom_views['all'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request' ) ) . '"' . $class . '>' . 
                              sprintf(
								  /* translators: %s: number of submissions in the All view. */
								  esc_html__( 'All (%s)', 'prayerpop' ),
								  number_format_i18n( $total_active_posts )
							  ) . '</a>';
        
        // Pending posts
        if ( $pending_count > 0 ) {
            $class = ( 'pending' === $current_post_status ) ? ' class="current"' : '';
            $custom_views['pending'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request&post_status=pending' ) ) . '"' . $class . '>' . 
                                     sprintf(
										 /* translators: %s: number of pending submissions. */
										 esc_html__( 'Pending (%s)', 'prayerpop' ),
										 number_format_i18n( $pending_count )
									 ) . '</a>';
        }

        // Approved posts
        $approved_count = 0;
        if ( isset( $post_counts->approved ) ) {
            $approved_count += $post_counts->approved;
        }
        
	        if ( $approved_count > 0 ) {
	            $class = ( 'approved' === $current_post_status ) ? ' class="current"' : '';
	            $custom_views['approved'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request&post_status=approved' ) ) . '"' . $class . '>' . 
	                                      sprintf(
										  /* translators: %s: number of approved submissions. */
										  esc_html__( 'Approved (%s)', 'prayerpop' ),
										  number_format_i18n( $approved_count )
									  ) . '</a>';
	        }

	        if ( isset( $post_counts->answered ) && $post_counts->answered > 0 ) {
	            $class = ( 'answered' === $current_post_status ) ? ' class="current"' : '';
	            $custom_views['answered'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request&post_status=answered' ) ) . '"' . $class . '>' .
	                                      sprintf(
										  /* translators: %s: number of answered submissions. */
										  esc_html__( 'Answered (%s)', 'prayerpop' ),
										  number_format_i18n( $post_counts->answered )
									  ) . '</a>';
	        }

        // Declined posts
        if ( isset( $post_counts->declined ) && $post_counts->declined > 0 ) {
            $class = ( 'declined' === $current_post_status ) ? ' class="current"' : '';
            $custom_views['declined'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request&post_status=declined' ) ) . '"' . $class . '>' . 
                                      sprintf(
									  /* translators: %s: number of declined submissions. */
									  esc_html__( 'Declined (%s)', 'prayerpop' ),
									  number_format_i18n( $post_counts->declined )
								  ) . '</a>';
        }
        
        // Archived posts
        if ( isset( $post_counts->archived ) && $post_counts->archived > 0 ) {
            $class = ( 'archived' === $current_post_status ) ? ' class="current"' : '';
            $custom_views['archived'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request&post_status=archived' ) ) . '"' . $class . '>' . 
                                      sprintf(
									  /* translators: %s: number of archived submissions. */
									  esc_html__( 'Archived (%s)', 'prayerpop' ),
									  number_format_i18n( $post_counts->archived )
								  ) . '</a>';
        }
        
        // Trash posts
        if ( $post_counts->trash > 0 ) {
            $class = ( 'trash' === $current_post_status ) ? ' class="current"' : '';
            $custom_views['trash'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=prayer_request&post_status=trash' ) ) . '"' . $class . '>' . 
                                   sprintf(
								   /* translators: %s: number of trashed submissions. */
								   esc_html__( 'Trash (%s)', 'prayerpop' ),
								   number_format_i18n( $post_counts->trash )
							   ) . '</a>';
        }
        
        return $custom_views;
    }

    /**
     * Get count of pending posts (including private ones).
     *
     * @return int Count of pending posts.
     */
    private function get_pending_count() {
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
     * Meta query clause for unresolved pending items.
     *
     * @return array
     */
	    private function get_pending_queue_meta_clause() {
	        return \Prayer_Pop_Run::build_pending_queue_meta_clause();
	    }

    /**
     * Enqueue admin styles for the list table.
     */
    public function enqueue_styles( $hook ) {
        if ( 'edit.php' !== $hook ) {
            return;
		}

        $screen = get_current_screen();
        if ( isset( $screen->post_type ) && 'prayer_request' === $screen->post_type ) {
            wp_enqueue_style( 'prayer-pop-admin-list', PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-admin-list.css', array(), PRAYERPOP_VERSION );
            wp_enqueue_script(
                'prayer-pop-admin-bulk-email',
                PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-admin-bulk-email.js',
                array(),
                PRAYERPOP_VERSION,
                true
            );
            wp_enqueue_script(
                'prayer-pop-admin-tour',
                PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-admin-tour.js',
                array(),
                PRAYERPOP_VERSION,
                true
            );

	            wp_localize_script(
	                'prayer-pop-admin-bulk-email',
	                'prayerPopBulkEmail',
	                array(
	                    'fieldLabel'   => esc_html__( 'Recipient email', 'prayerpop' ),
	                    'fieldPlaceholder' => esc_html__( 'name@example.com', 'prayerpop' ),
	                    'invalidEmail' => esc_html__( 'Please enter a valid recipient email address.', 'prayerpop' ),
	                    'answerPromptNew' => esc_html__( 'Optional answer update message (shown on the prayer card):', 'prayerpop' ),
	                    'answerPromptEdit' => esc_html__( 'Update the answered prayer message (leave empty to remove it):', 'prayerpop' ),
	                    'answerPromptBulk' => esc_html__( 'Optional answered prayer message for selected items (leave empty to keep current messages):', 'prayerpop' ),
	                    'answerModalTitleNew' => esc_html__( 'Mark Prayer as Answered', 'prayerpop' ),
	                    'answerModalTitleEdit' => esc_html__( 'Update Answered Prayer', 'prayerpop' ),
	                    'answerModalTitleBulk' => esc_html__( 'Bulk Mark as Answered', 'prayerpop' ),
	                    'answerModalDescriptionNew' => esc_html__( 'Optional answer update message shown on the prayer card.', 'prayerpop' ),
	                    'answerModalDescriptionEdit' => esc_html__( 'Update the answered prayer message. Leave empty to remove it.', 'prayerpop' ),
	                    'answerModalDescriptionBulk' => esc_html__( 'Add optional answer notes for selected prayer requests. You can fill only the ones you need.', 'prayerpop' ),
	                    'answerModalCancel' => esc_html__( 'Cancel', 'prayerpop' ),
	                    'answerModalSave' => esc_html__( 'Save', 'prayerpop' ),
	                    'answerModalNoSelection' => esc_html__( 'Please select at least one submission first.', 'prayerpop' ),
	                    'answerModalSubmissionLabel' => esc_html__( 'Submission', 'prayerpop' ),
	                    'answerModalNoteLabel' => esc_html__( 'Answered note', 'prayerpop' ),
	                    'bulkEditModalTitle' => esc_html__( 'Bulk Edit Submissions', 'prayerpop' ),
	                    'bulkEditModalDescription' => esc_html__( 'Edit selected submissions here. Leave Name empty to keep the item anonymous.', 'prayerpop' ),
	                    'bulkEditNoSelection' => esc_html__( 'Please select at least one submission first.', 'prayerpop' ),
	                    'bulkEditNameLabel' => esc_html__( 'Name', 'prayerpop' ),
	                    'bulkEditNamePlaceholder' => esc_html__( 'Leave empty for Anonymous', 'prayerpop' ),
	                    'bulkEditTextLabel' => esc_html__( 'Submission text', 'prayerpop' ),
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'inlineNonce' => wp_create_nonce( 'prayer_pop_inline_badge_edit' ),
						'inlineSaveError' => esc_html__( 'Could not update this value. Please try again.', 'prayerpop' ),
						'inlineInvalid' => esc_html__( 'Invalid update option.', 'prayerpop' ),
	                        'inlineTextSave' => esc_html__( 'Save', 'prayerpop' ),
	                        'inlineTextCancel' => esc_html__( 'Cancel', 'prayerpop' ),
	                        'inlineTextSaveError' => esc_html__( 'Could not save this text. Please try again.', 'prayerpop' ),
	                        'compactViewOnLabel' => esc_html__( 'Compact On', 'prayerpop' ),
	                        'compactViewOffLabel' => esc_html__( 'Compact Off', 'prayerpop' ),
	                        'compactViewOnTitle' => esc_html__( 'Switch to full submission text', 'prayerpop' ),
	                        'compactViewOffTitle' => esc_html__( 'Switch to compact submission text', 'prayerpop' ),
		                )
		            );
            wp_localize_script(
                'prayer-pop-admin-tour',
                'prayerPopAdminTour',
                array(
                    'enabled'      => true,
                    'settingsUrl'  => admin_url( 'admin.php?page=prayer-pop-settings' ),
                    'docsUrl'      => admin_url( 'admin.php?page=prayer-pop-settings&tab=documentation' ),
                    'buttonLabel'  => esc_html__( 'How to Use', 'prayerpop' ),
                    'nudgeText'    => esc_html__( 'New here? Click How to Use for a quick walkthrough of this screen.', 'prayerpop' ),
                    'titlePrefix'  => esc_html__( 'Step', 'prayerpop' ),
                    'ofLabel'      => esc_html__( 'of', 'prayerpop' ),
                    'backLabel'    => esc_html__( 'Back', 'prayerpop' ),
                    'nextLabel'    => esc_html__( 'Next', 'prayerpop' ),
                    'doneLabel'    => esc_html__( 'Done', 'prayerpop' ),
                    'closeLabel'   => esc_html__( 'Close', 'prayerpop' ),
                    'demoActionNotice' => esc_html__( 'Submission updated successfully.', 'prayerpop' ),
                    'demoName'     => esc_html__( 'Demo User', 'prayerpop' ),
                    'demoMessage'  => esc_html__( 'Please pray for wisdom and peace in my family this week.', 'prayerpop' ),
                    'demoDateText' => esc_html__( 'Last Modified', 'prayerpop' ),
                    'demoDateValue' => esc_html__( '2026/03/13 at 10:30 am', 'prayerpop' ),
                    'demoTypeRequest' => esc_html__( 'Prayer Request', 'prayerpop' ),
                    'demoVisibilityPublic' => esc_html__( 'Public', 'prayerpop' ),
                    'demoStatusPending' => esc_html__( 'Pending Action', 'prayerpop' ),
                    'demoStatusApproved' => esc_html__( 'Approved', 'prayerpop' ),
                    'demoStatusAnswered' => esc_html__( 'Answered', 'prayerpop' ),
                    'demoStatusDeclined' => esc_html__( 'Declined', 'prayerpop' ),
                    'demoStatusArchived' => esc_html__( 'Archived', 'prayerpop' ),
                    'demoActionEdit' => esc_html__( 'Edit', 'prayerpop' ),
                    'demoActionTrash' => esc_html__( 'Move to Trash', 'prayerpop' ),
                    'demoActionArchive' => esc_html__( 'Archive', 'prayerpop' ),
                    'demoActionApprove' => esc_html__( 'Approve', 'prayerpop' ),
                    'demoActionDecline' => esc_html__( 'Decline', 'prayerpop' ),
                    'steps'        => array(
                        array(
                            'selector' => '.wrap .pp-admin-tour-temp-notice',
                            'selectorAll' => true,
                            'selectorLimit' => 2,
                            'title'    => esc_html__( 'Notifications', 'prayerpop' ),
                            'body'     => esc_html__( 'Important results and action confirmations show here, including updates after you edit, approve, decline, archive, or trash submissions. Keep this area visible when processing submissions.', 'prayerpop' ),
                        ),
                        array(
                            'selector' => '.subsubsub',
                            'title'    => esc_html__( 'Quick Filters', 'prayerpop' ),
                            'body'     => esc_html__( 'Use quick filters for one-click queues like Pending, Approved, Declined, Archived, or Trash.', 'prayerpop' ),
                        ),
                        array(
                            'selector' => '.tablenav.top .alignleft.actions:not(.bulkactions)',
                            'title'    => esc_html__( 'Search and Advanced Filters', 'prayerpop' ),
                            'body'     => esc_html__( 'Use search, date, status, visibility, and type filters to narrow down exactly what you need.', 'prayerpop' ),
                        ),
                        array(
                            'selector' => '.tablenav.top .bulkactions',
                            'title'    => esc_html__( 'Bulk Actions', 'prayerpop' ),
	                            'body'     => sprintf(
	                                /* translators: %s: settings link */
	                                __(
	                                    '<ul>' .
										'<li><strong>Print Submissions</strong><br>Create a print view (or save PDF) from selected submissions.</li>' .
										'<li><strong>Send via Email</strong><br>Send selected submissions to a specific email address. You can update the email template in %s.</li>' .
										'<li><strong>Approve selected</strong><br>Approve selected prayer requests.</li>' .
										'<li><strong>Decline selected</strong><br>Decline selected prayer requests.</li>' .
										'<li><strong>Mark prayer as answered</strong><br>Move selected approved prayer requests to Answered (with optional answer note).</li>' .
										'<li><strong>Edit selected</strong><br>Edit names and submission text for selected rows in one modal.</li>' .
										'<li><strong>Archive</strong><br>Remove selected submissions from active workflow while keeping history.</li>' .
										'<li><strong>Move to Trash</strong><br>Move selected submissions to Trash.</li>' .
										'</ul>',
										'prayerpop'
									),
	                                '<a href="' . esc_url( admin_url( 'admin.php?page=prayer-pop-settings&tab=notifications' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Notifications > Email Template Settings', 'prayerpop' ) . '</a>'
	                            ),
                        ),
                        array(
                            'selector' => 'th#pp_name, .pp-tour-demo-row td.column-pp_name',
                            'selectorAll' => true,
                            'selectorLimit' => 2,
                            'title'    => esc_html__( 'Submissions: Name and Text Editing', 'prayerpop' ),
                            'body'     => esc_html__( 'This area shows the column title, name, and submission text. Click the name to edit the person name, or click the submission text to edit the message inline. For answered prayers, you can also edit the answer update text here. After changes, click Save.', 'prayerpop' ),
                            'before'   => 'open_inline_submission_text',
                        ),
                        array(
                            'selector' => '.pp-tour-demo-row td.column-pp_type .pp-inline-editable, .pp-tour-demo-row td.column-pp_visibility .pp-inline-editable, .pp-tour-demo-row td.column-pp_status .pp-inline-editable',
                            'selectorAll' => true,
                            'selectorLimit' => 3,
                            'selectorSecondary' => '.pp-tour-badge-menu-preview',
                            'selectorSecondaryAll' => true,
                            'selectorSecondaryLimit' => 3,
                            'title'    => esc_html__( 'Type, Visibility, and Status', 'prayerpop' ),
                            'body'     => sprintf(
                                /* translators: %s: documentation link */
                                __(
                                    '<ul><li><strong>Type</strong><br>The Type label identifies prayer requests.</li><li><strong>Visibility</strong><br>The Visibility label shows whether a prayer request is public.</li><li><strong>Status</strong><br>Click the Status label to move the item through Pending, Approved, Answered, Declined, or Archived.</li></ul><p>For workflow details and examples, see %s.</p>', 'prayerpop' ),
                                '<a href="' . esc_url( admin_url( 'admin.php?page=prayer-pop-settings&tab=documentation' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'prayerpop' ) . '</a>'
                            ),
                            'before'   => 'open_inline_core_badges',
                        ),
                        array(
                            'selector' => '.pp-tour-demo-row td.column-pp_actions .pp-actions-column',
                            'title'    => esc_html__( 'Action Buttons', 'prayerpop' ),
                            'body'     => esc_html__( 'This column shows relevant quick actions for each submission, such as edit, approve, decline, archive, trash, restore, or mark a prayer as answered.', 'prayerpop' ),
                        ),
                        array(
                            'selector' => '#show-settings-link',
                            'title'    => esc_html__( 'Screen Options Button', 'prayerpop' ),
                            'body'     => esc_html__( 'Open Screen Options to control which columns you want to see and how many submissions are shown per page.', 'prayerpop' ),
                            'before'   => 'open_screen_options',
                        ),
                        array(
                            'selector' => '#screen-options-wrap',
                            'title'    => esc_html__( 'Screen Options Panel', 'prayerpop' ),
                            'body'     => esc_html__( 'This panel lets you tune the admin table view for your team workflow.', 'prayerpop' ),
                            'before'   => 'open_screen_options',
                        ),
                        array(
                            'selector' => '',
                            'title'    => esc_html__( 'Need More Help?', 'prayerpop' ),
                            'body'     => sprintf(
                                /* translators: 1: settings link, 2: documentation link */
                                __( 'Open %1$s for workflow setup, styling, notifications, and email template options. For a feature overview and practical usage examples, open %2$s.', 'prayerpop' ),
                                '<a href="' . esc_url( admin_url( 'admin.php?page=prayer-pop-settings' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Settings', 'prayerpop' ) . '</a>',
                                '<a href="' . esc_url( admin_url( 'admin.php?page=prayer-pop-settings&tab=documentation' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'prayerpop' ) . '</a>'
                            ),
                        ),
                    ),
                )
            );
	        }
	    }

	/**
	 * Render linked brand logo on submissions page footer.
	 *
	 * @return void
	 */
	public function render_submissions_footer_logo( $which = '' ) {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-prayer_request' !== $screen->id || 'bottom' !== $which ) {
			return;
		}
		?>
		<div class="prayer-pop-submissions-logo-row" aria-hidden="true">
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
	 * Handle inline updates from clickable badges in the submissions table.
	 *
	 * @return void
	 */
	public function handle_inline_update_submission_field() {
		check_ajax_referer( 'prayer_pop_inline_badge_edit', 'nonce' );

		if ( ! \Prayer_Pop_Run::current_user_can_manage_submissions() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to update submissions.', 'prayerpop' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$field   = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value   = isset( $_POST['value'] ) ? sanitize_key( wp_unslash( $_POST['value'] ) ) : '';
        $text_fields = array( 'name_text', 'submission_text', 'answered_note' );
        $is_text_field = in_array( $field, $text_fields, true );
        $text_value_input = '';
        if ( $is_text_field && isset( $_POST['text_value'] ) ) {
            $text_value_input = trim( sanitize_textarea_field( wp_unslash( $_POST['text_value'] ) ) );
        }
		$has_answered_message = array_key_exists( 'answered_message', $_POST );
		$answered_message_input = $has_answered_message ? sanitize_textarea_field( wp_unslash( $_POST['answered_message'] ) ) : '';
		$answered_message_input = trim( $answered_message_input );
		if ( $post_id <= 0 || '' === $field || 'prayer_request' !== get_post_type( $post_id ) || ( ! $is_text_field && '' === $value ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid update request.', 'prayerpop' ) ), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to edit this submission.', 'prayerpop' ) ), 403 );
		}

		$current_status = (string) get_post_status( $post_id );
		$current_type   = sanitize_key( (string) get_post_meta( $post_id, 'prayer_pop_type', true ) );
		$is_public      = '1' === (string) get_post_meta( $post_id, 'prayer_pop_public', true );

        if ( 'name_text' === $field ) {
            if ( '' === $text_value_input ) {
                delete_post_meta( $post_id, 'prayer_pop_name' );
            } else {
                update_post_meta( $post_id, 'prayer_pop_name', sanitize_text_field( $text_value_input ) );
            }
        } elseif ( 'submission_text' === $field ) {
            if ( '' === $text_value_input ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Submission text cannot be empty.', 'prayerpop' ) ), 400 );
            }

            wp_update_post(
                array(
                    'ID'           => $post_id,
                    'post_content' => $text_value_input,
                )
            );
        } elseif ( 'answered_note' === $field ) {
            $effective_type = ( '' !== $current_type ) ? $current_type : 'prayer_request';
            if ( 'answered' !== $current_status ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Only answered prayers can store an answer update.', 'prayerpop' ) ), 400 );
            }
            if ( 'prayer_request' !== $effective_type ) {
                wp_send_json_error( array( 'message' => esc_html__( 'Answered note is available only for prayer requests.', 'prayerpop' ) ), 400 );
            }

            if ( '' === $text_value_input ) {
                delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY );
            } else {
                update_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY, $text_value_input );
            }
		} elseif ( 'type' === $field ) {
			$value = 'prayer_request';

			if ( 'prayer_request' !== $value ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid type value.', 'prayerpop' ) ), 400 );
			}

			update_post_meta( $post_id, 'prayer_pop_type', $value );
			if ( false ) {
				if ( 'answered' === $current_status ) {
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'approved',
						)
					);
				}
				delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_AT_META_KEY );
				delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY );
			} else {
				if ( '' === (string) get_post_meta( $post_id, 'i_prayed_count', true ) ) {
					update_post_meta( $post_id, 'i_prayed_count', 0 );
				}
				if ( '' === (string) get_post_meta( $post_id, 'celebrate_count', true ) ) {
					update_post_meta( $post_id, 'celebrate_count', 0 );
				}
			}
		} elseif ( 'visibility' === $field ) {
			$value = 'public';

			if ( 'public' !== $value ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid visibility value.', 'prayerpop' ) ), 400 );
			}

			$new_public = ( 'public' === $value ) ? '1' : '0';
			update_post_meta( $post_id, 'prayer_pop_public', $new_public );
			if ( false ) {
				update_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, '0' );
			} else {
				delete_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY );
			}

			if ( ! in_array( $current_status, array( 'archived', 'trash' ), true ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'pending',
					)
				);
			}
			delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_AT_META_KEY );
			delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY );

			// If this item is archived, preserve archived status but normalize restore target.
			if ( 'archived' === $current_status ) {
				update_post_meta( $post_id, \Prayer_Pop_Run::PRE_ARCHIVE_STATUS_META_KEY, 'pending' );
			}
		} elseif ( 'status' === $field ) {
			$type = ( '' !== $current_type ) ? $current_type : 'prayer_request';
			if ( $is_public ) {
				$allowed = array( 'pending', 'approved', 'declined', 'archived' );
				if ( 'prayer_request' === $type ) {
					$allowed[] = 'answered';
				}
				if ( ! in_array( $value, $allowed, true ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Invalid status value for this submission.', 'prayerpop' ) ), 400 );
				}

				if ( 'archived' === $value ) {
					if ( ! in_array( $current_status, array( 'archived', 'trash' ), true ) ) {
						update_post_meta( $post_id, \Prayer_Pop_Run::PRE_ARCHIVE_STATUS_META_KEY, sanitize_key( $current_status ) );
						wp_update_post(
							array(
								'ID'          => $post_id,
								'post_status' => 'archived',
							)
						);
						update_post_meta( $post_id, \Prayer_Pop_Run::ARCHIVED_AT_META_KEY, current_time( 'timestamp' ) );
					}
				} else {
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => $value,
						)
					);
					delete_post_meta( $post_id, \Prayer_Pop_Run::ARCHIVED_AT_META_KEY );
					delete_post_meta( $post_id, \Prayer_Pop_Run::PRE_ARCHIVE_STATUS_META_KEY );
				}

				delete_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY );
				if ( 'answered' === $value ) {
					update_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_AT_META_KEY, current_time( 'timestamp' ) );
					if ( $has_answered_message ) {
						if ( '' === $answered_message_input ) {
							delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY );
						} else {
							update_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY, $answered_message_input );
						}
					}
				} else {
					delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_AT_META_KEY );
					delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY );
				}
			} else {
				$allowed_private = array( 'pending_private', 'reviewed_private', 'archived' );
				if ( ! in_array( $value, $allowed_private, true ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Invalid status value for private submission.', 'prayerpop' ) ), 400 );
				}

				delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_AT_META_KEY );
				delete_post_meta( $post_id, \Prayer_Pop_Run::ANSWERED_MESSAGE_META_KEY );

				if ( 'archived' === $value ) {
					if ( ! in_array( $current_status, array( 'archived', 'trash' ), true ) ) {
						update_post_meta( $post_id, \Prayer_Pop_Run::PRE_ARCHIVE_STATUS_META_KEY, sanitize_key( $current_status ) );
						wp_update_post(
							array(
								'ID'          => $post_id,
								'post_status' => 'archived',
							)
						);
						update_post_meta( $post_id, \Prayer_Pop_Run::ARCHIVED_AT_META_KEY, current_time( 'timestamp' ) );
					}
				} else {
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'pending',
						)
					);
					delete_post_meta( $post_id, \Prayer_Pop_Run::ARCHIVED_AT_META_KEY );
					delete_post_meta( $post_id, \Prayer_Pop_Run::PRE_ARCHIVE_STATUS_META_KEY );
					if ( 'reviewed_private' === $value ) {
						update_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, '1' );
					} else {
						update_post_meta( $post_id, \Prayer_Pop_Run::PRIVATE_REVIEWED_META_KEY, '0' );
					}
				}
			}
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Unsupported inline field.', 'prayerpop' ) ), 400 );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Submission updated.', 'prayerpop' ) ) );
	}

	/**
	 * Build dropdown option data for an inline-editable badge.
	 *
	 * @param string $field   Field id (type|visibility|status).
	 * @param int    $post_id Submission ID.
	 * @return array<int, array{value:string,label:string,badge_class:string}>
	 */
	private function get_inline_field_options( $field, $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return array();
		}

		$field = sanitize_key( (string) $field );
		if ( 'type' === $field ) {
			$labels          = get_option( 'prayer_pop_texts', array() );
			$prayer_label    = isset( $labels['text_prayer_request_label'] ) ? $labels['text_prayer_request_label'] : esc_html__( 'Prayer Request', 'prayerpop' );
			$options = array(
				array(
					'value'      => 'prayer_request',
					'label'      => (string) $prayer_label,
					'badge_class'=> 'pp-type-request',
				),
			);

			return $options;
		}

		if ( 'visibility' === $field ) {
			$options = array(
				array(
					'value'      => 'public',
					'label'      => esc_html__( 'Public', 'prayerpop' ),
					'badge_class'=> 'pp-visibility-public',
				),
			);

			return $options;
		}

		if ( 'status' === $field ) {
			$is_public = '1' === (string) get_post_meta( $post_id, 'prayer_pop_public', true );
			$type      = sanitize_key( (string) get_post_meta( $post_id, 'prayer_pop_type', true ) );
			if ( $is_public ) {
				$options = array(
					array(
						'value'      => 'pending',
						'label'      => esc_html__( 'Pending Action', 'prayerpop' ),
						'badge_class'=> 'pp-status-pending',
					),
					array(
						'value'      => 'approved',
						'label'      => esc_html__( 'Approved', 'prayerpop' ),
						'badge_class'=> 'pp-status-approved',
					),
					array(
						'value'      => 'declined',
						'label'      => esc_html__( 'Declined', 'prayerpop' ),
						'badge_class'=> 'pp-status-declined',
					),
					array(
						'value'      => 'archived',
						'label'      => esc_html__( 'Archived', 'prayerpop' ),
						'badge_class'=> 'pp-status-archived',
					),
				);
				if ( 'prayer_request' === $type ) {
					$options[] = array(
						'value'      => 'answered',
						'label'      => esc_html__( 'Answered', 'prayerpop' ),
						'badge_class'=> 'pp-status-answered',
					);
				}
				return $options;
			}

			return array(
				array(
					'value'      => 'pending_private',
					'label'      => esc_html__( 'Pending Review', 'prayerpop' ),
					'badge_class'=> 'pp-status-pending',
				),
				array(
					'value'      => 'reviewed_private',
					'label'      => esc_html__( 'Reviewed', 'prayerpop' ),
					'badge_class'=> 'pp-status-private',
				),
				array(
					'value'      => 'archived',
					'label'      => esc_html__( 'Archived', 'prayerpop' ),
					'badge_class'=> 'pp-status-archived',
				),
			);
		}

		return array();
	}

	/**
	 * Render a badge that can open an inline dropdown editor.
	 *
	 * @param int    $post_id     Submission ID.
	 * @param string $field       Field id.
	 * @param string $value       Current value.
	 * @param string $label       Visible label.
	 * @param string $badge_class Badge class suffix.
	 * @param array  $options     Dropdown options.
	 * @return string
	 */
	private function render_inline_badge( $post_id, $field, $value, $label, $badge_class, $options = array(), $extra_data = array() ) {
		$post_id     = absint( $post_id );
		$field       = sanitize_key( (string) $field );
		$value       = sanitize_key( (string) $value );
		$label       = (string) $label;
		$badge_class = trim( (string) $badge_class );
		$classes     = trim( 'pp-badge ' . $badge_class );

		if ( ! \Prayer_Pop_Run::current_user_can_manage_submissions() || empty( $options ) ) {
			return '<span class="' . esc_attr( $classes ) . '">' . esc_html( $label ) . '</span>';
		}

		$options_json = wp_json_encode( array_values( $options ) );
		if ( false === $options_json ) {
			$options_json = '[]';
		}

		$extra_attrs = '';
		if ( is_array( $extra_data ) ) {
			foreach ( $extra_data as $data_key => $data_value ) {
				$data_key = sanitize_key( (string) $data_key );
				if ( '' === $data_key ) {
					continue;
				}
				$extra_attrs .= ' data-' . esc_attr( $data_key ) . '="' . esc_attr( (string) $data_value ) . '"';
			}
		}

		return '<button type="button" class="' . esc_attr( $classes ) . ' pp-inline-editable" data-post-id="' . esc_attr( $post_id ) . '" data-field="' . esc_attr( $field ) . '" data-value="' . esc_attr( $value ) . '" data-options="' . esc_attr( $options_json ) . '"' . $extra_attrs . ' aria-haspopup="true" aria-expanded="false">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Render an actions-column icon button with custom tooltip UI.
	 *
	 * @param string $url              Action URL.
	 * @param string $class_names      CSS classes for the anchor.
	 * @param string $icon             Icon key.
	 * @param string $label            Tooltip/aria label text.
	 * @param string $extra_attributes Optional pre-escaped HTML attributes.
	 * @return string
	 */
	private function render_action_icon_button( $url, $class_names, $icon, $label, $extra_attributes = '' ) {
		$class_names = trim( preg_replace( '/\s+/', ' ', (string) $class_names ) );
		$label       = (string) $label;
		$icon        = (string) $icon;
		$attrs       = trim( (string) $extra_attributes );
		$icon_markup = $this->get_action_icon_svg( $icon );
		if ( '' !== $attrs ) {
			$attrs = ' ' . $attrs;
		}

		return '<span class="pp-action-tooltip-wrap"><a href="' . esc_url( $url ) . '" class="' . esc_attr( $class_names ) . '" aria-label="' . esc_attr( $label ) . '"' . $attrs . '>' . $icon_markup . '</a><span class="pp-action-tooltip" role="tooltip">' . esc_html( $label ) . '</span></span>';
	}

	/**
	 * Return an inline SVG icon for action buttons (Tabler style).
	 *
	 * @param string $icon Icon key.
	 * @return string
	 */
	private function get_action_icon_svg( $icon ) {
		$icon  = sanitize_key( (string) $icon );
		$paths = '';

		switch ( $icon ) {
			case 'edit':
				$paths = '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>';
				break;
			case 'trash':
				$paths = '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>';
				break;
			case 'archive':
				$paths = '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>';
				break;
			case 'unarchive':
				$paths = '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M12 16V10"/><path d="m9.5 12.5 2.5-2.5 2.5 2.5"/>';
				break;
			case 'restore':
				$paths = '<path d="M9 14l-4-4 4-4"/><path d="M5 10h11a4 4 0 1 1 0 8h-1"/>';
				break;
			case 'approve':
				$paths = '<path d="M20 6L9 17l-5-5"/>';
				break;
			case 'decline':
				$paths = '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>';
				break;
			case 'view':
				$paths = '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>';
				break;
			case 'answer':
				$paths = '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 12 2 2 4-4"/>';
				break;
			case 'unanswer':
				$paths = '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/>';
				break;
			default:
				$paths = '<circle cx="12" cy="12" r="3"/>';
				break;
		}

		return '<svg class="pp-action-icon-svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
	}

    /**
     * Order prayer requests so that pending requests appear first.
     *
     * @param string   $orderby Existing ORDER BY clause.
     * @param \WP_Query $query   Current query instance.
     * @return string Custom ORDER BY SQL.
     */
    public function order_admin_list( $orderby, $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return $orderby;
        }

        if ( 'prayer_request' !== $query->get( 'post_type' ) ) {
            return $orderby;
        }

		global $wpdb;
		$posts = $wpdb->posts;
		$meta  = $wpdb->postmeta;
		$now   = time();

		$requested_orderby = $query->get( 'orderby' );
		if ( is_array( $requested_orderby ) ) {
			$requested_orderby = (string) key( $requested_orderby );
		}
		$requested_orderby = sanitize_key( (string) $requested_orderby );

		$requested_order = strtoupper( (string) $query->get( 'order' ) );
		if ( ! in_array( $requested_order, array( 'ASC', 'DESC' ), true ) ) {
			$requested_order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		}
		$direction = ( 'ASC' === $requested_order ) ? 'ASC' : 'DESC';

		// Pin unresolved pending queue first (matches pending tab logic):
		// - public pending that is not waiting for scheduled release
		// - private pending not marked as reviewed
		$pending_rank_sql = "CASE
			WHEN {$posts}.post_status = 'pending'
			AND (
				(
					EXISTS (
						SELECT 1 FROM {$meta} pm_public
						WHERE pm_public.post_id = {$posts}.ID
						AND pm_public.meta_key = 'prayer_pop_public'
						AND pm_public.meta_value = '1'
					)
					AND
					(
						NOT EXISTS (
							SELECT 1 FROM {$meta} pm_release_missing
							WHERE pm_release_missing.post_id = {$posts}.ID
						)
						OR EXISTS (
							SELECT 1 FROM {$meta} pm_release_ready
							WHERE pm_release_ready.post_id = {$posts}.ID
							AND CAST(pm_release_ready.meta_value AS UNSIGNED) <= {$now}
						)
					)
				)
				OR
				(
					(
						EXISTS (
							SELECT 1 FROM {$meta} pm_private
							WHERE pm_private.post_id = {$posts}.ID
							AND pm_private.meta_key = 'prayer_pop_public'
							AND pm_private.meta_value = '0'
						)
						OR NOT EXISTS (
							SELECT 1 FROM {$meta} pm_private_missing
							WHERE pm_private_missing.post_id = {$posts}.ID
							AND pm_private_missing.meta_key = 'prayer_pop_public'
						)
					)
					AND
					(
						NOT EXISTS (
							SELECT 1 FROM {$meta} pm_review_missing
							WHERE pm_review_missing.post_id = {$posts}.ID
							AND pm_review_missing.meta_key = 'prayer_pop_private_reviewed'
						)
						OR EXISTS (
							SELECT 1 FROM {$meta} pm_review_open
							WHERE pm_review_open.post_id = {$posts}.ID
							AND pm_review_open.meta_key = 'prayer_pop_private_reviewed'
							AND pm_review_open.meta_value != '1'
						)
					)
				)
			) THEN 0
			ELSE 1
		END";

		// Default load/date sort: unresolved pending queue first, then "Last Modified".
		if ( '' === $requested_orderby || 'date' === $requested_orderby ) {
			return "{$pending_rank_sql} ASC, {$posts}.post_modified_gmt {$direction}, {$posts}.ID {$direction}";
		}

		if ( 'pp_status' === $requested_orderby ) {
			$status_rank_sql = "CASE
				WHEN {$posts}.post_status = 'pending' THEN 1
				WHEN {$posts}.post_status IN ('approved','answered') THEN 2
				WHEN {$posts}.post_status = 'declined' THEN 3
				WHEN {$posts}.post_status = 'archived' THEN 4
				ELSE 5
			END";
			return "{$status_rank_sql} {$direction}, {$posts}.post_modified_gmt DESC, {$posts}.ID DESC";
		}

		if ( 'pp_visibility' === $requested_orderby ) {
			$visibility_rank_sql = "CASE
				WHEN EXISTS (
					SELECT 1 FROM {$meta} pm_vis_public
					WHERE pm_vis_public.post_id = {$posts}.ID
					AND pm_vis_public.meta_key = 'prayer_pop_public'
					AND pm_vis_public.meta_value = '1'
				) THEN 0
				ELSE 1
			END";
			return "{$visibility_rank_sql} {$direction}, {$posts}.post_modified_gmt DESC, {$posts}.ID DESC";
		}

		if ( 'pp_type' === $requested_orderby ) {
			$type_rank_sql = "CASE
				WHEN EXISTS (
					SELECT 1 FROM {$meta} pm_type_prayer
					WHERE pm_type_prayer.post_id = {$posts}.ID
					AND pm_type_prayer.meta_key = 'prayer_pop_type'
					AND pm_type_prayer.meta_value = 'prayer_request'
				) THEN 0
				WHEN EXISTS (
					SELECT 1 FROM {$meta} pm_type_request
					WHERE pm_type_request.post_id = {$posts}.ID
					AND pm_type_request.meta_key = 'prayer_pop_type'
					AND pm_type_request.meta_value = 'prayer_request'
				) THEN 1
				ELSE 2
			END";
			return "{$type_rank_sql} {$direction}, {$posts}.post_modified_gmt DESC, {$posts}.ID DESC";
		}

		// For unknown sort keys, defer to WordPress/other plugins orderby.
		return $orderby;
	    }
}
