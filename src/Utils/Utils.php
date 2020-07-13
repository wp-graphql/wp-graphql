<?php
namespace WPGraphQL\Utils;

class Utils {

	/**
	 * Maps new input query args and sanitizes the input
	 *
	 * @param array $args The raw query args from the GraphQL query
	 * @param array $map  The mapping of where each of the args should go
	 *
	 * @since  0.5.0
	 * @return array
	 */
	public static function map_input( $args, $map ) {

		if ( ! is_array( $args ) || ! is_array( $map ) ) {
			return [];
		}

		$query_args = [];

		foreach ( $args as $arg => $value ) {

			if ( is_array( $value ) && ! empty( $value ) ) {
				$value = array_map(
					function( $value ) {
						if ( is_string( $value ) ) {
							$value = sanitize_text_field( $value );
						}

						return $value;
					},
					$value
				);
			} elseif ( is_string( $value ) ) {
				$value = sanitize_text_field( $value );
			}

			if ( array_key_exists( $arg, $map ) ) {
				$query_args[ $map[ $arg ] ] = $value;
			} else {
				$query_args[ $arg ] = $value;
			}
		}

		return $query_args;

	}

	/**
	 * Checks the post_date_gmt or modified_gmt and prepare any post or
	 * modified date for single post output.
	 *
	 * @since 4.7.0
	 *
	 * @param string      $date_gmt GMT publication time.
	 * @param string|null $date     Optional. Local publication time. Default null.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	public static function prepare_date_response( $date_gmt, $date = null ) {
		// Use the date if passed.
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}
		// Return null if $date_gmt is empty/zeros.
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}
		// Return the formatted datetime.
		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Given a field name, formats it for GraphQL
	 *
	 * @param string $field_name The field name to format
	 *
	 * @return string
	 */
	public static function format_field_name( $field_name ) {
		$field_name = lcfirst( preg_replace( '[^a-zA-Z0-9 -]', '_', $field_name ) );
		$field_name = lcfirst( str_replace( '_', ' ', ucwords( $field_name, '_' ) ) );
		$field_name = lcfirst( str_replace( '-', ' ', ucwords( $field_name, '_' ) ) );
		$field_name = lcfirst( str_replace( ' ', '', ucwords( $field_name, ' ' ) ) );
		return $field_name;
	}

	/**
	 * Given a type name, formats it for GraphQL
	 *
	 * @param string $type_name The type name to format
	 *
	 * @return string
	 */
	public static function format_type_name( $type_name ) {
		return ucfirst( self::format_field_name( $type_name ) );
	}

}
