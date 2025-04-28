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
		register_graphql_enum_type(
			'CommentNodeIdTypeEnum',
			[
				'description' => static function () {
					return __( 'Identifier types for retrieving a specific comment. Specifies which unique attribute is used to find a particular comment.', 'wp-graphql' );
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
