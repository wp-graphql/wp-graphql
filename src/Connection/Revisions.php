<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeRegistry;

class Revisions {

	/**
	 * Register connections to Revisions
	 *
	 * @param TypeRegistry $type_registry Instance of the TypeRegistry
	 *
	 * @return void
	 */
	public static function register_connections( TypeRegistry $type_registry ) {

		/**
		 * The Root Query
		 */
		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'ContentRevisionUnion',
				'queryClass'     => 'WP_Query',
				'fromFieldName'  => 'revisions',
				'connectionArgs' => PostObjects::get_connection_args(),
				'resolve'        => function( $root, $args, $context, $info ) {
					return DataSource::resolve_post_objects_connection( $root, $args, $context, $info, 'revision' );
				},
			]
		);

		register_graphql_connection(
			[
				'fromType'       => 'User',
				'toType'         => 'ContentRevisionUnion',
				'queryClass'     => 'WP_Query',
				'fromFieldName'  => 'revisions',
				'description'    => __( 'Connection between the User and Revisions authored by the user', 'wp-graphql' ),
				'connectionArgs' => PostObjects::get_connection_args(),
				'resolve'        => function( $root, $args, $context, $info ) {
					return DataSource::resolve_post_objects_connection( $root, $args, $context, $info, 'revision' );
				},
			]
		);

	}
}
