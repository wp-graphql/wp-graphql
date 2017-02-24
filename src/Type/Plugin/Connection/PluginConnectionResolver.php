<?php
namespace WPGraphQL\Type\Plugin\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class PluginConnectionResolver {

	/**
	 * Creates the connection for plugins
	 *
	 * @param mixed       $source  The query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.5.0
	 * @return array
	 * @access public
	 */
	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {

		// File has not loaded.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// This is missing must use and drop in plugins.
		$plugins = apply_filters( 'all_plugins', get_plugins() );

		return Relay::connectionFromArray( $plugins, $args );

	}

}
