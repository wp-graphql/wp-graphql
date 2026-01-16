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
				'description' => static function () {
					return __( 'An object with a globally unique identifier. All objects that can be identified by a unique ID implement this interface.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'id' => [
							'type'        => [ 'non_null' => 'ID' ],
							'description' => static function () {
								return __( 'The globally unique ID for the object', 'wp-graphql' );
							},
						],
					];
				},
				'resolveType' => static function ( $node ) {
					return DataSource::resolve_node_type( $node );
				},
			]
		);
	}
}
