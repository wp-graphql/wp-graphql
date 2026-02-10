<?php
namespace WPGraphQL\Acf;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Acf\Admin\Settings;
use WPGraphQL\AppContext;

/**
 * Configures how an ACF Field Type should interact with WPGraphQL
 *
 * - Controls the Admin UI Field Settings for the field
 * - Controls how the field shows in the GraphQL Schema
 * - Controls how the field resolves in GraphQL Requests
 */
class AcfGraphQLFieldType {

	/**
	 * @var string
	 */
	private $acf_field_type;

	/**
	 * @var array<mixed>|callable
	 */
	protected $config;

	/**
	 * @var array<mixed>
	 */
	protected $admin_fields = [];

	/**
	 * @var \WPGraphQL\Acf\AcfGraphQLFieldResolver
	 */
	protected $resolver;

	/**
	 * @var array<mixed>
	 */
	protected $excluded_admin_field_settings = [];

	/**
	 * Constructor.
	 *
	 * @param string                $acf_field_type The name of the ACF Field Type
	 * @param array<mixed>|callable $config The config for how tha ACF Field Type should map to the WPGraphQL Schema and display Admin settings for the field.
	 */
	public function __construct( string $acf_field_type, $config = [] ) {
		$this->set_acf_field_type( $acf_field_type );
		$this->set_config( $config );
		$this->set_excluded_admin_field_settings();
		$this->resolver = new AcfGraphQLFieldResolver( $this );
	}

	/**
	 * @param array<mixed>|callable $config The config for the ACF GraphQL Field Type
	 */
	public function set_config( $config = [] ): void {
		$_config = [];

		if ( is_callable( $config ) ) {
			$config = $config( $this->get_acf_field_type(), $this );
		}

		if ( is_array( $config ) ) {
			$_config = $config;
		}

		/**
		 * Filter the config before setting it on the class
		 *
		 * @param array $_config The Config passed to the AcfGraphQLFieldType
		 * @param \WPGraphQL\Acf\AcfGraphQLFieldType $acf_graphql_field_type The AcfGraphQLFieldType instance
		 */
		$this->config = apply_filters( 'wpgraphql/acf/field_type_config', $_config, $this );
	}

	/**
	 * Get the config for the Field Type
	 *
	 * @param string|null $setting_name The name of the setting to get the config for.
	 *
	 * @return mixed
	 */
	public function get_config( ?string $setting_name = null ) {
		if ( empty( $setting_name ) || ! is_array( $this->config ) ) {
			return $this->config;
		}

		return $this->config[ $setting_name ] ?? null;
	}

