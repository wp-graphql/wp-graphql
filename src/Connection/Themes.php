<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\ThemeConnectionResolver;
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
					$resolver = new ThemeConnectionResolver( $root, $args, $context, $info );
					return $resolver->get_connection();
				},
			]
		);

	}

}
