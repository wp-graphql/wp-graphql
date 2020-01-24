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
					'author' => [
						'type'        => 'User',
						'description' => __( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( ! isset( $post->authorId ) || ! absint( $post->authorId ) ) {
								return null;
							};

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_user( $post->authorId, $context );
						},
					],
				],
			]
		);
	}
}