	/**
	 * Return Admin Field Settings for configuring GraphQL Behavior.
	 *
	 * @param array<mixed>                  $field The Instance of the ACF Field the settings are for
	 * @param \WPGraphQL\Acf\Admin\Settings $settings The Settings class
	 *
	 * @return mixed|void
	 */
	public function get_admin_field_settings( array $field, Settings $settings ) {
		$default_admin_settings = [];

		$default_admin_settings['show_in_graphql'] = [
			'label'         => __( 'Show in GraphQL', 'wpgraphql-acf' ),
			'instructions'  => __( 'Whether the field should be queryable via GraphQL. NOTE: Changing this to false for existing field can cause a breaking change to the GraphQL Schema. Proceed with caution.', 'wpgraphql-acf' ),
			'name'          => 'show_in_graphql',
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 1,
			'value'         => ! isset( $field['show_in_graphql'] ) || (bool) $field['show_in_graphql'],
			'conditions'    => [],
		];

		$default_admin_settings['graphql_description'] = [
			'label'         => __( 'GraphQL Description', 'wpgraphql-acf' ),
			'instructions'  => __( 'The description of the field, shown in the GraphQL Schema. Should not include any special characters.', 'wpgraphql-acf' ),
			'name'          => 'graphql_description',
			'type'          => 'text',
			'ui'            => true,
			'default_value' => null,
			'placeholder'   => __( 'Explanation of how this field should be used in the GraphQL Schema', 'wpgraphql-acf' ),
			'conditions'    => [
				'field'    => 'show_in_graphql',
				'operator' => '==',
				'value'    => '1',
			],
		];

		$default_admin_settings['graphql_field_name'] = [
			'label'         => __( 'GraphQL Field Name', 'wpgraphql-acf' ),
			'instructions'  => __( 'The name of the field in the GraphQL Schema. Should only contain numbers and letters. Must start with a letter. Recommended format is "snakeCase".', 'wpgraphql-acf' ),
			'name'          => 'graphql_field_name',
			'type'          => 'text',
			'ui'            => true,
			'required'      => false,
			// we don't allow underscores if the value is auto formatted
			'placeholder'   => __( 'newFieldName', 'wpgraphql-acf' ),
			'default_value' => '',
			'conditions'    => [
				'field'    => 'show_in_graphql',
				'operator' => '==',
				'value'    => '1',
			],
		];

		$default_admin_settings['graphql_non_null'] = [
			'label'         => __( 'GraphQL Non-Null', 'wpgraphql-acf' ),
			'instructions'  => __( 'Whether the field should be Non-Null in the GraphQL Schema. <br/><br/><strong>Use with caution.</strong> Only check this if you can guarantee there will be data stored for this field on all objects that have this field. i.e. the field should be required and should have data entered for all previous entries with this field. Unchecking this, if already checked, is considered a breaking change to the GraphQL Schema.', 'wpgraphql-acf' ),
			'name'          => 'graphql_non_null',
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 0,
			'value'         => isset( $field['graphql_non_null'] ) && true === (bool) $field['graphql_non_null'],
			'conditions'    => [
				[
					'field'    => 'show_in_graphql',
					'operator' => '==',
					'value'    => '1',
				],
			],
		];

		$default_admin_settings = apply_filters( 'wpgraphql/acf/field_type_default_admin_settings', $default_admin_settings );

		// Get the admin fields for the field type
		$admin_fields = $this->get_admin_fields( $field, $default_admin_settings, $settings );

		$excluded_admin_fields = $this->get_config( 'exclude_admin_fields' );

		// Remove excluded fields
		if ( is_array( $excluded_admin_fields ) ) {
			foreach ( $excluded_admin_fields as $excluded ) {
				unset( $admin_fields[ $excluded ] );
			}
		}

		return apply_filters( 'wpgraphql/acf/field_type_admin_settings', $admin_fields );
	}

	/**
	 * @param array<mixed>                  $acf_field The ACF Field to get the settings for
	 * @param array<mixed>                  $default_admin_settings The default admin settings
	 * @param \WPGraphQL\Acf\Admin\Settings $settings Instance of the Settings class
	 *
	 * @return array<mixed>
	 */
	public function get_admin_fields( array $acf_field, array $default_admin_settings, Settings $settings ): array {
		if ( ! empty( $this->admin_fields ) ) {
			return $this->admin_fields;
		}

		$admin_fields = $this->get_config( 'admin_fields' );

		if ( is_array( $admin_fields ) ) {
			$this->admin_fields = $admin_fields;
		} elseif ( is_callable( $admin_fields ) ) {
			$this->admin_fields = $admin_fields( $default_admin_settings, $acf_field, $this->config, $settings );
		} else {
			$this->admin_fields = $default_admin_settings;
		}

		return $this->admin_fields;
	}

	/**
	 * Get the ACF field_type for the field
	 */
	public function get_acf_field_type(): string {
		return $this->acf_field_type;
	}

	/**
	 * Set the ACF Field Type
	 *
	 * @param string $acf_field_type
	 */
	protected function set_acf_field_type( string $acf_field_type ): void {
		$this->acf_field_type = $acf_field_type;
	}

	/**
	 * Set the field settings to be excluded for the field
	 */
	protected function set_excluded_admin_field_settings(): void {
		$this->excluded_admin_field_settings = [];

		$excluded_admin_fields = $this->get_config( 'exclude_admin_fields' );

		if ( empty( $excluded_admin_fields ) ) {
			return;
		}

		if ( ! is_array( $excluded_admin_fields ) ) {
			return;
		}

		$this->excluded_admin_field_settings = $excluded_admin_fields;
	}

