<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Get texts from settings using centralized defaults with caching
$texts = Prayer_Pop_Defaults::get_texts();

// Get settings using centralized cache
$settings = Prayer_Pop_Defaults::get_settings();

// Ensure $selected_animation is set
if ( ! isset( $selected_animation ) ) {
	$selected_animation = Prayer_Pop_Defaults::get_popup_animation();
} else {
	$selected_animation = Prayer_Pop_Defaults::get_popup_animation( array( 'bubble_animation' => $selected_animation ) );
}

// Fetch the prayer-request heading and description.
$prayer_request_header      = $texts['text_prayer_request_header'];
$prayer_request_description = $texts['text_prayer_request_description'];
$testimony_header           = $texts['text_testimony_header'];
$testimony_description      = $texts['text_testimony_description'];

// Get general settings (already loaded above via Prayer_Pop_Defaults::get_settings())
$allow_anonymous = isset($settings['allow_anonymous']) ? $settings['allow_anonymous'] : true;
$chat_settings   = class_exists( 'Prayer_Pop_Chat' ) ? Prayer_Pop_Chat::settings() : array();
$chat_enabled    = ! empty( $chat_settings['enabled'] );
$chat_team_name  = trim( (string) ( $chat_settings['team_name'] ?? '' ) );
$chat_team_name  = '' !== $chat_team_name ? $chat_team_name : (string) $texts['text_bubble_label'];
$chat_profile_image = ! empty( $chat_settings['profile_image_id'] ) ? wp_get_attachment_image_url( absint( $chat_settings['profile_image_id'] ), 'thumbnail' ) : '';
$chat_reply_time = trim( (string) ( $texts['text_chat_reply_time'] ?? '' ) );
$chat_reply_time = '' !== $chat_reply_time ? $chat_reply_time : __( 'Replies within a day', 'prayerpop' );
$prayer_enabled  = ! array_key_exists( 'show_prayer_request_button', $settings ) || ! empty( $settings['show_prayer_request_button'] );
$testimony_enabled = ! array_key_exists( 'show_testimony_button', $settings ) || ! empty( $settings['show_testimony_button'] );
$popup_intro_enabled = ! array_key_exists( 'popup_intro_enabled', $settings ) || ! empty( $settings['popup_intro_enabled'] );
$default_submission_type = $prayer_enabled ? 'prayer_request' : 'testimony';
$show_submission_choices = $prayer_enabled && $testimony_enabled;
$show_initial_options = $chat_enabled || $show_submission_choices;
$popup_intro_image_id  = isset( $settings['popup_intro_image_id'] ) ? absint( $settings['popup_intro_image_id'] ) : 0;
$popup_intro_image_url = $popup_intro_image_id ? wp_get_attachment_image_url( $popup_intro_image_id, 'large' ) : '';
$popup_intro_image_alt = $popup_intro_image_id ? (string) get_post_meta( $popup_intro_image_id, '_wp_attachment_image_alt', true ) : '';
$popup_intro_classes   = 'prayer-pop-popup-intro prayer-pop-popup-intro--hero' . ( $popup_intro_image_url ? ' prayer-pop-popup-intro--has-image' : '' );
$popup_intro_text_enabled = '' !== trim( (string) $texts['text_popup_intro_title'] ) || '' !== trim( (string) $texts['text_popup_intro_description'] );
// Show the shared welcome panel whenever visitors must choose a destination.
// A single enabled submission type still opens directly into its form.
$has_popup_intro          = $popup_intro_enabled && $show_initial_options && ( $popup_intro_image_url || $popup_intro_text_enabled );

// Update name placeholder based on anonymous setting
$name_placeholder = $allow_anonymous ? 
    $texts['text_name_placeholder'] : 
    str_replace(' (optional)', '', $texts['text_name_placeholder']);
$default_header = 'testimony' === $default_submission_type ? $testimony_header : $prayer_request_header;
$default_description = 'testimony' === $default_submission_type ? $testimony_description : $prayer_request_description;
$default_message_placeholder = 'testimony' === $default_submission_type ? $texts['text_testimony_message_placeholder'] : $texts['text_message_placeholder'];
$default_submit_label = 'testimony' === $default_submission_type ? $texts['text_testimony_submit_button'] : $texts['text_submit_button'];

