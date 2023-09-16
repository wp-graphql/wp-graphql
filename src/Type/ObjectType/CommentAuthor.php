<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Model\CommentAuthor as CommentAuthorModel;

class CommentAuthor {

	/**
	 * Register the CommentAuthor Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'CommentAuthor',
			[
				'description'     => __( 'A Comment Author object', 'wp-graphql' ),
				'interfaces'      => [ 'Node', 'Commenter' ],
				'model'           => CommentAuthorModel::class,
				'eagerlyLoadType' => true,
				'fields'          => [
					'id'           => [
						'description' => __( 'The globally unique identifier for the comment author object', 'wp-graphql' ),
					],
					'name'         => [
						'type'        => 'String',
						'description' => __( 'The name for the comment author.', 'wp-graphql' ),
					],
					'email'        => [
						'type'        => 'String',
						'description' => __( 'The email for the comment author', 'wp-graphql' ),
					],
					'url'          => [
						'type'        => 'String',
						'description' => __( 'The url the comment author.', 'wp-graphql' ),
					],
					'isRestricted' => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'avatar'       => [
						'args'    => [
							'size'         => [
								'type'         => 'Int',
								'description'  => __( 'The size attribute of the avatar field can be used to fetch avatars of different sizes. The value corresponds to the dimension in pixels to fetch. The default is 96 pixels.', 'wp-graphql' ),
								'defaultValue' => 96,
							],
							'forceDefault' => [
								'type'        => 'Boolean',
								'description' => __( 'Whether to always show the default image, never the Gravatar. Default false', 'wp-graphql' ),
							],
							'rating'       => [
								'type'        => 'AvatarRatingEnum',
								'description' => __( 'The rating level of the avatar.', 'wp-graphql' ),
							],

						],
						'resolve' => static function ( $comment_author, $args ) {
							/**
							 * If the $comment_author is a user, the User model only returns the email address if the requesting user is authenticated.
							 * But, to resolve the Avatar we need a valid email, even for unauthenticated requests.
							 *
							 * If the email isn't visible, we use the comment ID to retrieve it, then use it to resolve the avatar.
							 *
							 * The email address is not publicly exposed, adhering to the rules of the User model.
							 */
							$comment_author_email = ! empty( $comment_author->email ) ? $comment_author->email : get_comment_author_email( $comment_author->databaseId );

							if ( empty( $comment_author_email ) ) {
								return null;
							}

							$avatar_args = [];
							if ( is_numeric( $args['size'] ) ) {
								$avatar_args['size'] = absint( $args['size'] );
								if ( ! $avatar_args['size'] ) {
									$avatar_args['size'] = 96;
								}
							}

							if ( ! empty( $args['forceDefault'] ) && true === $args['forceDefault'] ) {
								$avatar_args['force_default'] = true;
							}

							if ( ! empty( $args['rating'] ) ) {
								$avatar_args['rating'] = esc_sql( (string) $args['rating'] );
							}

							$avatar = get_avatar_data( $comment_author_email, $avatar_args );

							// if there's no url returned, return null
							if ( empty( $avatar['url'] ) ) {
								return null;
							}

							return new \WPGraphQL\Model\Avatar( $avatar );
						},
					],
				],
			]
		);
	}
}
