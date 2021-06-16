<?php

namespace WPGraphQL\Admin\Settings;

use WPGraphQL\Utils\Utils;

/**
 * Class SettingsRegistry
 *
 * This settings class is based on the WordPress Settings API Class v1.3 from Tareq Hasan of WeDevs
 *
 * @see     https://github.com/tareq1988/wordpress-settings-api-class
 * @author  Tareq Hasan <tareq@weDevs.com>
 * @link    https://tareq.co Tareq Hasan
 *
 * @package WPGraphQL\Admin\Settings
 */
class SettingsRegistry {

	/**
	 * Settings sections array
	 *
	 * @var array
	 */
	protected $settings_sections = [];

	/**
	 * Settings fields array
	 *
	 * @var array
	 */
	protected $settings_fields = [];

	/**
	 * SettingsRegistry constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * @return array
	 */
	public function get_settings_sections() {
		return $this->settings_sections;
	}

	/**
	 * @return array
	 */
	public function get_settings_fields() {
		return $this->settings_fields;
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	function admin_enqueue_scripts() {
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_media();
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Set settings sections
	 *
	 * @param string $slug    Setting Section Slug
	 * @param array  $section setting section config
	 *
	 * @return SettingsRegistry
	 */
	function register_section( string $slug, array $section ) {
		$section['id']                    = $slug;
		$this->settings_sections[ $slug ] = $section;

		return $this;
	}

	/**
	 * Register fields to a section
	 *
	 * @param string $section The slug of the section to register a field to
	 * @param array  $fields  settings fields array
	 *
	 * @return SettingsRegistry
	 */
	function register_fields( string $section, array $fields ) {
		foreach ( $fields as $field ) {
			$this->register_field( $section, $field );
		}

		return $this;
	}

	/**
	 * Register a field to a section
	 *
	 * @param string $section The slug of the section to register a field to
	 * @param array  $field   The config for the field being registered
	 *
	 * @return SettingsRegistry
	 */
	function register_field( string $section, array $field ) {
		$defaults = [
			'name'  => '',
			'label' => '',
			'desc'  => '',
			'type'  => 'text',
		];

		$field_config = wp_parse_args( $field, $defaults );

		// Get the field name before the filter is passed.
		$field_name = $field_config['name'];

		// Unset it, as we don't want it to be filterable
		unset( $field_config['name'] );

		/**
		 * Filter the setting field config
		 *
		 * @param array  $field_config The field config for the setting
		 * @param string $field_name   The name of the field (unfilterable in the config)
		 * @param string $section      The slug of the section the field is registered to
		 */
		$field = apply_filters( 'graphql_setting_field_config', $field_config, $field_name, $section );

		// Add the field name back after the filter has been applied
		$field['name'] = $field_name;

		// Add the field to the section
		$this->settings_fields[ $section ][] = $field;

		return $this;
	}

	/**
	 * Initialize and registers the settings sections and fileds to WordPress
	 *
	 * Usually this should be called at `admin_init` hook.
	 *
	 * This function gets the initiated settings sections and fields. Then
	 * registers them to WordPress and ready for use.
	 *
	 * @return void
	 */
	function admin_init() {

		// Action that fires when settings are being initialized
		do_action( 'graphql_init_settings', $this );

		/**
		 * Filter the settings sections
		 *
		 * @param array $setting_sections The registered settings sections
		 */
		$setting_sections = apply_filters( 'graphql_settings_sections', $this->settings_sections );

		foreach ( $setting_sections as $id => $section ) {
			if ( false === get_option( $id ) ) {
				add_option( $id );
			}

			if ( isset( $section['desc'] ) && ! empty( $section['desc'] ) ) {
				$section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
				$callback        = function () use ( $section ) {
					echo wp_kses( str_replace( '"', '\"', $section['desc'] ), Utils::get_allowed_wp_kses_html() );
				};
			} elseif ( isset( $section['callback'] ) ) {
				$callback = $section['callback'];
			} else {
				$callback = null;
			}

			add_settings_section( $id, $section['title'], $callback, $id );
		}

		//register settings fields
		foreach ( $this->settings_fields as $section => $field ) {
			foreach ( $field as $option ) {

				$name     = $option['name'];
				$type     = isset( $option['type'] ) ? $option['type'] : 'text';
				$label    = isset( $option['label'] ) ? $option['label'] : '';
				$callback = isset( $option['callback'] ) ? $option['callback'] : [
					$this,
					'callback_' . $type,
				];

				$args = [
					'id'                => $name,
					'class'             => isset( $option['class'] ) ? $option['class'] : $name,
					'label_for'         => "{$section}[{$name}]",
					'desc'              => isset( $option['desc'] ) ? $option['desc'] : '',
					'name'              => $label,
					'section'           => $section,
					'size'              => isset( $option['size'] ) ? $option['size'] : null,
					'options'           => isset( $option['options'] ) ? $option['options'] : '',
					'std'               => isset( $option['default'] ) ? $option['default'] : '',
					'sanitize_callback' => isset( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : '',
					'type'              => $type,
					'placeholder'       => isset( $option['placeholder'] ) ? $option['placeholder'] : '',
					'min'               => isset( $option['min'] ) ? $option['min'] : '',
					'max'               => isset( $option['max'] ) ? $option['max'] : '',
					'step'              => isset( $option['step'] ) ? $option['step'] : '',
					'disabled'          => isset( $option['disabled'] ) ? (bool) $option['disabled'] : false,
					'value'             => isset( $option['value'] ) ? $option['value'] : null,
				];

				add_settings_field( "{$section}[{$name}]", $label, $callback, $section, $section, $args );
			}
		}

		// creates our settings in the options table
		foreach ( $this->settings_sections as $id => $section ) {
			register_setting( $id, $id, [ $this, 'sanitize_options' ] );
		}
	}

	/**
	 * Get field description for display
	 *
	 * @param array $args settings field args
	 *
	 * @return string
	 */
	public function get_field_description( array $args ): string {
		if ( ! empty( $args['desc'] ) ) {
			$desc = sprintf( '<p class="description">%s</p>', $args['desc'] );
		} else {
			$desc = '';
		}

		return $desc;
	}

	/**
	 * Displays a text field for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_text( array $args ) {
		$value       = isset( $args['value'] ) && ! empty( $args['value'] ) ? esc_attr( $args['value'] ) : esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size        = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$type        = isset( $args['type'] ) ? $args['type'] : 'text';
		$placeholder = empty( $args['placeholder'] ) ? '' : ' placeholder="' . $args['placeholder'] . '"';
		$disabled    = isset( $args['disabled'] ) && true === $args['disabled'] ? 'disabled' : null;
		$html        = sprintf( '<input type="%1$s" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s"%6$s %7$s>', $type, $size, $args['section'], $args['id'], $value, $placeholder, $disabled );
		$html       .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a url field for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_url( array $args ) {
		$this->callback_text( $args );
	}

	/**
	 * Displays a number field for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_number( array $args ) {
		$value       = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size        = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$type        = isset( $args['type'] ) ? $args['type'] : 'number';
		$placeholder = empty( $args['placeholder'] ) ? '' : ' placeholder="' . $args['placeholder'] . '"';
		$min         = ( '' === $args['min'] ) ? '' : ' min="' . $args['min'] . '"';
		$max         = ( '' === $args['max'] ) ? '' : ' max="' . $args['max'] . '"';
		$step        = ( '' === $args['step'] ) ? '' : ' step="' . $args['step'] . '"';

		$html  = sprintf( '<input type="%1$s" class="%2$s-number" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s"%6$s%7$s%8$s%9$s>', $type, $size, $args['section'], $args['id'], $value, $placeholder, $min, $max, $step );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a checkbox for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_checkbox( array $args ) {

		$value    = isset( $args['value'] ) && ! empty( $args['value'] ) ? esc_attr( $args['value'] ) : esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$disabled = isset( $args['disabled'] ) && true === $args['disabled'] ? 'disabled' : null;

		$html  = '<fieldset>';
		$html .= sprintf( '<label for="wpuf-%1$s[%2$s]">', $args['section'], $args['id'] );
		$html .= sprintf( '<input type="hidden" name="%1$s[%2$s]" value="off">', $args['section'], $args['id'] );
		$html .= sprintf( '<input type="checkbox" class="checkbox" id="wpuf-%1$s[%2$s]" name="%1$s[%2$s]" value="on" %3$s %4$s>', $args['section'], $args['id'], checked( $value, 'on', false ), $disabled );
		$html .= sprintf( '%1$s</label>', $args['desc'] );
		$html .= '</fieldset>';

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a multicheckbox for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_multicheck( array $args ) {

		$value = $this->get_option( $args['id'], $args['section'], $args['std'] );
		$html  = '<fieldset>';
		$html .= sprintf( '<input type="hidden" name="%1$s[%2$s]" value="">', $args['section'], $args['id'] );
		foreach ( $args['options'] as $key => $label ) {
			$checked = isset( $value[ $key ] ) ? $value[ $key ] : '0';
			$html   .= sprintf( '<label for="wpuf-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key );
			$html   .= sprintf( '<input type="checkbox" class="checkbox" id="wpuf-%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="%3$s" %4$s>', $args['section'], $args['id'], $key, checked( $checked, $key, false ) );
			$html   .= sprintf( '%1$s</label><br>', $label );
		}

		$html .= $this->get_field_description( $args );
		$html .= '</fieldset>';

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a radio button for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_radio( array $args ) {

		$value = $this->get_option( $args['id'], $args['section'], $args['std'] );
		$html  = '<fieldset>';

		foreach ( $args['options'] as $key => $label ) {
			$html .= sprintf( '<label for="wpuf-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key );
			$html .= sprintf( '<input type="radio" class="radio" id="wpuf-%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s" %4$s>', $args['section'], $args['id'], $key, checked( $value, $key, false ) );
			$html .= sprintf( '%1$s</label><br>', $label );
		}

		$html .= $this->get_field_description( $args );
		$html .= '</fieldset>';

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a selectbox for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_select( array $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$html  = sprintf( '<select class="%1$s" name="%2$s[%3$s]" id="%2$s[%3$s]">', $size, $args['section'], $args['id'] );

		foreach ( $args['options'] as $key => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected( $value, $key, false ), $label );
		}

		$html .= sprintf( '</select>' );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a textarea for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_textarea( array $args ) {

		$value       = esc_textarea( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size        = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$placeholder = empty( $args['placeholder'] ) ? '' : ' placeholder="' . $args['placeholder'] . '"';

		$html  = sprintf( '<textarea rows="5" cols="55" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]"%4$s>%5$s</textarea>', $size, $args['section'], $args['id'], $placeholder, $value );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays the html for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_html( array $args ) {
		echo wp_kses( $this->get_field_description( $args ), Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a rich text textarea for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_wysiwyg( array $args ) {

		$value = $this->get_option( $args['id'], $args['section'], $args['std'] );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : '500px';

		echo '<div style="max-width: ' . esc_attr( $size ) . ';">';

		$editor_settings = [
			'teeny'         => true,
			'textarea_name' => $args['section'] . '[' . $args['id'] . ']',
			'textarea_rows' => 10,
		];

		if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
			$editor_settings = array_merge( $editor_settings, $args['options'] );
		}

		wp_editor( $value, $args['section'] . '-' . $args['id'], $editor_settings );

		echo '</div>';

		echo wp_kses( $this->get_field_description( $args ), Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a file upload field for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_file( array $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$id    = $args['section'] . '[' . $args['id'] . ']';
		$label = isset( $args['options']['button_label'] ) ? $args['options']['button_label'] : __( 'Choose File' );

		$html  = sprintf( '<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s">', $size, $args['section'], $args['id'], $value );
		$html .= '<input type="button" class="button wpsa-browse" value="' . $label . '">';
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a password field for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_password( array $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$html  = sprintf( '<input type="password" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s">', $size, $args['section'], $args['id'], $value );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Displays a color picker field for a settings field
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_color( $args ) {

		$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$html  = sprintf( '<input type="text" class="%1$s-text wp-color-picker-field" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s" data-default-color="%5$s">', $size, $args['section'], $args['id'], $value, $args['std'] );
		$html .= $this->get_field_description( $args );

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}


	/**
	 * Displays a select box for creating the pages select box
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_pages( array $args ) {

		$dropdown_args = array_merge( [
			'selected' => esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) ),
			'name'     => $args['section'] . '[' . $args['id'] . ']',
			'id'       => $args['section'] . '[' . $args['id'] . ']',
			'echo'     => 0,
		], $args );

		$clean_args = [];
		foreach ( $dropdown_args as $key => $arg ) {
			$clean_args[ $key ] = wp_kses( $arg, Utils::get_allowed_wp_kses_html() );
		}
		echo wp_dropdown_pages( $clean_args );
	}

	/**
	 * Displays a select box for user roles
	 *
	 * @param array $args settings field args
	 *
	 * @return void
	 */
	function callback_user_role_select( array $args ) {
		$selected = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );

		if ( empty( $selected ) ) {
			$selected = isset( $args['defualt'] ) ? $args['defualt'] : null;
		}

		$name = $args['section'] . '[' . $args['id'] . ']';
		$id   = $args['section'] . '[' . $args['id'] . ']';

		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="any">Any</option>';
		wp_dropdown_roles( $selected );
		echo '</select>';
		echo wp_kses( $this->get_field_description( $args ), Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Sanitize callback for Settings API
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	function sanitize_options( array $options ) {

		if ( ! $options ) {
			return $options;
		}

		foreach ( $options as $option_slug => $option_value ) {
			$sanitize_callback = $this->get_sanitize_callback( $option_slug );

			// If callback is set, call it
			if ( $sanitize_callback ) {
				$options[ $option_slug ] = call_user_func( $sanitize_callback, $option_value );
				continue;
			}
		}

		return $options;
	}

	/**
	 * Get sanitization callback for given option slug
	 *
	 * @param string $slug option slug
	 *
	 * @return mixed string or bool false
	 */
	function get_sanitize_callback( $slug = '' ) {
		if ( empty( $slug ) ) {
			return false;
		}

		// Iterate over registered fields and see if we can find proper callback
		foreach ( $this->settings_fields as $section => $options ) {
			foreach ( $options as $option ) {
				if ( $slug !== $option['name'] ) {
					continue;
				}

				// Return the callback name
				return isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : false;
			}
		}

		return false;
	}

	/**
	 * Get the value of a settings field
	 *
	 * @param string $option  settings field name
	 * @param string $section the section name this field belongs to
	 * @param string $default default text if it's not found
	 *
	 * @return string
	 */
	function get_option( $option, $section, $default = '' ) {

		$options = get_option( $section );

		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return $default;
	}

	/**
	 * Show navigations as tab
	 *
	 * Shows all the settings section labels as tab
	 *
	 * @return void
	 */
	function show_navigation() {
		$html = '<h2 class="nav-tab-wrapper">';

		$count = count( $this->settings_sections );

		// don't show the navigation if only one section exists
		if ( 1 === $count ) {
			return;
		}

		foreach ( $this->settings_sections as $tab ) {
			$html .= sprintf( '<a href="#%1$s" class="nav-tab" id="%1$s-tab">%2$s</a>', $tab['id'], $tab['title'] );
		}

		$html .= '</h2>';

		echo wp_kses( $html, Utils::get_allowed_wp_kses_html() );
	}

	/**
	 * Show the section settings forms
	 *
	 * This function displays every sections in a different form
	 *
	 * @return void
	 */
	function show_forms() {
		?>
		<div class="metabox-holder">
			<?php foreach ( $this->settings_sections as $id => $form ) { ?>
				<div id="<?php echo esc_attr( $id ); ?>" class="group" style="display: none;">
					<form method="post" action="options.php">
						<?php
						do_action( 'graphql_settings_form_top', $form );
						settings_fields( $id );
						do_settings_sections( $id );
						do_action( 'graphql_settings_form_bottom', $form );
						if ( isset( $this->settings_fields[ $id ] ) ) :
							?>
							<div style="padding-left: 10px">
								<?php submit_button(); ?>
							</div>
						<?php endif; ?>
					</form>
				</div>
			<?php } ?>
		</div>
		<?php
		$this->script();
	}

	/**
	 * Tabbable JavaScript codes & Initiate Color Picker
	 *
	 * This code uses localstorage for displaying active tabs
	 *
	 * @return void
	 */
	function script() {
		?>
		<script>
			jQuery(document).ready(function ($) {
				//Initiate Color Picker
				$('.wp-color-picker-field').wpColorPicker();

				// Switches option sections
				$('.group').hide();
				var activetab = '';
				if (typeof (localStorage) != 'undefined') {
					activetab = localStorage.getItem("activetab");
				}

				//if url has section id as hash then set it as active or override the current local storage value
				if (window.location.hash) {
					activetab = window.location.hash;
					if (typeof (localStorage) != 'undefined') {
						localStorage.setItem("activetab", activetab);
					}
				}

				if (activetab != '' && $(activetab).length) {
					$(activetab).fadeIn();
				} else {
					$('.group:first').fadeIn();
				}
				$('.group .collapsed').each(function () {
					$(this).find('input:checked').parent().parent().parent().nextAll().each(
						function () {
							if ($(this).hasClass('last')) {
								$(this).removeClass('hidden');
								return false;
							}
							$(this).filter('.hidden').removeClass('hidden');
						});
				});

				if (activetab != '' && $(activetab + '-tab').length) {
					$(activetab + '-tab').addClass('nav-tab-active');
				} else {
					$('.nav-tab-wrapper a:first').addClass('nav-tab-active');
				}
				$('.nav-tab-wrapper a').click(function (evt) {
					$('.nav-tab-wrapper a').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active').blur();
					var clicked_group = $(this).attr('href');
					if (typeof (localStorage) != 'undefined') {
						localStorage.setItem("activetab", $(this).attr('href'));
					}
					$('.group').hide();
					$(clicked_group).fadeIn();
					evt.preventDefault();
				});

				$('.wpsa-browse').on('click', function (event) {
					event.preventDefault();

					var self = $(this);

					// Create the media frame.
					var file_frame = wp.media.frames.file_frame = wp.media({
						title: self.data('uploader_title'),
						button: {
							text: self.data('uploader_button_text'),
						},
						multiple: false
					});

					file_frame.on('select', function () {
						attachment = file_frame.state().get('selection').first().toJSON();
						self.prev('.wpsa-url').val(attachment.url).change();
					});

					// Finally, open the modal
					file_frame.open();
				});
			});
		</script>
		<?php
		$this->_style_fix();
	}

	/**
	 * Add styles to adjust some settings
	 *
	 * @return void
	 */
	function _style_fix() {
		global $wp_version;

		if ( version_compare( $wp_version, '3.8', '<=' ) ) :
			?>
			<style type="text/css">
				/** WordPress 3.8 Fix **/
				.form-table th {
					padding: 20px 10px;
				}

				#wpbody-content .metabox-holder {
					padding-top: 5px;
				}
			</style>
			<?php
		endif;
	}

}
