<?php
namespace WPGraphQL\Type;

class PostObjectsOrderby {
	public static function register_type() {
		register_graphql_input_type( 'PostObjectsOrderby', [
			'description' => __( 'Options for ordering the PostObjects connection', 'wp-graphql' ),
			'fields' => [
				'field' => [
					'type' => 'PostObjectsOrderbyFieldEnum',
					'description' => __( 'The field used to determine order by', 'wp-graphql' ),
				],
				'order' => [
					'type' => 'PostObjectOrderEnum',
					'description' => __( 'The cardinality to order by', 'wp-graphql' ),
				],
			]
		] );
	}
}