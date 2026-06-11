<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Prayer_Pop_Settings_Style
 *
 * Handles the Style Customization section.
 */
class Prayer_Pop_Settings_Style {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue the scripts.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'prayer-pop_page_prayer-pop-settings' !== $hook ) {
			return;
		}
		
		// Ensure admin stylesheet handle exists before adding inline CSS.
		if ( ! wp_style_is( 'prayer-pop-admin', 'enqueued' ) ) {
			wp_enqueue_style(
				'prayer-pop-admin',
				PRAYERPOP_PLUGIN_URL . 'assets/css/prayer-pop-admin.css',
				array(),
				PRAYERPOP_VERSION
			);
		}

		// Enqueue our style settings script
		$style_settings_js_version = file_exists( PRAYERPOP_PLUGIN_DIR . 'assets/js/prayer-pop-style-settings.js' )
			? (string) filemtime( PRAYERPOP_PLUGIN_DIR . 'assets/js/prayer-pop-style-settings.js' )
			: PRAYERPOP_VERSION;
		wp_enqueue_script( 
			'prayer-pop-style-settings', 
			PRAYERPOP_PLUGIN_URL . 'assets/js/prayer-pop-style-settings.js', 
			array('jquery'), 
			$style_settings_js_version, 
			true 
		);

		// Add custom styles for our color picker and range sliders
		wp_add_inline_style( 'prayer-pop-admin', '
			.prayer-pop-color-field-wrapper {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.prayer-pop-color-input {
				padding: 2px;
				height: 40px;
				width: 60px;
				border: 1px solid #ddd;
				border-radius: 4px;
				cursor: pointer;
			}
			.prayer-pop-color-preview {
				width: 30px;
				height: 30px;
				border-radius: 4px;
				border: 1px solid #ddd;
			}
			.prayer-pop-size-control {
				display: flex;
				align-items: center;
				gap: 15px;
				width: 100%;
				max-width: 680px;
			}
			.prayer-pop-size-control--icon {
				max-width: 760px;
			}
			.prayer-pop-range-slider {
				flex: 1;
				min-width: 360px;
				height: 8px;
				background: #f0f0f0;
				border-radius: 4px;
				outline: none;
				border: none;
				cursor: pointer;
				-webkit-appearance: none;
			}
			.prayer-pop-range-slider::-webkit-slider-thumb {
				-webkit-appearance: none;
				appearance: none;
				width: 20px;
				height: 20px;
				background: #2755AA;
				border-radius: 50%;
				cursor: pointer;
				box-shadow: 0 2px 4px rgba(0,0,0,0.2);
			}
			.prayer-pop-range-slider::-moz-range-thumb {
				width: 20px;
				height: 20px;
				background: #2755AA;
				border-radius: 50%;
				cursor: pointer;
				border: none;
				box-shadow: 0 2px 4px rgba(0,0,0,0.2);
			}
			.prayer-pop-range-slider::-webkit-slider-track {
				height: 8px;
				background: #f0f0f0;
				border-radius: 4px;
			}
			.prayer-pop-range-slider::-moz-range-track {
				height: 8px;
				background: #f0f0f0;
				border-radius: 4px;
				border: none;
			}
			.size-display {
				min-width: 72px;
				text-align: center;
				font-weight: 600;
				font-size: 14px;
				color: #2755AA;
				background: #f8f9fa;
				padding: 5px 10px;
				border-radius: 4px;
				border: 1px solid #e0e0e0;
			}
		');
	}

	/**
	 * Register style settings.
	 */
	public function register_settings() {
		register_setting( 'prayer_pop_settings_group', 'prayer_pop_styles', array( $this, 'sanitize_styles' ) );

			add_settings_section(
				'prayer_pop_style_section',
				'<span class="dashicons dashicons-admin-appearance"></span> ' . esc_html__( 'Style Customization', 'prayerpop' ),
				array( $this, 'render_style_section_description' ),
				'prayer-pop-settings-style'
			);

			$this->add_color_field( 'global_bg_color', esc_html__( 'Primary Color', 'prayerpop' ), '#2755AA' );

			add_settings_field(
				'global_font_family',
				esc_html__( 'Font Family', 'prayerpop' ),
				array( $this, 'font_family_callback' ),
				'prayer-pop-settings-style',
				'prayer_pop_style_section',
				array(
					'id'      => 'global_font_family',
					'default' => 'system-ui',
				)
			);

			add_settings_section(
				'prayer_pop_bubble_icon_section',
				'<span class="dashicons dashicons-format-image"></span> ' . esc_html__( 'Bubble Icon', 'prayerpop' ),
				array( $this, 'render_bubble_icon_section' ),
				'prayer-pop-settings-style'
			);

			add_settings_section( 'prayer_pop_bubble_icon_type_fields_section', '', '__return_empty_string', 'prayer-pop-settings-style-icon-fields' );
			add_settings_section( 'prayer_pop_bubble_icon_dashicon_fields_section', '', '__return_empty_string', 'prayer-pop-settings-style-icon-fields' );
			add_settings_section( 'prayer_pop_bubble_icon_tabler_fields_section', '', '__return_empty_string', 'prayer-pop-settings-style-icon-fields' );
			add_settings_section( 'prayer_pop_bubble_icon_shared_fields_section', '', '__return_empty_string', 'prayer-pop-settings-style-icon-fields' );

			add_settings_field(
				'bubble_icon_type',
				esc_html__( 'Icon Type', 'prayerpop' ),
				array( $this, 'bubble_icon_type_callback' ),
				'prayer-pop-settings-style-icon-fields',
				'prayer_pop_bubble_icon_type_fields_section'
			);
			add_settings_field(
				'bubble_dashicon',
				esc_html__( 'Select Icon', 'prayerpop' ),
				array( $this, 'bubble_dashicon_callback' ),
				'prayer-pop-settings-style-icon-fields',
				'prayer_pop_bubble_icon_dashicon_fields_section'
			);
			add_settings_field(
				'bubble_tabler_icon',
				esc_html__( 'Select Tabler Icon', 'prayerpop' ),
				array( $this, 'bubble_tabler_icon_callback' ),
				'prayer-pop-settings-style-icon-fields',
				'prayer_pop_bubble_icon_tabler_fields_section'
			);
			add_settings_field(
				'bubble_icon_color',
				esc_html__( 'Icon Color', 'prayerpop' ),
				array( $this, 'color_field_callback' ),
				'prayer-pop-settings-style-icon-fields',
				'prayer_pop_bubble_icon_shared_fields_section',
				array(
					'id'      => 'bubble_icon_color',
					'default' => '#ffffff',
				)
			);

			add_settings_field(
				'style_customization_reset_action',
				'',
				array( $this, 'style_customization_reset_button_callback' ),
				'prayer-pop-settings-style',
				'prayer_pop_style_section',
				array(
					'class' => 'prayer-pop-style-reset-row',
				)
			);

		return;
	}

	/**
	 * Render style section description with reset action.
	 *
	 * @return void
	 */
	public function render_style_section_description() {
		?>
		<div class="prayer-pop-style-reset-wrap">
			<p class="description">
				<?php esc_html_e( 'Customize your core colors, typography, and bubble controls (size, animation, layout, icon, and position), or reset this section back to defaults.', 'prayerpop' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render reset button row for style customization section.
	 *
	 * @return void
	 */
	public function style_customization_reset_button_callback() {
		?>
		<div class="prayer-pop-style-reset-row-actions">
			<button
				type="submit"
				class="button prayer-pop-reset-submit"
				id="prayer-pop-reset-style-customization"
				name="prayer_pop_reset_action"
				value="style_customization"
				formnovalidate
				data-confirm="<?php echo esc_attr__( 'Reset style customization values to defaults?', 'prayerpop' ); ?>"
			>
				<?php esc_html_e( 'Reset Style Customization', 'prayerpop' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Helper function to add a color field.
	 */
	private function add_color_field( $id, $label, $default, $section = 'prayer_pop_style_section' ) {
		$page = 'prayer-pop-settings-style';
		if ( in_array( $section, array( 'prayer_pop_bubble_icon_dashicon_fields_section', 'prayer_pop_bubble_icon_tabler_fields_section', 'prayer_pop_bubble_icon_shared_fields_section' ), true ) ) {
			$page = 'prayer-pop-settings-style-icon-fields';
		}

		add_settings_field(
			$id,
			$label,
			array( $this, 'color_field_callback' ),
			$page,
			$section,
			array(
				'id' => $id,
				'default' => $default,
				'label_for' => $id,
			)
		);
	}

	/**
	 * Helper function to add a size field.
	 */
	private function add_size_field( $id, $label, $default, $section = 'prayer_pop_style_section' ) {
		add_settings_field(
			$id,
			$label,
			array( $this, 'size_field_callback' ),
			'prayer-pop-settings-style',
			$section,
			array(
				'id' => $id,
				'default' => $default,
				'label_for' => $id
			)
		);
	}

	/**
	 * Callback for rendering a color field.
	 */
	public function color_field_callback( $args ) {
		$options = get_option( 'prayer_pop_styles', array() );
		$value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : $args['default'];
		?>
		<div class="prayer-pop-color-field-wrapper">
			<input type="color" 
				   id="<?php echo esc_attr( $args['id'] ); ?>"
				   name="prayer_pop_styles[<?php echo esc_attr( $args['id'] ); ?>]"
				   value="<?php echo esc_attr( $value ); ?>"
				   class="prayer-pop-color-input"
				   title="<?php esc_attr_e( 'Choose a color', 'prayerpop' ); ?>" />
			<span class="color-value"><?php echo esc_html( $value ); ?></span>
		</div>
		<?php
	}

	/**
	 * Callback for rendering a size field.
	 */
	public function size_field_callback( $args ) {
		$options = get_option( 'prayer_pop_styles', array() );
		$value = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : $args['default'];
		
		// Extract numeric value and unit
		preg_match('/([0-9.]+)([a-z%]+)?/', $value, $matches);
		$numeric_value = isset($matches[1]) ? $matches[1] : '0';
		?>
		<div class="prayer-pop-size-field-wrapper">
			<input type="text" 
				   id="<?php echo esc_attr( $args['id'] ); ?>"
				   name="prayer_pop_styles[<?php echo esc_attr( $args['id'] ); ?>]"
				   value="<?php echo esc_attr( $value ); ?>"
				   class="regular-text"
				   placeholder="e.g., 15px, 1.2em, 80%">
			<p class="description"><?php esc_html_e('Enter value with unit (use: px, em, rem, or %)', 'prayerpop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Callback for rendering the font family field.
	 */
	public function font_family_callback( $args = array() ) {
		$options = get_option( 'prayer_pop_styles', array() );
		$field_id = isset( $args['id'] ) ? sanitize_key( $args['id'] ) : 'global_font_family';
		$default  = isset( $args['default'] ) ? sanitize_text_field( $args['default'] ) : 'system-ui';
		$current  = isset( $options[ $field_id ] ) ? $options[ $field_id ] : $default;
		$fonts = array(
			'system-ui' => esc_html__( 'System Default', 'prayerpop' ),
			'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif' => esc_html__( 'WordPress Default', 'prayerpop' ),
			'Georgia, serif' => esc_html__( 'Georgia', 'prayerpop' ),
			'"Helvetica Neue", Helvetica, Arial, sans-serif' => esc_html__( 'Helvetica Neue', 'prayerpop' ),
			'Times, "Times New Roman", serif' => esc_html__( 'Times New Roman', 'prayerpop' ),
			'Arial, Helvetica, sans-serif' => esc_html__( 'Arial', 'prayerpop' ),
			'Tahoma, Geneva, sans-serif' => esc_html__( 'Tahoma', 'prayerpop' ),
			'Verdana, Geneva, sans-serif' => esc_html__( 'Verdana', 'prayerpop' ),
			'"Trebuchet MS", Helvetica, sans-serif' => esc_html__( 'Trebuchet MS', 'prayerpop' ),
			'Impact, Charcoal, sans-serif' => esc_html__( 'Impact', 'prayerpop' ),
			'"Courier New", Courier, monospace' => esc_html__( 'Courier New', 'prayerpop' ),
		);
		?>
		<select name="prayer_pop_styles[<?php echo esc_attr( $field_id ); ?>]" id="<?php echo esc_attr( $field_id ); ?>">
			<?php foreach ( $fonts as $value => $label ): ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php
			if ( 'heading_font_family' === $field_id ) {
				esc_html_e( 'Select the font family used for form headings.', 'prayerpop' );
			} else {
				esc_html_e( 'Select the font family to be used throughout the plugin.', 'prayerpop' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Callback for rendering the animation field.
	 */
	public function animation_field_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$animations = array(
			'none'      => esc_html__( 'None', 'prayerpop' ),
			'fade-in'   => esc_html__( 'Fade In', 'prayerpop' ),
			'slide-up'  => esc_html__( 'Slide Up', 'prayerpop' ),
			'bounce-in' => esc_html__( 'Bounce In', 'prayerpop' ),
		);
		$current = isset( $options['bubble_animation'] ) ? $options['bubble_animation'] : 'fade-in';
		if ( ! in_array( $current, array_keys( $animations ), true ) ) {
			$current = 'fade-in';
		}
		?>
		<div class="prayer-pop-animation-preview">
			<select name="prayer_pop_styles[bubble_animation]" id="bubble_animation">
				<?php foreach ( $animations as $value => $label ): ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button preview-animation">
				<?php esc_html_e( 'Preview Animation', 'prayerpop' ); ?>
			</button>
			<div class="animation-preview-bubble"></div>
		</div>
		<?php
	}

	/**
	 * Callback for rendering the bubble layout field.
	 */
	public function bubble_layout_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$layouts = array(
			'icon_text' => esc_html__( 'Icon + Text', 'prayerpop' ),
			'text_icon' => esc_html__( 'Text + Icon', 'prayerpop' ),
			'icon'      => esc_html__( 'Icon Only', 'prayerpop' ),
			'text'      => esc_html__( 'Text Only', 'prayerpop' ),
		);
		$current = isset( $options['bubble_layout'] ) ? sanitize_key( $options['bubble_layout'] ) : 'icon_text';
		if ( ! isset( $layouts[ $current ] ) ) {
			$current = 'icon_text';
		}
		?>
		<select name="prayer_pop_styles[bubble_layout]" id="bubble_layout">
			<?php foreach ( $layouts as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Choose whether the bubble shows icon only, text only, or both (and in which order).', 'prayerpop' ); ?>
		</p>
		<?php
	}

	/**
	 * Callback for rendering the bubble position field.
	 */
	public function bubble_position_callback() {
		$options   = get_option( 'prayer_pop_styles', array() );
		$positions = array(
			'right' => esc_html__( 'Bottom Right', 'prayerpop' ),
			'left'  => esc_html__( 'Bottom Left', 'prayerpop' ),
		);
		$current = isset( $options['bubble_position'] ) ? sanitize_key( $options['bubble_position'] ) : 'right';
		if ( ! isset( $positions[ $current ] ) ) {
			$current = 'right';
		}
		?>
		<select name="prayer_pop_styles[bubble_position]" id="bubble_position">
			<?php foreach ( $positions as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Choose whether the bubble (and popup form) opens from the bottom right or bottom left.', 'prayerpop' ); ?>
		</p>
		<?php
	}

	/**
	 * Callback for rendering the bubble design mode field.
	 */
	public function bubble_design_mode_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$modes   = array(
			'adaptive'     => esc_html__( 'Adaptive Rectangle', 'prayerpop' ),
			'fixed_square' => esc_html__( 'Fixed Square', 'prayerpop' ),
			'fixed_circle' => esc_html__( 'Fixed Circle', 'prayerpop' ),
		);
		$current = isset( $options['bubble_design_mode'] ) ? sanitize_key( $options['bubble_design_mode'] ) : 'fixed_circle';
		if ( ! isset( $modes[ $current ] ) ) {
			$current = 'fixed_circle';
		}
		?>
		<select name="prayer_pop_styles[bubble_design_mode]" id="bubble_design_mode">
			<?php foreach ( $modes as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Adaptive rectangle grows with content. Fixed square/circle keep a constant bubble size and center content inside.', 'prayerpop' ); ?>
		</p>
		<?php
	}

	/**
	 * Callback for rendering the font weight field.
	 */
	public function font_weight_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$current = isset( $options['heading_font_weight'] ) ? $options['heading_font_weight'] : '600';
		$weights = array(
			'300' => esc_html__( 'Light (300)', 'prayerpop' ),
			'400' => esc_html__( 'Regular (400)', 'prayerpop' ),
			'500' => esc_html__( 'Medium (500)', 'prayerpop' ),
			'600' => esc_html__( 'Semi-Bold (600)', 'prayerpop' ),
			'700' => esc_html__( 'Bold (700)', 'prayerpop' ),
			'800' => esc_html__( 'Extra Bold (800)', 'prayerpop' ),
		);
		?>
		<select name="prayer_pop_styles[heading_font_weight]" id="heading_font_weight">
			<?php foreach ( $weights as $value => $label ): ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Select the font weight for headings.', 'prayerpop' ); ?></p>
		<?php
	}

	/**
	 * Render layout section with two-column card layout
	 */
	public function render_layout_section() {
		$options = get_option( 'prayer_pop_styles', array() );
		
		// Define layout fields with their labels and defaults
		$layout_fields = $this->get_layout_field_definitions();
		
		?>
		<div class="prayer-pop-layout-card">
			<div class="layout-card-header">
				<p><?php esc_html_e( 'Configure padding, margins, and dimensions for your PrayerPop elements.', 'prayerpop' ); ?></p>
			</div>
			<div class="layout-fields-grid">
				<?php foreach ( $layout_fields as $field_id => $field_data ) : ?>
					<div class="layout-field">
						<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field_data['label'] ); ?></label>
						<input type="text" 
							   id="<?php echo esc_attr( $field_id ); ?>"
							   name="prayer_pop_styles[<?php echo esc_attr( $field_id ); ?>]"
							   value="<?php echo esc_attr( isset( $options[ $field_id ] ) ? $options[ $field_id ] : $field_data['default'] ); ?>"
							   class="regular-text"
							   placeholder="<?php echo esc_attr( $field_data['default'] ); ?>">
						<p class="description"><?php echo esc_html( $field_data['description'] ); ?></p>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="prayer-pop-layout-reset-row">
					<button
						type="submit"
						class="button prayer-pop-reset-submit prayer-pop-layout-reset-button"
						id="prayer-pop-reset-layout-settings"
						name="prayer_pop_reset_action"
						value="layout"
						formnovalidate
						data-confirm="<?php echo esc_attr__( 'Reset layout settings to defaults?', 'prayerpop' ); ?>"
					>
						<?php esc_html_e( 'Reset Layout Defaults', 'prayerpop' ); ?>
					</button>
				</div>
			</div>
		<?php
	}

	/**
	 * Return style customization defaults for targeted reset.
	 *
	 * @return array<string, string>
	 */
	public function get_style_customization_defaults() {
		return array(
			'global_bg_color'         => '#2755AA',
			'bubble_bg_color'         => '#2755AA',
			'global_font_color'       => '#ffffff',
			'global_label_color'      => '#333333',
			'global_button_hover_color'=> '#1F4A99',
			'global_textarea_bg_color'=> '#f9f9f9',
			'global_border_color'     => '#ddd',
			'heading_font_family'     => 'system-ui',
			'heading_font_size'       => '24px',
			'heading_font_weight'     => '600',
			'global_font_family'      => 'system-ui',
			'global_font_size'        => '16px',
		);
	}

	/**
	 * Return layout defaults for targeted reset.
	 *
	 * @return array<string, string>
	 */
	public function get_layout_defaults() {
		$definitions = $this->get_layout_field_definitions();
		$defaults    = array();

		foreach ( $definitions as $field_id => $field_data ) {
			$defaults[ $field_id ] = isset( $field_data['default'] ) ? $field_data['default'] : '';
		}

		return $defaults;
	}

	/**
	 * Shared layout field definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_layout_field_definitions() {
		return array(
			'global_padding' => array(
				'label'       => esc_html__( 'Global Padding', 'prayerpop' ),
				'default'     => '15px',
				'description' => esc_html__( 'Internal spacing for elements (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'global_margin' => array(
				'label'       => esc_html__( 'Global Margin', 'prayerpop' ),
				'default'     => '15px',
				'description' => esc_html__( 'External spacing between elements (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'global_border_radius' => array(
				'label'       => esc_html__( 'Border Radius', 'prayerpop' ),
				'default'     => '8px',
				'description' => esc_html__( 'Rounded corners for elements (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'bubble_border_radius' => array(
				'label'       => esc_html__( 'Bubble Border Radius', 'prayerpop' ),
				'default'     => '8px',
				'description' => esc_html__( 'Rounded corners for the bubble specifically (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'bubble_padding' => array(
				'label'       => esc_html__( 'Bubble Padding', 'prayerpop' ),
				'default'     => '15px',
				'description' => esc_html__( 'Internal padding for the bubble (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'bubble_margin' => array(
				'label'       => esc_html__( 'Bubble Margin', 'prayerpop' ),
				'default'     => '20px',
				'description' => esc_html__( 'External margin for the bubble (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'bubble_height' => array(
				'label'       => esc_html__( 'Bubble Height', 'prayerpop' ),
				'default'     => '60px',
				'description' => esc_html__( 'Height of the prayer bubble (use: px, em, rem, or %)', 'prayerpop' ),
			),
			'checkbox_margin' => array(
				'label'       => esc_html__( 'Checkbox Margin', 'prayerpop' ),
				'default'     => '8px',
				'description' => esc_html__( 'Spacing below checkboxes (use: px, em, rem, or %)', 'prayerpop' ),
			),
		);
	}

	/**
	 * Render bubble icon section description
	 */
	public function render_bubble_icon_section() {
		$options   = get_option( 'prayer_pop_styles', array() );
		$icon_type = isset( $options['bubble_icon_type'] ) ? sanitize_key( $options['bubble_icon_type'] ) : 'dashicon';

		?>
		<div class="prayer-pop-icon-section-renderer">
		<p><?php esc_html_e( 'Customize the icon displayed in the prayer bubble. Choose from WordPress Dashicons, Tabler SVG icons, or leave it empty for text-only bubbles.', 'prayerpop' ); ?></p>
		<p class="description"><?php esc_html_e( 'When Icon Type is enabled, search and pick from a single combined icon list (Dashicons + Tabler).', 'prayerpop' ); ?></p>
		<table class="form-table" role="presentation">
			<?php do_settings_fields( 'prayer-pop-settings-style-icon-fields', 'prayer_pop_bubble_icon_type_fields_section' ); ?>
			<?php if ( in_array( $icon_type, array( 'dashicon', 'tabler' ), true ) ) : ?>
				<?php do_settings_fields( 'prayer-pop-settings-style-icon-fields', 'prayer_pop_bubble_icon_dashicon_fields_section' ); ?>
				<?php do_settings_fields( 'prayer-pop-settings-style-icon-fields', 'prayer_pop_bubble_icon_shared_fields_section' ); ?>
			<?php endif; ?>
			</table>
			</div>
			<?php
	}

	/**
	 * Bubble icon type callback
	 */
	public function bubble_icon_type_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$current = isset( $options['bubble_icon_type'] ) ? $options['bubble_icon_type'] : 'dashicon';
		$allowed = array( 'none', 'dashicon', 'tabler' );
		if ( ! in_array( $current, $allowed, true ) ) {
			$current = 'dashicon';
		}
		$display_current = ( 'none' === $current ) ? 'none' : $current;
		?>
		<select name="prayer_pop_styles[bubble_icon_type]" id="bubble_icon_type">
			<option value="none" <?php selected( $display_current, 'none' ); ?>>
				<?php esc_html_e( 'No icon', 'prayerpop' ); ?>
			</option>
			<option value="dashicon" <?php selected( $display_current, 'dashicon' ); ?>>
				<?php esc_html_e( 'Icon Library (Dashicons + Tabler)', 'prayerpop' ); ?>
			</option>
			<option value="tabler" <?php selected( $display_current, 'tabler' ); ?> style="display:none;">
				<?php esc_html_e( 'Tabler SVG Icons (MIT)', 'prayerpop' ); ?>
			</option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose no icon, or use the combined icon library.', 'prayerpop' ); ?></p>
		<?php
	}

	/**
	 * Bubble dashicon callback
	 */
	public function bubble_dashicon_callback() {
		$options        = get_option( 'prayer_pop_styles', array() );
		$icon_type      = isset( $options['bubble_icon_type'] ) ? sanitize_key( $options['bubble_icon_type'] ) : 'dashicon';
		$current_dash   = isset( $options['bubble_dashicon'] ) ? sanitize_key( $options['bubble_dashicon'] ) : 'prayerpop';
		$current_tabler = isset( $options['bubble_tabler_icon'] ) ? sanitize_key( $options['bubble_tabler_icon'] ) : 'pray';
		$dataset_url    = $this->get_tabler_icon_dataset_url();
		$prayerpop_icon_url = $this->get_prayerpop_icon_url();
		$dashicons      = $this->get_all_dashicons();

		if ( 'prayerpop' !== $current_dash && ! isset( $dashicons[ $current_dash ] ) ) {
			$current_dash = 'prayerpop';
		}
		if ( ! $this->is_valid_tabler_icon( $current_tabler ) ) {
			$current_tabler = 'pray';
		}

		$selected_value = 'dashicon:' . $current_dash;
		if ( 'tabler' === $icon_type && '' !== $current_tabler ) {
			$selected_value = 'tabler:' . $current_tabler;
		}
		?>
		<div class="prayer-pop-dashicon-selector" id="dashicon_field_wrapper">
			<input type="hidden" name="prayer_pop_styles[bubble_dashicon]" id="bubble_dashicon" value="<?php echo esc_attr( $current_dash ); ?>">
			<input type="hidden" name="prayer_pop_styles[bubble_tabler_icon]" id="bubble_tabler_icon" value="<?php echo esc_attr( $current_tabler ); ?>">
			<div class="dashicon-search-wrapper">
				<input type="text" 
					   id="dashicon_search" 
					   placeholder="<?php esc_attr_e( 'Search icons (Dashicons + Tabler)...', 'prayerpop' ); ?>" 
					   class="regular-text"
					   autocomplete="off">
				<button type="button" id="dashicon_clear_search" class="button"><?php esc_html_e( 'Clear', 'prayerpop' ); ?></button>
			</div>
			
			<div class="dashicon-dropdown-wrapper">
				<select id="bubble_icon_library_select" size="8" aria-describedby="icon_library_results_count">
				<option value="dashicon:prayerpop" <?php selected( $selected_value, 'dashicon:prayerpop' ); ?> data-source="dashicon" data-key="prayerpop" data-search="prayerpop brand logo icon">
					<?php esc_html_e( 'PrayerPop • Brand Icon (prayerpop)', 'prayerpop' ); ?>
				</option>
				<?php foreach ( $dashicons as $value => $label ) : ?>
						<option value="<?php echo esc_attr( 'dashicon:' . $value ); ?>" <?php selected( $selected_value, 'dashicon:' . $value ); ?> data-source="dashicon" data-key="<?php echo esc_attr( $value ); ?>" data-search="<?php echo esc_attr( strtolower( $label . ' ' . $value . ' dashicon wordpress' ) ); ?>">
							<?php echo esc_html( 'WP • ' . $label . ' (' . $value . ')' ); ?>
					</option>
				<?php endforeach; ?>
				<?php if ( 'tabler' === $icon_type ) : ?>
					<option value="<?php echo esc_attr( 'tabler:' . $current_tabler ); ?>" selected data-source="tabler" data-key="<?php echo esc_attr( $current_tabler ); ?>" data-search="<?php echo esc_attr( strtolower( $current_tabler . ' tabler svg' ) ); ?>">
						<?php echo esc_html( 'Tabler • ' . ucwords( str_replace( '-', ' ', $current_tabler ) ) . ' (' . $current_tabler . ')' ); ?>
					</option>
				<?php endif; ?>
			</select>
				<p id="icon_library_results_count" class="description"></p>
			</div>
			
			<div class="dashicon-preview-wrapper">
			<div class="dashicon-preview prayer-pop-icon-preview-shell" id="dashicon_preview">
				<?php if ( 'tabler' === $icon_type ) : ?>
					<?php echo wp_kses_post( $this->build_tabler_svg_markup( $current_tabler ) ); ?>
				<?php elseif ( 'prayerpop' === $current_dash ) : ?>
					<img src="<?php echo esc_url( $prayerpop_icon_url ); ?>" alt="<?php esc_attr_e( 'PrayerPop icon', 'prayerpop' ); ?>" class="prayer-pop-brand-icon">
				<?php else : ?>
					<span class="dashicons dashicons-<?php echo esc_attr( $current_dash ); ?>"></span>
				<?php endif; ?>
			</div>
				<div class="dashicon-info">
					<strong><?php esc_html_e( 'Selected:', 'prayerpop' ); ?></strong>
					<span id="dashicon_name">
						<?php
						if ( 'tabler' === $icon_type ) {
							echo esc_html( 'Tabler • ' . ucwords( str_replace( '-', ' ', $current_tabler ) ) );
						} elseif ( 'prayerpop' === $current_dash ) {
							echo esc_html__( 'PrayerPop • Brand Icon', 'prayerpop' );
						} else {
							echo esc_html( 'WP • ' . ( isset( $dashicons[ $current_dash ] ) ? $dashicons[ $current_dash ] : ucfirst( str_replace( '-', ' ', $current_dash ) ) ) );
						}
						?>
					</span>
					<br>
					<code id="dashicon_class">
						<?php
						if ( 'tabler' === $icon_type ) {
							echo esc_html( 'tabler:' . $current_tabler );
						} elseif ( 'prayerpop' === $current_dash ) {
							echo esc_html( 'dashicon:prayerpop' );
						} else {
							echo esc_html( 'dashicons-' . $current_dash );
						}
						?>
					</code>
				</div>
			</div>
			
			<p class="description"><?php esc_html_e( 'Search and select an icon from both WordPress Dashicons and Tabler SVG icons.', 'prayerpop' ); ?></p>
		</div>
		
		<?php
		$prayer_pop_inline_css = implode( "\n", array(
			'		.prayer-pop-dashicon-selector {',
			'			max-width: 600px;',
			'		}',
			'		.dashicon-search-wrapper {',
			'			display: flex;',
			'			gap: 10px;',
			'			margin-bottom: 10px;',
			'			align-items: center;',
			'		}',
			'		.dashicon-search-wrapper input {',
			'			flex: 1;',
			'		}',
			'		.dashicon-dropdown-wrapper {',
			'			margin-bottom: 15px;',
			'		}',
			'		.dashicon-dropdown-wrapper select {',
			'			width: 100%;',
			'			height: 200px;',
			'			font-family: monospace;',
			'			font-size: 13px;',
			'		}',
			'		.dashicon-dropdown-wrapper option {',
			'			padding: 5px;',
			'			line-height: 1.4;',
			'		}',
			'		.dashicon-preview-wrapper {',
			'			display: flex;',
			'			align-items: center;',
			'			gap: 15px;',
			'			padding: 10px;',
			'			background: #f9f9f9;',
			'			border-radius: 4px;',
			'			margin-bottom: 10px;',
			'		}',
			'		.dashicon-preview {',
			'			display: flex;',
			'			align-items: center;',
			'			justify-content: center;',
			'			width: 72px;',
			'			height: 72px;',
			'			background: #2755AA;',
			'			border-radius: 8px;',
			'		}',
			'		.prayer-pop-icon-preview-shell .dashicons {',
			'			font-size: 42px;',
			'			width: 42px;',
			'			height: 42px;',
			'			line-height: 42px;',
			'			color: currentColor;',
			'		}',
			'		.prayer-pop-icon-preview-shell .prayer-pop-tabler-icon {',
			'			width: 42px;',
			'			height: 42px;',
			'			display: block;',
			'			margin: 0;',
			'			color: currentColor;',
			'		}',
			'		.prayer-pop-icon-preview-shell .prayer-pop-brand-icon {',
			'			width: 42px;',
			'			height: 42px;',
			'			display: block;',
			'			margin: 0;',
			'		}',
			'		.dashicon-info {',
			'			flex: 1;',
			'		}',
			'		.dashicon-info code {',
			'			background: #f0f0f0;',
			'			padding: 2px 6px;',
			'			border-radius: 3px;',
			'			font-size: 12px;',
			'		}',
			'		.dashicon-dropdown-wrapper option[style*="display: none"] {',
			'			display: none !important;',
			'		}',
		) );
		wp_add_inline_style( 'prayer-pop-admin', $prayer_pop_inline_css );
		?>
		
		<?php
		$prayer_pop_inline_js = implode( "\n", array(
			'		(function($) {',
			'			$(document).ready(function() {',
			'				const datasetUrl = __DATASET_URL__;',
			'				const prayerPopIconUrl = __PRAYERPOP_ICON_URL__;',
			'				const resultLimit = 400;',
			'				const $iconType = $(\'#bubble_icon_type\');',
			'				const $dashicon = $(\'#bubble_dashicon\');',
			'				const $tabler = $(\'#bubble_tabler_icon\');',
			'				const $select = $(\'#bubble_icon_library_select\');',
			'				const $search = $(\'#dashicon_search\');',
			'				const $clear = $(\'#dashicon_clear_search\');',
			'				const $preview = $(\'#dashicon_preview\');',
			'				const $name = $(\'#dashicon_name\');',
			'				const $class = $(\'#dashicon_class\');',
			'				const $count = $(\'#icon_library_results_count\');',
			'				const $globalColor = $(\'#global_bg_color\');',
			'				const $bubbleColor = $(\'#bubble_bg_color\');',
			'					const $iconColor = $(\'#bubble_icon_color\');',
			'					let tablerNodes = {};',
			'					const iconIndex = [];',
			'					const featuredIconValues = [',
			'						\'dashicon:prayerpop\',',
			'						\'tabler:pray\',',
			'						\'tabler:heart-handshake\',',
			'						\'tabler:message-circle-heart\',',
			'						\'tabler:message-heart\',',
			'						\'tabler:heart\',',
			'						\'tabler:message-question\',',
			'						\'tabler:message-circle-question\',',
			'						\'tabler:help-circle\',',
			'						\'tabler:question-mark\',',
			'						\'tabler:message-circle\',',
			'						\'tabler:messages\',',
			'						\'tabler:bubble-text\',',
			'						\'tabler:bubble\',',
			'						\'tabler:building-church\',',
			'						\'tabler:cross\',',
			'						\'tabler:peace\',',
			'						\'tabler:sparkles\',',
			'						\'tabler:stars\',',
			'						\'tabler:book-2\',',
			'						\'tabler:book\',',
			'						\'tabler:flame\',',
			'						\'dashicon:heart\',',
			'						\'dashicon:editor-help\',',
			'						\'dashicon:format-chat\',',
			'						\'dashicon:testimonial\',',
			'						\'dashicon:book\',',
			'						\'dashicon:groups\',',
			'						\'dashicon:sos\',',
			'						\'dashicon:lightbulb\'',
			'					];',
			'					const featuredIconRank = featuredIconValues.reduce(function(ranks, value, index) {',
			'						ranks[value] = index;',
			'						return ranks;',
			'					}, {});',
			'',
			'				function escapeAttr(value) {',
			'					return String(value).replace(/[&<>"\']/g, function(chr) {',
			'						return {\'&\':\'&amp;\',\'<\':\'&lt;\',\'>\':\'&gt;\',\'"\':\'&quot;\',"\'":\'&#39;\'}[chr];',
			'					});',
			'				}',
			'',
			'				function humanize(iconName) {',
			'					return String(iconName || \'\').replace(/-/g, \' \').replace(/\\b\\w/g, function(chr) {',
			'						return chr.toUpperCase();',
			'					});',
			'				}',
			'',
			'				function getPreviewBackgroundColor() {',
			'					const bubble = $bubbleColor.val();',
			'					if (bubble && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(bubble)) {',
			'						return bubble;',
			'					}',
			'					const global = $globalColor.val();',
			'					if (global && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(global)) {',
			'						return global;',
			'					}',
			'					return \'#2755AA\';',
			'				}',
			'',
			'				function updatePreviewBackgroundColor() {',
			'					$preview.css(\'background-color\', getPreviewBackgroundColor());',
			'				}',
			'',
			'				function getPreviewIconColor() {',
			'					const iconColor = $iconColor.val();',
			'					if (iconColor && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(iconColor)) {',
			'						return iconColor;',
			'					}',
			'					return \'#ffffff\';',
			'				}',
			'',
			'				function updatePreviewIconColor() {',
			'					$preview.css(\'color\', getPreviewIconColor());',
			'				}',
			'',
			'				function tablerMarkup(iconName) {',
			'					const nodes = tablerNodes[iconName];',
			'					if (!Array.isArray(nodes)) {',
			'						return \'\';',
			'					}',
			'					let markup = \'<svg class="prayer-pop-tabler-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">\';',
			'					nodes.forEach(function(node) {',
			'						if (!Array.isArray(node) || node.length !== 2) {',
			'							return;',
			'						}',
			'						const tag = String(node[0] || \'\');',
			'						const attrs = node[1] || {};',
			'						if (tag !== \'path\' && tag !== \'g\') {',
			'							return;',
			'						}',
			'						let attrString = \'\';',
			'						Object.keys(attrs).forEach(function(attrKey) {',
			'							const attrValue = attrs[attrKey];',
			'							if ([\'d\', \'fill\', \'opacity\', \'stroke\', \'transform\', \'stroke-width\'].indexOf(attrKey) === -1) {',
			'								return;',
			'							}',
			'							attrString += \' \' + attrKey + \'="\' + escapeAttr(attrValue) + \'"\';',
			'						});',
			'						markup += \'<\' + tag + attrString + \'></\' + tag + \'>\';',
			'					});',
			'					markup += \'</svg>\';',
			'					return markup;',
			'				}',
			'',
			'				function prayerPopMarkup() {',
			'					return \'<span class="prayer-pop-brand-icon-mask" aria-hidden="true"></span>\';',
			'				}',
			'',
			'				function addInitialEntries() {',
			'					$select.find(\'option[data-source]\').each(function() {',
			'						const $option = $(this);',
			'						const source = String($option.data(\'source\') || \'\');',
			'						const key = String($option.data(\'key\') || \'\');',
			'						const label = $option.text();',
			'						if (!source || !key) {',
			'							return;',
			'						}',
			'						iconIndex.push({',
			'							value: source + \':\' + key,',
			'							source: source,',
			'							key: key,',
			'							label: label,',
			'							search: String($option.data(\'search\') || label).toLowerCase()',
			'						});',
			'					});',
			'				}',
			'',
			'					function addTablerEntries() {',
			'					for (let i = iconIndex.length - 1; i >= 0; i -= 1) {',
			'						if (iconIndex[i].source === \'tabler\') {',
			'							iconIndex.splice(i, 1);',
			'						}',
			'					}',
			'					Object.keys(tablerNodes).sort().forEach(function(iconName) {',
			'						const nodes = tablerNodes[iconName];',
			'						if (!Array.isArray(nodes)) {',
			'							return;',
			'						}',
			'						iconIndex.push({',
			'							value: \'tabler:\' + iconName,',
			'							source: \'tabler\',',
			'							key: iconName,',
			'							label: \'Tabler • \' + humanize(iconName) + \' (\' + iconName + \')\',',
			'							search: (iconName + \' \' + humanize(iconName) + \' tabler svg\').toLowerCase()',
			'						});',
			'						});',
			'					}',
			'',
			'					function sortIconIndex() {',
			'						iconIndex.sort(function(a, b) {',
			'							const aRank = Object.prototype.hasOwnProperty.call(featuredIconRank, a.value) ? featuredIconRank[a.value] : Number.MAX_SAFE_INTEGER;',
			'							const bRank = Object.prototype.hasOwnProperty.call(featuredIconRank, b.value) ? featuredIconRank[b.value] : Number.MAX_SAFE_INTEGER;',
			'							if (aRank !== bRank) {',
			'								return aRank - bRank;',
			'							}',
			'							return a.label.localeCompare(b.label);',
			'						});',
			'					}',
			'',
			'				function getSelectedValue() {',
			'					const selected = $select.val();',
			'					if (selected) {',
			'						return String(selected);',
			'					}',
			'					const currentType = String($iconType.val() || \'dashicon\');',
			'					if (currentType === \'tabler\' && $tabler.val()) {',
			'						return \'tabler:\' + $tabler.val();',
			'					}',
			'					return \'dashicon:\' + ($dashicon.val() || \'prayerpop\');',
			'				}',
			'',
			'				function renderOptions(searchTerm) {',
			'					const term = String(searchTerm || \'\').toLowerCase().trim();',
			'					const matches = iconIndex.filter(function(icon) {',
			'						return term === \'\' || icon.search.indexOf(term) !== -1;',
			'					});',
			'					const selectedValue = getSelectedValue();',
			'					const shown = matches.slice(0, resultLimit);',
			'					const selectedMatch = matches.find(function(icon) {',
			'						return icon.value === selectedValue;',
			'					});',
			'					if (selectedMatch && !shown.some(function(icon) { return icon.value === selectedValue; })) {',
			'						shown.unshift(selectedMatch);',
			'						if (shown.length > resultLimit) {',
			'							shown.pop();',
			'						}',
			'					}',
			'',
			'					$select.empty();',
			'					shown.forEach(function(icon) {',
			'						const option = document.createElement(\'option\');',
			'						option.value = icon.value;',
			'						option.textContent = icon.label;',
			'						option.selected = icon.value === selectedValue;',
			'						option.setAttribute(\'data-source\', icon.source);',
			'						option.setAttribute(\'data-key\', icon.key);',
			'						$select.append(option);',
			'					});',
			'',
			'					if (shown.length === 0) {',
			'						const option = document.createElement(\'option\');',
			'						option.value = \'\';',
			'						option.textContent = __NO_ICONS_FOUND__;',
			'						option.disabled = true;',
			'						$select.append(option);',
			'					}',
			'',
			'					if (matches.length > resultLimit) {',
			'						$count.text(__SHOWING_FIRST__ + \' \' + resultLimit + \' \' + __OF_LABEL__ + \' \' + matches.length + \' \' + __RESULTS_NARROW__);',
			'					} else {',
			'						$count.text(matches.length + \' \' + __ICON_COUNT_LABEL__);',
			'					}',
			'				}',
			'',
			'				function updateSelection() {',
			'					const selectedValue = String($select.val() || \'\');',
			'					if (!selectedValue || selectedValue.indexOf(\':\') === -1) {',
			'						return;',
			'					}',
			'					const parts = selectedValue.split(\':\');',
			'					const source = parts[0];',
			'					const key = parts.slice(1).join(\':\');',
			'					if (!key) {',
			'						return;',
			'					}',
			'',
			'					if (source === \'tabler\') {',
			'						$iconType.val(\'tabler\');',
			'						$tabler.val(key);',
			'						$name.text(\'Tabler • \' + humanize(key));',
			'						$class.text(\'tabler:\' + key);',
			'						const markup = tablerMarkup(key);',
			'						if (markup) {',
			'							$preview.html(markup);',
			'						}',
			'						return;',
			'					}',
			'',
			'					$iconType.val(\'dashicon\');',
			'					$dashicon.val(key);',
			'					const selectedText = $select.find(\'option:selected\').text();',
			'					const name = selectedText.indexOf(\'(\') > -1 ? selectedText.split(\' (\')[0] : selectedText;',
			'					$name.text(name);',
			'					if (key === \'prayerpop\') {',
			'						$class.text(\'dashicon:prayerpop\');',
			'						$preview.html(prayerPopMarkup());',
			'					} else {',
			'						$class.text(\'dashicons-\' + key);',
			'						$preview.html(\'<span class="dashicons dashicons-\' + key.replace(/"/g, \'\') + \'"></span>\');',
			'					}',
			'				}',
			'',
			'					addInitialEntries();',
			'					sortIconIndex();',
			'',
			'				$search.on(\'input\', function() {',
			'					renderOptions($(this).val());',
			'				});',
			'',
			'				$clear.on(\'click\', function() {',
			'					$search.val(\'\');',
			'					renderOptions(\'\');',
			'					$search.focus();',
			'				});',
			'',
			'				$select.on(\'change\', updateSelection);',
			'',
			'				fetch(datasetUrl, { cache: \'force-cache\' })',
			'					.then(function(response) {',
			'						if (!response.ok) {',
			'							throw new Error(\'Dataset load failed\');',
			'						}',
			'						return response.json();',
			'					})',
			'						.then(function(data) {',
			'							tablerNodes = data && typeof data === \'object\' ? data : {};',
			'							addTablerEntries();',
			'							sortIconIndex();',
			'							renderOptions($search.val());',
			'							updateSelection();',
			'						})',
			'						.catch(function() {',
			'							sortIconIndex();',
			'							renderOptions($search.val());',
			'							$count.append(\' \' + __TABLER_LOAD_FAILED__);',
			'					});',
			'',
			'				$preview.css(\'--prayer-pop-brand-icon-url\', \'url("\' + prayerPopIconUrl.replace(/"/g, \'\\\\"\') + \'")\');',
			'				$(document).on(\'input change\', \'#global_bg_color, #bubble_bg_color\', updatePreviewBackgroundColor);',
			'				$(document).on(\'input change\', \'#bubble_icon_color\', updatePreviewIconColor);',
			'',
			'				renderOptions(\'\');',
			'				updatePreviewBackgroundColor();',
			'				updatePreviewIconColor();',
			'			});',
			'		})(jQuery);',
		) );
		$prayer_pop_inline_js = strtr(
			$prayer_pop_inline_js,
			array(
				'__DATASET_URL__' => wp_json_encode( $dataset_url ),
				'__PRAYERPOP_ICON_URL__' => wp_json_encode( $prayerpop_icon_url ),
				'__NO_ICONS_FOUND__' => wp_json_encode( __( 'No icons found', 'prayerpop' ) ),
				'__SHOWING_FIRST__' => wp_json_encode( __( 'Showing first', 'prayerpop' ) ),
				'__OF_LABEL__' => wp_json_encode( __( 'of', 'prayerpop' ) ),
				'__RESULTS_NARROW__' => wp_json_encode( __( 'results. Keep typing to narrow down.', 'prayerpop' ) ),
				'__ICON_COUNT_LABEL__' => wp_json_encode( __( 'icon(s)', 'prayerpop' ) ),
				'__TABLER_LOAD_FAILED__' => wp_json_encode( __( 'Tabler dataset could not be loaded.', 'prayerpop' ) ),
			)
		);
		wp_add_inline_script( 'prayer-pop-admin', $prayer_pop_inline_js );
		?>
		<?php
	}

	/**
	 * Bubble Tabler SVG icon callback.
	 */
	public function bubble_tabler_icon_callback() {
		$options      = get_option( 'prayer_pop_styles', array() );
		$current_icon = isset( $options['bubble_tabler_icon'] ) ? sanitize_key( $options['bubble_tabler_icon'] ) : 'pray';
		$dataset_url  = $this->get_tabler_icon_dataset_url();

		if ( ! $this->is_valid_tabler_icon( $current_icon ) ) {
			$current_icon = 'pray';
		}

		$preview_markup = $this->build_tabler_svg_markup( $current_icon );
		?>
		<div class="prayer-pop-tabler-selector" id="tabler_icon_field_wrapper">
			<input type="hidden" name="prayer_pop_styles[bubble_tabler_icon]" id="bubble_tabler_icon" value="<?php echo esc_attr( $current_icon ); ?>">
			<div class="dashicon-search-wrapper">
				<input type="text"
					   id="tabler_icon_search"
					   placeholder="<?php esc_attr_e( 'Search Tabler icons...', 'prayerpop' ); ?>"
					   class="regular-text"
					   autocomplete="off">
				<button type="button" id="tabler_icon_clear_search" class="button"><?php esc_html_e( 'Clear', 'prayerpop' ); ?></button>
			</div>

			<div class="dashicon-dropdown-wrapper">
				<select id="bubble_tabler_icon_select" size="8" aria-describedby="tabler_icon_results_count"></select>
				<p id="tabler_icon_results_count" class="description"></p>
			</div>

			<div class="dashicon-preview-wrapper">
				<div class="dashicon-preview prayer-pop-tabler-preview-shell" id="tabler_icon_preview"><?php echo wp_kses_post( $preview_markup ); ?></div>
				<div class="dashicon-info">
					<strong><?php esc_html_e( 'Selected:', 'prayerpop' ); ?></strong>
					<span id="tabler_icon_name"><?php echo esc_html( ucwords( str_replace( '-', ' ', $current_icon ) ) ); ?></span>
					<br>
					<code id="tabler_icon_key"><?php echo esc_html( $current_icon ); ?></code>
				</div>
			</div>

			<p class="description">
				<?php esc_html_e( 'Search and select a Tabler SVG icon. Tip: try "pray", "cross", "heart", or "hands".', 'prayerpop' ); ?>
			</p>
		</div>

		<?php
		$prayer_pop_inline_css = implode( "\n", array(
			'		.prayer-pop-tabler-selector {',
			'			max-width: 600px;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-search-wrapper {',
			'			display: flex;',
			'			gap: 10px;',
			'			margin-bottom: 10px;',
			'			align-items: center;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-search-wrapper input {',
			'			flex: 1;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-dropdown-wrapper {',
			'			margin-bottom: 15px;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-dropdown-wrapper select {',
			'			width: 100%;',
			'			height: 200px;',
			'			font-family: monospace;',
			'			font-size: 13px;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-dropdown-wrapper option {',
			'			padding: 5px;',
			'			line-height: 1.4;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-preview-wrapper {',
			'			display: flex;',
			'			align-items: center;',
			'			gap: 15px;',
			'			padding: 10px;',
			'			border-radius: 4px;',
			'			margin-bottom: 10px;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-info {',
			'			flex: 1;',
			'		}',
			'		.prayer-pop-tabler-selector .dashicon-info code {',
			'			background: #f0f0f0;',
			'			padding: 2px 6px;',
			'			border-radius: 3px;',
			'			font-size: 12px;',
			'		}',
			'		.prayer-pop-tabler-preview-shell {',
			'			display: flex;',
			'			align-items: center;',
			'			justify-content: center;',
			'			width: 64px;',
			'			height: 64px;',
			'			min-width: 64px;',
			'			border-radius: 8px;',
			'			background: #2755AA;',
			'			transition: background-color 0.2s ease;',
			'		}',
			'		.prayer-pop-tabler-preview-shell .prayer-pop-tabler-icon {',
			'			width: 36px;',
			'			height: 36px;',
			'			display: block;',
			'			margin: 0;',
			'			color: currentColor;',
			'		}',
		) );
		wp_add_inline_style( 'prayer-pop-admin', $prayer_pop_inline_css );
		?>

		<?php
		$prayer_pop_inline_js = implode( "\n", array(
			'		(function($) {',
			'			$(document).ready(function() {',
			'				const datasetUrl = __DATASET_URL__;',
			'				const initialIcon = __INITIAL_ICON__;',
			'				const resultLimit = 300;',
			'				const $hidden = $(\'#bubble_tabler_icon\');',
			'				const $search = $(\'#tabler_icon_search\');',
			'				const $clear = $(\'#tabler_icon_clear_search\');',
			'				const $select = $(\'#bubble_tabler_icon_select\');',
			'				const $preview = $(\'#tabler_icon_preview\');',
			'				const $name = $(\'#tabler_icon_name\');',
			'				const $key = $(\'#tabler_icon_key\');',
			'				const $count = $(\'#tabler_icon_results_count\');',
			'				const $globalColor = $(\'#global_bg_color\');',
			'				const $bubbleColor = $(\'#bubble_bg_color\');',
			'				const $iconColor = $(\'#bubble_icon_color\');',
			'				let iconNodes = {};',
			'				let iconIndex = [];',
			'',
			'				function humanize(iconName) {',
			'					return iconName.replace(/-/g, \' \').replace(/\\b\\w/g, function(chr) {',
			'						return chr.toUpperCase();',
			'					});',
			'				}',
			'',
			'				function escapeAttr(value) {',
			'					return String(value).replace(/[&<>"\']/g, function(chr) {',
			'						return {\'&\':\'&amp;\',\'<\':\'&lt;\',\'>\':\'&gt;\',\'"\':\'&quot;\',"\'":\'&#39;\'}[chr];',
			'					});',
			'				}',
			'',
			'				function iconMarkup(iconName) {',
			'					const nodes = iconNodes[iconName];',
			'					if (!Array.isArray(nodes)) {',
			'						return \'\';',
			'					}',
			'',
			'					let markup = \'<svg class="prayer-pop-tabler-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">\';',
			'					nodes.forEach(function(node) {',
			'						if (!Array.isArray(node) || node.length !== 2) {',
			'							return;',
			'						}',
			'						const tag = String(node[0] || \'\');',
			'						const attrs = node[1] || {};',
			'						if (tag !== \'path\' && tag !== \'g\') {',
			'							return;',
			'						}',
			'',
			'						let attrString = \'\';',
			'						Object.keys(attrs).forEach(function(attrKey) {',
			'							const attrValue = attrs[attrKey];',
			'							if ([\'d\', \'fill\', \'opacity\', \'stroke\', \'transform\', \'stroke-width\'].indexOf(attrKey) === -1) {',
			'								return;',
			'							}',
			'							attrString += \' \' + attrKey + \'="\' + escapeAttr(attrValue) + \'"\';',
			'						});',
			'						markup += \'<\' + tag + attrString + \'></\' + tag + \'>\';',
			'					});',
			'					markup += \'</svg>\';',
			'					return markup;',
			'				}',
			'',
			'				function updateSelection(iconName) {',
			'					if (!iconName || !iconNodes[iconName]) {',
			'						return;',
			'					}',
			'					$hidden.val(iconName);',
			'					$name.text(humanize(iconName));',
			'					$key.text(iconName);',
			'					$preview.html(iconMarkup(iconName));',
			'				}',
			'',
			'				function getPreviewBackgroundColor() {',
			'					const bubble = $bubbleColor.val();',
			'					if (bubble && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(bubble)) {',
			'						return bubble;',
			'					}',
			'					const global = $globalColor.val();',
			'					if (global && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(global)) {',
			'						return global;',
			'					}',
			'					return \'#2755AA\';',
			'				}',
			'',
			'				function updatePreviewBackgroundColor() {',
			'					$preview.css(\'background-color\', getPreviewBackgroundColor());',
			'				}',
			'',
			'				function getPreviewIconColor() {',
			'					const iconColor = $iconColor.val();',
			'					if (iconColor && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(iconColor)) {',
			'						return iconColor;',
			'					}',
			'					return \'#ffffff\';',
			'				}',
			'',
			'				function updatePreviewIconColor() {',
			'					$preview.css(\'color\', getPreviewIconColor());',
			'				}',
			'',
			'				function renderOptions(searchTerm) {',
			'					const term = String(searchTerm || \'\').toLowerCase().trim();',
			'					const matches = iconIndex.filter(function(icon) {',
			'						return term === \'\' || icon.search.indexOf(term) !== -1;',
			'					});',
			'					const selectedValue = $hidden.val();',
			'					const shown = matches.slice(0, resultLimit);',
			'					const selectedMatch = matches.find(function(icon) {',
			'						return icon.name === selectedValue;',
			'					});',
			'					if (selectedMatch && !shown.some(function(icon) { return icon.name === selectedValue; })) {',
			'						shown.unshift(selectedMatch);',
			'						if (shown.length > resultLimit) {',
			'							shown.pop();',
			'						}',
			'					}',
			'',
			'					$select.empty();',
			'					shown.forEach(function(icon) {',
			'						const option = document.createElement(\'option\');',
			'						option.value = icon.name;',
			'						option.textContent = icon.label + \' (\' + icon.name + \')\';',
			'						option.selected = icon.name === selectedValue;',
			'						$select.append(option);',
			'					});',
			'',
			'					if (shown.length === 0) {',
			'						const option = document.createElement(\'option\');',
			'						option.value = \'\';',
			'						option.textContent = __NO_ICONS_FOUND__;',
			'						option.disabled = true;',
			'						$select.append(option);',
			'					}',
			'',
			'					if (matches.length > resultLimit) {',
			'						$count.text(__SHOWING_FIRST__ + \' \' + resultLimit + \' \' + __OF_LABEL__ + \' \' + matches.length + \' \' + __RESULTS_NARROW__);',
			'					} else {',
			'						$count.text(matches.length + \' \' + __ICON_COUNT_LABEL__);',
			'					}',
			'				}',
			'',
			'				fetch(datasetUrl, { cache: \'force-cache\' })',
			'					.then(function(response) {',
			'						if (!response.ok) {',
			'							throw new Error(\'Dataset load failed\');',
			'						}',
			'						return response.json();',
			'					})',
			'					.then(function(data) {',
			'						iconNodes = data && typeof data === \'object\' ? data : {};',
			'						iconIndex = Object.keys(iconNodes).sort().filter(function(iconName) {',
			'							const nodes = iconNodes[iconName];',
			'							return Array.isArray(nodes) && nodes.some(function(node) {',
			'								return Array.isArray(node) && node[0] === \'path\';',
			'							});',
			'						}).map(function(iconName) {',
			'							const label = humanize(iconName);',
			'							return {',
			'								name: iconName,',
			'								label: label,',
			'								search: (iconName + \' \' + label).toLowerCase()',
			'							};',
			'						});',
			'',
			'						let selected = initialIcon;',
			'						if (!iconNodes[selected]) {',
			'							selected = iconNodes.pray ? \'pray\' : (iconIndex.length ? iconIndex[0].name : \'\');',
			'						}',
			'',
			'						if (selected) {',
			'							$hidden.val(selected);',
			'							updateSelection(selected);',
			'						}',
			'',
			'						renderOptions(\'\');',
			'						updatePreviewBackgroundColor();',
			'						updatePreviewIconColor();',
			'					})',
			'					.catch(function() {',
			'						$count.text(__TABLER_LOAD_FAILED_RESAVE__);',
			'					});',
			'',
			'				$search.on(\'input\', function() {',
			'					renderOptions($(this).val());',
			'				});',
			'',
			'				$clear.on(\'click\', function() {',
			'					$search.val(\'\');',
			'					renderOptions(\'\');',
			'					$search.focus();',
			'				});',
			'',
			'				$select.on(\'change\', function() {',
			'					updateSelection($(this).val());',
			'				});',
			'',
			'				$(document).on(\'input change\', \'#global_bg_color, #bubble_bg_color\', function() {',
			'					updatePreviewBackgroundColor();',
			'				});',
			'				$(document).on(\'input change\', \'#bubble_icon_color\', function() {',
			'					updatePreviewIconColor();',
			'				});',
			'',
			'				updatePreviewBackgroundColor();',
			'				updatePreviewIconColor();',
			'			});',
			'		})(jQuery);',
		) );
		$prayer_pop_inline_js = strtr(
			$prayer_pop_inline_js,
			array(
				'__DATASET_URL__' => wp_json_encode( $dataset_url ),
				'__INITIAL_ICON__' => wp_json_encode( $current_icon ),
				'__NO_ICONS_FOUND__' => wp_json_encode( __( 'No icons found', 'prayerpop' ) ),
				'__SHOWING_FIRST__' => wp_json_encode( __( 'Showing first', 'prayerpop' ) ),
				'__OF_LABEL__' => wp_json_encode( __( 'of', 'prayerpop' ) ),
				'__RESULTS_NARROW__' => wp_json_encode( __( 'results. Keep typing to narrow down.', 'prayerpop' ) ),
				'__ICON_COUNT_LABEL__' => wp_json_encode( __( 'icon(s)', 'prayerpop' ) ),
				'__TABLER_LOAD_FAILED_RESAVE__' => wp_json_encode( __( 'Could not load Tabler icon data. Re-save or refresh the page.', 'prayerpop' ) ),
			)
		);
		wp_add_inline_script( 'prayer-pop-admin', $prayer_pop_inline_js );
		?>
		<?php
	}

	/**
	 * Get the Tabler icon dataset URL.
	 *
	 * @return string
	 */
	private function get_tabler_icon_dataset_url() {
		return PRAYERPOP_PLUGIN_URL . 'assets/data/tabler-nodes-outline.json';
	}

	/**
	 * Get bundled PrayerPop icon URL.
	 *
	 * @return string
	 */
	private function get_prayerpop_icon_url() {
		return PRAYERPOP_PLUGIN_URL . 'assets/images/prayerpop-icon.svg';
	}

	/**
	 * Returns the Tabler icon map loaded from plugin data.
	 *
	 * @return array<string, array>
	 */
	private function get_tabler_icon_nodes_map() {
		static $icons = null;
		if ( null !== $icons ) {
			return $icons;
		}

		$icons = array();
		$file  = PRAYERPOP_PLUGIN_DIR . 'assets/data/tabler-nodes-outline.json';
		if ( ! file_exists( $file ) ) {
			return $icons;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $file );
		if ( false === $raw || '' === $raw ) {
			return $icons;
		}

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			$icons = $decoded;
		}

		return $icons;
	}

	/**
	 * Check whether the requested Tabler icon exists in the local dataset.
	 *
	 * @param string $icon_name Icon slug.
	 * @return bool
	 */
	private function is_valid_tabler_icon( $icon_name ) {
		$icons = $this->get_tabler_icon_nodes_map();

		return isset( $icons[ $icon_name ] ) && $this->tabler_icon_has_renderable_paths( $icons[ $icon_name ] );
	}

	/**
	 * Check that icon node data contains at least one renderable path.
	 *
	 * @param mixed $nodes Icon node definition.
	 * @return bool
	 */
	private function tabler_icon_has_renderable_paths( $nodes ) {
		if ( ! is_array( $nodes ) ) {
			return false;
		}

		foreach ( $nodes as $node ) {
			if ( is_array( $node ) && isset( $node[0] ) && 'path' === $node[0] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build sanitized inline SVG markup for a Tabler icon slug.
	 *
	 * @param string $icon_name Icon slug.
	 * @return string
	 */
	private function build_tabler_svg_markup( $icon_name ) {
		$icon_name = sanitize_key( $icon_name );
		if ( '' === $icon_name || ! $this->is_valid_tabler_icon( $icon_name ) ) {
			return '';
		}

		$icons = $this->get_tabler_icon_nodes_map();
		$nodes = $icons[ $icon_name ];
		$svg   = '<svg class="prayer-pop-tabler-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || 2 !== count( $node ) ) {
				continue;
			}

			$tag_name = isset( $node[0] ) ? (string) $node[0] : '';
			$attrs    = isset( $node[1] ) && is_array( $node[1] ) ? $node[1] : array();
			if ( ! in_array( $tag_name, array( 'path', 'g' ), true ) ) {
				continue;
			}

			$attr_string = '';
			foreach ( $attrs as $attr_name => $attr_value ) {
				if ( ! in_array( $attr_name, array( 'd', 'fill', 'opacity', 'stroke', 'transform', 'stroke-width' ), true ) ) {
					continue;
				}
				$attr_string .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( (string) $attr_value ) );
			}

			$svg .= sprintf( '<%1$s%2$s></%1$s>', esc_attr( $tag_name ), $attr_string );
		}

		$svg .= '</svg>';

		return wp_kses( $svg, $this->get_allowed_svg_html() );
	}

	/**
	 * Allowed SVG tags/attributes for inline icon rendering.
	 *
	 * @return array
	 */
	private function get_allowed_svg_html() {
		return array(
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
		);
	}
	
	/**
	 * Get complete list of WordPress Dashicons
	 */
	private function get_all_dashicons() {
		return array(
			// Admin Menu
			'admin-appearance' => __( 'Appearance', 'prayerpop' ),
			'admin-collapse' => __( 'Collapse', 'prayerpop' ),
			'admin-comments' => __( 'Comments', 'prayerpop' ),
			'admin-customizer' => __( 'Customizer', 'prayerpop' ),
			'admin-generic' => __( 'Generic', 'prayerpop' ),
			'admin-home' => __( 'Home', 'prayerpop' ),
			'admin-links' => __( 'Links', 'prayerpop' ),
			'admin-media' => __( 'Media', 'prayerpop' ),
			'admin-multisite' => __( 'Multisite', 'prayerpop' ),
			'admin-network' => __( 'Network', 'prayerpop' ),
			'admin-page' => __( 'Page', 'prayerpop' ),
			'admin-plugins' => __( 'Plugins', 'prayerpop' ),
			'admin-post' => __( 'Post', 'prayerpop' ),
			'admin-settings' => __( 'Settings', 'prayerpop' ),
			'admin-site' => __( 'Site', 'prayerpop' ),
			'admin-site-alt' => __( 'Site Alt', 'prayerpop' ),
			'admin-site-alt2' => __( 'Site Alt 2', 'prayerpop' ),
			'admin-site-alt3' => __( 'Site Alt 3', 'prayerpop' ),
			'admin-tools' => __( 'Tools', 'prayerpop' ),
			'admin-users' => __( 'Users', 'prayerpop' ),
			
			// Welcome Screen
			'welcome-add-page' => __( 'Add Page', 'prayerpop' ),
			'welcome-comments' => __( 'Comments', 'prayerpop' ),
			'welcome-learn-more' => __( 'Learn More', 'prayerpop' ),
			'welcome-view-site' => __( 'View Site', 'prayerpop' ),
			'welcome-widgets-menus' => __( 'Widgets Menus', 'prayerpop' ),
			'welcome-write-blog' => __( 'Write Blog', 'prayerpop' ),
			
			// Post Formats
			'format-aside' => __( 'Aside', 'prayerpop' ),
			'format-audio' => __( 'Audio', 'prayerpop' ),
			'format-chat' => __( 'Chat', 'prayerpop' ),
			'format-gallery' => __( 'Gallery', 'prayerpop' ),
			'format-image' => __( 'Image', 'prayerpop' ),
			'format-quote' => __( 'Quote', 'prayerpop' ),
			'format-status' => __( 'Status', 'prayerpop' ),
			'format-video' => __( 'Video', 'prayerpop' ),
			
			// Media
			'media-archive' => __( 'Media Archive', 'prayerpop' ),
			'media-audio' => __( 'Media Audio', 'prayerpop' ),
			'media-code' => __( 'Media Code', 'prayerpop' ),
			'media-default' => __( 'Media Default', 'prayerpop' ),
			'media-document' => __( 'Media Document', 'prayerpop' ),
			'media-interactive' => __( 'Media Interactive', 'prayerpop' ),
			'media-spreadsheet' => __( 'Media Spreadsheet', 'prayerpop' ),
			'media-text' => __( 'Media Text', 'prayerpop' ),
			'media-video' => __( 'Media Video', 'prayerpop' ),
			'playlist-audio' => __( 'Playlist Audio', 'prayerpop' ),
			'playlist-video' => __( 'Playlist Video', 'prayerpop' ),
			
			// Image Editing
			'image-crop' => __( 'Image Crop', 'prayerpop' ),
			'image-filter' => __( 'Image Filter', 'prayerpop' ),
			'image-flip-horizontal' => __( 'Image Flip Horizontal', 'prayerpop' ),
			'image-flip-vertical' => __( 'Image Flip Vertical', 'prayerpop' ),
			'image-rotate' => __( 'Image Rotate', 'prayerpop' ),
			'image-rotate-left' => __( 'Image Rotate Left', 'prayerpop' ),
			'image-rotate-right' => __( 'Image Rotate Right', 'prayerpop' ),
			
			// Editor
			'editor-aligncenter' => __( 'Align Center', 'prayerpop' ),
			'editor-alignleft' => __( 'Align Left', 'prayerpop' ),
			'editor-alignright' => __( 'Align Right', 'prayerpop' ),
			'editor-bold' => __( 'Bold', 'prayerpop' ),
			'editor-break' => __( 'Break', 'prayerpop' ),
			'editor-code' => __( 'Code', 'prayerpop' ),
			'editor-contract' => __( 'Contract', 'prayerpop' ),
			'editor-customchar' => __( 'Custom Character', 'prayerpop' ),
			'editor-expand' => __( 'Expand', 'prayerpop' ),
			'editor-help' => __( 'Help', 'prayerpop' ),
			'editor-indent' => __( 'Indent', 'prayerpop' ),
			'editor-insertmore' => __( 'Insert More', 'prayerpop' ),
			'editor-italic' => __( 'Italic', 'prayerpop' ),
			'editor-justify' => __( 'Justify', 'prayerpop' ),
			'editor-kitchensink' => __( 'Kitchen Sink', 'prayerpop' ),
			'editor-ltr' => __( 'Left to Right', 'prayerpop' ),
			'editor-ol' => __( 'Ordered List', 'prayerpop' ),
			'editor-ol-rtl' => __( 'Ordered List RTL', 'prayerpop' ),
			'editor-outdent' => __( 'Outdent', 'prayerpop' ),
			'editor-paragraph' => __( 'Paragraph', 'prayerpop' ),
			'editor-paste-text' => __( 'Paste Text', 'prayerpop' ),
			'editor-paste-word' => __( 'Paste Word', 'prayerpop' ),
			'editor-quote' => __( 'Quote', 'prayerpop' ),
			'editor-removeformatting' => __( 'Remove Formatting', 'prayerpop' ),
			'editor-rtl' => __( 'Right to Left', 'prayerpop' ),
			'editor-spellcheck' => __( 'Spell Check', 'prayerpop' ),
			'editor-strikethrough' => __( 'Strikethrough', 'prayerpop' ),
			'editor-table' => __( 'Table', 'prayerpop' ),
			'editor-textcolor' => __( 'Text Color', 'prayerpop' ),
			'editor-ul' => __( 'Unordered List', 'prayerpop' ),
			'editor-underline' => __( 'Underline', 'prayerpop' ),
			'editor-unlink' => __( 'Unlink', 'prayerpop' ),
			'editor-video' => __( 'Video', 'prayerpop' ),
			
			// Posts
			'align-center' => __( 'Align Center Alt', 'prayerpop' ),
			'align-full-width' => __( 'Align Full Width', 'prayerpop' ),
			'align-left' => __( 'Align Left Alt', 'prayerpop' ),
			'align-none' => __( 'Align None', 'prayerpop' ),
			'align-pull-left' => __( 'Align Pull Left', 'prayerpop' ),
			'align-pull-right' => __( 'Align Pull Right', 'prayerpop' ),
			'align-right' => __( 'Align Right Alt', 'prayerpop' ),
			'align-wide' => __( 'Align Wide', 'prayerpop' ),
			'block-default' => __( 'Block Default', 'prayerpop' ),
			'button' => __( 'Button', 'prayerpop' ),
			'cloud-saved' => __( 'Cloud Saved', 'prayerpop' ),
			'cloud-upload' => __( 'Cloud Upload', 'prayerpop' ),
			'columns' => __( 'Columns', 'prayerpop' ),
			'cover-image' => __( 'Cover Image', 'prayerpop' ),
			'embed-audio' => __( 'Embed Audio', 'prayerpop' ),
			'embed-generic' => __( 'Embed Generic', 'prayerpop' ),
			'embed-photo' => __( 'Embed Photo', 'prayerpop' ),
			'embed-post' => __( 'Embed Post', 'prayerpop' ),
			'embed-video' => __( 'Embed Video', 'prayerpop' ),
			'exit' => __( 'Exit', 'prayerpop' ),
			'html' => __( 'HTML', 'prayerpop' ),
			'info-outline' => __( 'Info Outline', 'prayerpop' ),
			'insert-after' => __( 'Insert After', 'prayerpop' ),
			'insert-before' => __( 'Insert Before', 'prayerpop' ),
			'insert' => __( 'Insert', 'prayerpop' ),
			'remove' => __( 'Remove', 'prayerpop' ),
			'table-col-after' => __( 'Table Column After', 'prayerpop' ),
			'table-col-before' => __( 'Table Column Before', 'prayerpop' ),
			'table-col-delete' => __( 'Table Column Delete', 'prayerpop' ),
			'table-row-after' => __( 'Table Row After', 'prayerpop' ),
			'table-row-before' => __( 'Table Row Before', 'prayerpop' ),
			'table-row-delete' => __( 'Table Row Delete', 'prayerpop' ),
			
			// Sorting
			'leftright' => __( 'Left Right', 'prayerpop' ),
			'sort' => __( 'Sort', 'prayerpop' ),
			'randomize' => __( 'Randomize', 'prayerpop' ),
			'list-view' => __( 'List View', 'prayerpop' ),
			'excerpt-view' => __( 'Excerpt View', 'prayerpop' ),
			'grid-view' => __( 'Grid View', 'prayerpop' ),
			'move' => __( 'Move', 'prayerpop' ),
			
			// Social
			'facebook' => __( 'Facebook', 'prayerpop' ),
			'facebook-alt' => __( 'Facebook Alt', 'prayerpop' ),
			'googleplus' => __( 'Google Plus', 'prayerpop' ),
			'instagram' => __( 'Instagram', 'prayerpop' ),
			'linkedin' => __( 'LinkedIn', 'prayerpop' ),
			'pinterest' => __( 'Pinterest', 'prayerpop' ),
			'podio' => __( 'Podio', 'prayerpop' ),
			'reddit' => __( 'Reddit', 'prayerpop' ),
			'share' => __( 'Share', 'prayerpop' ),
			'share-alt' => __( 'Share Alt', 'prayerpop' ),
			'share-alt2' => __( 'Share Alt 2', 'prayerpop' ),
			'twitter' => __( 'Twitter', 'prayerpop' ),
			'twitter-alt' => __( 'Twitter Alt', 'prayerpop' ),
			'whatsapp' => __( 'WhatsApp', 'prayerpop' ),
			'youtube' => __( 'YouTube', 'prayerpop' ),
			
			// Jobs
			'businessperson' => __( 'Business Person', 'prayerpop' ),
			'businesswoman' => __( 'Business Woman', 'prayerpop' ),
			'businessman' => __( 'Businessman', 'prayerpop' ),
			
			// Products
			'products' => __( 'Products', 'prayerpop' ),
			'awards' => __( 'Awards', 'prayerpop' ),
			'forms' => __( 'Forms', 'prayerpop' ),
			'analytics' => __( 'Analytics', 'prayerpop' ),
			'chart-pie' => __( 'Chart Pie', 'prayerpop' ),
			'chart-bar' => __( 'Chart Bar', 'prayerpop' ),
			'chart-line' => __( 'Chart Line', 'prayerpop' ),
			'chart-area' => __( 'Chart Area', 'prayerpop' ),
			
			// Taxonomies
			'category' => __( 'Category', 'prayerpop' ),
			'tag' => __( 'Tag', 'prayerpop' ),
			
			// WordPress.org specific
			'wordpress' => __( 'WordPress', 'prayerpop' ),
			'wordpress-alt' => __( 'WordPress Alt', 'prayerpop' ),
			'pressthis' => __( 'Press This', 'prayerpop' ),
			'update' => __( 'Update', 'prayerpop' ),
			'update-alt' => __( 'Update Alt', 'prayerpop' ),
			'screenoptions' => __( 'Screen Options', 'prayerpop' ),
			'info' => __( 'Info', 'prayerpop' ),
			'cart' => __( 'Cart', 'prayerpop' ),
			'feedback' => __( 'Feedback', 'prayerpop' ),
			'plugins-checked' => __( 'Plugins Checked', 'prayerpop' ),
			
			// Internal/Products
			'dismiss' => __( 'Dismiss', 'prayerpop' ),
			'marker' => __( 'Marker', 'prayerpop' ),
			'star-filled' => __( 'Star Filled', 'prayerpop' ),
			'star-half' => __( 'Star Half', 'prayerpop' ),
			'star-empty' => __( 'Star Empty', 'prayerpop' ),
			'flag' => __( 'Flag', 'prayerpop' ),
			'warning' => __( 'Warning', 'prayerpop' ),
			
			// Navigation
			'menu' => __( 'Menu', 'prayerpop' ),
			'menu-alt' => __( 'Menu Alt', 'prayerpop' ),
			'menu-alt2' => __( 'Menu Alt 2', 'prayerpop' ),
			'menu-alt3' => __( 'Menu Alt 3', 'prayerpop' ),
			'arrow-up' => __( 'Arrow Up', 'prayerpop' ),
			'arrow-down' => __( 'Arrow Down', 'prayerpop' ),
			'arrow-right' => __( 'Arrow Right', 'prayerpop' ),
			'arrow-left' => __( 'Arrow Left', 'prayerpop' ),
			'arrow-up-alt' => __( 'Arrow Up Alt', 'prayerpop' ),
			'arrow-down-alt' => __( 'Arrow Down Alt', 'prayerpop' ),
			'arrow-right-alt' => __( 'Arrow Right Alt', 'prayerpop' ),
			'arrow-left-alt' => __( 'Arrow Left Alt', 'prayerpop' ),
			'arrow-up-alt2' => __( 'Arrow Up Alt 2', 'prayerpop' ),
			'arrow-down-alt2' => __( 'Arrow Down Alt 2', 'prayerpop' ),
			'arrow-right-alt2' => __( 'Arrow Right Alt 2', 'prayerpop' ),
			'arrow-left-alt2' => __( 'Arrow Left Alt 2', 'prayerpop' ),
			'sort' => __( 'Sort', 'prayerpop' ),
			'leftright' => __( 'Left Right', 'prayerpop' ),
			'randomize' => __( 'Randomize', 'prayerpop' ),
			'list-view' => __( 'List View', 'prayerpop' ),
			'excerpt-view' => __( 'Excerpt View', 'prayerpop' ),
			'grid-view' => __( 'Grid View', 'prayerpop' ),
			'move' => __( 'Move', 'prayerpop' ),
			
			// Misc
			'hammer' => __( 'Hammer', 'prayerpop' ),
			'art' => __( 'Art', 'prayerpop' ),
			'migrate' => __( 'Migrate', 'prayerpop' ),
			'performance' => __( 'Performance', 'prayerpop' ),
			'universal-access' => __( 'Universal Access', 'prayerpop' ),
			'universal-access-alt' => __( 'Universal Access Alt', 'prayerpop' ),
			'tickets' => __( 'Tickets', 'prayerpop' ),
			'nametag' => __( 'Name Tag', 'prayerpop' ),
			'clipboard' => __( 'Clipboard', 'prayerpop' ),
			'heart' => __( 'Heart', 'prayerpop' ),
			'megaphone' => __( 'Megaphone', 'prayerpop' ),
			'schedule' => __( 'Schedule', 'prayerpop' ),
			'wordpress' => __( 'WordPress', 'prayerpop' ),
			'wordpress-alt' => __( 'WordPress Alt', 'prayerpop' ),
			'pressthis' => __( 'Press This', 'prayerpop' ),
			'update' => __( 'Update', 'prayerpop' ),
			'screenoptions' => __( 'Screen Options', 'prayerpop' ),
			'cart' => __( 'Cart', 'prayerpop' ),
			'feedback' => __( 'Feedback', 'prayerpop' ),
			'cloud' => __( 'Cloud', 'prayerpop' ),
			'translation' => __( 'Translation', 'prayerpop' ),
			'tag' => __( 'Tag', 'prayerpop' ),
			'category' => __( 'Category', 'prayerpop' ),
			'archive' => __( 'Archive', 'prayerpop' ),
			'tagcloud' => __( 'Tag Cloud', 'prayerpop' ),
			'text' => __( 'Text', 'prayerpop' ),
			
			// Communication
			'email' => __( 'Email', 'prayerpop' ),
			'email-alt' => __( 'Email Alt', 'prayerpop' ),
			'email-alt2' => __( 'Email Alt 2', 'prayerpop' ),
			'networking' => __( 'Networking', 'prayerpop' ),
			'phone' => __( 'Phone', 'prayerpop' ),
			'smartphone' => __( 'Smartphone', 'prayerpop' ),
			'tablet' => __( 'Tablet', 'prayerpop' ),
			'desktop' => __( 'Desktop', 'prayerpop' ),
			'laptop' => __( 'Laptop', 'prayerpop' ),
			'buddicons-activity' => __( 'Activity', 'prayerpop' ),
			'buddicons-bbpress-logo' => __( 'bbPress Logo', 'prayerpop' ),
			'buddicons-buddypress-logo' => __( 'BuddyPress Logo', 'prayerpop' ),
			'buddicons-community' => __( 'Community', 'prayerpop' ),
			'buddicons-forums' => __( 'Forums', 'prayerpop' ),
			'buddicons-friends' => __( 'Friends', 'prayerpop' ),
			'buddicons-groups' => __( 'Groups', 'prayerpop' ),
			'buddicons-pm' => __( 'Private Message', 'prayerpop' ),
			'buddicons-replies' => __( 'Replies', 'prayerpop' ),
			'buddicons-topics' => __( 'Topics', 'prayerpop' ),
			'buddicons-tracking' => __( 'Tracking', 'prayerpop' ),
			
			// Post Status
			'post-status' => __( 'Post Status', 'prayerpop' ),
			'post-trash' => __( 'Post Trash', 'prayerpop' ),
			
			// Special
			'lock' => __( 'Lock', 'prayerpop' ),
			'unlock' => __( 'Unlock', 'prayerpop' ),
			'calendar' => __( 'Calendar', 'prayerpop' ),
			'calendar-alt' => __( 'Calendar Alt', 'prayerpop' ),
			'visibility' => __( 'Visibility', 'prayerpop' ),
			'hidden' => __( 'Hidden', 'prayerpop' ),
			'post-status' => __( 'Post Status', 'prayerpop' ),
			'edit' => __( 'Edit', 'prayerpop' ),
			'trash' => __( 'Trash', 'prayerpop' ),
			'sticky' => __( 'Sticky', 'prayerpop' ),
			'external' => __( 'External', 'prayerpop' ),
			'admin-links' => __( 'Links', 'prayerpop' ),
			'admin-page' => __( 'Page', 'prayerpop' ),
			'admin-post' => __( 'Post', 'prayerpop' ),
			'format-standard' => __( 'Standard', 'prayerpop' ),
			'format-image' => __( 'Image', 'prayerpop' ),
			'format-gallery' => __( 'Gallery', 'prayerpop' ),
			'format-audio' => __( 'Audio', 'prayerpop' ),
			'format-video' => __( 'Video', 'prayerpop' ),
			'format-chat' => __( 'Chat', 'prayerpop' ),
			'format-status' => __( 'Status', 'prayerpop' ),
			'format-aside' => __( 'Aside', 'prayerpop' ),
			'format-quote' => __( 'Quote', 'prayerpop' ),
			'format-links' => __( 'Links', 'prayerpop' ),
			'undo' => __( 'Undo', 'prayerpop' ),
			'redo' => __( 'Redo', 'prayerpop' ),
			'editor-ul' => __( 'Unordered List', 'prayerpop' ),
			'editor-ol' => __( 'Ordered List', 'prayerpop' ),
			'editor-quote' => __( 'Quote', 'prayerpop' ),
			'editor-alignleft' => __( 'Align Left', 'prayerpop' ),
			'editor-aligncenter' => __( 'Align Center', 'prayerpop' ),
			'editor-alignright' => __( 'Align Right', 'prayerpop' ),
			'editor-insertmore' => __( 'Insert More', 'prayerpop' ),
			'editor-spellcheck' => __( 'Spell Check', 'prayerpop' ),
			'editor-expand' => __( 'Expand', 'prayerpop' ),
			'editor-contract' => __( 'Contract', 'prayerpop' ),
			'editor-kitchensink' => __( 'Kitchen Sink', 'prayerpop' ),
			'editor-underline' => __( 'Underline', 'prayerpop' ),
			'editor-justify' => __( 'Justify', 'prayerpop' ),
			'editor-textcolor' => __( 'Text Color', 'prayerpop' ),
			'editor-paste-word' => __( 'Paste Word', 'prayerpop' ),
			'editor-paste-text' => __( 'Paste Text', 'prayerpop' ),
			'editor-removeformatting' => __( 'Remove Formatting', 'prayerpop' ),
			'editor-video' => __( 'Video', 'prayerpop' ),
			'editor-customchar' => __( 'Custom Character', 'prayerpop' ),
			'editor-outdent' => __( 'Outdent', 'prayerpop' ),
			'editor-indent' => __( 'Indent', 'prayerpop' ),
			'editor-help' => __( 'Help', 'prayerpop' ),
			'editor-strikethrough' => __( 'Strikethrough', 'prayerpop' ),
			'editor-unlink' => __( 'Unlink', 'prayerpop' ),
			'editor-rtl' => __( 'Right to Left', 'prayerpop' ),
			'editor-ltr' => __( 'Left to Right', 'prayerpop' ),
			'editor-break' => __( 'Break', 'prayerpop' ),
			'editor-code' => __( 'Code', 'prayerpop' ),
			'editor-paragraph' => __( 'Paragraph', 'prayerpop' ),
			'editor-table' => __( 'Table', 'prayerpop' ),
			'align-left' => __( 'Align Left', 'prayerpop' ),
			'align-right' => __( 'Align Right', 'prayerpop' ),
			'align-center' => __( 'Align Center', 'prayerpop' ),
			'align-none' => __( 'Align None', 'prayerpop' ),
			'lock' => __( 'Lock', 'prayerpop' ),
			'unlock' => __( 'Unlock', 'prayerpop' ),
			'calendar' => __( 'Calendar', 'prayerpop' ),
			'calendar-alt' => __( 'Calendar Alt', 'prayerpop' ),
			'visibility' => __( 'Visibility', 'prayerpop' ),
			'hidden' => __( 'Hidden', 'prayerpop' ),
			'post-status' => __( 'Post Status', 'prayerpop' ),
			'edit' => __( 'Edit', 'prayerpop' ),
			'trash' => __( 'Trash', 'prayerpop' ),
			'sticky' => __( 'Sticky', 'prayerpop' ),
			'external' => __( 'External', 'prayerpop' ),
			
			// Additional Common Icons
			'plus' => __( 'Plus', 'prayerpop' ),
			'plus-alt' => __( 'Plus Alt', 'prayerpop' ),
			'plus-alt2' => __( 'Plus Alt 2', 'prayerpop' ),
			'minus' => __( 'Minus', 'prayerpop' ),
			'dismiss' => __( 'Dismiss', 'prayerpop' ),
			'marker' => __( 'Marker', 'prayerpop' ),
			'star-filled' => __( 'Star Filled', 'prayerpop' ),
			'star-half' => __( 'Star Half', 'prayerpop' ),
			'star-empty' => __( 'Star Empty', 'prayerpop' ),
			'flag' => __( 'Flag', 'prayerpop' ),
			'warning' => __( 'Warning', 'prayerpop' ),
			'location' => __( 'Location', 'prayerpop' ),
			'location-alt' => __( 'Location Alt', 'prayerpop' ),
			'vault' => __( 'Vault', 'prayerpop' ),
			'shield' => __( 'Shield', 'prayerpop' ),
			'shield-alt' => __( 'Shield Alt', 'prayerpop' ),
			'sos' => __( 'SOS', 'prayerpop' ),
			'search' => __( 'Search', 'prayerpop' ),
			'slides' => __( 'Slides', 'prayerpop' ),
			'analytics' => __( 'Analytics', 'prayerpop' ),
			'chart-pie' => __( 'Chart Pie', 'prayerpop' ),
			'chart-bar' => __( 'Chart Bar', 'prayerpop' ),
			'chart-line' => __( 'Chart Line', 'prayerpop' ),
			'chart-area' => __( 'Chart Area', 'prayerpop' ),
			'groups' => __( 'Groups', 'prayerpop' ),
			'businessman' => __( 'Businessman', 'prayerpop' ),
			'businesswoman' => __( 'Business Woman', 'prayerpop' ),
			'businessperson' => __( 'Business Person', 'prayerpop' ),
			'id' => __( 'ID', 'prayerpop' ),
			'id-alt' => __( 'ID Alt', 'prayerpop' ),
			'products' => __( 'Products', 'prayerpop' ),
			'awards' => __( 'Awards', 'prayerpop' ),
			'forms' => __( 'Forms', 'prayerpop' ),
			'portfolio' => __( 'Portfolio', 'prayerpop' ),
			'book' => __( 'Book', 'prayerpop' ),
			'book-alt' => __( 'Book Alt', 'prayerpop' ),
			'download' => __( 'Download', 'prayerpop' ),
			'upload' => __( 'Upload', 'prayerpop' ),
			'backup' => __( 'Backup', 'prayerpop' ),
			'clock' => __( 'Clock', 'prayerpop' ),
			'lightbulb' => __( 'Light Bulb', 'prayerpop' ),
			'microphone' => __( 'Microphone', 'prayerpop' ),
			'dashboard' => __( 'Dashboard', 'prayerpop' ),
			'admin-generic' => __( 'Generic', 'prayerpop' ),
			'admin-home' => __( 'Home', 'prayerpop' ),
			'admin-collapse' => __( 'Collapse', 'prayerpop' ),
			'filter' => __( 'Filter', 'prayerpop' ),
			'admin-media' => __( 'Media', 'prayerpop' ),
			'admin-page' => __( 'Page', 'prayerpop' ),
			'admin-post' => __( 'Post', 'prayerpop' ),
			'admin-appearance' => __( 'Appearance', 'prayerpop' ),
			'admin-plugins' => __( 'Plugins', 'prayerpop' ),
			'plugins-checked' => __( 'Plugins Checked', 'prayerpop' ),
			'admin-users' => __( 'Users', 'prayerpop' ),
			'admin-tools' => __( 'Tools', 'prayerpop' ),
			'admin-settings' => __( 'Settings', 'prayerpop' ),
			'admin-network' => __( 'Network', 'prayerpop' ),
			'admin-site' => __( 'Site', 'prayerpop' ),
			'admin-customizer' => __( 'Customizer', 'prayerpop' ),
			'admin-multisite' => __( 'Multisite', 'prayerpop' ),
			'admin-links' => __( 'Links', 'prayerpop' ),
			'format-links' => __( 'Links Format', 'prayerpop' ),
			'admin-comments' => __( 'Comments', 'prayerpop' ),
			'admin-appearance' => __( 'Appearance', 'prayerpop' ),
			'format-standard' => __( 'Standard Format', 'prayerpop' ),
			'format-aside' => __( 'Aside Format', 'prayerpop' ),
			'format-quote' => __( 'Quote Format', 'prayerpop' ),
			'format-gallery' => __( 'Gallery Format', 'prayerpop' ),
			'format-image' => __( 'Image Format', 'prayerpop' ),
			'format-video' => __( 'Video Format', 'prayerpop' ),
			'format-status' => __( 'Status Format', 'prayerpop' ),
			'format-audio' => __( 'Audio Format', 'prayerpop' ),
			'format-chat' => __( 'Chat Format', 'prayerpop' ),
			'welcome-write-blog' => __( 'Write Blog', 'prayerpop' ),
			'welcome-add-page' => __( 'Add Page', 'prayerpop' ),
			'welcome-view-site' => __( 'View Site', 'prayerpop' ),
			'welcome-widgets-menus' => __( 'Widgets Menus', 'prayerpop' ),
			'welcome-comments' => __( 'Comments', 'prayerpop' ),
			'welcome-learn-more' => __( 'Learn More', 'prayerpop' ),
			'image-crop' => __( 'Image Crop', 'prayerpop' ),
			'image-rotate' => __( 'Image Rotate', 'prayerpop' ),
			'image-rotate-left' => __( 'Image Rotate Left', 'prayerpop' ),
			'image-rotate-right' => __( 'Image Rotate Right', 'prayerpop' ),
			'image-flip-vertical' => __( 'Image Flip Vertical', 'prayerpop' ),
			'image-flip-horizontal' => __( 'Image Flip Horizontal', 'prayerpop' ),
			'image-filter' => __( 'Image Filter', 'prayerpop' ),
			'undo' => __( 'Undo', 'prayerpop' ),
			'redo' => __( 'Redo', 'prayerpop' ),
			'editor-bold' => __( 'Bold', 'prayerpop' ),
			'editor-italic' => __( 'Italic', 'prayerpop' ),
			'editor-ul' => __( 'Unordered List', 'prayerpop' ),
			'editor-ol' => __( 'Ordered List', 'prayerpop' ),
			'editor-ol-rtl' => __( 'Ordered List RTL', 'prayerpop' ),
			'editor-quote' => __( 'Quote', 'prayerpop' ),
			'editor-alignleft' => __( 'Align Left', 'prayerpop' ),
			'editor-aligncenter' => __( 'Align Center', 'prayerpop' ),
			'editor-alignright' => __( 'Align Right', 'prayerpop' ),
			'editor-insertmore' => __( 'Insert More', 'prayerpop' ),
			'editor-spellcheck' => __( 'Spell Check', 'prayerpop' ),
			'editor-expand' => __( 'Expand', 'prayerpop' ),
			'editor-contract' => __( 'Contract', 'prayerpop' ),
			'editor-kitchensink' => __( 'Kitchen Sink', 'prayerpop' ),
			'editor-underline' => __( 'Underline', 'prayerpop' ),
			'editor-justify' => __( 'Justify', 'prayerpop' ),
			'editor-textcolor' => __( 'Text Color', 'prayerpop' ),
			'editor-paste-word' => __( 'Paste Word', 'prayerpop' ),
			'editor-paste-text' => __( 'Paste Text', 'prayerpop' ),
			'editor-removeformatting' => __( 'Remove Formatting', 'prayerpop' ),
			'editor-video' => __( 'Video', 'prayerpop' ),
			'editor-customchar' => __( 'Custom Character', 'prayerpop' ),
			'editor-outdent' => __( 'Outdent', 'prayerpop' ),
			'editor-indent' => __( 'Indent', 'prayerpop' ),
			'editor-help' => __( 'Help', 'prayerpop' ),
			'editor-strikethrough' => __( 'Strikethrough', 'prayerpop' ),
			'editor-unlink' => __( 'Unlink', 'prayerpop' ),
			'editor-rtl' => __( 'Right to Left', 'prayerpop' ),
			'editor-ltr' => __( 'Left to Right', 'prayerpop' ),
			'editor-break' => __( 'Break', 'prayerpop' ),
			'editor-code' => __( 'Code', 'prayerpop' ),
			'editor-paragraph' => __( 'Paragraph', 'prayerpop' ),
			'editor-table' => __( 'Table', 'prayerpop' ),
			'align-left' => __( 'Align Left', 'prayerpop' ),
			'align-right' => __( 'Align Right', 'prayerpop' ),
			'align-center' => __( 'Align Center', 'prayerpop' ),
			'align-none' => __( 'Align None', 'prayerpop' ),
			'align-full-width' => __( 'Align Full Width', 'prayerpop' ),
			'align-pull-left' => __( 'Align Pull Left', 'prayerpop' ),
			'align-pull-right' => __( 'Align Pull Right', 'prayerpop' ),
			'align-wide' => __( 'Align Wide', 'prayerpop' ),
			'block-default' => __( 'Block Default', 'prayerpop' ),
			'button' => __( 'Button', 'prayerpop' ),
			'cloud-saved' => __( 'Cloud Saved', 'prayerpop' ),
			'cloud-upload' => __( 'Cloud Upload', 'prayerpop' ),
			'columns' => __( 'Columns', 'prayerpop' ),
			'cover-image' => __( 'Cover Image', 'prayerpop' ),
			'embed-audio' => __( 'Embed Audio', 'prayerpop' ),
			'embed-generic' => __( 'Embed Generic', 'prayerpop' ),
			'embed-photo' => __( 'Embed Photo', 'prayerpop' ),
			'embed-post' => __( 'Embed Post', 'prayerpop' ),
			'embed-video' => __( 'Embed Video', 'prayerpop' ),
			'exit' => __( 'Exit', 'prayerpop' ),
			'html' => __( 'HTML', 'prayerpop' ),
			'info-outline' => __( 'Info Outline', 'prayerpop' ),
			'insert-after' => __( 'Insert After', 'prayerpop' ),
			'insert-before' => __( 'Insert Before', 'prayerpop' ),
			'insert' => __( 'Insert', 'prayerpop' ),
			'remove' => __( 'Remove', 'prayerpop' ),
			'table-col-after' => __( 'Table Column After', 'prayerpop' ),
			'table-col-before' => __( 'Table Column Before', 'prayerpop' ),
			'table-col-delete' => __( 'Table Column Delete', 'prayerpop' ),
			'table-row-after' => __( 'Table Row After', 'prayerpop' ),
			'table-row-before' => __( 'Table Row Before', 'prayerpop' ),
			'table-row-delete' => __( 'Table Row Delete', 'prayerpop' ),
			'leftright' => __( 'Left Right', 'prayerpop' ),
			'sort' => __( 'Sort', 'prayerpop' ),
			'randomize' => __( 'Randomize', 'prayerpop' ),
			'list-view' => __( 'List View', 'prayerpop' ),
			'excerpt-view' => __( 'Excerpt View', 'prayerpop' ),
			'grid-view' => __( 'Grid View', 'prayerpop' ),
			'move' => __( 'Move', 'prayerpop' ),
			'hammer' => __( 'Hammer', 'prayerpop' ),
			'art' => __( 'Art', 'prayerpop' ),
			'migrate' => __( 'Migrate', 'prayerpop' ),
			'performance' => __( 'Performance', 'prayerpop' ),
			'universal-access' => __( 'Universal Access', 'prayerpop' ),
			'universal-access-alt' => __( 'Universal Access Alt', 'prayerpop' ),
			'tickets' => __( 'Tickets', 'prayerpop' ),
			'nametag' => __( 'Name Tag', 'prayerpop' ),
			'clipboard' => __( 'Clipboard', 'prayerpop' ),
			'heart' => __( 'Heart', 'prayerpop' ),
			'megaphone' => __( 'Megaphone', 'prayerpop' ),
			'schedule' => __( 'Schedule', 'prayerpop' ),
			
			// Media
			'media-archive' => __( 'Media Archive', 'prayerpop' ),
			'media-audio' => __( 'Media Audio', 'prayerpop' ),
			'media-code' => __( 'Media Code', 'prayerpop' ),
			'media-default' => __( 'Media Default', 'prayerpop' ),
			'media-document' => __( 'Media Document', 'prayerpop' ),
			'media-interactive' => __( 'Media Interactive', 'prayerpop' ),
			'media-spreadsheet' => __( 'Media Spreadsheet', 'prayerpop' ),
			'media-text' => __( 'Media Text', 'prayerpop' ),
			'media-video' => __( 'Media Video', 'prayerpop' ),
			'playlist-audio' => __( 'Playlist Audio', 'prayerpop' ),
			'playlist-video' => __( 'Playlist Video', 'prayerpop' ),
			'controls-play' => __( 'Play', 'prayerpop' ),
			'controls-pause' => __( 'Pause', 'prayerpop' ),
			'controls-forward' => __( 'Forward', 'prayerpop' ),
			'controls-skipforward' => __( 'Skip Forward', 'prayerpop' ),
			'controls-back' => __( 'Back', 'prayerpop' ),
			'controls-skipback' => __( 'Skip Back', 'prayerpop' ),
			'controls-repeat' => __( 'Repeat', 'prayerpop' ),
			'controls-volumeon' => __( 'Volume On', 'prayerpop' ),
			'controls-volumeoff' => __( 'Volume Off', 'prayerpop' ),
			
			// Special Religious/Church Context Icons
			'palmtree' => __( 'Palm Tree', 'prayerpop' ),
			'smiley' => __( 'Smiley', 'prayerpop' ),
			'thumbs-up' => __( 'Thumbs Up', 'prayerpop' ),
			'thumbs-down' => __( 'Thumbs Down', 'prayerpop' ),
			'yes' => __( 'Yes', 'prayerpop' ),
			'yes-alt' => __( 'Yes Alt', 'prayerpop' ),
			'no' => __( 'No', 'prayerpop' ),
			'no-alt' => __( 'No Alt', 'prayerpop' ),
			'cloud' => __( 'Cloud', 'prayerpop' ),
			'translation' => __( 'Translation', 'prayerpop' ),
			'tagcloud' => __( 'Tag Cloud', 'prayerpop' ),
			'text' => __( 'Text', 'prayerpop' ),
			'archive' => __( 'Archive', 'prayerpop' ),
			
			// Final Common Icons
			'rss' => __( 'RSS', 'prayerpop' ),
			'email' => __( 'Email', 'prayerpop' ),
			'email-alt' => __( 'Email Alt', 'prayerpop' ),
			'email-alt2' => __( 'Email Alt 2', 'prayerpop' ),
			'networking' => __( 'Networking', 'prayerpop' ),
			'amazon' => __( 'Amazon', 'prayerpop' ),
			'google' => __( 'Google', 'prayerpop' ),
			'phone' => __( 'Phone', 'prayerpop' ),
			'smartphone' => __( 'Smartphone', 'prayerpop' ),
			'tablet' => __( 'Tablet', 'prayerpop' ),
			'desktop' => __( 'Desktop', 'prayerpop' ),
			'laptop' => __( 'Laptop', 'prayerpop' ),
			'database' => __( 'Database', 'prayerpop' ),
			'database-add' => __( 'Database Add', 'prayerpop' ),
			'database-export' => __( 'Database Export', 'prayerpop' ),
			'database-import' => __( 'Database Import', 'prayerpop' ),
			'database-remove' => __( 'Database Remove', 'prayerpop' ),
			'database-view' => __( 'Database View', 'prayerpop' ),
		);
	}

	/**
	 * Bubble icon size callback
	 */
	public function bubble_icon_size_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$size = isset( $options['bubble_icon_size'] ) ? $options['bubble_icon_size'] : '170';
		?>
		<div class="prayer-pop-size-control prayer-pop-size-control--icon">
			<input type="range" 
				   name="prayer_pop_styles[bubble_icon_size]" 
				   id="bubble_icon_size_range"
				   min="25" 
				   max="250" 
				   step="5"
				   value="<?php echo esc_attr( $size ); ?>"
				   oninput="document.getElementById('bubble_icon_size_value_display').textContent=this.value + '%';"
				   onchange="document.getElementById('bubble_icon_size_value_display').textContent=this.value + '%';"
				   class="prayer-pop-range-slider">
			<div class="size-display">
				<output id="bubble_icon_size_value_display" for="bubble_icon_size_range" aria-live="polite"><?php echo esc_html( $size ); ?>%</output>
			</div>
			<p class="description"><?php esc_html_e('Scales the size of the icon (25% - 250% of default size)', 'prayerpop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Bubble size callback
	 */
	public function bubble_size_callback() {
		$options = get_option( 'prayer_pop_styles', array() );
		$size = isset( $options['bubble_size'] ) ? $options['bubble_size'] : '100';
		?>
		<div class="prayer-pop-size-control">
			<input type="range" 
				   name="prayer_pop_styles[bubble_size]" 
				   id="bubble_size_range"
				   min="50" 
				   max="150" 
				   step="5"
				   value="<?php echo esc_attr( $size ); ?>"
				   oninput="document.getElementById('bubble_size_value_display').textContent=this.value + '%';"
				   onchange="document.getElementById('bubble_size_value_display').textContent=this.value + '%';"
				   class="prayer-pop-range-slider">
			<div class="size-display">
				<output id="bubble_size_value_display" for="bubble_size_range" aria-live="polite"><?php echo esc_html( $size ); ?>%</output>
			</div>
			<p class="description"><?php esc_html_e('Scales the overall size of the bubble (50% - 150% of default size)', 'prayerpop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Sanitize style settings.
	 */
	public function sanitize_styles( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs during Settings API save request with options.php nonce.
		$reset_action = isset( $_POST['prayer_pop_reset_action'] )
			? sanitize_key( wp_unslash( $_POST['prayer_pop_reset_action'] ) )
			: '';

		$sanitized = array();
		foreach ( $input as $key => $value ) {
			if ( strpos( $key, 'color' ) !== false ) {
				$sanitized[ $key ] = sanitize_hex_color( $value );
			} elseif ( $key === 'bubble_animation' ) {
				$allowed_animations = array( 'none', 'fade-in', 'slide-up', 'bounce-in' );
				$sanitized[ $key ]  = in_array( $value, $allowed_animations, true ) ? $value : 'fade-in';
				} elseif ( $key === 'bubble_layout' ) {
					$allowed_layouts = array( 'icon', 'text', 'icon_text', 'text_icon' );
					$sanitized[ $key ] = in_array( $value, $allowed_layouts, true ) ? $value : 'icon_text';
				} elseif ( $key === 'bubble_position' ) {
					$allowed_positions = array( 'right', 'left' );
					$sanitized[ $key ] = in_array( $value, $allowed_positions, true ) ? $value : 'right';
				} elseif ( $key === 'bubble_design_mode' ) {
				$allowed_modes = array( 'adaptive', 'fixed_square', 'fixed_circle' );
				$sanitized[ $key ] = in_array( $value, $allowed_modes, true ) ? $value : 'adaptive';
			} elseif ( $key === 'bubble_icon_type' ) {
				$allowed_types = array( 'none', 'dashicon', 'tabler' );
				$sanitized[ $key ] = in_array( $value, $allowed_types, true ) ? $value : 'none';
			} elseif ( $key === 'bubble_dashicon' ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( $key === 'bubble_tabler_icon' ) {
				$sanitized[ $key ] = sanitize_key( $value );
			} elseif ( $key === 'bubble_tabler_svg' ) {
				// Stored value is generated server-side from selected icon only.
				continue;
			} elseif ( $key === 'bubble_icon_size' ) {
				$size = absint( $value );
				$sanitized[ $key ] = max( 25, min( 250, $size ) ); // Clamp between 25-250
			} elseif ( $key === 'bubble_size' ) {
				$size = absint( $value );
				$sanitized[ $key ] = max( 50, min( 150, $size ) ); // Clamp between 50-150
			} else {
				// For size values, ensure they have valid units
					if ( strpos( $key, 'size' ) !== false || 
						 strpos( $key, 'radius' ) !== false || 
						 strpos( $key, 'padding' ) !== false || 
						 strpos( $key, 'margin' ) !== false ||
						 strpos( $key, 'height' ) !== false ||
						 strpos( $key, 'width' ) !== false ||
						 strpos( $key, 'gap' ) !== false ) {
					// Check if value contains valid CSS unit
					if ( !preg_match('/^[0-9.]+(%|px|em|rem)$/', $value) ) {
						// If no valid unit found, append 'px' as default
						$value = preg_replace('/[^0-9.]/', '', $value) . 'px';
					}
				}
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		// Type-aware guard: keep icon-specific values consistent with selected icon type.
		$icon_type = isset( $sanitized['bubble_icon_type'] ) ? $sanitized['bubble_icon_type'] : 'none';
		if ( 'dashicon' !== $icon_type ) {
			unset( $sanitized['bubble_dashicon'] );
		}
		if ( 'tabler' !== $icon_type ) {
			unset( $sanitized['bubble_tabler_icon'], $sanitized['bubble_tabler_svg'] );
		} else {
			$tabler_icon = isset( $sanitized['bubble_tabler_icon'] ) ? $sanitized['bubble_tabler_icon'] : 'pray';
			if ( ! $this->is_valid_tabler_icon( $tabler_icon ) ) {
				$tabler_icon = $this->is_valid_tabler_icon( 'pray' ) ? 'pray' : '';
			}

			if ( '' !== $tabler_icon ) {
				$sanitized['bubble_tabler_icon'] = $tabler_icon;
				$sanitized['bubble_tabler_svg']  = $this->build_tabler_svg_markup( $tabler_icon );
			} else {
				unset( $sanitized['bubble_tabler_icon'], $sanitized['bubble_tabler_svg'] );
			}
		}

		// Remove deprecated FAQ style keys; FAQ now uses global style controls.
		unset(
			$sanitized['faq_title_color'],
			$sanitized['faq_bg_color'],
			$sanitized['faq_question_color'],
			$sanitized['faq_answer_color'],
			$sanitized['faq_icon_color'],
			$sanitized['faq_border_color'],
			$sanitized['faq_border_radius'],
			$sanitized['faq_item_gap'],
			$sanitized['faq_padding']
		);

		// Apply section-specific reset actions through Settings API save flow.
		if ( 'layout' === $reset_action ) {
			foreach ( $this->get_layout_defaults() as $field_key => $default_value ) {
				$sanitized[ $field_key ] = $default_value;
			}
		} elseif ( 'style_customization' === $reset_action ) {
			foreach ( $this->get_style_customization_defaults() as $field_key => $default_value ) {
				$sanitized[ $field_key ] = $default_value;
			}
		}

		return $sanitized;
	}
}
