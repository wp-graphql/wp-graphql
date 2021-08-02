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

		register_graphql_interface_type( 'DatabaseIdentifier', [
			'description' => __( 'Object that can be identified with a Database ID', 'wp-graphql' ),
			'fields'      => [
				'databaseId' => [
					'type'        => [ 'non_null' => 'Int' ],
					'description' => __( 'The unique identifier stored in the database', 'wp-graphql' ),
				],
			],
		]);
	}
}
