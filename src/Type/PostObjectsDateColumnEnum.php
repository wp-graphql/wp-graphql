<?php
namespace WPGraphQL\Type;

class PostObjectsDateColumnEnum {
	public static function register_type() {
		register_graphql_enum_type('PostObjectsDateColumnEnum', [
			'description' => __( 'The column used to search by', 'wp-graphql' ),
			'values' => [
				'DATE'     => [
					'value' => 'post_date',
				],
				'MODIFIED' => [
					'value' => 'post_modified',
				],
			],
		]);
	}
}