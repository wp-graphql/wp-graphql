<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

class Themes {
	public static function register_connections() {
		register_graphql_connection([
			'fromType' => 'RootQuery',
			'toType' => 'Theme',
			'fromFieldName' => 'themes',
			'resolve' => function( $root, $args, $context, $info ) {
				return DataSource::resolve_themes_connection( $root, $args, $context, $info );
			},
		]);
	}
}