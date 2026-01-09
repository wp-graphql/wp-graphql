<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithRevisions {

	/**
	 * Registers the NodeWithRevisions Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithRevisions',
			[
				'interfaces'  => [ 'Node' ],
				'description' => static function () {
					return __( 'Content that maintains a history of changes. Provides access to previous versions of the content and the ability to restore earlier revisions.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'isRevision' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'True if the node is a revision of another node', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
