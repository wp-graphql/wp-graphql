<?php
/**
 * ACF extension for WP-GraphQL
 *
 * @package WPGraphQL\ACF
 */

namespace WPGraphQL\Acf\Admin;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\LocationRules\LocationRules;
use WPGraphQL\Acf\Registry;
use WPGraphQL\Acf\Utils;
use WP_Post;


/**
 * Class ACF_Settings
 *
 * @package WPGraphQL\ACF
 */
class Settings {

	/**
	 * @var bool
	 */
	protected $is_acf6_or_higher = false;

	/**
	 * @var \WPGraphQL\Acf\Registry
	 */
	protected $registry;

	/**
	 * Get the WPGraphQL for ACF Registry
	 */
	protected function get_registry(): Registry {
		if ( ! $this->registry instanceof Registry ) {
			$this->registry = new Registry();
		}

		return $this->registry;
	}

	/**
	 * Initialize ACF Settings for the plugin
	 */
	public function init(): void {
		$this->is_acf6_or_higher = defined( 'ACF_MAJOR_VERSION' ) && version_compare( ACF_MAJOR_VERSION, '6', '>=' );

		/**
		 * Add settings to individual fields to allow each field granular control
		 * over how it's shown in the GraphQL Schema
		 */
		add_filter(
			'acf/field_group/additional_field_settings_tabs',
			static function ( $tabs ) {
				$tabs['graphql'] = __( 'GraphQL', 'wpgraphql-acf' );
				return $tabs;
			}
		);

		// Set up the Field Settings for each field type.
		$this->setup_field_settings();

		/**
		 * Enqueue scripts to enhance the UI of the ACF Field Group Settings
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_graphql_acf_scripts' ], 10, 1 );

		/**
		 * Register meta boxes for the ACF Field Group Settings
		 */
		if ( ! defined( 'ACF_VERSION' ) || version_compare( ACF_VERSION, '6.1', '<' ) ) {
			add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		} else {
			add_action( 'acf/field_group/render_group_settings_tab/graphql', [ $this, 'display_graphql_field_group_fields' ] );
			add_filter(
				'acf/field_group/additional_group_settings_tabs',
				static function ( $tabs ) {
					$tabs['graphql'] = __( 'GraphQL', 'wpgraphql-acf' );

					return $tabs;
				}
			);
		}



		/**
		 * Register an AJAX action and callback for converting ACF Location rules to GraphQL Types
		 */
		add_action( 'wp_ajax_get_acf_field_group_graphql_types', [ $this, 'graphql_types_ajax_callback' ] );

		add_filter( 'manage_acf-field-group_posts_columns', [ $this, 'wpgraphql_admin_table_column_headers' ], 11, 1 );

		add_action( 'manage_acf-field-group_posts_custom_column', [ $this, 'wpgraphql_admin_table_columns_html' ], 11, 2 );
	}

	/**
	 * Set up the Field Settings for configuring how each field should map to GraphQL
	 */
	protected function setup_field_settings(): void {
		if ( ! function_exists( 'acf_get_field_types' ) ) {
			return;
		}

		// for ACF versions below 6.1, there are no field setting tabs, so we add the
		// graphql fields to each
		if ( ! defined( 'ACF_VERSION' ) || version_compare( ACF_VERSION, '6.1', '<' ) ) {
			add_action( 'acf/render_field_settings', [ $this, 'add_field_settings' ] );
		} else {

			// @phpstan-ignore-next-line
			$acf_field_types = acf_get_field_types();

			// We want to add settings to _all_ field types
			$acf_field_types = is_array( $acf_field_types ) && ! empty( $acf_field_types ) ? array_keys( $acf_field_types ) : [];

			if ( ! empty( $acf_field_types ) ) {
				array_map(
					function ( $field_type ) {
						add_action(
							'acf/field_group/render_field_settings_tab/graphql/type=' . $field_type,
							function ( $acf_field ) use ( $field_type ) {
								$this->add_field_settings( $acf_field, (string) $field_type );
							},
							10,
							1
						);
					},
					$acf_field_types
				);
			}
		}
	}

