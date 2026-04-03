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
	 * @param string   $url              The full URL (e.g., /graphql/persisted/{queryHash}/variables/{variablesHash}).
	 * @param string   $query_hash       SHA-256 hash of the normalized query document.
	 * @param string   $variables_hash   SHA-256 hash of the canonicalized variables JSON.
	 * @param string   $query_doc        The full GraphQL query document.
	 * @param string   $variables        The variables JSON string.
	 * @param string[] $cache_keys       Cache keys from X-GraphQL-Keys header.
	 * @param bool     $store_document    Whether to store the document (if it doesn't exist). Default true.
	 * @param bool     $record_cache_tags Whether to persist URL ↔ cache key associations for edge purge. Default true.
	 * @since next-version The `$record_cache_tags` parameter was added.
	 */
	public function store( string $url, string $query_hash, string $variables_hash, string $query_doc, string $variables, array $cache_keys, bool $store_document = true, bool $record_cache_tags = true ): void;

	/**
	 * Get query document and variables by query hash and variables hash
	 *
	 * @param string $query_hash       SHA-256 hash of the normalized query document.
	 * @param string $variables_hash   SHA-256 hash of the canonicalized variables JSON.
	 * @return array{query_document: string, variables: string}|null
	 */
	public function get_query( string $query_hash, string $variables_hash ): ?array;

	/**
	 * Update last successful warm-execution time (for garbage collection).
	 *
	 * @param string $query_hash       Query hash.
	 * @param string $variables_hash   Variables hash (empty string if none).
	 */
	public function touch_execution( string $query_hash, string $variables_hash ): void;

	/**
	 * Get all URLs tagged with a specific cache key
	 *
	 * @param string $cache_key The cache key (e.g., 'post:123', 'list:post').
	 * @return string[]
	 */
	public function get_urls_for_key( string $cache_key ): array;

	/**
	 * Get distinct persisted URLs for a query hash (all variable permutations that were tagged).
	 *
	 * @since next-version
	 * @param string $query_hash SHA-256 hash of the normalized query document.
	 * @return string[] URL paths.
	 */
	public function get_urls_for_query_hash( string $query_hash ): array;

	/**
	 * Delete all index entries for a specific cache key.
	 *
	 * Optional for custom invalidation; bundled PurgeHandler clears by URL after edge purge.
	 *
	 * @param string $cache_key The cache key to delete.
	 */
	public function delete_by_key( string $cache_key ): void;

	/**
	 * Delete all cache-key tag rows for a specific URL (key map only).
	 *
	 * Does not remove the execution record; warm GET continues to resolve until GC.
	 *
	 * @param string $url The persisted path.
	 */
	public function delete_by_url( string $url ): void;

	/**
	 * Check if a document exists for a given query hash
	 *
	 * @param string $query_hash SHA-256 hash of the normalized query document.
	 * @return bool True if document exists, false otherwise.
	 */
	public function document_exists( string $query_hash ): bool;
}
