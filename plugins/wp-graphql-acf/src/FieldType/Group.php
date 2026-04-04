<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

class Group {

	/**
	 * Register support for the "group" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'group',
			[
				'graphql_type' => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {
					$sub_field_group = $field_config->get_acf_field();
					$parent_type     = $field_config->get_parent_graphql_type_name( $sub_field_group );
					$field_name      = $field_config->get_graphql_field_name();

					$type_name = Utils::format_type_name( $parent_type . ' ' . $field_name );

					$sub_field_group['graphql_type_name']  = $type_name;
					$sub_field_group['graphql_field_name'] = $type_name;
					$sub_field_group['parent']             = $sub_field_group['key'];

					// Determine if the group is a clone field
					$cloned_type = null;

					// if the field group is actually a cloned field group, we
					// can return the GraphQL Type of the cloned field group
					if ( isset( $sub_field_group['_clone'] ) ) {
						$cloned_from = acf_get_field( $sub_field_group['_clone'] );

						if ( ! empty( $cloned_from['clone'] ) && is_array( $cloned_from['clone'] ) ) {
							foreach ( $cloned_from['clone'] as $clone_field ) {
								$cloned_group = acf_get_field_group( $clone_field );

								// if there is no cloned group, continue to the next clone field
								if ( ! $cloned_group ) {
									continue;
								}

								// if the cloned group is not an ACF field group, continue to the next clone field
								if ( ! acf_is_field_group( $cloned_group ) ) {
									continue;
								}

								// if the cloned group should not show in GraphQL, continue to the next clone field
								if ( ! $field_config->get_registry()->should_field_group_show_in_graphql( $cloned_group ) ) {
									continue;
								}

								// determine the GraphQL Type of the cloned field group
								$cloned_type = $field_config->get_registry()->get_field_group_graphql_type_name( $cloned_group );
								break;
							}
						}
					}

					// If the group is a clone field, return the cloned type instead of registering
					// another Type in the registry
					if ( $cloned_type ) {
						$cloned_type_name = Utils::format_type_name( $cloned_type . ' ' . $field_name );
						if ( $field_config->get_registry()->has_registered_field_group( $cloned_type_name ) ) {
							return $cloned_type_name;
						}

						$sub_field_group['graphql_type_name']  = $cloned_type_name;
						$sub_field_group['graphql_field_name'] = $cloned_type_name;
						$field_config->get_registry()->register_acf_field_groups_to_graphql(
							[
								$sub_field_group,
							]
						);

						return $cloned_type_name;
					}

					$field_config->get_registry()->register_acf_field_groups_to_graphql(
						[
							$sub_field_group,
						]
					);

					return $type_name;
				},
				'resolve'      => static function ( $root, $args, AppContext $context, $info, $field_type, FieldConfig $field_config ) {
					$value = $field_config->resolve_field( $root, $args, $context, $info );

					if ( ! empty( $value ) ) {
						return $value;
					}

					$root['value']           = $value;
					$root['acf_field_group'] = $field_config->get_acf_field();

					return $root;
				},
			]
		);
	}
}
