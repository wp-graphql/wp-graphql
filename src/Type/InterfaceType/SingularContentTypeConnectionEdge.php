<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class SingularContentTypeConnectionEdge {

	/**
	 * Register the SingularContentTypeConnectionEdge Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'SingularContentTypeConnectionEdge', [
			'interfaces'  => [ 'SingularConnection', 'Edge' ],
			'description' => __( 'Connection to ContentType Node', 'wp-graphql' ),
			'fields'      => [
				'node' => [
					'type'        => [ 'non_null' => 'ContentType' ],
					'description' => __( 'The ContentType Node', 'wp-graphql' ),
				],
			],
		] );

	}

}
