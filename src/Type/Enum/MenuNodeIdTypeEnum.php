<?php
namespace WPGraphQL\Type\Enum;

/**
 * Class MenuNodeIdTypeEnum
 *
 * @package WPGraphQL\Type\Enum
 */
class MenuNodeIdTypeEnum {

	/**
	 * Register the MenuNodeIdTypeEnum
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'MenuNodeIdTypeEnum',
			[
				'description' => __( 'The Type of Identifier used to fetch a single node. Default is "ID". To be used along with the "id" field.', 'wp-graphql' ),
				'values'      => [
					'ID'          => [
						'name'        => 'ID',
						'value'       => 'global_id',
						'description' => __( 'Identify a menu node by the (hashed) Global ID.', 'wp-graphql' ),
					],
					'DATABASE_ID' => [
						'name'        => 'DATABASE_ID',
						'value'       => 'database_id',
						'description' => __( 'Identify a menu node by the Database ID.', 'wp-graphql' ),
					],
					'LOCATION'    => [
						'name'        => 'LOCATION',
						'value'       => 'location',
						'description' => __( 'Identify a menu node by the slug of menu location to which it is assigned', 'wp-graphql' ),
					],
					'NAME'        => [
						'name'        => 'NAME',
						'value'       => 'name',
						'description' => __( 'Identify a menu node by its name', 'wp-graphql' ),
					],
					'SLUG'        => [
						'name'        => 'SLUG',
						'value'       => 'slug',
						'description' => __( 'Identify a menu node by its slug', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
