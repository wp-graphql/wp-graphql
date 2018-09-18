<?php
namespace WPGraphQL\Type;

class MenuConnectionWhereArgs {
	public static function register_type() {
		register_graphql_input_type( 'MenuConnectionWhereArgs', [
			'description' => __( 'Options for filtering the connection', 'wp-graphql' ),
			'fields' => [
				'id' => [
					'type'        => 'Int',
					'description' => __( 'The ID of the object', 'wp-graphql' ),
				],
				'location' => [
					'type'        => 'MenuLocationEnum',
					'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
				],
				'slug' => [
					'type'        => 'String',
					'description' => __( 'The slug of the menu to query items for', 'wp-graphql' ),
				],
			]
		]);
	}
}