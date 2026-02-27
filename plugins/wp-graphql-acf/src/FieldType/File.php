<?php

namespace WPGraphQL\Acf\FieldType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;

class File {

	/**
	 * Register support for the "file" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'file',
			[
				'exclude_admin_fields' => [ 'graphql_non_null' ],
				'graphql_type'         => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {
					if ( empty( $field_config->get_graphql_field_group_type_name() ) || empty( $field_config->get_graphql_field_name() ) ) {
						return null;
					}


					$type_name = $field_config->get_graphql_field_group_type_name();
					$to_type   = 'MediaItem';

					$field_config->register_graphql_connections(
						[
							'description'           => $field_config->get_field_description(),
							'acf_field'             => $field_config->get_acf_field(),
							'acf_field_group'       => $field_config->get_acf_field_group(),
							'fromType'              => $type_name,
							'toType'                => $to_type,
							'fromFieldName'         => $field_config->get_graphql_field_name(),
							'oneToOne'              => true,
							'resolve'               => static function ( $root, $args, AppContext $context, ResolveInfo $info ) use ( $field_config ) {
								$value = $field_config->resolve_field( $root, $args, $context, $info );

								if ( is_object( $value ) && isset( $value->ID ) ) {
									$value = $value->ID;
								}

								if ( is_array( $value ) && isset( $value['ID'] ) ) {
									$value = $value['ID'];
								}

								if ( empty( $value ) || ! absint( $value ) ) {
									return null;
								}

								$resolver = new PostObjectConnectionResolver( $root, $args, $context, $info, 'attachment' );
								return $resolver
									->one_to_one()
									->set_query_arg( 'p', absint( $value ) )
									->get_connection();
							},
							'allowFieldUnderscores' => true,
						]
					);

					return 'connection';
				},
			]
		);
	}
}
