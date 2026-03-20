<?php
/**
 * Store interface for URL→cache-key index
 *
 * @package WPGraphQL\PQC\Store
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Store;

/**
 * Interface StoreInterface
 *
 * @package WPGraphQL\PQC\Store
 */
interface StoreInterface {

	/**
	 * Store a URL and its associated query, variables, and cache keys
	 *
	 * @param string $url           The full URL (e.g., /graphql/persisted/{queryHash}/variables/{variablesHash}).
	 * @param string $query_hash    SHA-256 hash of the normalized query document.
	 * @param string $variables_hash SHA-256 hash of the canonicalized variables JSON.
	 * @param string $query_doc     The full GraphQL query document.
	 * @param string $variables     The variables JSON string.
	 * @param array  $cache_keys    Array of cache keys from X-GraphQL-Keys header.
	 * @return void
	 */
	public function store( string $url, string $query_hash, string $variables_hash, string $query_doc, string $variables, array $cache_keys ): void;

	/**
	 * Get query document and variables by query hash and variables hash
	 *
	 * @param string $query_hash    SHA-256 hash of the normalized query document.
	 * @param string $variables_hash SHA-256 hash of the canonicalized variables JSON.
	 * @return array|null Array with 'query_document' and 'variables' keys, or null if not found.
	 */
	public function get_query( string $query_hash, string $variables_hash ): ?array;

	/**
	 * Get all URLs tagged with a specific cache key
	 *
	 * @param string $cache_key The cache key (e.g., 'post:123', 'list:post').
	 * @return array Array of URLs.
	 */
	public function get_urls_for_key( string $cache_key ): array;

	/**
	 * Delete all index entries for a specific cache key
	 *
	 * @param string $cache_key The cache key to delete.
	 * @return void
	 */
	public function delete_by_key( string $cache_key ): void;

	/**
	 * Delete all index entries for a specific URL
	 *
	 * @param string $url The URL to delete all entries for.
	 * @return void
	 */
	public function delete_by_url( string $url ): void;
}
