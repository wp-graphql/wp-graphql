<?php

namespace WPGraphQL;

use WPGraphQL\Utils\Utils;

/**
 * @todo Remove in 3.0.0
 * @deprecated 0.6.0.
 * @codeCoverageIgnore
 */
class Types {
	/**
	 * @deprecated since v0.6.0. Use Utils:map_input instead
	 *
	 * @param mixed[] $args The raw query args from the GraphQL query.
	 * @param mixed[] $map  The mapping of where each of the args should go.
	 *
	 * @return array<string,mixed>
	 */
	public static function map_input( $args, $map ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				/* translators: %s is the class name */
				esc_html__( 'This method is deprecated and will be removed in the next major version of WPGraphQL. Use %s instead.', 'wp-graphql' ),
				esc_html( \WPGraphQL\Utils\Utils::class . '::map_input()' ),
			),
			'0.6.0'
		);
		return Utils::map_input( $args, $map );
	}

	/**
	 * @deprecated since v0.6.0 use Utils::prepare_date_response(); instead
	 * @param string      $date_gmt GMT publication time.
	 * @param string|null $date     Optional. Local publication time. Default null.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	public static function prepare_date_response( $date_gmt, $date = null ) {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
			/* translators: %s is the class name */
				esc_html__( 'This method is deprecated and will be removed in the next major version of WPGraphQL. Use %s instead.', 'wp-graphql' ),
				esc_html( \WPGraphQL\Utils\Utils::class . '::prepare_date_response()' ),
			),
			'0.6.0'
		);
		return Utils::prepare_date_response( $date_gmt, $date );
	}
}
