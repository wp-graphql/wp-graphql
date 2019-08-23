<?php

namespace WPGraphQL\Type\Input;

class MenuItemsConnectionWhereArgs {
	public static function register_type() {
		register_graphql_input_type(
			'MenuItemsWhereArgs',
			[
				'description' => __( 'Options for filtering the connection', 'wp-graphql' ),
				'fields'      => [
					'id'       => [
						'type'        => 'Int',
						'description' => __( 'The ID of the object', 'wp-graphql' ),
					],
					'location' => [
						'type'        => 'MenuLocationEnum',
						'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
