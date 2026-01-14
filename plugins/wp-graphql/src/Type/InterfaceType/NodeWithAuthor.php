<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithAuthor {
	/**
	 * Registers the NodeWithAuthor Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithAuthor',
			[
				'interfaces'  => [ 'Node' ],
				'description' => static function () {
					return __( 'Content that can be attributed to a specific user. Provides fields for accessing the author\'s information and establishing content ownership.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'authorId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'The globally unique identifier of the author of the node', 'wp-graphql' );
							},
						],
						'authorDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The database identifier of the author of the node', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