// JavaScript data is localized in core/class-prayer-pop.php enqueue_scripts()
?>
<?php
// Get bubble icon settings (styles already loaded via Prayer_Pop_Defaults::get_styles())
$bubble_styles    = $styles;
$icon_type        = isset( $bubble_styles['bubble_icon_type'] ) ? sanitize_key( $bubble_styles['bubble_icon_type'] ) : 'dashicon';
$dashicon         = isset( $bubble_styles['bubble_dashicon'] ) ? sanitize_key( $bubble_styles['bubble_dashicon'] ) : 'prayerpop';
$tabler_svg       = isset( $bubble_styles['bubble_tabler_svg'] ) ? (string) $bubble_styles['bubble_tabler_svg'] : '';
// The bubble sits on the brand colour, so the white mark reads on any
// bubble background. The full-colour icon stays the one shown in the
// icon browser, where tiles sit on a light admin surface.
$prayerpop_icon_url = PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-icon-white.svg';
$icon_color       = isset( $bubble_styles['bubble_icon_color'] ) ? $bubble_styles['bubble_icon_color'] : '#ffffff';
$icon_size        = isset( $bubble_styles['bubble_icon_size'] ) ? (int) $bubble_styles['bubble_icon_size'] : 170;

if ( ! in_array( $icon_type, array( 'none', 'dashicon', 'tabler' ), true ) ) {
	$icon_type = 'dashicon';
}

// This build uses an icon-only bubble display.
$bubble_display_mode = 'icon';

$bubble_shape_mode = isset( $bubble_styles['bubble_design_mode'] ) ? sanitize_key( $bubble_styles['bubble_design_mode'] ) : '';
if ( '' === $bubble_shape_mode && isset( $attrs['bubble']['advanced']['shapeMode']['desktop']['value'] ) ) {
	$legacy_shape_mode = (string) $attrs['bubble']['advanced']['shapeMode']['desktop']['value'];
	$legacy_map        = array(
		'dynamic'   => 'adaptive',
		'rectangle' => 'adaptive',
		'square'    => 'fixed_square',
		'circle'    => 'fixed_circle',
	);
	$bubble_shape_mode = isset( $legacy_map[ $legacy_shape_mode ] ) ? $legacy_map[ $legacy_shape_mode ] : 'fixed_circle';
}
if ( ! in_array( $bubble_shape_mode, array( 'adaptive', 'fixed_square', 'fixed_circle' ), true ) ) {
	$bubble_shape_mode = 'fixed_circle';
}

$bubble_position = isset( $bubble_styles['bubble_position'] ) ? sanitize_key( $bubble_styles['bubble_position'] ) : 'right';
if ( ! in_array( $bubble_position, array( 'right', 'left' ), true ) ) {
	$bubble_position = 'right';
}

$bubble_padding = isset( $attrs['bubble']['advanced']['bubblePadding']['desktop']['value'] ) ? (string) $attrs['bubble']['advanced']['bubblePadding']['desktop']['value'] : '15px';
if ( ! preg_match( '/^\d+(?:\.\d+)?px$/', $bubble_padding ) ) {
	$bubble_padding = '15px';
}

$bubble_shape_size = isset( $attrs['bubble']['advanced']['shapeSize']['desktop']['value'] ) ? (string) $attrs['bubble']['advanced']['shapeSize']['desktop']['value'] : '64px';
if ( ! preg_match( '/^\d+(?:\.\d+)?px$/', $bubble_shape_size ) ) {
	$bubble_shape_size = '64px';
}

// Prepare icon/image content.
$icon_content = '';
$icon_styles  = array();
$icon_scale   = max( 0.25, min( 2.5, $icon_size / 100 ) );

// Add color styling for dashicons
if ( in_array( $icon_type, array( 'dashicon', 'tabler' ), true ) && $icon_color ) {
    $icon_styles[] = 'color: ' . esc_attr( $icon_color );
}

$icon_inline_style = ! empty( $icon_styles ) ? implode( '; ', $icon_styles ) : '';
$bubble_style_attr = sprintf(
	'--prayerpop-bubble-padding: %1$s; --prayerpop-bubble-shape-size: %2$s; --prayerpop-icon-scale: %3$s;',
	esc_attr( $bubble_padding ),
	esc_attr( $bubble_shape_size ),
	esc_attr( rtrim( rtrim( sprintf( '%.3F', $icon_scale ), '0' ), '.' ) )
);
?>
<div
	id="prayer-pop-bubble"
	class="<?php echo esc_attr( $selected_animation ); ?>"
	data-icon-type="<?php echo esc_attr( $icon_type ); ?>"
	data-bubble-display="<?php echo esc_attr( $bubble_display_mode ); ?>"
	data-bubble-shape="<?php echo esc_attr( $bubble_shape_mode ); ?>"
	data-bubble-position="<?php echo esc_attr( $bubble_position ); ?>"
	style="<?php echo esc_attr( $bubble_style_attr ); ?>"
