<?php
namespace WPGraphQL\Type;

class CommentsOrderEnum {
	public static function register_type() {
		register_graphql_enum_type( 'CommentsOrderEnum', [
			'description' => __( 'Cardinality for ordering the comments', 'wp-graphql' ),
			'values'       => [
				'ASC'  => [
					'value' => 'ASC',
				],
				'DESC' => [
					'value' => 'DESC',
				],
			],
			'defaultValue' => 'DESC',
 		]);
	}
}