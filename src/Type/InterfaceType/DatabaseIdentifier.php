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
					return __( 'Object that can be identified with a Database ID', 'wp-graphql' );
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
