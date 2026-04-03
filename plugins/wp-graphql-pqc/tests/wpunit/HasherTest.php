<?php
/**
 * Tests for PQC query/variable hashing (SPEC alignment).
 *
 * @package WPGraphQL\PQC\Tests\WPUnit
 */

use WPGraphQL\PQC\Utils\Hasher;

/**
 * Class HasherTest
 */
class HasherTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Hash is stable 64-char hex for a valid document.
	 */
	public function test_hash_query_is_deterministic_lowercase_hex64(): void {
		$query = 'query { __typename }';
		$h1    = Hasher::hash_query( $query );
		$h2    = Hasher::hash_query( $query );
		$this->assertNotNull( $h1 );
		$this->assertSame( $h1, $h2 );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $h1 );
	}

	/**
	 * Parser normalization: different whitespace, same hash.
	 */
	public function test_hash_query_normalizes_whitespace(): void {
		$a = 'query { __typename }';
		$b = "query {\n  __typename\n}";
		$this->assertSame( Hasher::hash_query( $a ), Hasher::hash_query( $b ) );
	}

	/**
	 * Invalid GraphQL returns null hash.
	 */
	public function test_hash_query_invalid_returns_null(): void {
		$this->assertNull( Hasher::hash_query( 'not graphql {{{' ) );
	}

	/**
	 * SPEC: empty / null variables do not produce a variables hash.
	 */
	public function test_hash_variables_null_and_empty_array(): void {
		$this->assertNull( Hasher::hash_variables( null ) );
		$this->assertNull( Hasher::hash_variables( [] ) );
	}

	/**
	 * Variables JSON key order does not change hash.
	 */
	public function test_hash_variables_key_order_insensitive(): void {
		$h1 = Hasher::hash_variables( [ 'a' => 1, 'b' => 2 ] );
		$h2 = Hasher::hash_variables( [ 'b' => 2, 'a' => 1 ] );
		$this->assertNotNull( $h1 );
		$this->assertSame( $h1, $h2 );
	}
}
