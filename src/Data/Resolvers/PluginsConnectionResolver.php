<?php
namespace WPGraphQL\Data\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

class PluginsConnectionResolver {

	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {

		// File has not loaded.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// This is missing must use and drop in plugins.
		$plugins = apply_filters( 'all_plugins', get_plugins() );

		return Relay::connectionFromArray( $plugins, $args );

	}

}