	/**
	 * Handle the AJAX callback for converting ACF Location settings to GraphQL Types
	 */
	public function graphql_types_ajax_callback(): void {
		if ( ! isset( $_REQUEST['data'] ) ) {
			echo esc_html( __( 'No location rules were found', 'wpgraphql-acf' ) );

			/** @noinspection ForgottenDebugOutputInspection */
			wp_die();
		}

		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['nonce'] ), 'wp_graphql_acf' ) ) {
			wp_send_json_error();
		}

		$form_data           = [];
		$sanitized_post_data = wp_strip_all_tags( $_REQUEST['data'] );

		parse_str( $sanitized_post_data, $form_data );

		if ( empty( $form_data ) ) {
			wp_send_json( __( 'No form data.', 'wpgraphql-acf' ) );
		}

		if ( empty( $form_data['acf_field_group']['location'] ) ) {
			wp_send_json( __( 'No field group locations found.', 'wpgraphql-acf' ) );
		}

		$field_group = $form_data['acf_field_group'];

		$rules = new LocationRules( [ $field_group ] );
		$rules->determine_location_rules();

		$group_title = $form_data['post_title'] ?? '';
		$group_name  = $field_group['graphql_field_name'] ?? $group_title;
		$group_name  = \WPGraphQL\Utils\Utils::format_field_name( $group_name, true );

		$all_rules = $rules->get_rules();

		if ( isset( $all_rules[ strtolower( $group_name ) ] ) ) {
			wp_send_json(
				[
					'graphql_types' => array_values( $all_rules[ strtolower( $group_name ) ] ),
				]
			);
		}
		wp_send_json( [ 'graphql_types' => null ] );
	}

	/**
	 * Register the GraphQL Settings metabox for the ACF Field Group post type
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'wpgraphql-acf-meta-box',
			__( 'GraphQL', 'wpgraphql-acf' ),
			[
				$this,
				'display_graphql_field_group_fields',
			],
			[ 'acf-field-group' ]
		);
	}


	/**
	 * Display the GraphQL Settings fields on the ACF Field Group add/edit admin page
	 *
	 * @param array<mixed>|\WP_Post $field_group The Field Group being edited
	 *
	 * @throws \GraphQL\Error\Error
	 * @throws \Exception
	 */
	public function display_graphql_field_group_fields( $field_group ): void {
		if ( $field_group instanceof WP_Post ) {
			$field_group = (array) $field_group;
		}

		// Render a field in the Field Group settings to allow for a Field Group to be shown in GraphQL.
		acf_render_field_wrap(
			[
				'label'        => __( 'Show in GraphQL', 'wpgraphql-acf' ),
				'instructions' => __( 'If the field group is active, and this is set to show, the fields in this group will be available in the WPGraphQL Schema based on the respective Location rules. NOTE: Changing a field "show_in_graphql" to "false" could create breaking changes for client applications already querying for this field group.', 'wpgraphql-acf' ),
				'type'         => 'true_false',
				'name'         => 'show_in_graphql',
				'prefix'       => 'acf_field_group',
				'value'        => isset( $field_group['show_in_graphql'] ) ? (bool) $field_group['show_in_graphql'] : 1,
				'ui'           => 1,
			],
			'div',
			'label',
			true
		);

		// Render a field in the Field Group settings to set the GraphQL field name for the field group.
		acf_render_field_wrap(
			[
				'label'        => __( 'GraphQL Type Name', 'wpgraphql-acf' ),
				'instructions' => __( 'The GraphQL Type name representing the field group in the GraphQL Schema. Must start with a letter. Can only contain Letters, Numbers and underscores. Best practice is to use "PascalCase" for GraphQL Types.', 'wpgraphql-acf' ),
				'type'         => 'text',
				'prefix'       => 'acf_field_group',
				'name'         => 'graphql_field_name',
				'required'     => isset( $field_group['show_in_graphql'] ) && (bool) $field_group['show_in_graphql'],
				'placeholder'  => __( 'FieldGroupTypeName', 'wpgraphql-acf' ),
				'value'        => ! empty( $field_group['graphql_field_name'] ) ? $field_group['graphql_field_name'] : '',
			],
			'div',
			'label',
			true
		);

		acf_render_field_wrap(
			[
				'label'        => __( 'Manually Set GraphQL Types for Field Group', 'wpgraphql-acf' ),
				'instructions' => __( 'By default, ACF Field groups are added to the GraphQL Schema based on the field group\'s location rules. Checking this box will let you manually control the GraphQL Types the field group should be shown on in the GraphQL Schema using the checkboxes below, and the Location Rules will no longer effect the GraphQL Types.', 'wpgraphql-acf' ),
				'type'         => 'true_false',
				'name'         => 'map_graphql_types_from_location_rules',
				'prefix'       => 'acf_field_group',
				'value'        => isset( $field_group['map_graphql_types_from_location_rules'] ) && $field_group['map_graphql_types_from_location_rules'],
				'ui'           => 1,
			],
			'div',
			'label',
			true
		);

		$choices = Utils::get_all_graphql_types();

		acf_render_field_wrap(
			[
				'label'        => __( 'GraphQL Types to Show the Field Group On', 'wpgraphql-acf' ),
				'instructions' => __( 'Select the Types in the WPGraphQL Schema to show the fields in this field group on', 'wpgraphql-acf' ),
				'type'         => 'checkbox',
				'prefix'       => 'acf_field_group',
				'name'         => 'graphql_types',
				'value'        => ! empty( $field_group['graphql_types'] ) ? $field_group['graphql_types'] : [],
				'toggle'       => true,
				'choices'      => $choices,
			],
			'div',
			'label',
			true
		);

		// Render a field in the Field Group settings to show interfaces for a Field Group to be shown in GraphQL.
		$interfaces            = $this->get_registry()->get_field_group_interfaces( $field_group );
		$field_group_type_name = $this->get_registry()->get_field_group_graphql_type_name( $field_group );

		acf_render_field_wrap(
			[
				'label'        => __( 'GraphQL Interfaces', 'wpgraphql-acf' ),
				// translators: %s is the GraphQL Type Name representing an ACF Field Group in the GraphQL Schema
				'instructions' => sprintf( __( "These are the GraphQL Interfaces implemented by the '%s' GraphQL Type", 'wpgraphql-acf' ), $field_group_type_name ),
				'type'         => 'message',
				'name'         => 'graphql_interfaces',
				'prefix'       => 'acf_field_group',
				'message'      => ! empty( $interfaces ) ? '<ul><li>' . implode( '</li><li>', $interfaces ) . '</li></ul>' : [],
				'readonly'     => true,
			],
			'div',
			'label',
			true
		);

		?>
		<div class="acf-hidden">
			<input
				type="hidden"
				name="acf_field_group[key]"
				value="<?php echo esc_attr( $field_group['key'] ); ?>"
			/>
		</div>
		<script type="text/javascript">
			if (typeof acf !== 'undefined') {
				acf.newPostbox({
					'id': 'wpgraphql-acf-meta-box',
					'label': <?php echo $this->is_acf6_or_higher ? 'top' : "'left'"; ?>
				});
			}
		</script>
		<?php
	}

	/**
	 * Add settings to each field to show in GraphQL
	 *
	 * @param array<mixed> $field The field to add the setting to.
	 * @param string|null  $field_type The Type of field being configured.
	 */
	public function add_field_settings( array $field, ?string $field_type = null ): void {

		// We define a non-empty string for field type for ACF versions before 6.1
		if ( ! defined( 'ACF_VERSION' ) || version_compare( ACF_VERSION, '6.1', '<' ) ) {
			$field_type = '<6.1';
		}

		if ( empty( $field_type ) ) {
			return;
		}

		$acf_field_type = Utils::get_graphql_field_type( $field_type );

		if ( ! $acf_field_type instanceof AcfGraphQLFieldType ) {
			$admin_field_settings = [
				'not_supported' => [
					'type'         => 'message',
					'label'        => __( 'Not supported in the GraphQL Schema', 'wpgraphql-acf' ),
					// translators: %s is the name of the ACF Field Type
					'instructions' => sprintf( __( 'The "%s" Field Type is not set up to map to the GraphQL Schema. If you want to query this field type in the Schema, visit our guide for <a href="" target="_blank" rel="nofollow">adding GraphQL support for additional ACF field types</a>.', 'wpgraphql-acf' ), $field_type ),
					'conditions'   => [],
				],
			];
		} else {
			$admin_field_settings = $acf_field_type->get_admin_field_settings( $field, $this );
		}

		if ( ! empty( $admin_field_settings ) && is_array( $admin_field_settings ) ) {
			foreach ( $admin_field_settings as $admin_field_setting_config ) {
				if ( empty( $admin_field_setting_config ) || ! is_array( $admin_field_setting_config ) ) {
					continue;
				}

				$default_config = [
					'conditions' => [
						'field'    => 'show_in_graphql',
						'operator' => '==',
						'value'    => '1',
					],
					'ui'         => true,
					// used in the acf_render_field_setting below. Can be overridden per-field
					'global'     => true,
				];

				// Merge the default field setting with the passed in field setting
				$setting_field_config = array_merge( $default_config, $admin_field_setting_config );

				acf_render_field_setting( $field, $setting_field_config, (bool) $setting_field_config['global'] );
			}
		}
	}

	/**
	 * Get the config for the non_null field
	 *
	 * @param array<mixed> $override Array of settings to override the default behavior
	 *
	 * @return array<mixed>
	 */
	public function get_graphql_resolve_type_field_config( array $override = [] ): array {
		return array_merge(
			[
				'label'         => __( 'GraphQL Resolve Type', 'wpgraphql-acf' ),
				'instructions'  => __( 'The GraphQL Type the field will show in the Schema as and resolve to.', 'wpgraphql-acf' ),
				'name'          => 'graphql_resolve_type',
				'key'           => 'graphql_resolve_type',
				'type'          => 'select',
				'multiple'      => false,
				'ui'            => false,
				'allow_null'    => false,
				'default_value' => 'list:string',
				'choices'       => [
					'string'      => 'String',
					'int'         => 'Int',
					'float'       => 'Float',
					'list:string' => '[String] (List of Strings)',
					'list:int'    => '[Int] (List of Integers)',
					'list:float'  => '[Float] (List of Floats)',
				],
			],
			$override
		);
	}

	/**
	 * This enqueues admin script.
	 *
	 * @param string $screen The screen that scripts are being enqueued to
	 */
	public function enqueue_graphql_acf_scripts( string $screen ): void {
		global $post;

		if ( ! ( 'post-new.php' === $screen || 'post.php' === $screen ) ) {
			return;
		}

		if ( ! isset( $post->post_type ) || 'acf-field-group' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script(
			'graphql-acf',
			plugins_url( '/assets/admin/js/main.js', __DIR__ ),
			[
				'jquery',
				'acf-input',
				'acf-field-group',
			],
			WPGRAPHQL_FOR_ACF_VERSION,
			true
		);

		wp_localize_script(
			'graphql-acf',
			'wp_graphql_acf',
			[
				'nonce' => wp_create_nonce( 'wp_graphql_acf' ),
			]
		);
	}

	/**
	 * Add header to the field group admin page columns showing types and interfaces
	 *
	 * @param array<mixed> $_columns The column headers to add the values to.
	 *
	 * @return array<mixed> The column headers with the added wp-graphql columns
	 */
	public function wpgraphql_admin_table_column_headers( array $_columns ): array {
		$columns  = [];
		$is_added = false;

		foreach ( $_columns as $name => $value ) {
			$columns[ $name ] = $value;
			// After the location column, add the wpgraphql specific columns
			if ( 'acf-location' === $name ) {
				$columns['acf-wpgraphql-type']       = __( 'GraphQL Type', 'wpgraphql-acf' );
				$columns['acf-wpgraphql-interfaces'] = __( 'GraphQL Interfaces', 'wpgraphql-acf' );
				$columns['acf-wpgraphql-locations']  = __( 'GraphQL Locations', 'wpgraphql-acf' );
				$is_added                            = true;
			}
		}
		// If not added after the specific column, add to the end of the list
		if ( ! $is_added ) {
			$columns['acf-wpgraphql-type']       = __( 'GraphQL Type', 'wpgraphql-acf' );
			$columns['acf-wpgraphql-interfaces'] = __( 'GraphQL Interfaces', 'wpgraphql-acf' );
			$columns['acf-wpgraphql-locations']  = __( 'GraphQL Locations', 'wpgraphql-acf' );
		}

		return $columns;
	}

	/**
	 * Add values to the field group admin page columns showing types and interfaces
	 *
	 * @param string $column_name The column being processed.
	 * @param int    $post_id     The field group id being processed
	 *
	 * @throws \GraphQL\Error\Error
	 */
	public function wpgraphql_admin_table_columns_html( string $column_name, int $post_id ): void {
		global $field_group;

		if ( empty( $post_id ) ) {
			echo null;
		}

		$field_group = acf_get_field_group( $post_id );

		if ( empty( $field_group ) ) {
			return;
		}

		switch ( $column_name ) {
			case 'acf-wpgraphql-type':
				$type_name = $this->get_registry()->get_field_group_graphql_type_name( $field_group );

				echo ! empty( $type_name ) ? '<span class="acf-wpgraphql-type">' . acf_esc_html( $type_name ) . '</span>' : '';
				break;
			case 'acf-wpgraphql-interfaces':
				$interfaces = $this->get_registry()->get_field_group_interfaces( $field_group );
				$html       = Utils::array_list_by_limit( $interfaces, 5 );

				echo '<span class="acf-wpgraphql-interfaces">' . acf_esc_html( $html ) . '</span>';
				break;
			case 'acf-wpgraphql-locations':
				$acf_field_groups = $this->get_registry()->get_acf_field_groups();
				$locations        = $this->get_registry()->get_graphql_locations_for_field_group( $field_group, $acf_field_groups );
				if ( $locations ) {
					$html = Utils::array_list_by_limit( $locations, 5 );

					echo '<span class="acf-wpgraphql-location-types">' . acf_esc_html( $html ) . '</span>';
				}
				break;
			default:
				echo null;
		}
	}
}
