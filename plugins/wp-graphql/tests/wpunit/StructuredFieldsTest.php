<?php

use WPGraphQL\Utils\StructuredFields;

/**
 * Tests for the RFC 8941 Structured Field Dictionary parser.
 *
 * Cases follow the parsing algorithms of RFC 8941 Section 4.2 and are modeled on the
 * official test vectors (https://github.com/httpwg/structured-field-tests): a compliant
 * serializer's output must parse, and any parse error must discard the entire field
 * value (null), never a partial result.
 */
class StructuredFieldsTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @dataProvider validDictionaryProvider
	 *
	 * @param string $input    The raw field value.
	 * @param array  $expected The expected parse result.
	 */
	public function testValidDictionariesParse( string $input, array $expected ) {
		$this->assertSame( $expected, StructuredFields::parse_dictionary( $input ) );
	}

	public function validDictionaryProvider() {
		return [
			'empty value is an empty dictionary'      => [ '', [] ],
			'single integer member'                   => [
				'database_id=123',
				[ 'database_id' => [ 'type' => 'integer', 'value' => 123 ] ],
			],
			'multiple members with spaces'            => [
				'a=1, b=2',
				[
					'a' => [ 'type' => 'integer', 'value' => 1 ],
					'b' => [ 'type' => 'integer', 'value' => 2 ],
				],
			],
			'duplicate keys: last member wins'        => [
				'a=1, a=2',
				[ 'a' => [ 'type' => 'integer', 'value' => 2 ] ],
			],
			'string containing a comma'               => [
				'nonce="a,b"',
				[ 'nonce' => [ 'type' => 'string', 'value' => 'a,b' ] ],
			],
			'string escapes for DQUOTE and backslash' => [
				'v="a\\"b\\\\c"',
				[ 'v' => [ 'type' => 'string', 'value' => 'a"b\\c' ] ],
			],
			'empty string'                            => [
				'v=""',
				[ 'v' => [ 'type' => 'string', 'value' => '' ] ],
			],
			'parameters are parsed and discarded'     => [
				'a=1;x=y;z, b="q";p=1',
				[
					'a' => [ 'type' => 'integer', 'value' => 1 ],
					'b' => [ 'type' => 'string', 'value' => 'q' ],
				],
			],
			'valueless member is boolean true'        => [
				'flag',
				[ 'flag' => [ 'type' => 'boolean', 'value' => true ] ],
			],
			'explicit booleans'                       => [
				'a=?1, b=?0',
				[
					'a' => [ 'type' => 'boolean', 'value' => true ],
					'b' => [ 'type' => 'boolean', 'value' => false ],
				],
			],
			'token member'                            => [
				't=abc:def/ghi',
				[ 't' => [ 'type' => 'token', 'value' => 'abc:def/ghi' ] ],
			],
			'decimal member'                          => [
				'd=1.25',
				[ 'd' => [ 'type' => 'decimal', 'value' => 1.25 ] ],
			],
			'negative integer'                        => [
				'n=-42',
				[ 'n' => [ 'type' => 'integer', 'value' => -42 ] ],
			],
			'byte sequence'                           => [
				'b=:aGVsbG8=:',
				[ 'b' => [ 'type' => 'byte-sequence', 'value' => 'hello' ] ],
			],
			'inner list with parameters'              => [
				'l=(1 2 "three");p=1',
				[
					'l' => [
						'type'  => 'inner-list',
						'value' => [
							[ 'type' => 'integer', 'value' => 1 ],
							[ 'type' => 'integer', 'value' => 2 ],
							[ 'type' => 'string', 'value' => 'three' ],
						],
					],
				],
			],
			'empty inner list'                        => [
				'l=()',
				[
					'l' => [
						'type'  => 'inner-list',
						'value' => [],
					],
				],
			],
			'key charset'                             => [
				'*key-1.a_b=1',
				[ '*key-1.a_b' => [ 'type' => 'integer', 'value' => 1 ] ],
			],
			'leading and trailing spaces'             => [
				'  a=1  ',
				[ 'a' => [ 'type' => 'integer', 'value' => 1 ] ],
			],
			'fifteen digit integer'                   => [
				'a=123456789012345',
				[ 'a' => [ 'type' => 'integer', 'value' => 123456789012345 ] ],
			],
		];
	}

	/**
	 * @dataProvider invalidDictionaryProvider
	 *
	 * @param string $input The raw field value.
	 */
	public function testParseErrorsDiscardTheEntireFieldValue( string $input ) {
		$this->assertNull( StructuredFields::parse_dictionary( $input ) );
	}

	public function invalidDictionaryProvider() {
		return [
			'trailing comma'                   => [ 'a=1,' ],
			'unterminated string'              => [ 's="abc' ],
			'invalid string escape'            => [ 's="a\\nb"' ],
			'control character in string'      => [ "s=\"a\x07b\"" ],
			'uppercase key'                    => [ 'A=1' ],
			'key starting with a digit'        => [ '1a=1' ],
			'missing comma between members'    => [ 'a=1 b=2' ],
			'sixteen digit integer'            => [ 'a=1234567890123456' ],
			'decimal with four fraction chars' => [ 'd=1.1234' ],
			'decimal with no fraction'         => [ 'd=1.' ],
			'invalid base64 byte sequence'     => [ 'b=:a!b:' ],
			'unterminated byte sequence'       => [ 'b=:aGVsbG8=' ],
			'invalid boolean'                  => [ 'f=?2' ],
			'unterminated inner list'          => [ 'l=(1 2' ],
			'unknown bare item type'           => [ 'x=@123' ],
			'member with missing value'        => [ 'a=' ],
			'lone comma'                       => [ ',' ],
		];
	}
}
