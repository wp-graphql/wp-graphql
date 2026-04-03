<?php
/**
 * Database store behavior (executions, documents, key map, purge side effects).
 *
 * @package WPGraphQL\PQC\Tests\WPUnit
 */

use WPGraphQL\PQC\Database\Schema;
use WPGraphQL\PQC\Store\StoreFactory;
use WPGraphQL\PQC\Utils\Hasher;

/**
 * Class DBStoreTest
 */
class DBStoreTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Schema::ensure_schema();
	}

	/**
	 * @return \WPGraphQL\PQC\Store\DBStore
	 */
	private function store(): \WPGraphQL\PQC\Store\DBStore {
		/** @var \WPGraphQL\PQC\Store\DBStore $store */
		$store = StoreFactory::get_store();

		return $store;
	}

	public function test_get_query_returns_null_when_missing(): void {
		$this->assertNull( $this->store()->get_query( str_repeat( 'a', 64 ), '' ) );
	}

	public function test_store_and_get_query_round_trip(): void {
		$query     = 'query { __typename }';
		$hash      = Hasher::hash_query( $query );
		$normalized = Hasher::normalize_query_document( $query );
		$this->assertNotNull( $hash );
		$this->assertNotNull( $normalized );

		$url = '/graphql/persisted/' . $hash;
		$this->store()->store( $url, $hash, '', $normalized, '', [], true, false );

		$row = $this->store()->get_query( $hash, '' );
		$this->assertIsArray( $row );
		$this->assertSame( $normalized, $row['query_document'] );
		$this->assertSame( '', $row['variables'] );
	}

	public function test_document_exists(): void {
		$query      = 'query { __typename }';
		$hash       = Hasher::hash_query( $query );
		$normalized = Hasher::normalize_query_document( $query );
		$this->assertNotNull( $hash );
		$this->assertNotNull( $normalized );

		$this->assertFalse( $this->store()->document_exists( $hash ) );
		$this->store()->store( '/graphql/persisted/' . $hash, $hash, '', $normalized, '', [], true, false );
		$this->assertTrue( $this->store()->document_exists( $hash ) );
	}

	public function test_get_urls_for_key_populated_when_cache_keys_stored(): void {
		$key = 'pqc_wpunit_url_key_' . wp_generate_password( 12, false, false );
		$query      = 'query { __typename }';
		$hash       = Hasher::hash_query( $query );
		$normalized = Hasher::normalize_query_document( $query );
		$this->assertNotNull( $hash );
		$this->assertNotNull( $normalized );

		$url = '/graphql/persisted/' . $hash;
		$this->store()->store( $url, $hash, '', $normalized, '', [ $key ], true, true );

		$urls = $this->store()->get_urls_for_key( $key );
		$this->assertContains( $url, $urls, '', true );
	}

	public function test_graphql_purge_removes_key_map_but_keeps_execution(): void {
		$key = 'pqc_wpunit_purge_' . wp_generate_password( 12, false, false );
		$query      = 'query { __typename }';
		$hash       = Hasher::hash_query( $query );
		$normalized = Hasher::normalize_query_document( $query );
		$this->assertNotNull( $hash );
		$this->assertNotNull( $normalized );

		$url = '/graphql/persisted/' . $hash;
		$this->store()->store( $url, $hash, '', $normalized, '', [ $key ], true, true );

		$this->assertNotEmpty( $this->store()->get_urls_for_key( $key ) );

		do_action( 'graphql_purge', $key, 'test_event', 'localhost' );

		$this->assertSame( [], $this->store()->get_urls_for_key( $key ) );
		$this->assertNotNull( $this->store()->get_query( $hash, '' ) );
	}
}
