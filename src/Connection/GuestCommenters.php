<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\GuestCommenterConnectionResolver;


/**
 * Class Comments
 *
 * This class organizes the registration of connections to Comments
 *
 * @package WPGraphQL\Connection
 */
class GuestCommenters {

	/**
	 * Register connections to Guest Commenters.
	 *
	 * Connections from Root Query to Guest Commenters .
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_connections() {

		/**
		 * Register connection from RootQuery to Guest Commenters
		 */
		register_graphql_connection( self::get_connection_config() );
	}

	/**
	 * Given an array of $args, this returns the connection config, merging the provided args
	 * with the defaults
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public static function get_connection_config( $args = [] ) {
		$defaults = [
			'fromType'       => 'RootQuery',
			'toType'         => 'GuestCommenter',
			'fromFieldName'  => 'guestCommenters',
			'connectionArgs' => self::get_connection_args(),
			'resolve'        => function ( $source, $args, $context, $info ) {

				$resolver = new GuestCommenterConnectionResolver( $source, $args, $context, $info );

				return $resolver->get_connection();
		
			},
		];

		return array_merge( $defaults, $args );
	}

	/**
	 * This returns the connection args for the Comment connection
	 *
	 * @return array
	 */
	public static function get_connection_args() {
		return [
			'authorEmail'        => [
				'type'        => 'String',
				'description' => __( 'Guest commenter email address.', 'wp-graphql' ),
			],
			'authorIn'           => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of author IDs to include comments for.', 'wp-graphql' ),
			],
			'authorNotIn'        => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of author IDs to exclude comments for.', 'wp-graphql' ),
			],
			// 'orderby'            => [
			// 	'type'        => 'CommentsConnectionOrderbyEnum',
			// 	'description' => __( 'Field to order the comments by.', 'wp-graphql' ),
			// ],
			// 'order'              => [
			// 	'type'        => 'OrderEnum',
			// 	'description' => __( 'The cardinality of the order of the connection', 'wp-graphql' ),
			// ],
			// 'search'             => [
			// 	'type'        => 'String',
			// 	'description' => __( 'Search term(s) to retrieve matching comments for.', 'wp-graphql' ),
			// ],
		];
	}
}
