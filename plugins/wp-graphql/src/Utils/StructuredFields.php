<?php

namespace WPGraphQL\Utils;

/**
 * A parser for RFC 8941 Structured Field Dictionaries.
 *
 * Implements the parsing algorithms of RFC 8941 (Structured Field Values for HTTP),
 * Section 4.2, for the Dictionary top-level type. Items of every RFC 8941 type
 * (Integer, Decimal, String, Token, Byte Sequence, Boolean), Inner Lists, and
 * Parameters are all recognized syntactically, so the output of any compliant
 * serializer parses. Consumers receive each member's bare value and type and apply
 * their own profile on top (the preview context, for example, accepts Integer and
 * String members and ignores the rest); parameters are parsed and discarded.
 *
 * As RFC 8941 Section 4.2 requires, a parse error discards the entire field value:
 * parse_dictionary() returns null and the consumer proceeds as if the header were
 * absent.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8941
 *
 * @internal Plumbing for WPGraphQL's header parsing, not public API.
 *
 * @phpstan-type StructuredFieldMember array{type:string,value:mixed}
 */
class StructuredFields {

	/**
	 * Parses a Structured Field Dictionary (RFC 8941 Section 4.2.2).
	 *
	 * @param string $field_value The raw field value.
	 *
	 * @return array<string,array{type:string,value:mixed}>|null Map of member key to
	 *         ['type' => one of integer|decimal|string|token|byte-sequence|boolean|inner-list,
	 *         'value' => the bare value], or null when parsing fails.
	 */
	public static function parse_dictionary( string $field_value ): ?array {
		$input = $field_value;
		$pos   = 0;
		$end   = strlen( $input );

		self::discard_sp( $input, $pos, $end );

		$dictionary = [];

		while ( $pos < $end ) {
			$key = self::parse_key( $input, $pos, $end );

			if ( null === $key ) {
				return null;
			}

			if ( $pos < $end && '=' === $input[ $pos ] ) {
				++$pos;
				$member = self::parse_item_or_inner_list( $input, $pos, $end );
			} else {
				// A member without a value is Boolean true; it may still carry parameters.
				$member = [
					'type'  => 'boolean',
					'value' => true,
				];
				if ( ! self::parse_parameters( $input, $pos, $end ) ) {
					$member = null;
				}
			}

			if ( null === $member ) {
				return null;
			}

			// Duplicate keys: the last member wins (RFC 8941 Section 4.2.2).
			$dictionary[ $key ] = $member;

			self::discard_sp( $input, $pos, $end );

			if ( $pos >= $end ) {
				return $dictionary;
			}

			if ( ',' !== $input[ $pos ] ) {
				return null;
			}

			++$pos;
			self::discard_sp( $input, $pos, $end );

			// A trailing comma is a parse error.
			if ( $pos >= $end ) {
				return null;
			}
		}

		return $dictionary;
	}

	/**
	 * Discards leading SP characters (RFC 8941 uses SP only, not OWS-with-HTAB).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position, advanced past any SP.
	 * @param int    $end   The field value length.
	 */
	private static function discard_sp( string $input, int &$pos, int $end ): void {
		while ( $pos < $end && ' ' === $input[ $pos ] ) {
			++$pos;
		}
	}

	/**
	 * Parses a key (RFC 8941 Section 4.2.3.3).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return ?string The key, or null when parsing fails.
	 */
	private static function parse_key( string $input, int &$pos, int $end ): ?string {
		if ( $pos >= $end ) {
			return null;
		}

		$char = $input[ $pos ];

		// The first character must be lcalpha or "*".
		if ( '*' !== $char && ( $char < 'a' || $char > 'z' ) ) {
			return null;
		}

		$key = '';
		while ( $pos < $end ) {
			$char = $input[ $pos ];
			if ( ( $char >= 'a' && $char <= 'z' ) || ( $char >= '0' && $char <= '9' ) || '_' === $char || '-' === $char || '.' === $char || '*' === $char ) {
				$key .= $char;
				++$pos;
				continue;
			}
			break;
		}

		return $key;
	}

	/**
	 * Parses an Item or Inner List member, discarding its parameters
	 * (RFC 8941 Sections 4.2.1.1 and 4.2.3).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The member, or null when parsing fails.
	 */
	private static function parse_item_or_inner_list( string $input, int &$pos, int $end ): ?array {
		if ( $pos < $end && '(' === $input[ $pos ] ) {
			return self::parse_inner_list( $input, $pos, $end );
		}

		return self::parse_item( $input, $pos, $end );
	}

