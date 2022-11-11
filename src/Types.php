<?php

namespace WPGraphQL;

use WPGraphQL\Utils\Utils;

/**
 * This class was used to access Type definitions pre v0.4.0, but is no longer used.
 * See upgrade guide vor v0.4.0 (https://github.com/wp-graphql/wp-graphql/releases/tag/v0.4.0) for
 * information on updating to use non-static TypeRegistry methods to get_type(), etc.
 *
 * @deprecated since v0.6.0. Old static methods can now be done by accessing the
 *             TypeRegistry class from within the `graphql_register_types` hook
 */
class Types {

	/**
	 * @deprecated since v0.6.0. Use Utils:map_input instead
	 *
	 * @param array $args The raw query args from the GraphQL query.
	 * @param array $map  The mapping of where each of the args should go.
	 *
	 * @return array
	 */
	public static function map_input( $args, $map ) {
		_deprecated_function( __METHOD__, '0.6.0', 'WPGraphQL\Utils\Utils::map_input()' );
		return Utils::map_input( $args, $map );
	}

	/**
	 * @deprecated since v0.6.0 use Utils::prepare_date_response(); instead
	 * @param string      $date_gmt GMT publication time.
	 * @param string|null $date     Optional. Local publication time. Default null.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	public static function prepare_date_response( $date_gmt, $date = null ) {
		_deprecated_function( __METHOD__, '0.6.0', 'WPGraphQL\Utils\Utils::prepare_date_response()' );
		return Utils::prepare_date_response( $date_gmt, $date );
	}

}
