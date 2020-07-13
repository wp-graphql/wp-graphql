<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithAuthor {
	/**
	 * @param TypeRegistry $type_registry Instance of the Type Registry
	 */
	public static function register_type( $type_registry ) {
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
