<?php

namespace WPGraphQL\Type\InterfaceType;

/**
 * Class DatabaseIdentifier
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class DatabaseIdentifier {

	/**
	 * Register the DatabaseIdentifier Interface.
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_interface_type(
			'DatabaseIdentifier',
			[
				'description' => __( 'An object that has a unique numeric identifier in the database. Provides consistent access to the database ID across different object types.', 'wp-graphql' ),
				'fields'      => [
					'databaseId' => [
						'type'        => [ 'non_null' => 'Int' ],
						'description' => __( 'The unique identifier stored in the database', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
