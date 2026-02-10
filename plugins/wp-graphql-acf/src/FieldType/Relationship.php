<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;

class Relationship {

	/**
	 * Register support for the "textarea" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'relationship',
			[
				'exclude_admin_fields' => [ 'graphql_non_null' ],
				'admin_fields'         => static function ( $admin_fields, $field, $config, \WPGraphQL\Acf\Admin\Settings $settings ): array {
					return self::get_admin_fields( $admin_fields, $field, $config, $settings );
				},
				'graphql_type'         => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {
					return self::get_graphql_type( $field_config, $acf_field_type );
				},
			]
		);
	}

	/**
	 * @param array<mixed>                  $admin_fields Admin Fields to display in the GraphQL Tab when configuring an ACF Field within a Field Group
	 * @param array<mixed>                  $field The ACF Field the settings belong to
	 * @param array<mixed>                  $config The
	 * @param \WPGraphQL\Acf\Admin\Settings $settings
	 *
	 * @return mixed
	 */
	public static function get_admin_fields( $admin_fields, $field, $config, \WPGraphQL\Acf\Admin\Settings $settings ) {
		$admin_fields[] = [
			'type'          => 'select',
			'name'          => 'graphql_connection_type',
			'label'         => __( 'GraphQL Connection Type', 'wpgraphql-acf' ),
			'choices'       => [
				'one_to_one'  => __( 'One to One Connection', 'wpgraphql-acf' ),
				'one_to_many' => __( 'One to Many Connection', 'wpgraphql-acf' ),
			],
			'default_value' => 'one_to_many',
			'instructions'  => __( 'Select whether the field should be presented in the schema as a standard GraphQL "Connection" that can return 0, 1 or more nodes, or a "One to One" connection that can return exactly 0 or 1 node. Changing this field will change the GraphQL Schema and could cause breaking changes.', 'wpgraphql-acf' ),
			'conditions'    => [],
		];
		return $admin_fields;
	}

	/**
	 * @param \WPGraphQL\Acf\FieldConfig         $field_config
	 * @param \WPGraphQL\Acf\AcfGraphQLFieldType $acf_field_type
	 *
	 * @throws \Exception
	 */
	public static function get_graphql_type( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ): string {
		$acf_field = $field_config->get_acf_field();

		$connection_type = $acf_field['graphql_connection_type'] ?? 'one_to_many';
		$is_one_to_one   = 'one_to_one' === $connection_type;

		$connection_config = [
			'toType'   => 'ContentNode',
			'oneToOne' => $is_one_to_one,
			'resolve'  => static function ( $root, $args, AppContext $context, $info ) use ( $field_config, $is_one_to_one ) {
				$value = $field_config->resolve_field( $root, $args, $context, $info );

				$ids = [];

				if ( empty( $value ) ) {
					return null;
				}

				if ( ! is_array( $value ) ) {
					$ids[] = $value;
				} else {
					$ids = $value;
				}

				$ids = array_filter(
					array_map(
						static function ( $id ) {
							if ( is_object( $id ) && isset( $id->ID ) ) {
								$id = $id->ID;
							}
							// filter out values that are not IDs
							// this means that external urls or urls to things like
							// archive links will not resolve.
							return absint( $id ) ?: null;
						},
						$ids
					)
				);

				if ( empty( $ids ) ) {
					return null;
				}

				// override the args to filter by a specific set of IDs
				$args['where']['in'] = $ids;
				$resolver            = new PostObjectConnectionResolver( $root, $args, $context, $info, 'any' );

				if ( $is_one_to_one ) {
					$resolver = $resolver->one_to_one();
				}

				$resolver->set_query_arg( 'post_status', 'any' );
				return $resolver->get_connection();
			},
		];

		$field_config->register_graphql_connections( $connection_config );

		return 'connection';
	}
}
