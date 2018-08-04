<?php

namespace WPGraphQL\Type\Widget\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Type\TermObject\Connection\TermObjectConnectionResolver;

/**
 * Class WidgetConnectionResolver
 *
 * @package WPGraphQL\Type\Widget\Connection
 * @since   0.0.31
 */
class WidgetConnectionResolver extends TermObjectConnectionResolver {

	/**
	 * Return the term args to be used when getting the term object.
	 *
	 * @param mixed       $source  The query source being passed down to the resolver
	 * @param array       $args    The arguments that were provided to the query
	 * @param AppContext  $context Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info    Info about fields passed down the resolve tree
	 *
	 * @return array
	 * @throws \Exception
	 * @since  0.0.31
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$term_args = [

		];

		return $term_args;
	}

}