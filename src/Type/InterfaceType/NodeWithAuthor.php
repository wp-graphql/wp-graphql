<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithAuthor {
	/**
	 * Registers the NodeWithAuthor Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithAuthor',
			[
				'description' => __( 'A node that can have an author assigned to it', 'wp-graphql' ),
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
