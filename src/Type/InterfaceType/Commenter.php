<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class CommenterInterface
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class Commenter {

	/**
	 * Register the Commenter Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'Commenter',
			[
				'description' => static function () {
					return __( 'A user or guest who has submitted a comment. Provides identification and contact information for the comment author.', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node', 'DatabaseIdentifier' ],
				'resolveType' => static function ( $comment_author ) use ( $type_registry ) {
					if ( $comment_author instanceof User ) {
						$type = $type_registry->get_type( 'User' );
					} else {
						$type = $type_registry->get_type( 'CommentAuthor' );
					}

					return $type;
				},
				'fields'      => static function () {
					return [
						'id'           => [
							'type'        => [
								'non_null' => 'ID',
							],
							'description' => static function () {
								return __( 'The globally unique identifier for the comment author.', 'wp-graphql' );
							},
						],
						'avatar'       => [
							'type'        => 'Avatar',
							'description' => static function () {
								return __( 'Avatar object for user. The avatar object can be retrieved in different sizes by specifying the size argument.', 'wp-graphql' );
							},
						],
						'databaseId'   => [
							'type'        => [
								'non_null' => 'Int',
							],
							'description' => static function () {
								return __( 'Identifies the primary key from the database.', 'wp-graphql' );
							},
						],
						'name'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The name of the author of a comment.', 'wp-graphql' );
							},
						],
						'email'        => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The email address of the author of a comment.', 'wp-graphql' );
							},
						],
						'url'          => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The url of the author of a comment.', 'wp-graphql' );
							},
						],
						'isRestricted' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the author information is considered restricted. (not fully public)', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
