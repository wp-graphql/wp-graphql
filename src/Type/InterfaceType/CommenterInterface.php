<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class CommenterInterface
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class CommenterInterface {

	/**
	 * Register the Commenter Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'Commenter', [
			'description' => __( 'The author of a comment', 'wp-graphql' ),
			'resolveType' => function( $comment_author ) use ( $type_registry ) {
				if ( $comment_author instanceof User ) {
					$type = $type_registry->get_type( 'User' );
				} else {
					$type = $type_registry->get_type( 'CommentAuthor' );
				}

				return $type;
			},
			'fields'      => [
				'id'           => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => __( 'The globally unique identifier for the comment author.', 'wp-graphql' ),
				],
				'databaseId'   => [
					'type'        => [
						'non_null' => 'Int',
					],
					'description' => __( 'Identifies the primary key from the database.', 'wp-graphql' ),
				],
				'name'         => [
					'type'        => 'String',
					'description' => __( 'The name of the author of a comment.', 'wp-graphql' ),
				],
				'email'        => [
					'type'        => 'String',
					'description' => __( 'The email address of the author of a comment.', 'wp-graphql' ),
				],
				'url'          => [
					'type'        => 'String',
					'description' => __( 'The url of the author of a comment.', 'wp-graphql' ),
				],
				'isRestricted' => [
					'type'        => 'Boolean',
					'description' => __( 'Whether the author information is considered restricted. (not fully public)', 'wp-graphql' ),
				],
			],
		] );

	}

}
