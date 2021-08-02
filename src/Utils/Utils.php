<?php

namespace WPGraphQL\Utils;

use WPGraphQL\Model\Model;

class Utils {

	/**
	 * Maps new input query args and sanitizes the input
	 *
	 * @param mixed|array|string $args The raw query args from the GraphQL query
	 * @param mixed|array|string $map  The mapping of where each of the args should go
	 *
	 * @return array
	 * @since  0.5.0
	 */
	public static function map_input( $args, $map ) {

		if ( ! is_array( $args ) || ! is_array( $map ) ) {
			return [];
		}

		$query_args = [];

		foreach ( $args as $arg => $value ) {

			if ( is_array( $value ) && ! empty( $value ) ) {
				$value = array_map(
					function ( $value ) {
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
	 * @param string $date_gmt GMT publication time.
	 * @param mixed|string|null $date Optional. Local publication time. Default null.
	 *
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 * @since 4.7.0
	 */
	public static function prepare_date_response( string $date_gmt, $date = null ) {
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
	public static function format_field_name( string $field_name ) {

		$replaced = preg_replace( '[^a-zA-Z0-9 -]', '_', $field_name );

		// If any values were replaced, use the replaced string as the new field name
		if ( ! empty( $replaced ) ) {
			$field_name = $replaced;
		}

		$field_name = lcfirst( $field_name );
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

	/**
	 * Helper function that defines the allowed HTML to use on the Settings pages
	 *
	 * @return array
	 */
	public static function get_allowed_wp_kses_html() {
		$allowed_atts = [
			'align'      => [],
			'class'      => [],
			'type'       => [],
			'id'         => [],
			'dir'        => [],
			'lang'       => [],
			'style'      => [],
			'xml:lang'   => [],
			'src'        => [],
			'alt'        => [],
			'href'       => [],
			'rel'        => [],
			'rev'        => [],
			'target'     => [],
			'novalidate' => [],
			'value'      => [],
			'name'       => [],
			'tabindex'   => [],
			'action'     => [],
			'method'     => [],
			'for'        => [],
			'width'      => [],
			'height'     => [],
			'data'       => [],
			'title'      => [],
			'checked'    => [],
			'disabled'   => [],
		];

		return [
			'form'     => $allowed_atts,
			'label'    => $allowed_atts,
			'input'    => $allowed_atts,
			'textarea' => $allowed_atts,
			'iframe'   => $allowed_atts,
			'script'   => $allowed_atts,
			'style'    => $allowed_atts,
			'strong'   => $allowed_atts,
			'small'    => $allowed_atts,
			'table'    => $allowed_atts,
			'span'     => $allowed_atts,
			'abbr'     => $allowed_atts,
			'code'     => $allowed_atts,
			'pre'      => $allowed_atts,
			'div'      => $allowed_atts,
			'img'      => $allowed_atts,
			'h1'       => $allowed_atts,
			'h2'       => $allowed_atts,
			'h3'       => $allowed_atts,
			'h4'       => $allowed_atts,
			'h5'       => $allowed_atts,
			'h6'       => $allowed_atts,
			'ol'       => $allowed_atts,
			'ul'       => $allowed_atts,
			'li'       => $allowed_atts,
			'em'       => $allowed_atts,
			'hr'       => $allowed_atts,
			'br'       => $allowed_atts,
			'tr'       => $allowed_atts,
			'td'       => $allowed_atts,
			'p'        => $allowed_atts,
			'a'        => $allowed_atts,
			'b'        => $allowed_atts,
			'i'        => $allowed_atts,
		];
	}
}
