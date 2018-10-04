<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

class Plugins {
	public static function register_connections() {
		register_graphql_connection([
			'fromType' => 'RootQuery',
			'toType' => 'Plugin',
			'fromFieldName' => 'plugins',
			'resolve' => function( $root, $args, $context, $info ) {
				return DataSource::resolve_plugins_connection( $root, $args, $context, $info );
			},
		]);
	}
}