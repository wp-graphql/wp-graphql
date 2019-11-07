<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;

/**
 * Class ThemeConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
 */
class ThemeConnectionResolver {

	/**
	 * Creates the connection for themes
	 *
	 * @param mixed       $source  The query results of the query calling this relation
	 * @param array       $args    Query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.5.0
	 * @return array
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$themes_array = [];
		$themes       = wp_get_themes();
		if ( is_array( $themes ) && ! empty( $themes ) ) {
			foreach ( $themes as $theme ) {
				$theme_obj = DataSource::resolve_theme( $theme->get_stylesheet() );
				if ( 'private' !== $theme_obj->get_visibility() ) {
					$themes_array[] = $theme_obj;
				}
			}
		}

		$connection = Relay::connectionFromArray( $themes_array, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}

		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $themes_array ) ? $connection : null;
	}

}
