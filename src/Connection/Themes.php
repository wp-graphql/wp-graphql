<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

/**
 * Class Themes
 *
 * This class organizes registering connections to Themes
 *
 * @package WPGraphQL\Connection
 */
class Themes {

	/**
	 * Register the connections
	 *
	 * @access public
	 */
	public static function register_connections() {

		/**
		 * Registers the RootQuery connection
		 */
		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'Theme',
				'fromFieldName' => 'themes',
				'resolve'       => function ( $root, $args, $context, $info ) {
					return DataSource::resolve_themes_connection( $root, $args, $context, $info );
				},
			]
		);

	}

}