	/**
	 * @return array<mixed>
	 */
	public function get_excluded_admin_field_settings(): array {
		return apply_filters( 'wpgraphql/acf/excluded_admin_field_settings', $this->excluded_admin_field_settings );
	}

	/**
	 * @param mixed                                $root The value of the previously resolved field in the tree
	 * @param array<mixed>                         $args The arguments input on the field
	 * @param \WPGraphQL\AppContext                $context The Context passed through resolution
	 * @param \GraphQL\Type\Definition\ResolveInfo $info Information about the field resolving
	 * @param \WPGraphQL\Acf\AcfGraphQLFieldType   $field_type The Type of ACF Field resolving
	 * @param \WPGraphQL\Acf\FieldConfig           $field_config The Config of the ACF Field resolving
	 *
	 * @return array<mixed>|callable|mixed|null
	 */
	public function get_resolver( $root, array $args, AppContext $context, ResolveInfo $info, self $field_type, FieldConfig $field_config ) {
		$acf_field = $field_config->get_acf_field();

		/**
		 * If external code has filtered this to return anything other than null, use the filtered type instead of the default
		 *
		 * @param mixed                                $null         Filtered value. If filtered to anything other than null, use the filter response instead of the default logic below
		 * @param mixed                                $root         The value of the previously resolved field in the tree
		 * @param array                                $args         The arguments input on the field
		 * @param \WPGraphQL\AppContext                $context      The Context passed through resolution
		 * @param \GraphQL\Type\Definition\ResolveInfo $info         Information about the field resolving
		 * @param \WPGraphQL\Acf\AcfGraphQLFieldType   $field_type   The Type of ACF Field resolving
		 * @param \WPGraphQL\Acf\FieldConfig           $field_config The Config of the ACF Field resolving
		 * @param array                                $acf_field    The ACF Field config
		 */
		$pre_get_resolver = apply_filters( 'wpgraphql/acf/field_type_resolver', null, $root, $args, $context, $info, $field_type, $field_config, $acf_field );

		if ( null !== $pre_get_resolver ) {
			return $pre_get_resolver;
		}

		$resolver = $field_config->resolve_field( $root, $args, $context, $info );

		if ( isset( $acf_field['graphql_resolver'] ) ) {
			$resolver = $acf_field['graphql_resolver'];
		} elseif ( ! empty( $this->get_config( 'resolve' ) ) ) {
			if ( is_callable( $this->get_config( 'resolve' ) ) ) {
				$resolver = $this->get_config( 'resolve' )( $root, $args, $context, $info, $field_type, $field_config );
			} else {
				$resolver = $this->get_config( 'resolve' );
			}
		}

		return $resolver;
	}

	/**
	 * Determine the GraphQL Type the field should resolve as.
	 *
	 * @param \WPGraphQL\Acf\FieldConfig $field_config
	 *
	 * @return array<mixed>|string
	 */
	public function get_resolve_type( FieldConfig $field_config ) {
		$acf_field = $field_config->get_acf_field();

		/**
		 * If external code has filtered this to return anything other than null, use the filtered type instead of the default
		 */
		$pre_get_resolve_type = apply_filters( 'wpgraphql/acf/field_type_resolve_type', null, $field_config, $acf_field );

		if ( null !== $pre_get_resolve_type ) {
			return $pre_get_resolve_type;
		}

		$resolve_type = 'String';

		if ( isset( $acf_field['graphql_resolve_type'] ) ) {
			$resolve_type = $acf_field['graphql_resolve_type'];
		} elseif ( ! empty( $this->get_config( 'graphql_type' ) ) ) {
			if ( is_callable( $this->get_config( 'graphql_type' ) ) ) {
				$resolve_type = $this->get_config( 'graphql_type' )( $field_config, $this );
			} else {
				$resolve_type = $this->get_config( 'graphql_type' );
			}
		}

		if ( 'connection' === $resolve_type ) {
			return $resolve_type;
		}

		// If the ACF Field is set to "graphql_non_null", map it to the schema as non_null
		if ( isset( $acf_field['graphql_non_null'] ) && true === (bool) $acf_field['graphql_non_null'] ) {
			$resolve_type = [ 'non_null' => $resolve_type ];
		}

		return $resolve_type;
	}
}
