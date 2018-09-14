<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

class Themes {

	protected static $to_type = 'Theme';

	public static function register_connection( $from_type = 'RootQuery' ) {

		register_graphql_connection([
			'fromType' => $from_type,
			'toType' => self::$to_type,
			'fromFieldName' => 'themes',
			'connectionFields' => [
				'nodes' => [
					'type'        => [
						'list_of' => 'Theme',
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
			'resolve' => function( $source, $args, $context, $info ) {
				return DataSource::resolve_themes_connection( $source, $args, $context, $info );
			},
		]);
	}
}