>
    <!-- Bubble Icon -->
	    <div id="prayer-pop-icon">
	        <?php if ( $icon_type === 'dashicon' ): ?>
				<?php if ( 'prayerpop' === $dashicon ) : ?>
					<span class="prayer-pop-visual-icon prayer-pop-brand-icon-wrap">
						<img src="<?php echo esc_url( $prayerpop_icon_url ); ?>" alt="<?php echo esc_attr( $texts['text_bubble_icon_alt'] ); ?>" class="prayer-pop-brand-icon">
					</span>
				<?php else : ?>
	            	<span class="prayer-pop-visual-icon dashicons dashicons-<?php echo esc_attr( $dashicon ); ?> prayer-pop-dashicon"<?php echo '' !== $icon_inline_style ? ' style="' . esc_attr( $icon_inline_style ) . '"' : ''; ?>></span>
				<?php endif; ?>
			<?php elseif ( $icon_type === 'tabler' && '' !== $tabler_svg ) : ?>
				<span class="prayer-pop-visual-icon prayer-pop-tabler-icon-wrap"<?php echo '' !== $icon_inline_style ? ' style="' . esc_attr( $icon_inline_style ) . '"' : ''; ?>>
					<?php
					echo wp_kses(
						$tabler_svg,
						array(
							'svg'  => array(
								'class'           => true,
								'xmlns'           => true,
								'width'           => true,
								'height'          => true,
								'viewbox'         => true,
								'fill'            => true,
								'stroke'          => true,
								'stroke-width'    => true,
								'stroke-linecap'  => true,
								'stroke-linejoin' => true,
								'aria-hidden'     => true,
								'focusable'       => true,
							),
							'path' => array(
								'd'       => true,
								'fill'    => true,
								'opacity' => true,
								'stroke'  => true,
							),
							'g'    => array(
								'transform'    => true,
								'stroke-width' => true,
							),
						)
					);
					?>
				</span>
	        <?php endif; ?>
	    </div>
	<span class="prayer-pop-chat-unread-badge" aria-hidden="true" hidden></span>
</div>

