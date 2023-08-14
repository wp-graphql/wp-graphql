<?php
namespace WPGraphQL\Type\Enum;

/**
 * Class CommentNodeIdTypeEnum
 *
 * @package WPGraphQL\Type\Enum
 */
class CommentNodeIdTypeEnum {

	/**
	 * Register the CommentNodeIdTypeEnum
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type( 'CommentNodeIdTypeEnum', [
			'description' => __( 'The Type of Identifier used to fetch a single comment node. Default is "ID". To be used along with the "id" field.', 'wp-graphql' ),
			'values'      => [
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
			],
		]);
	}
}
