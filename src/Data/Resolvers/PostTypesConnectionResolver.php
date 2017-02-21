<?php
namespace WPGraphQL\Data\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

class PostTypesConnectionResolver {

	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {
		$post_types = get_post_types( [], 'objects' );

		return Relay::connectionFromArray( $post_types, $args );
	}

}
