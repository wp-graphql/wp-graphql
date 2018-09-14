<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

class Plugins {

	protected static $to_type = 'Plugin';

	public static function register_connection( $from_type = 'RootQuery' ) {

		register_graphql_connection([
			'fromType' => $from_type,
			'toType' => self::$to_type,
			'fromFieldName' => 'plugins',
			'connectionFields' => [
				'nodes' => [
					'type'        => [
						'list_of' => 'Plugin',
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
			'resolve' => function( $source, $args, $context, $info ) {
				return DataSource::resolve_plugins_connection( $source, $args, $context, $info );
			},
		]);
	}
}