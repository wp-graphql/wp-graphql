<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Data\DataSource;

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
								'description' => __( 'Whether to always show the default image, never the Gravatar. Default false' ),
							],
							'rating'       => [
								'type'        => 'AvatarRatingEnum',
								'description' => __( 'The rating level of the avatar.', 'wp-graphql' ),
							],

						],
						'resolve' => function ( $comment_author, $args, $context, $info ) {

							if ( ! isset( $comment_author->email ) ) {
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
								$avatar_args['rating'] = esc_sql( $args['rating'] );
							}

							$avatar = get_avatar_data( $comment_author->email, $avatar_args );

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
