<?php

namespace WPGraphQL\Type\Widget;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class WidgetQuery
 *
 * @package WPGraphQL\Type\Widget
 * @since   0.0.31
 */
class WidgetQuery {
  /**
	 * Holds the root_query field definition
	 *
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Method that returns the root query field definition
	 *
	 * @return array
	 * @since  0.0.31
	 */
	public static function root_query() {
    if ( null === self::$root_query ) {

			self::$root_query = [
				
			];

		}

		return self::$root_query;
  }
}