	/**
	 * Parses an Inner List (RFC 8941 Section 4.2.1.2), discarding parameters.
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The inner list, or null when parsing fails.
	 */
	private static function parse_inner_list( string $input, int &$pos, int $end ): ?array {
		// Consume the opening "(".
		++$pos;

		$items = [];

		while ( $pos < $end ) {
			self::discard_sp( $input, $pos, $end );

			if ( $pos < $end && ')' === $input[ $pos ] ) {
				++$pos;
				if ( ! self::parse_parameters( $input, $pos, $end ) ) {
					return null;
				}

				return [
					'type'  => 'inner-list',
					'value' => $items,
				];
			}

			$item = self::parse_item( $input, $pos, $end );

			if ( null === $item ) {
				return null;
			}

			$items[] = $item;

			// Each item must be followed by SP or the closing ")".
			if ( $pos >= $end || ( ' ' !== $input[ $pos ] && ')' !== $input[ $pos ] ) ) {
				return null;
			}
		}

		// The end of the inner list was never found.
		return null;
	}

	/**
	 * Parses an Item: a bare item plus parameters, which are discarded
	 * (RFC 8941 Section 4.2.3).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The item, or null when parsing fails.
	 */
	private static function parse_item( string $input, int &$pos, int $end ): ?array {
		$bare_item = self::parse_bare_item( $input, $pos, $end );

		if ( null === $bare_item ) {
			return null;
		}

		if ( ! self::parse_parameters( $input, $pos, $end ) ) {
			return null;
		}

		return $bare_item;
	}

	/**
	 * Parses a bare item, dispatching on its first character
	 * (RFC 8941 Section 4.2.3.1).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The bare item, or null when parsing fails.
	 */
	private static function parse_bare_item( string $input, int &$pos, int $end ): ?array {
		if ( $pos >= $end ) {
			return null;
		}

		$char = $input[ $pos ];

		if ( '-' === $char || ( $char >= '0' && $char <= '9' ) ) {
			return self::parse_number( $input, $pos, $end );
		}

		if ( '"' === $char ) {
			return self::parse_string( $input, $pos, $end );
		}

		if ( '*' === $char || ( $char >= 'a' && $char <= 'z' ) || ( $char >= 'A' && $char <= 'Z' ) ) {
			return self::parse_token( $input, $pos, $end );
		}

		if ( ':' === $char ) {
			return self::parse_byte_sequence( $input, $pos, $end );
		}

		if ( '?' === $char ) {
			return self::parse_boolean( $input, $pos, $end );
		}

		return null;
	}

	/**
	 * Parses an Integer or Decimal (RFC 8941 Section 4.2.4).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The number, or null when parsing fails.
	 */
	private static function parse_number( string $input, int &$pos, int $end ): ?array {
		$sign = 1;

		if ( '-' === $input[ $pos ] ) {
			$sign = -1;
			++$pos;
		}

		if ( $pos >= $end || $input[ $pos ] < '0' || $input[ $pos ] > '9' ) {
			return null;
		}

		$digits     = '';
		$is_decimal = false;

		while ( $pos < $end ) {
			$char = $input[ $pos ];

			if ( $char >= '0' && $char <= '9' ) {
				$digits .= $char;
				++$pos;
			} elseif ( ! $is_decimal && '.' === $char ) {
				// The integer part of a decimal is at most 12 digits.
				if ( strlen( $digits ) > 12 ) {
					return null;
				}
				$digits    .= '.';
				$is_decimal = true;
				++$pos;
			} else {
				break;
			}

			// Integers are at most 15 digits; decimals at most 16 characters including the dot.
			if ( ( ! $is_decimal && strlen( $digits ) > 15 ) || ( $is_decimal && strlen( $digits ) > 16 ) ) {
				return null;
			}
		}

		if ( ! $is_decimal ) {
			return [
				'type'  => 'integer',
				'value' => $sign * (int) $digits,
			];
		}

		$fraction = substr( $digits, (int) strpos( $digits, '.' ) + 1 );

		// A decimal must have 1-3 fractional digits.
		if ( '' === $fraction || strlen( $fraction ) > 3 ) {
			return null;
		}

		return [
			'type'  => 'decimal',
			'value' => $sign * (float) $digits,
		];
	}

