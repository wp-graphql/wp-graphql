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
				'description' => static function () {
					return __( 'An object that has a unique numeric identifier in the database. Provides consistent access to the database ID across different object types.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'databaseId' => [
							'type'        => [ 'non_null' => 'Int' ],
							'description' => static function () {
								return __( 'The unique identifier stored in the database', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
