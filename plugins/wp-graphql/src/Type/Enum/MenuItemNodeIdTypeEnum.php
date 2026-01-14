<?php
namespace WPGraphQL\Type\Enum;

/**
 * Class MenuItemNodeIdTypeEnum
 *
 * @package WPGraphQL\Type\Enum
 */
class MenuItemNodeIdTypeEnum {

	/**
	 * Register the MenuItemNodeIdTypeEnum
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'MenuItemNodeIdTypeEnum',
			[
				'description' => static function () {
					return __( 'Identifier types for retrieving a specific menu item. Determines whether to look up menu items by global ID or database ID.', 'wp-graphql' );
				},
				'values'      => [
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
				],
			]
		);
	}
}
