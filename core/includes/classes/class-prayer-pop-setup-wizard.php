<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * First-run setup for PrayerPop Free. Mirrors the Pro setup wizard's look
 * and step machinery, but with far fewer steps — Free has no license, no
 * layout choice, no FAQ/custom links, and no classic footer navigation, so
 * several Pro steps collapse into one here.
 */
class Prayer_Pop_Setup_Wizard {
	const COMPLETED_OPTION = 'prayer_pop_setup_completed';
	const LAST_STEP_OPTION = 'prayer_pop_setup_last_step';
	const STEPS            = array( 'welcome', 'church', 'appearance', 'notifications', 'grow', 'complete' );

	public static function init() {
		add_action( 'wp_ajax_prayer_pop_setup_wizard_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_prayer_pop_setup_wizard_finish', array( __CLASS__, 'finish' ) );
		add_action( 'wp_ajax_prayer_pop_setup_wizard_track_step', array( __CLASS__, 'track_step' ) );
	}

	public static function render( $is_open ) {
		$general       = Prayer_Pop_Defaults::get_settings();
		$styles        = Prayer_Pop_Defaults::get_styles();
		$chat          = Prayer_Pop_Chat::settings();
		$notifications = get_option( 'prayer_pop_notification_settings', array() );
		$notifications = is_array( $notifications ) ? $notifications : array();
		$profile_image = ! empty( $chat['profile_image_id'] ) ? wp_get_attachment_image_url( $chat['profile_image_id'], 'thumbnail' ) : '';
		$opening_message = array_key_exists( 'initial_opening_message', $chat ) ? $chat['initial_opening_message'] : '';
		$preview_url   = add_query_arg( array( 'prayer_pop_preview' => '1', '_pp_preview_nonce' => wp_create_nonce( 'prayer_pop_frontend_preview' ) ), home_url( '/' ) );
		$close_url     = admin_url( 'admin.php?page=prayer-pop-settings&tab=popup' );
		$modal_class   = $is_open ? 'prayer-pop-welcome-modal is-open' : 'prayer-pop-welcome-modal';
		$initial_step  = get_option( self::LAST_STEP_OPTION, 'welcome' );
		if ( ! in_array( $initial_step, self::STEPS, true ) || 'complete' === $initial_step ) {
			$initial_step = 'welcome';
		}
		?>
		<div id="prayer-pop-welcome-modal" class="<?php echo esc_attr( $modal_class ); ?>" role="dialog" aria-modal="true" aria-labelledby="prayer-pop-welcome-title" aria-hidden="<?php echo $is_open ? 'false' : 'true'; ?>">
			<div class="prayer-pop-welcome-modal__backdrop"></div>
			<div class="prayer-pop-welcome-modal__dialog prayer-pop-setup-wizard" data-welcome-step="<?php echo esc_attr( $initial_step ); ?>" data-welcome-initial-step="<?php echo esc_attr( $initial_step ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'prayer_pop_setup_wizard' ) ); ?>" data-preview-url="<?php echo esc_url( $preview_url ); ?>">
				<a href="<?php echo esc_url( $close_url ); ?>" class="button-link prayer-pop-welcome-modal__close" data-welcome-close="1" aria-label="<?php esc_attr_e( 'Finish setup later', 'prayerpop' ); ?>">&times;</a>
				<div class="prayer-pop-setup-progress" aria-label="<?php esc_attr_e( 'Setup progress', 'prayerpop' ); ?>"><span data-setup-progress></span></div>
				<div class="prayer-pop-setup-header">
					<span class="prayer-pop-setup-badge" aria-hidden="true"><?php echo Prayer_Pop_Settings::get_logo_markup( 'icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized via wp_kses() in get_logo_markup(). ?></span>
					<span class="prayer-pop-setup-step-label" data-setup-progress-label></span>
				</div>

				<section class="prayer-pop-welcome-step prayer-pop-welcome-step-onboarding" data-setup-step="welcome">
					<h2 id="prayer-pop-welcome-title"><?php esc_html_e( 'Welcome to PrayerPop', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'Thanks for installing PrayerPop. The next four steps set up your team identity, your appearance, and who gets notified when a request comes in. It takes about two minutes, and you can change any of it later.', 'prayerpop' ); ?></p>
					<p><?php esc_html_e( 'When you’re done, your church will have a prayer bubble live on the site, ready for requests and testimonies.', 'prayerpop' ); ?></p>
					<?php self::actions( '', 'church', false ); ?>
				</section>

				<section class="prayer-pop-welcome-step" data-setup-step="church">
					<h2><?php esc_html_e( 'Your church', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'Tell visitors who they are connecting with and choose what they can do.', 'prayerpop' ); ?></p>
					<?php self::text_field( 'team_name', __( 'Church or organisation name', 'prayerpop' ), $chat['team_name'] ); ?>
					<div class="prayer-pop-inline-setting-row">
						<div>
							<h3 class="prayer-pop-settings-table-title"><?php esc_html_e( 'Church logo or image', 'prayerpop' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Shown next to your team name in PrayerPop Chat.', 'prayerpop' ); ?></p>
						</div>
						<div class="prayer-pop-setup-image-picker">
							<input type="hidden" name="profile_image_id" value="<?php echo esc_attr( $chat['profile_image_id'] ); ?>">
							<img class="prayer-pop-setup-image-preview" src="<?php echo esc_url( $profile_image ); ?>" alt=""<?php echo $profile_image ? '' : ' hidden'; ?>>
							<button type="button" class="button" data-setup-image><?php esc_html_e( 'Choose image', 'prayerpop' ); ?></button>
							<span class="prayer-pop-setup-image-status"><?php echo $chat['profile_image_id'] ? esc_html__( 'Image selected', 'prayerpop' ) : esc_html__( 'No image selected', 'prayerpop' ); ?></span>
						</div>
					</div>
					<p class="prayer-pop-setup-features-legend"><?php esc_html_e( 'Choose what visitors can do', 'prayerpop' ); ?></p>
					<?php
					self::toggle_field( 'show_prayer_request_button', __( 'Prayer requests', 'prayerpop' ), ! empty( $general['show_prayer_request_button'] ) );
					self::toggle_field( 'show_testimony_button', __( 'Testimonies', 'prayerpop' ), ! empty( $general['show_testimony_button'] ) );
					self::toggle_field( 'chat_enabled', __( 'PrayerPop Chat', 'prayerpop' ), ! empty( $chat['enabled'] ), __( 'Lets visitors start a two-way conversation with your team instead of only submitting a form.', 'prayerpop' ) );
					self::textarea_field( 'initial_opening_message', __( 'Chat opening message', 'prayerpop' ), $opening_message, array( 'rows' => 3, 'description' => __( 'Shown as one team message before a visitor starts their first conversation. Leave blank to show none.', 'prayerpop' ) ) );
					self::actions( 'welcome', 'appearance' );
					?>
				</section>

				<section class="prayer-pop-welcome-step" data-setup-step="appearance">
					<h2><?php esc_html_e( 'Appearance', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'Choose the main color and position that fit your church website.', 'prayerpop' ); ?></p>
					<?php
					self::color_field( 'global_bg_color', __( 'Primary colour', 'prayerpop' ), $styles['global_bg_color'] ?? '#2755aa' );
					?>
					<div class="prayer-pop-inline-setting-row">
						<h3 class="prayer-pop-settings-table-title"><?php esc_html_e( 'Bubble position', 'prayerpop' ); ?></h3>
						<div class="prayer-pop-setup-radio-group">
							<label><input type="radio" name="bubble_position" value="right" <?php checked( $styles['bubble_position'] ?? 'right', 'right' ); ?>> <?php esc_html_e( 'Bottom right', 'prayerpop' ); ?></label>
							<label><input type="radio" name="bubble_position" value="left" <?php checked( $styles['bubble_position'] ?? '', 'left' ); ?>> <?php esc_html_e( 'Bottom left', 'prayerpop' ); ?></label>
						</div>
					</div>
					<?php
					$animations = array(
						'none'        => __( 'None', 'prayerpop' ),
						'fade-in'     => __( 'Smooth fade', 'prayerpop' ),
						'gentle-rise' => __( 'Gentle rise', 'prayerpop' ),
						'soft-scale'  => __( 'Soft scale', 'prayerpop' ),
						'slide-up'    => __( 'Slide up', 'prayerpop' ),
						'bounce-in'   => __( 'Soft pop', 'prayerpop' ),
					);
					$current_animation = $styles['bubble_animation'] ?? 'gentle-rise';
					?>
					<div class="prayer-pop-inline-setting-row">
						<h3 class="prayer-pop-settings-table-title"><?php esc_html_e( 'Popup animation', 'prayerpop' ); ?></h3>
						<select name="bubble_animation" class="regular-text">
							<?php foreach ( $animations as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_animation, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<a class="button button-secondary" data-setup-preview data-setup-preview-save="appearance" target="_blank" href="<?php echo esc_url( $preview_url ); ?>"><?php esc_html_e( 'Save and preview the bubble in a new tab', 'prayerpop' ); ?></a>
					<?php self::actions( 'church', 'notifications' ); ?>
				</section>

				<section class="prayer-pop-welcome-step" data-setup-step="notifications">
					<h2><?php esc_html_e( 'Notifications', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'Add the email addresses that should be notified about new submissions, and choose whether submissions need your approval first.', 'prayerpop' ); ?></p>
					<?php
					self::textarea_field( 'notification_emails', __( 'Notification recipients', 'prayerpop' ), $notifications['notification_emails'] ?? get_option( 'admin_email' ), array( 'description' => __( 'Add one or more email addresses, separated by commas or new lines. This does not create a WordPress account or give access to your website.', 'prayerpop' ) ) );
					?>
					<label class="prayer-pop-setup-check"><input type="checkbox" name="enable_notifications" value="1" <?php checked( ! empty( $notifications['enable_notifications'] ) ); ?>> <?php esc_html_e( 'Send new submission notifications', 'prayerpop' ); ?></label>
					<label class="prayer-pop-setup-check"><input type="checkbox" name="require_admin_approval" value="1" <?php checked( ! empty( $general['require_admin_approval'] ) ); ?>> <?php esc_html_e( 'Review submissions before they are actioned', 'prayerpop' ); ?></label>
					<?php self::actions( 'appearance', 'grow' ); ?>
				</section>

				<section class="prayer-pop-welcome-step" data-setup-step="grow">
					<h2><?php esc_html_e( 'Grow adoption', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'Setup is done. The last step is making sure your church actually knows PrayerPop is there, and keeps getting value from it.', 'prayerpop' ); ?></p>
					<div class="prayer-pop-subsection-card">
						<h3 class="prayer-pop-settings-table-title"><?php esc_html_e( 'Announce it more than once', 'prayerpop' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Lead with why it exists for your church, then how to use it. What it does can come last.', 'prayerpop' ); ?></p>
						<p class="description"><?php esc_html_e( 'Something like: “So every prayer request reaches our pastoral team and no one carries a burden alone, tap the prayer bubble on our website any time. A request or a testimony takes seconds.”', 'prayerpop' ); ?></p>
						<p class="description"><?php esc_html_e( 'One announcement will not stick. Plan a month of reminders from the pulpit, in the bulletin, on the screens before service, and on social media. Judge whether people are using it after that, not before.', 'prayerpop' ); ?></p>
					</div>
					<div class="prayer-pop-setup-quote">
						<blockquote><?php esc_html_e( '“Prayer is not overcoming God’s reluctance, but laying hold of His willingness.”', 'prayerpop' ); ?></blockquote>
						<cite><?php esc_html_e( 'Martin Luther', 'prayerpop' ); ?></cite>
					</div>
					<div class="prayer-pop-setup-actions"><button type="button" class="button" data-setup-back="notifications"><?php esc_html_e( 'Back', 'prayerpop' ); ?></button><div class="prayer-pop-setup-actions__right"><button type="button" class="button-link" data-welcome-close="1"><?php esc_html_e( 'Finish later', 'prayerpop' ); ?></button><button type="button" class="button button-primary" data-setup-finish><?php esc_html_e( 'Complete setup', 'prayerpop' ); ?></button></div></div>
				</section>

				<section class="prayer-pop-welcome-step" data-setup-step="complete">
					<h2><?php esc_html_e( 'PrayerPop is ready', 'prayerpop' ); ?></h2>
					<p><?php esc_html_e( 'Introduce it during a Sunday service, share your website link, and show people how to send a request or testimony. Make sure someone on your team checks new submissions regularly.', 'prayerpop' ); ?></p>
					<div class="prayer-pop-setup-lift">
						<div class="prayer-pop-subsection-card">
							<h3 class="prayer-pop-settings-table-title"><?php esc_html_e( 'Get more out of PrayerPop', 'prayerpop' ); ?></h3>
							<ul class="prayer-pop-setup-next-steps">
								<li><?php esc_html_e( 'Review who receives notifications as your team changes, so requests never sit unseen.', 'prayerpop' ); ?></li>
								<li><?php esc_html_e( 'Check your Data settings occasionally so submission and Chat retention periods still match your team’s needs.', 'prayerpop' ); ?></li>
							</ul>
						</div>
						<div class="prayer-pop-subsection-card prayer-pop-setup-pro">
							<h3 class="prayer-pop-settings-table-title"><?php esc_html_e( 'PrayerPop Pro', 'prayerpop' ); ?> <span class="prayer-pop-setup-pro-pill"><?php esc_html_e( 'Pay once, keep forever', 'prayerpop' ); ?></span></h3>
							<p class="description"><?php esc_html_e( 'Everything below is a one time 49 euro licence for your site. No subscription, no renewal.', 'prayerpop' ); ?></p>
							<ul class="prayer-pop-setup-next-steps">
								<li><?php esc_html_e( 'Private requests stay private. Approved ones go on your public prayer wall, so the church sees answered prayer and prays together.', 'prayerpop' ); ?></li>
								<li><?php esc_html_e( 'Send selected requests to your prayer team as a PDF, ready for Sunday.', 'prayerpop' ); ?></li>
								<li><?php esc_html_e( 'Prayer Campaigns for round the clock prayer and fasting signups.', 'prayerpop' ); ?></li>
								<li><?php esc_html_e( 'An interactive Prayer Wheel, where visitors draw a request and commit to a minute of prayer.', 'prayerpop' ); ?></li>
								<li><?php esc_html_e( 'And much more…', 'prayerpop' ); ?></li>
							</ul>
							<a class="button button-primary prayer-pop-setup-pro-cta" href="https://prayerpop.eu/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See the wall and Prayer Wheel in action', 'prayerpop' ); ?></a>
						</div>
					</div>
					<div class="prayer-pop-setup-blessing">
						<p><?php esc_html_e( 'Blessings to you and your church as you begin to use PrayerPop.', 'prayerpop' ); ?></p>
						<cite><?php esc_html_e( 'With love, PrayerPop', 'prayerpop' ); ?></cite>
					</div>
					<div class="prayer-pop-setup-actions"><div class="prayer-pop-setup-actions__right"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=prayer_request' ) ); ?>"><?php esc_html_e( 'Go to submissions', 'prayerpop' ); ?></a></div></div>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a color field using the exact same swatch+hex markup/classes as
	 * the Settings screen's color_field_callback(), so wizard color pickers
	 * look and behave identically to Settings.
	 *
	 * @param string $name  Input name attribute.
	 * @param string $label Visible field label.
	 * @param string $value Current hex value.
	 * @return void
	 */
	private static function color_field( $name, $label, $value ) {
		?>
		<div class="prayer-pop-inline-setting-row">
			<h3 class="prayer-pop-settings-table-title"><?php echo esc_html( $label ); ?></h3>
			<div class="prayer-pop-color-field-wrapper">
				<input type="color" class="prayer-pop-color-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<span class="color-value"><?php echo esc_html( $value ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single-line text field using the same label-left/value-right
	 * .prayer-pop-inline-setting-row layout used throughout the wizard.
	 *
	 * @param string $name  Input name attribute.
	 * @param string $label Visible field label.
	 * @param string $value Current value.
	 * @return void
	 */
	private static function text_field( $name, $label, $value ) {
		?>
		<div class="prayer-pop-inline-setting-row">
			<h3 class="prayer-pop-settings-table-title"><?php echo esc_html( $label ); ?></h3>
			<input type="text" name="<?php echo esc_attr( $name ); ?>" class="regular-text" value="<?php echo esc_attr( $value ); ?>">
		</div>
		<?php
	}

	/**
	 * Render a textarea field using the same label-left/value-right layout.
	 *
	 * @param string $name  Textarea name attribute.
	 * @param string $label Visible field label.
	 * @param string $value Current value.
	 * @param array  $args  Optional: description, rows.
	 * @return void
	 */
	private static function textarea_field( $name, $label, $value, $args = array() ) {
		$description = $args['description'] ?? '';
		$rows        = $args['rows'] ?? 3;
		?>
		<div class="prayer-pop-inline-setting-row">
			<div>
				<h3 class="prayer-pop-settings-table-title"><?php echo esc_html( $label ); ?></h3>
				<?php if ( '' !== $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
			</div>
			<textarea name="<?php echo esc_attr( $name ); ?>" rows="<?php echo esc_attr( $rows ); ?>" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Render a feature toggle using the exact same switch markup/classes as
	 * the Settings screen, so wizard toggles look and behave identically.
	 *
	 * @param string $name    Checkbox name attribute.
	 * @param string $label   Visible feature label.
	 * @param bool   $checked Whether the toggle starts on.
	 * @param string $tooltip Optional info-tooltip text shown next to the label.
	 * @return void
	 */
	private static function toggle_field( $name, $label, $checked, $tooltip = '' ) {
		?>
		<div class="prayer-pop-inline-setting-row">
			<h3 class="prayer-pop-settings-table-title">
				<?php echo esc_html( $label ); ?>
				<?php if ( '' !== $tooltip ) : ?>
					<span class="prayer-pop-info-tooltip-wrap">
						<button type="button" class="prayer-pop-info-tooltip-button" aria-label="<?php esc_attr_e( 'More information', 'prayerpop' ); ?>">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						</button>
						<span class="prayer-pop-info-tooltip-content" role="tooltip"><?php echo esc_html( $tooltip ); ?></span>
					</span>
				<?php endif; ?>
			</h3>
			<div class="prayer-pop-toggle-wrapper">
				<label class="prayer-pop-toggle-switch">
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
					<span class="prayer-pop-toggle-slider"></span>
					<span class="toggle-status"><?php echo $checked ? esc_html__( 'On', 'prayerpop' ) : esc_html__( 'Off', 'prayerpop' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the shared Back / Finish later / Save and continue action row.
	 *
	 * @param string $previous  Step key for the Back button, or '' to omit it (first step).
	 * @param string $next      Step key the primary button advances to.
	 * @param bool   $show_back Whether to render the Back button.
	 * @return void
	 */
	private static function actions( $previous, $next, $show_back = true ) {
		?>
		<div class="prayer-pop-setup-actions">
			<?php if ( $show_back && '' !== $previous ) : ?>
				<button type="button" class="button" data-setup-back="<?php echo esc_attr( $previous ); ?>"><?php esc_html_e( 'Back', 'prayerpop' ); ?></button>
			<?php endif; ?>
			<div class="prayer-pop-setup-actions__right">
				<button type="button" class="button-link" data-welcome-close="1"><?php esc_html_e( 'Finish later', 'prayerpop' ); ?></button>
				<button type="button" class="button button-primary" data-setup-next="<?php echo esc_attr( $next ); ?>"><?php esc_html_e( 'Save and continue', 'prayerpop' ); ?></button>
			</div>
		</div>
		<?php
	}

	public static function save() {
		self::authorize();
		$step    = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$values  = self::values();
		$general = Prayer_Pop_Defaults::get_settings();
		$styles  = Prayer_Pop_Defaults::get_styles();
		$chat    = Prayer_Pop_Chat::settings();
		if ( 'church' === $step ) {
			$general['show_prayer_request_button'] = empty( $values['show_prayer_request_button'] ) ? 0 : 1;
			$general['show_testimony_button']       = empty( $values['show_testimony_button'] ) ? 0 : 1;
			$chat['team_name']                      = sanitize_text_field( $values['team_name'] ?? $chat['team_name'] );
			$chat['profile_image_id']               = absint( $values['profile_image_id'] ?? $chat['profile_image_id'] );
			$chat['enabled']                         = empty( $values['chat_enabled'] ) ? 0 : 1;
			$chat['initial_opening_message']        = wp_kses( $values['initial_opening_message'] ?? '', array( 'strong' => array(), 'em' => array(), 'br' => array() ) );
		} elseif ( 'appearance' === $step ) {
			$color = isset( $values['global_bg_color'] ) ? sanitize_hex_color( $values['global_bg_color'] ) : false;
			if ( $color ) {
				$styles['global_bg_color'] = $color;
			}
			$styles['bubble_position'] = ( $values['bubble_position'] ?? 'right' ) === 'left' ? 'left' : 'right';
			$animation = sanitize_key( $values['bubble_animation'] ?? 'gentle-rise' );
			$allowed_animations = array( 'none', 'fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in' );
			$styles['bubble_animation'] = in_array( $animation, $allowed_animations, true ) ? $animation : 'gentle-rise';
		} elseif ( 'notifications' === $step ) {
			$recipients     = self::emails( $values['notification_emails'] ?? '' );
			$notifications  = get_option( 'prayer_pop_notification_settings', array() );
			$notifications  = is_array( $notifications ) ? $notifications : array();
			$notifications['enable_notifications'] = empty( $values['enable_notifications'] ) ? 0 : 1;
			$notifications['notification_emails']  = implode( "\n", $recipients );
			$notifications['notification_email']   = ! empty( $recipients ) ? $recipients[0] : '';
			$notifications['notification_frequency'] = $notifications['notification_frequency'] ?? 'immediately';
			update_option( 'prayer_pop_notification_settings', $notifications );
			$general['require_admin_approval'] = empty( $values['require_admin_approval'] ) ? 0 : 1;
		}
		update_option( 'prayer_pop_general_settings', $general );
		update_option( 'prayer_pop_styles', $styles );
		update_option( Prayer_Pop_Chat::SETTINGS_OPTION, $chat );
		wp_send_json_success( array( 'message' => __( 'Saved.', 'prayerpop' ) ) );
	}

	public static function finish() {
		self::authorize();
		update_option( self::COMPLETED_OPTION, time(), false );
		delete_option( self::LAST_STEP_OPTION );
		wp_send_json_success( array( 'submissions_url' => admin_url( 'edit.php?post_type=prayer_request' ) ) );
	}

	/**
	 * Remember which step is currently showing, so reopening the wizard
	 * later (e.g. after "Finish later") resumes there instead of always
	 * restarting at the first step.
	 *
	 * @return void
	 */
	public static function track_step() {
		self::authorize();
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( in_array( $step, self::STEPS, true ) && 'complete' !== $step ) {
			update_option( self::LAST_STEP_OPTION, $step, false );
		}
		wp_send_json_success();
	}

	private static function authorize() {
		check_ajax_referer( 'prayer_pop_setup_wizard', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'prayerpop' ) ), 403 );
		}
	}

	private static function values() {
		$raw = isset( $_POST['values'] ) ? json_decode( wp_unslash( $_POST['values'] ), true ) : array();
		return is_array( $raw ) ? $raw : array();
	}

	private static function emails( $value ) {
		return array_values( array_filter( array_unique( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $value ) ) ) ) );
	}
}
