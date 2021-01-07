<?php

namespace WPGraphQL\Type\Enum;

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
				'description' => __( 'The Type of Identifier used to fetch a single resource. Default is ID.', 'wp-graphql' ),
				'values'      => self::get_values(),
			]
		);

		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );

				if ( empty( $post_type_object ) ) {
					return;
				}

				$values = self::get_values();
				if ( ! $post_type_object->hierarchical ) {
					$values['slug'] = [
						'name'        => 'SLUG',
						'value'       => 'slug',
						'description' => __( 'Identify a resource by the slug. Available to non-hierarchcial Types where the slug is a unique identifier.', 'wp-graphql' ),
					];
				}

				if ( 'attachment' === $post_type_object->name ) {
					$values['source_url'] = [
						'name'        => 'SOURCE_URL',
						'value'       => 'source_url',
						'description' => __( 'Identify a media item by its source url', 'wp-graphql' ),
					];
				}

				/**
				 * Register a unique Enum per Post Type. This allows for granular control
				 * over filtering and customizing the values available per Post Type.
				 */
				register_graphql_enum_type(
					$post_type_object->graphql_single_name . 'IdType',
					[
						'description' => __( 'The Type of Identifier used to fetch a single resource. Default is ID.', 'wp-graphql' ),
						'values'      => $values,
					]
				);

			}
		}

	}

	/**
	 * Get the values for the Enum definitions
	 *
	 * @return array
	 */
	public static function get_values() {
		return [
			'ID'          => [
				'name'        => 'ID',
				'value'       => 'global_id',
				'description' => __( 'Identify a resource by the (hashed) Global ID.', 'wp-graphql' ),
			],
			'DATABASE_ID' => [
				'name'        => 'DATABASE_ID',
				'value'       => 'database_id',
				'description' => __( 'Identify a resource by the Database ID.', 'wp-graphql' ),
			],
			'URI'         => [
				'name'        => 'URI',
				'value'       => 'uri',
				'description' => __( 'Identify a resource by the URI.', 'wp-graphql' ),
			],
		];
	}
}
