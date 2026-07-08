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
    $styles = Prayer_Pop_Defaults::get_styles();
    $selected_animation = isset( $styles['bubble_animation'] ) ? $styles['bubble_animation'] : 'fade-in';
    if ( ! in_array( $selected_animation, array( 'none', 'fade-in', 'slide-up', 'bounce-in' ), true ) ) {
        $selected_animation = 'fade-in';
    }
}

// Fetch the prayer-request heading and description.
$prayer_request_header      = $texts['text_prayer_request_header'];
$prayer_request_description = $texts['text_prayer_request_description'];

// Get general settings (already loaded above via Prayer_Pop_Defaults::get_settings())
$allow_anonymous = isset($settings['allow_anonymous']) ? $settings['allow_anonymous'] : true;

// Update name placeholder based on anonymous setting
$name_placeholder = $allow_anonymous ? 
    $texts['text_name_placeholder'] : 
    str_replace(' (optional)', '', $texts['text_name_placeholder']);

// JavaScript data is localized in core/class-prayer-pop.php enqueue_scripts()
?>
<?php
// Get bubble icon settings (styles already loaded via Prayer_Pop_Defaults::get_styles())
$bubble_styles    = $styles;
$icon_type        = isset( $bubble_styles['bubble_icon_type'] ) ? sanitize_key( $bubble_styles['bubble_icon_type'] ) : 'dashicon';
$dashicon         = isset( $bubble_styles['bubble_dashicon'] ) ? sanitize_key( $bubble_styles['bubble_dashicon'] ) : 'prayerpop';
$tabler_svg       = isset( $bubble_styles['bubble_tabler_svg'] ) ? (string) $bubble_styles['bubble_tabler_svg'] : '';
$prayerpop_icon_url = PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-icon.svg';
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
</div>

<!-- PrayerPop Form Modal -->
<div id="prayer-pop-modal" data-bubble-position="<?php echo esc_attr( $bubble_position ); ?>" style="display: none;">
    <div id="prayer-pop-form-container">
        <div id="prayer-pop-form-wrapper">
            <div id="prayer-pop-header">
                <h2 class="prayer-pop-heading"><?php echo esc_html( $prayer_request_header ); ?></h2>
            </div>
            <div id="prayer-pop-description">
                <p><?php echo esc_html( $prayer_request_description ); ?></p>
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

                <input type="hidden" name="prayer_pop_type" value="prayer_request">
                <textarea 
                    name="prayer_pop_message" 
                    maxlength="<?php echo esc_attr( (string) absint( Prayer_Pop_Ajax::MAX_MESSAGE_LENGTH ) ); ?>" 
                    required 
                    oninvalid="this.setCustomValidity('<?php echo esc_js($texts['text_required_field']); ?>')"
                    oninput="this.setCustomValidity('')"
                    placeholder="<?php echo esc_attr($texts['text_message_placeholder']); ?>"></textarea>
                
                <div id="prayer-pop-name-container">
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

                <div id="prayer-pop-error" style="display: none;">
                    <!-- Error Message will be inserted by JS -->
                </div>

                <button type="submit"><?php echo esc_html( $texts['text_submit_button'] ); ?></button>
            </form>
            <div id="prayer-pop-success" style="display: none;">
                <!-- Success Message will be inserted by JS -->
            </div>
        </div>
    </div>
</div>
