<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class CommentAuthorInterface
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class CommentAuthorInterface {

	/**
	 * Register the CommentAuthor Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'CommentWriter', [
			'description' => __( 'The author of a comment', 'wp-graphql' ),
			'resolveType' => function ( $commenter ) use ( $type_registry ) {
				if ( $commenter instanceof User ) {
					$type = $type_registry->get_type( 'User' );
				} else {
					$type = $type_registry->get_type( 'GuestCommenter' );
				}

				return $type;
			},
			'fields'      => [
				'id'           => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => __( 'The globally unique identifier for the commenter.', 'wp-graphql' ),
				],
				'avatar'       => [
					'type'        => 'Avatar',
					'description' => __( 'Avatar object for user. The avatar object can be retrieved in different sizes by specifying the size argument.', 'wp-graphql' ),
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
