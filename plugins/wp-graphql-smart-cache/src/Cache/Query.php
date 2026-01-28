<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\SmartCache\Cache;

use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Storage\Transient;
use WPGraphQL\SmartCache\Storage\WpCache;
use WPGraphQL\SmartCache\Storage\Ephemeral;

class Query {

	const GROUP_NAME = 'gql_cache';

	/**
	 * The storage object for the actual system of choice transient, database, object, memory, etc
	 *
	 * @var WpCache|Transient|Ephemeral
	 **/
	public static $storage = null;

	/**
	 * The current GraphQL request.
	 *
	 * @var \WPGraphQL\Request|null
	 */
	protected $request;

	/**
	 * @return void
	 */
	public function init() {
		if ( null === self::$storage ) {
			self::$storage = apply_filters(
				'graphql_cache_storage_object', //phpcs:ignore
				wp_using_ext_object_cache() ? new WpCache( self::GROUP_NAME ) : new Transient( self::GROUP_NAME )
			);
		}
	}

	/**
	 * Unique identifier for this request is normalized query string, operation and variables
	 *
	 * @param string|null $query_id queryId from the graphql query request
	 * @param string $query query string
	 * @param array $variables Variables sent with request or null
	 * @param string $operation Name of operation if specified on the request or null
	 *
	 * @return string|false unique id for this request or false if query not provided
	 */
	public function build_key( $query_id, $query, $variables = null, $operation = null ) {
		// Unique identifier for this request is normalized query string, operation and variables
		// If request is by queryId, get the saved query string, which is already normalized
		if ( $query_id ) {
			$saved_query = new Document();
			$query       = $saved_query->get( $query_id );
		} elseif ( $query ) {
			// Query string provided, normalize it
			$query_ast = \GraphQL\Language\Parser::parse( $query );
			$query     = \GraphQL\Language\Printer::doPrint( $query_ast );
		}

		if ( ! $query ) {
			return false;
		}

		// Get user ID from AppContext->viewer which is set at Request creation
		// and doesn't change even if wp_set_current_user(0) is called later.
		// We intentionally do NOT fall back to wp_get_current_user() because that
		// function's return value can change mid-request (e.g., when WPGraphQL calls
		// wp_set_current_user(0) in has_authentication_errors()). Using 0 as fallback
		// treats the request as unauthenticated, which is the safe default.
		if ( $this->request ) {
			$user_id = $this->request->app_context->viewer->ID;
		} else {
			$user_id = 0;
		}

		$parts = [
			'query'     => $query,
			'variables' => $variables ?: null,
			'operation' => $operation ?: null,
			'user'      => $user_id,
		];

		$parts_string = wp_json_encode( $parts );

		if ( false === $parts_string ) {
			return false;
		}

		return hash( 'sha256', $parts_string );
	}

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string $key unique id for this request
	 * @return mixed|array|object|null  The graphql response or null if not found
	 */
	public function get( $key ) {
		return self::$storage->get( $key );
	}

	/**
	 * Converts GraphQL query result to spec-compliant serializable array using provided function
	 *
	 * @param string $key unique id for this request
	 * @param mixed|array|object|null $data The graphql response
	 * @param int $expire Time in seconds for the data to persist in cache. Zero means no expiration.
	 *
	 * @return bool False if value was not set and true if value was set.
	 */
	public function save( $key, $data, $expire = DAY_IN_SECONDS ) {
		return self::$storage->set( $key, $data, $expire );
	}

	/**
	 * Delete the data from cache/transient based on the provided key
	 *
	 * @param string $key unique id for this request
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete( $key ) {
		return self::$storage->delete( $key );
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return int|false  Count of the number deleted. False if error, nothing to delete or caching not enabled.
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		return self::$storage->purge_all();
	}
}
