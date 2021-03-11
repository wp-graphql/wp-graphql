<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithAuthor {
	/**
	 * Registers the NodeWithAuthor Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithAuthor',
			[
				'description' => __( 'A node that can have an author assigned to it', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'DatabaseIdentifier', 'ContentNode' ],
				'fields'      => [
					'authorId'         => [
						'type'        => 'ID',
						'description' => __( 'The globally unique identifier of the author of the node', 'wp-graphql' ),
					],
					'authorDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database identifier of the author of the node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
