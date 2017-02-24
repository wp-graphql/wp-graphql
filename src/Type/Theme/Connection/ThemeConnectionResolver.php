<?php
namespace WPGraphQL\Type\Theme\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

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
	 */
	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {
		$themes = wp_get_themes();

		return Relay::connectionFromArray( $themes, $args );
	}

}
