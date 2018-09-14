<?php
namespace WPGraphQL\Type;

class PostObjectOrderEnum {
	public static function register_type() {
		register_graphql_enum_type( 'PostObjectOrderEnum', [
			'description' => __( 'The cardinality of the order', 'wp-graphql' ),
			'values' => [
				'ASC'  => [ 'value' => 'ASC' ],
				'DESC' => [ 'value' => 'DESC' ],
			],
		] );
	}
}