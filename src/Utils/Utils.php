<?php

namespace WPGraphQL\Utils;

use GraphQLRelay\Relay;

class Utils {

	/**
	 * Given a GraphQL Query string, return a hash
	 *
	 * @param string $query The Query String to hash
	 *
	 * @return string|null
	 */
	public static function get_query_id( string $query ) {

		/**
		 * Filter the hash algorithm to allow different algorithms.
		 *
		 * @string $algorithm Default is sha256. Possible values are those that work with the PHP hash() function. See: https://www.php.net/manual/en/function.hash-algos.php
		 */
		$hash_algorithm = apply_filters( 'graphql_query_id_hash_algorithm', 'sha256' );

		try {
			$query_ast = \GraphQL\Language\Parser::parse( $query );
			$query     = \GraphQL\Language\Printer::doPrint( $query_ast );
			return hash( $hash_algorithm, $query );
		} catch ( \Throwable $exception ) {
			return null;
		}
	}

	/**
	 * Maps new input query args and sanitizes the input
	 *
	 * @param mixed|array|string $args The raw query args from the GraphQL query
	 * @param mixed|array|string $map  The mapping of where each of the args should go
	 * @param string[]           $skip Fields to skipped and not be added to the output array.
	 *
	 * @return array
	 * @since  0.5.0
	 */
	public static function map_input( $args, $map, $skip = [] ) {
		if ( ! is_array( $args ) || ! is_array( $map ) ) {
			return [];
		}

		$query_args = [];

		foreach ( $args as $arg => $value ) {
			if ( [] !== $skip && in_array( $arg, $skip, true ) ) {
				continue;
			}

			if ( is_array( $value ) && ! empty( $value ) ) {
				$value = array_map(
					static function ( $value ) {
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
	 * Format a GraphQL name according to the GraphQL spec.
	 *
	 * Per the GraphQL spec, characters in names are limited to Latin ASCII letter, digits, or underscores.
	 *
	 * @see http://spec.graphql.org/draft/#sec-Names
	 *
	 * @param string $name The name to format.
	 * @param string $replacement The replacement character for invalid characters. Defaults to '_'.
	 * @param string $regex The regex to use to match invalid characters. Defaults to '/[^A-Za-z0-9_]/i'.
	 *
	 * @since v1.17.0
	 */
	public static function format_graphql_name( string $name, string $replacement = '_', string $regex = '/[^A-Za-z0-9_]/i' ): string {
		if ( empty( $name ) ) {
			return '';
		}

		/**
		 * Filter to manually format a GraphQL name according to custom rules.
		 *
		 * If anything other than null is returned, the result will be used for the name instead of the standard regex.
		 *
		 * Useful for providing custom transliteration rules that will convert non ASCII characters to ASCII.
		 *
		 * @param null|string $formatted_name The name to format. If not null, the result will be returned as the formatted name.
		 * @param string $original_name       The name to format.
		 * @param string $replacement         The replacement character for invalid characters. Defaults to '_'.
		 * @param string $regex               The regex to use to match invalid characters. Defaults to '/[^A-Za-z0-9_]/i'.
		 *
		 * @return string|null
		 */
		$pre_format_name = apply_filters( 'graphql_pre_format_name', null, $name, $replacement, $regex );

		// Check whether the filter is being used (correctly).
		if ( ! empty( $pre_format_name ) && is_string( $pre_format_name ) ) {
			// Don't trust the filter to return a formatted string.
			$name = trim( sanitize_text_field( $pre_format_name ) );
		} else {
			// Throw a warning if someone is using the filter incorrectly.
			if ( null !== $pre_format_name ) {
				graphql_debug(
					esc_html__( 'The `graphql_pre_format_name` filter must return a string or null.', 'wp-graphql' ),
					[
						'type'          => 'INVALID_GRAPHQL_NAME',
						'original_name' => esc_html( $name ),
					]
				);
			}

			// Remove all non-alphanumeric characters.
			$name = preg_replace( $regex, $replacement, $name );
		}

		if ( empty( $name ) ) {
			return '';
		}

		// Replace multiple consecutive leading underscores with a single underscore, since those are reserved.
		$name = preg_replace( '/^_+/', '_', trim( $name ) );

		return ! empty( $name ) ? $name : '';
	}

	/**
	 * Given a field name, formats it for GraphQL
	 *
	 * @param string $field_name         The field name to format
	 * @param bool   $allow_underscores  Whether the field should be formatted with underscores allowed. Default false.
	 *
	 * @return string
	 */
	public static function format_field_name( string $field_name, bool $allow_underscores = false ): string {
		// Bail if empty.
		if ( empty( $field_name ) ) {
			return '';
		}

		$formatted_field_name = graphql_format_name( $field_name, '_', '/[^a-zA-Z0-9 -]/' );

		// If the formatted name is empty, we want to return the original value so it displays in the error.
		if ( empty( $formatted_field_name ) ) {
			return $field_name;
		}

		// underscores are allowed by GraphQL, but WPGraphQL has historically
		// stripped them when formatting field names.
		// The $allow_underscores argument allows functions to opt-in to allowing underscores
		if ( true !== $allow_underscores ) {
			// uppercase words separated by an underscore, then replace the underscores with a space
			$formatted_field_name = lcfirst( str_replace( '_', ' ', ucwords( $formatted_field_name, '_' ) ) );
		}

		// uppercase words separated by a dash, then replace the dashes with a space
		$formatted_field_name = lcfirst( str_replace( '-', ' ', ucwords( $formatted_field_name, '-' ) ) );

		// uppercace words separated by a space, and replace spaces with no space
		$formatted_field_name = lcfirst( str_replace( ' ', '', ucwords( $formatted_field_name, ' ' ) ) );

		// Field names should be lcfirst.
		return lcfirst( $formatted_field_name );
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
	 * Returns a GraphQL type name for a given WordPress template name.
	 *
	 * If the template name has no ASCII characters, the file name will be used instead.
	 *
	 * @param string $name The template name.
	 * @param string $file The file name.
	 * @return string The formatted type name. If the name is empty, an empty string will be returned.
	 */
	public static function format_type_name_for_wp_template( string $name, string $file ): string {
		$name = ucwords( $name );
		// Strip out not ASCII characters.
		$name = graphql_format_name( $name, '', '/[^\w]/' );

		// If replaced_name is empty, use the file name.
		if ( empty( $name ) ) {
			$file_parts    = explode( '.', $file );
			$file_name     = ! empty( $file_parts[0] ) ? self::format_type_name( $file_parts[0] ) : '';
			$replaced_name = ! empty( $file_name ) ? graphql_format_name( $file_name, '', '/[^\w]/' ) : '';

			$name = ! empty( $replaced_name ) ? $replaced_name : $name;
		}

		// If the name is still empty, we don't have a valid type.
		if ( empty( $name ) ) {
			return '';
		}

		// Maybe prefix the name with "Template_".
		if ( preg_match( '/^\d/', $name ) || false === strpos( strtolower( $name ), 'template' ) ) {
			$name = 'Template_' . $name;
		}

		return $name;
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
			'selected'   => [],
		];

		return [
			'form'     => $allowed_atts,
			'label'    => $allowed_atts,
			'input'    => $allowed_atts,
			'textarea' => $allowed_atts,
			'iframe'   => $allowed_atts,
			'script'   => $allowed_atts,
			'select'   => $allowed_atts,
			'option'   => $allowed_atts,
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

	/**
	 * Helper function to get the WordPress database ID from a GraphQL ID type input.
	 *
	 * Returns false if not a valid ID.
	 *
	 * @param int|string $id The ID from the input args. Can be either the database ID (as either a string or int) or the global Relay ID.
	 *
	 * @return int|false
	 */
	public static function get_database_id_from_id( $id ) {
		// If we already have the database ID, send it back as an integer.
		if ( is_numeric( $id ) ) {
			return absint( $id );
		}

		$id_parts = Relay::fromGlobalId( $id );

		return ! empty( $id_parts['id'] ) && is_numeric( $id_parts['id'] ) ? absint( $id_parts['id'] ) : false;
	}

	/**
	 * Get the node type from the ID
	 *
	 * @param int|string $id The encoded Node ID.
	 *
	 * @return bool|null
	 */
	public static function get_node_type_from_id( $id ) {
		if ( is_numeric( $id ) ) {
			return null;
		}

		$id_parts = Relay::fromGlobalId( $id );
		return $id_parts['type'] ?: null;
	}
}