	/**
	 * Parses a String (RFC 8941 Section 4.2.5): printable ASCII in DQUOTEs, with
	 * backslash escapes for DQUOTE and backslash only.
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The string, or null when parsing fails.
	 */
	private static function parse_string( string $input, int &$pos, int $end ): ?array {
		// Consume the opening DQUOTE.
		++$pos;

		$value = '';

		while ( $pos < $end ) {
			$char = $input[ $pos ];
			++$pos;

			if ( '\\' === $char ) {
				if ( $pos >= $end ) {
					return null;
				}

				$escaped = $input[ $pos ];
				++$pos;

				if ( '"' !== $escaped && '\\' !== $escaped ) {
					return null;
				}

				$value .= $escaped;
				continue;
			}

			if ( '"' === $char ) {
				return [
					'type'  => 'string',
					'value' => $value,
				];
			}

			// Only printable ASCII (%x20-7E) is allowed.
			if ( $char < ' ' || $char > '~' ) {
				return null;
			}

			$value .= $char;
		}

		// The closing DQUOTE was never found.
		return null;
	}

	/**
	 * Parses a Token (RFC 8941 Section 4.2.6).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed} The token. The first character was already
	 *         validated by the bare-item dispatch, so parsing cannot fail.
	 */
	private static function parse_token( string $input, int &$pos, int $end ): array {
		$value = '';

		while ( $pos < $end ) {
			$char = $input[ $pos ];

			// tchar (RFC 7230) plus ":" and "/".
			if (
				( $char >= 'a' && $char <= 'z' ) || ( $char >= 'A' && $char <= 'Z' ) || ( $char >= '0' && $char <= '9' )
				|| false !== strpos( "!#$%&'*+-.^_`|~:/", $char )
			) {
				$value .= $char;
				++$pos;
				continue;
			}

			break;
		}

		return [
			'type'  => 'token',
			'value' => $value,
		];
	}

	/**
	 * Parses a Byte Sequence (RFC 8941 Section 4.2.7): base64 between colons.
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The decoded bytes, or null when parsing fails.
	 */
	private static function parse_byte_sequence( string $input, int &$pos, int $end ): ?array {
		// Consume the opening ":".
		++$pos;

		$b64 = '';

		while ( $pos < $end ) {
			$char = $input[ $pos ];
			++$pos;

			if ( ':' === $char ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- RFC 8941 defines Byte Sequences as base64; strict decoding is the parse-validity check.
				$decoded = base64_decode( $b64, true );

				if ( false === $decoded ) {
					return null;
				}

				return [
					'type'  => 'byte-sequence',
					'value' => $decoded,
				];
			}

			if ( ! ( ( $char >= 'a' && $char <= 'z' ) || ( $char >= 'A' && $char <= 'Z' ) || ( $char >= '0' && $char <= '9' ) || '+' === $char || '/' === $char || '=' === $char ) ) {
				return null;
			}

			$b64 .= $char;
		}

		// The closing ":" was never found.
		return null;
	}

	/**
	 * Parses a Boolean (RFC 8941 Section 4.2.8).
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return array{type:string,value:mixed}|null The boolean, or null when parsing fails.
	 */
	private static function parse_boolean( string $input, int &$pos, int $end ): ?array {
		// Consume the "?".
		++$pos;

		if ( $pos < $end && ( '0' === $input[ $pos ] || '1' === $input[ $pos ] ) ) {
			$value = '1' === $input[ $pos ];
			++$pos;

			return [
				'type'  => 'boolean',
				'value' => $value,
			];
		}

		return null;
	}

	/**
	 * Parses Parameters (RFC 8941 Section 4.2.3.2) and discards them: no current
	 * consumer uses parameters, but they must be parsed for the member (and anything
	 * after it) to be read correctly.
	 *
	 * @param string $input The field value.
	 * @param int    $pos   The current parse position.
	 * @param int    $end   The field value length.
	 *
	 * @return bool Whether the parameters (if any) parsed successfully.
	 */
	private static function parse_parameters( string $input, int &$pos, int $end ): bool {
		while ( $pos < $end && ';' === $input[ $pos ] ) {
			++$pos;
			self::discard_sp( $input, $pos, $end );

			$key = self::parse_key( $input, $pos, $end );

			if ( null === $key ) {
				return false;
			}

			if ( $pos < $end && '=' === $input[ $pos ] ) {
				++$pos;

				if ( null === self::parse_bare_item( $input, $pos, $end ) ) {
					return false;
				}
			}
		}

		return true;
	}
}
