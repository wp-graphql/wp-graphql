<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Data\DataSource;

class Node {

	/**
	 * Register the Node interface
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_interface_type(
			'Node',
			[
				'description' => __( 'An object with a globally unique identifier. All objects that can be identified by a unique ID implement this interface.', 'wp-graphql' ),
				'fields'      => [
					'id' => [
						'type'        => [ 'non_null' => 'ID' ],
						'description' => __( 'The globally unique ID for the object', 'wp-graphql' ),
					],
				],
				'resolveType' => static function ( $node ) {
					return DataSource::resolve_node_type( $node );
				},
			]
		);
	}
}
