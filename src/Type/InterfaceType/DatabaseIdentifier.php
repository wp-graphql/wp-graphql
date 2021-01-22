<?php

namespace WPGraphQL\Type\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class DatabaseIdentifier
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class DatabaseIdentifier {

	/**
	 * Register the DatabaseIdentifier Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

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
