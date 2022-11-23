<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class SingularCommenterConnectionEdge {

	/**
	 * Register the SingularCommenterConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'SingularCommenterConnectionEdge', [
			'interfaces'  => [ 'SingularConnection', 'Edge' ],
			'description' => __( 'Connection to Commenter Nodes', 'wp-graphql' ),
			'fields'      => [
				'node' => [
					'type'        => [ 'non_null' => 'Commenter' ],
					'description' => __( 'The Commenter Node', 'wp-graphql' ),
				],
			],
		] );

	}

}