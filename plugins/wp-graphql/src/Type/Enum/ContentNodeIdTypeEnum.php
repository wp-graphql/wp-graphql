<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Utils\Utils;

/**
 * Class - ContentNodeIdTypeEnum
 *
 * @package WPGraphQL\Type\Enum
 *
 * @phpstan-import-type PartialWPEnumValueConfig from \WPGraphQL\Type\WPEnumType
 */
class ContentNodeIdTypeEnum {

	/**
	 * Register the Enum used for setting the field to identify content nodes by
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'ContentNodeIdTypeEnum',
			[
				'description' => static function () {
					return __( 'Identifier types for retrieving specific content. Determines which property (global ID, database ID, URI) is used to locate content objects.', 'wp-graphql' );
				},
				'values'      => self::get_values(),
			]
		);

		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects' );

		foreach ( $allowed_post_types as $post_type_object ) {
			$values = self::get_values();

			if ( ! $post_type_object->hierarchical ) {
				$values['SLUG'] = [
					'name'        => 'SLUG',
					'value'       => 'slug',
					'description' => static function () {
						return __( 'Identify a resource by the slug. Available to non-hierarchcial Types where the slug is a unique identifier.', 'wp-graphql' );
					},
				];
			}

			if ( 'attachment' === $post_type_object->name ) {
				$values['SOURCE_URL'] = [
					'name'        => 'SOURCE_URL',
					'value'       => 'source_url',
					'description' => static function () {
						return __( 'Identify a media item by its source url', 'wp-graphql' );
					},
				];
			}

			/**
			 * Register a unique Enum per Post Type. This allows for granular control
			 * over filtering and customizing the values available per Post Type.
			 */
			register_graphql_enum_type(
				$post_type_object->graphql_single_name . 'IdType',
				[
					'description' => static function () use ( $post_type_object ) {
						// translators: %1$s is the post type name, %2$s is the post type name
						return sprintf( __( 'Identifier types for retrieving a specific %1$s. Specifies which unique attribute is used to find an exact %2$s.', 'wp-graphql' ), Utils::format_type_name( $post_type_object->graphql_single_name ), Utils::format_type_name( $post_type_object->graphql_single_name ) );
					},
					'values'      => $values,
				]
			);
		}
	}

	/**
	 * Returns the values for the Enum.
	 *
	 * @return array<string,PartialWPEnumValueConfig>
	 */
	public static function get_values() {
		return [
			'ID'          => [
				'name'        => 'ID',
				'value'       => 'global_id',
				'description' => static function () {
					return __( 'Identify a resource by the (hashed) Global ID.', 'wp-graphql' );
				},
			],
			'DATABASE_ID' => [
				'name'        => 'DATABASE_ID',
				'value'       => 'database_id',
				'description' => static function () {
					return __( 'Identify a resource by the Database ID.', 'wp-graphql' );
				},
			],
			'URI'         => [
				'name'        => 'URI',
				'value'       => 'uri',
				'description' => static function () {
					return __( 'Identify a resource by the URI.', 'wp-graphql' );
				},
			],
		];
	}
}
