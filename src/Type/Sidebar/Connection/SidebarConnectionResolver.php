<?php

namespace WPGraphQL\Type\Sidebar\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class SidebarConnectionResolver
 *
 * @package WPGraphQL\Type\Sidebar\Connection
 * @since   0.0.31
 */
class SidebarConnectionResolver {

	/**
	 * Creates the connection for sidebar
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
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		global $wp_registered_sidebars;

		$sidebar_array = array();
		foreach( $wp_registered_sidebars as $data ) {
			
			$sidebar = $data;

			$sidebar['is_sidebar'] = true;

			$sidebar_array[] = $sidebar;
		}

		$connection = Relay::connectionFromArray( $sidebar_array, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}
		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $sidebar_array ) ? $connection : null;
	}
}