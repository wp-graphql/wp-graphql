<?php

namespace WPGraphQL\Type\Connection;

use WPGraphQL\Data\Connection\CommentAuthorConnectionResolver;

/**
 * Registers RootQuery connections to CommentAuthor.
 *
 * @package WPGraphQL\Type\Connection
 */
class CommentAuthors {

	/**
	 * Register connections to CommentAuthor.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_connections() {
		// Reuse comment connection args, but drop userId — CommentAuthor is always a guest (user_id = 0).
		$connection_args = Comments::get_connection_args();
		unset( $connection_args['userId'] );

		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'CommentAuthor',
				'fromFieldName'  => 'commentAuthors',
				'connectionArgs' => $connection_args,
				'description'    => static function () {
					return __( 'Connection between the RootQuery type and the CommentAuthor type. Returns authors of comments that are not WordPress users.', 'wp-graphql' );
				},
				'resolve'        => static function ( $source, $args, $context, $info ) {
					$resolver = new CommentAuthorConnectionResolver( $source, $args, $context, $info );

					return $resolver->get_connection();
				},
			]
		);
	}
}
