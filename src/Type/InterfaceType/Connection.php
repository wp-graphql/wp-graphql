<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class Connection {
	/**
	 * Register the Connection Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'Connection',
			[
				'description' => static function () {
					return __( 'A plural connection from one Node Type in the Graph to another Node Type, with support for relational data via "edges".', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'pageInfo' => [
							'type'        => [ 'non_null' => 'PageInfo' ],
							'description' => static function () {
								return __( 'Information about pagination in a connection.', 'wp-graphql' );
							},
						],
						'edges'    => [
							'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Edge' ] ] ],
							'description' => static function () {
								return __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' );
							},
						],
						'nodes'    => [
							'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Node' ] ] ],
							'description' => static function () {
								return __( 'A list of connected nodes', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