<!-- PrayerPop Form Modal -->
<div id="prayer-pop-modal" data-bubble-position="<?php echo esc_attr( $bubble_position ); ?>" style="display: none;">
    <div id="prayer-pop-form-container">
	        <?php if ( $has_popup_intro ) : ?>
	            <div id="prayer-pop-popup-intro" class="<?php echo esc_attr( $popup_intro_classes ); ?>">
                <?php if ( $popup_intro_image_url ) : ?>
                    <div class="prayer-pop-popup-intro__media"><img src="<?php echo esc_url( $popup_intro_image_url ); ?>" alt="<?php echo esc_attr( $popup_intro_image_alt ); ?>"></div>
                <?php endif; ?>
	                <?php if ( $popup_intro_text_enabled ) : ?><div class="prayer-pop-popup-intro__content">
						<div class="prayer-pop-popup-intro__sender"><?php if ( $chat_profile_image ) : ?><img class="prayer-pop-popup-intro__avatar" src="<?php echo esc_url( $chat_profile_image ); ?>" alt=""><?php else : ?><span class="prayer-pop-popup-intro__avatar dashicons dashicons-groups" aria-hidden="true"></span><?php endif; ?><span><strong><?php echo esc_html( $chat_team_name ); ?></strong><small><i aria-hidden="true"></i><?php echo esc_html( $chat_reply_time ); ?></small></span></div>
						<div class="prayer-pop-popup-intro__message">
							<?php if ( '' !== trim( (string) $texts['text_popup_intro_title'] ) ) : ?><h2 class="prayer-pop-popup-intro__title"><?php echo esc_html( $texts['text_popup_intro_title'] ); ?></h2><?php endif; ?>
							<?php if ( '' !== trim( (string) $texts['text_popup_intro_description'] ) ) : ?><p class="prayer-pop-popup-intro__description"><?php echo nl2br( esc_html( $texts['text_popup_intro_description'] ) ); ?></p><?php endif; ?>
						</div>
	                </div><?php endif; ?>
	            </div>
	        <?php endif; ?>
	        <?php if ( $show_initial_options ) : ?>
	            <div id="prayer-pop-initial-options">
				<?php if ( $chat_enabled ) : ?>
				<button type="button" class="prayer-pop-option-button prayer-pop-option-button--menu ppm-classic-chat-launch"><span class="prayer-pop-option-button__icon dashicons dashicons-format-chat" aria-hidden="true"></span><span class="prayer-pop-option-button__label"><?php echo esc_html( $texts['text_chat_button'] ); ?></span><span class="prayer-pop-option-button__chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
				<?php endif; ?>
				<?php if ( $prayer_enabled ) : ?>
				<button type="button" class="prayer-pop-option-button prayer-pop-option-button--menu" data-option="prayer_request"><span class="prayer-pop-option-button__icon dashicons dashicons-heart" aria-hidden="true"></span><span class="prayer-pop-option-button__label"><?php echo esc_html( $texts['text_prayer_request_label'] ); ?></span><span class="prayer-pop-option-button__chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
				<?php endif; ?>
				<?php if ( $testimony_enabled ) : ?>
				<button type="button" class="prayer-pop-option-button prayer-pop-option-button--menu" data-option="testimony"><span class="prayer-pop-option-button__icon dashicons dashicons-awards" aria-hidden="true"></span><span class="prayer-pop-option-button__label"><?php echo esc_html( $texts['text_testimony_label'] ); ?></span><span class="prayer-pop-option-button__chevron dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
				<?php endif; ?>
            </div>
        <?php endif; ?>
	        <div id="prayer-pop-form-wrapper"<?php echo $show_initial_options ? ' style="display: none;"' : ''; ?>>
	            <?php if ( $show_initial_options ) : ?>
                <header class="ppm-classic-screen-header">
                    <button type="button" id="prayer-pop-back-button" aria-label="<?php esc_attr_e( 'Back to options', 'prayerpop' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
                    <div id="prayer-pop-header">
	                    <h2 class="prayer-pop-heading"><?php echo esc_html( $default_header ); ?></h2>
                    </div>
                </header>
            <?php else : ?>
                <div id="prayer-pop-header">
	                <h2 class="prayer-pop-heading"><?php echo esc_html( $default_header ); ?></h2>
                </div>
            <?php endif; ?>
            <div id="prayer-pop-description">
	                <p><?php echo esc_html( $default_description ); ?></p>
            </div>


            <form id="prayer-pop-form">
                <!-- Honeypot field for spam protection (hidden from real users) -->
                <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <label for="prayer-pop-honeypot"><?php echo esc_html( $texts['text_honeypot_label'] ); ?></label>
                    <input 
                        type="text" 
                        name="prayer_pop_honeypot" 
                        id="prayer-pop-honeypot" 
                        value="" 
                        autocomplete="off" 
                        tabindex="-1"
                    >
                </div>

                <!-- Timestamp field for basic time-based spam protection -->
                <input type="hidden" name="prayer_pop_start_time" id="prayer-pop-start-time" value="">

	                <input type="hidden" name="prayer_pop_type" value="<?php echo esc_attr( $default_submission_type ); ?>">
                <div class="prayer-pop-field-group">
                    <label class="prayer-pop-screen-reader-text" for="prayer-pop-message"><?php echo esc_html__( 'Message', 'prayerpop' ); ?></label>
                    <textarea
                        id="prayer-pop-message"
                        name="prayer_pop_message"
                        maxlength="<?php echo esc_attr( (string) absint( Prayer_Pop_Ajax::MAX_MESSAGE_LENGTH ) ); ?>"
                        required
                        oninvalid="this.setCustomValidity('<?php echo esc_js($texts['text_required_field']); ?>')"
                        oninput="this.setCustomValidity('')"
	                        placeholder="<?php echo esc_attr( $default_message_placeholder ); ?>"></textarea>

                    <div id="prayer-pop-name-container">
                        <label class="prayer-pop-screen-reader-text" for="prayer-pop-name"><?php echo esc_html__( 'Name', 'prayerpop' ); ?></label>
                        <input
                            type="text"
                            name="prayer_pop_name"
                            id="prayer-pop-name"
                            maxlength="<?php echo esc_attr( (string) absint( Prayer_Pop_Ajax::MAX_NAME_LENGTH ) ); ?>"
                            placeholder="<?php echo esc_attr($allow_anonymous ? $texts['text_name_placeholder'] : $texts['text_name_placeholder_required']); ?>"
                            <?php if (!$allow_anonymous): ?>
                            required
                            oninvalid="this.setCustomValidity('<?php echo esc_js($texts['text_required_field']); ?>')"
                            oninput="this.setCustomValidity('')"
                            <?php endif; ?>>
                    </div>
                </div>

                <div id="prayer-pop-error" role="alert" aria-live="assertive" style="display: none;">
                    <!-- Error Message will be inserted by JS -->
                </div>

	                <button type="submit"><?php echo esc_html( $default_submit_label ); ?></button>
            </form>
            <div id="prayer-pop-success" role="status" aria-live="polite" style="display: none;">
                <!-- Success Message will be inserted by JS -->
            </div>
        </div>
    </div>
</div>
