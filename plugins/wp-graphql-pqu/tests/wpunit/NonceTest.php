<?php
/**
 * Tests for persisted-query registration nonces.
 *
 * @package WPGraphQL\PQU\Tests\WPUnit
 */

use WPGraphQL\PQU\Utils\Nonce;

/**
 * Class NonceTest
 */
class NonceTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Generated token is 64-char lowercase hex.
	 */
	public function test_generate_returns_hex64(): void {
		$nonce = Nonce::generate( str_repeat( 'b', 64 ), '' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $nonce );
	}

	/**
	 * validate() succeeds when query and variables match stored payload.
	 */
	public function test_validate_succeeds_when_hashes_match(): void {
		$qh = str_repeat( 'c', 64 );
		$vh = str_repeat( 'd', 64 );
		$nonce = Nonce::generate( $qh, $vh );
		$this->assertTrue( Nonce::validate( $nonce, $qh, $vh ) );
	}

	/**
	 * Empty nonce is invalid.
	 */
	public function test_validate_fails_for_empty_nonce(): void {
		$this->assertFalse( Nonce::validate( '', str_repeat( 'e', 64 ), null ) );
	}

	/**
	 * Wrong query hash does not validate.
	 */
	public function test_validate_fails_when_query_hash_mismatch(): void {
		$qh = str_repeat( 'f', 64 );
		$nonce = Nonce::generate( $qh, '' );
		$this->assertFalse( Nonce::validate( $nonce, str_repeat( '0', 64 ), null ) );
	}

	/**
	 * Variables hash must match when nonce was created with non-empty variables hash.
	 */
	public function test_validate_fails_when_variables_hash_mismatch(): void {
		$qh = str_repeat( '1', 64 );
		$vh = str_repeat( '2', 64 );
		$nonce = Nonce::generate( $qh, $vh );
		$this->assertFalse( Nonce::validate( $nonce, $qh, str_repeat( '3', 64 ) ) );
	}

	/**
	 * After mark_used(), validate() fails (one-time registration token).
	 */
	public function test_validate_fails_after_mark_used(): void {
		$qh    = str_repeat( '4', 64 );
		$nonce = Nonce::generate( $qh, '' );
		$this->assertTrue( Nonce::validate( $nonce, $qh, null ) );
		$this->assertTrue( Nonce::mark_used( $nonce ) );
		$this->assertFalse( Nonce::validate( $nonce, $qh, null ) );
	}
}
