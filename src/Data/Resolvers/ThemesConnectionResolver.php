<?php
namespace WPGraphQL\Data\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

class ThemesConnectionResolver {

	public static function resolve( $source, array $args, $context, ResolveInfo $info ) {
		$themes = wp_get_themes();

		return Relay::connectionFromArray( $themes, $args );
	}

}
