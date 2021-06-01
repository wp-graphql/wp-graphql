<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;

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
	 * @throws Exception
	 */
	public static function register_type() {

		register_graphql_interface_type(
			'DatabaseIdentifier',
			[
				'description' => __( 'Object that can be identified with a Database ID', 'wp-graphql' ),
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
