<?php
namespace WPGraphQL\Data\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class PostTypesConnectionResolver - Connects post types to other types
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
 */
class PostTypesConnectionResolver {

	/**
	 * Creates the connection for post types
	 *
	 * @param mixed       $source  Results of the query calling this connection
	 * @param array       $args    Query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @return array
	 * @since  0.5.0
	 * @access public
	 */
	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {
		$post_types = get_post_types( [], 'objects' );

		return Relay::connectionFromArray( $post_types, $args );
	}

}
