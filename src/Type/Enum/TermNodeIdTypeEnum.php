<?php

namespace WPGraphQL\Type\Enum;

class TermNodeIdTypeEnum {

	/**
	 * Register the Enum used for setting the field to identify term nodes by
	 *
	 * @return void
	 */
	public static function register_type() {

		register_graphql_enum_type(
			'TermNodeIdTypeEnum',
			[
				'description' => __( 'The Type of Identifier used to fetch a single resource. Default is "ID". To be used along with the "id" field.', 'wp-graphql' ),
				'values'      => self::get_values(),
			]
		);

		/**
		 * Register a unique Enum per Taxonomy. This allows for granular control
		 * over filtering and customizing the values available per Taxonomy.
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$taxonomy_object = get_taxonomy( $taxonomy );

				register_graphql_enum_type(
					$taxonomy_object->graphql_single_name . 'IdType',
					[
						'description' => __( 'The Type of Identifier used to fetch a single resource. Default is ID.', 'wp-graphql' ),
						'values'      => self::get_values(),
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
			'SLUG'        => [
				'name'        => 'SLUG',
				'value'       => 'slug',
				'description' => __( 'Url friendly name of the node', 'wp-graphql' ),
			],
			'NAME'        => [
				'name'        => 'NAME',
				'value'       => 'name',
				'description' => __( 'The name of the node', 'wp-graphql' ),
			],
			'ID'          => [
				'name'        => 'ID',
				'value'       => 'global_id',
				'description' => __( 'The hashed Global ID', 'wp-graphql' ),
			],
			'DATABASE_ID' => [
				'name'        => 'DATABASE_ID',
				'value'       => 'database_id',
				'description' => __( 'The Database ID for the node', 'wp-graphql' ),
			],
			'URI'         => [
				'name'        => 'URI',
				'value'       => 'uri',
				'description' => __( 'The URI for the node', 'wp-graphql' ),
			],
		];
	}
}
