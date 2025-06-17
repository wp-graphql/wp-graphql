<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Utils\Utils;

/**
 * Class - TermNodeIdTypeEnum
 *
 * @package WPGraphQL\Type\Enum
 *
 * @phpstan-import-type PartialWPEnumValueConfig from \WPGraphQL\Type\WPEnumType
 */
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
				'description' => static function () {
					return __( 'The Type of Identifier used to fetch a single resource. Default is "ID". To be used along with the "id" field.', 'wp-graphql' );
				},
				'values'      => self::get_values(),
			]
		);

		/**
		 * Register a unique Enum per Taxonomy. This allows for granular control
		 * over filtering and customizing the values available per Taxonomy.
		 *
		 * @var \WP_Taxonomy[] $allowed_taxonomies
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		foreach ( $allowed_taxonomies as $tax_object ) {
			register_graphql_enum_type(
				$tax_object->graphql_single_name . 'IdType',
				[
					'description' => static function () use ( $tax_object ) {
						// translators: %1$s is the taxonomy name, %2$s is the taxonomy name
						return sprintf( __( 'Identifier types for retrieving a specific %1$s. Determines which unique property (global ID, database ID, slug, etc.) is used to locate the %2$s.', 'wp-graphql' ), Utils::format_type_name( $tax_object->graphql_single_name ), Utils::format_type_name( $tax_object->graphql_single_name ) );
					},
					'values'      => self::get_values(),
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
			'SLUG'        => [
				'name'        => 'SLUG',
				'value'       => 'slug',
				'description' => static function () {
					return __( 'Url friendly name of the node', 'wp-graphql' );
				},
			],
			'NAME'        => [
				'name'        => 'NAME',
				'value'       => 'name',
				'description' => static function () {
					return __( 'The name of the node', 'wp-graphql' );
				},
			],
			'ID'          => [
				'name'        => 'ID',
				'value'       => 'global_id',
				'description' => static function () {
					return __( 'The hashed Global ID', 'wp-graphql' );
				},
			],
			'DATABASE_ID' => [
				'name'        => 'DATABASE_ID',
				'value'       => 'database_id',
				'description' => static function () {
					return __( 'The Database ID for the node', 'wp-graphql' );
				},
			],
			'URI'         => [
				'name'        => 'URI',
				'value'       => 'uri',
				'description' => static function () {
					return __( 'The URI for the node', 'wp-graphql' );
				},
			],
		];
	}
}
