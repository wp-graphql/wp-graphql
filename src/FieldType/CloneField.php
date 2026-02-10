<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\Utils\Utils;

class CloneField {

	/**
	 * Register support for the 'clone' acf field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'clone',
			[
				'graphql_type' => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {

					$sub_field_group = $field_config->get_raw_acf_field();
					$parent_type     = $field_config->get_parent_graphql_type_name( $sub_field_group );
					$field_name      = $field_config->get_graphql_field_name();
					$type_name       = Utils::format_type_name( $parent_type . ' ' . $field_name );
					$prefix_name     = $sub_field_group['prefix_name'] ?? false;

					// If the "Clone" field has not set a "prefix_name",
					// return NULL to prevent registering a new type
					// The cloned
					if ( ! $prefix_name ) {
						return 'NULL';
					}

					$cloned_fields = array_filter(
						array_map(
							static function ( $cloned ) {
								return acf_get_field( $cloned );
							},
							$sub_field_group['clone']
						)
					);

					$cloned_group_interfaces = array_filter(
						array_map(
							static function ( $cloned ) use ( $field_config ) {
								$cloned_group = acf_get_field_group( $cloned );
								if ( empty( $cloned_group ) ) {
									return null;
								}
								return $field_config->get_registry()->get_field_group_graphql_type_name( $cloned_group ) . '_Fields';
							},
							$sub_field_group['clone']
						)
					);

					if ( ! empty( $cloned_group_interfaces ) ) {
						$type_name = self::register_prefixed_clone_field_type( $type_name, $sub_field_group, $cloned_fields, $field_config );
						register_graphql_interfaces_to_types( $cloned_group_interfaces, [ $type_name ] );
						return $type_name;
					}

					// If the "Clone" field has cloned individual fields
					if ( ! empty( $cloned_fields ) ) {
						return self::register_prefixed_clone_field_type( $type_name, $sub_field_group, $cloned_fields, $field_config );
					}

					// Bail by returning a NULL type
					return 'NULL';
				},
				// The clone field adds its own settings field to display
				'admin_fields' => static function ( $default_admin_settings, $field, $config, \WPGraphQL\Acf\Admin\Settings $settings ) {

					// Return one GraphQL Field, ignoring the default admin settings
					return [
						'graphql_clone_field' => [
							'type'         => 'message',
							'label'        => __( 'GraphQL Settings for Clone Fields', 'wpgraphql-acf' ),
							'instructions' => __( 'Clone Fields will inherit their GraphQL settings from the field(s) being cloned. If all Fields from a Field Group are cloned, an Interface representing the cloned field Group will be applied to this field group.', 'wpgraphql-acf' ),
							'conditions'   => [],
						],
					];
				},
			]
		);
	}

	/**
	 * @param string                     $type_name The name of the GraphQL Type representing the prefixed clone field
	 * @param array<mixed>               $sub_field_group  The Field Group representing the cloned field
	 * @param array<mixed>               $cloned_fields The cloned fields to be registered to the Cloned Field Type
	 * @param \WPGraphQL\Acf\FieldConfig $field_config The ACF Field Config
	 *
	 * @throws \Exception
	 */
	public static function register_prefixed_clone_field_type( string $type_name, array $sub_field_group, array $cloned_fields, FieldConfig $field_config ): string {
		$sub_field_group['graphql_type_name']  = $type_name;
		$sub_field_group['graphql_field_name'] = $type_name;
		$sub_field_group['parent']             = $sub_field_group['key'];
		$sub_field_group['sub_fields']         = $cloned_fields;

		$field_config->get_registry()->register_acf_field_groups_to_graphql(
			[
				$sub_field_group,
			]
		);
		return $type_name;
	}
}
