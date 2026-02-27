<?php
namespace WPGraphQL\Acf\FieldType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\UserConnectionResolver;

class User {

	/**
	 * Register support for the "user" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'user',
			[
				'exclude_admin_fields' => [ 'graphql_non_null' ],
				'graphql_type'         => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {
					if ( empty( $field_config->get_graphql_field_group_type_name() ) || empty( $field_config->get_graphql_field_name() ) ) {
						return null;
					}

					$to_type = 'User';
					$field_config->register_graphql_connections(
						[

							'description'           => $field_config->get_field_description(),
							'acf_field'             => $field_config->get_acf_field(),
							'acf_field_group'       => $field_config->get_acf_field_group(),
							'toType'                => $to_type,
							'oneToOne'              => false,
							'allowFieldUnderscores' => true,
							'resolve'               => static function ( $root, $args, AppContext $context, ResolveInfo $info ) use ( $field_config ) {
								$value = $field_config->resolve_field( $root, $args, $context, $info );

								if ( empty( $value ) ) {
									return null;
								}

								$values = is_array( $value ) ? $value : [];
								if ( ! is_array( $value ) ) {
									$values[] = $value;
								} else {
									$values = $value;
								}

								$value = array_map(
									static function ( $user ) {
										if ( is_array( $user ) && isset( $user['ID'] ) ) {
											return absint( $user['ID'] );
										}
										if ( is_object( $user ) && isset( $user->ID ) ) {
											return absint( $user->ID );
										}
										return absint( $user );
									},
									$values
								);

								if ( empty( $value ) ) {
									return null;
								}

								$args['where']['include'] = $value;
								$resolver                 = new UserConnectionResolver( $root, $args, $context, $info );
								$resolver->set_query_arg( 'orderby', 'include' );
								return $resolver->get_connection();
							},
						]
					);

					// The connection will be registered to the Schema so we return null for the field type
					return 'connection';
				},
			]
		);
	}
}
