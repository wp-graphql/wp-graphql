<?php
/**
 * For a GraphQL query, look for the results in the WP transient cache and return that.
 * If not cached, when return results to client, save results to transient cache for future requests.
 */

namespace WPGraphQL\SmartCache\Cache;

use WPGraphQL;
use WPGraphQL\Request;
use WPGraphQL\SmartCache\Admin\Settings;

class Results extends Query {

	const GLOBAL_DEFAULT_TTL = 600;

	/**
	 * Indicator of the GraphQL Query keys cached or not.
	 *
	 * @var array
	 */
	protected $is_cached = [];

	/**
	 * Stores whether the object cache is enabled.
	 *
	 * This is cached after first determination to ensure consistent behavior
	 * throughout the request lifecycle, even if WordPress auth state changes.
	 *
	 * @var bool|null
	 */
	protected $is_object_cache_enabled = null;

	/**
	 * @return void
	 */
	public function init() {
		add_filter( 'pre_graphql_execute_request', [ $this, 'get_query_results_from_cache_cb' ], 10, 2 );
		add_action( 'graphql_return_response', [ $this, 'save_query_results_to_cache_cb' ], 10, 8 );
		add_action( 'wpgraphql_cache_purge_nodes', [ $this, 'purge_nodes_cb' ], 10, 2 );
		add_action( 'wpgraphql_cache_purge_all', [ $this, 'purge_all_cb' ], 10, 0 );
		add_filter( 'graphql_request_results', [ $this, 'add_cache_key_to_response_extensions' ], 10, 7 );

		// Set Cache-Control: no-store for authenticated requests to prevent network/CDN caching
		add_filter( 'graphql_response_headers_to_send', [ $this, 'add_no_cache_headers_for_authenticated_requests' ], PHP_INT_MAX );

		parent::init();
	}

	/**
	 * Add Cache-Control: no-store header for authenticated requests.
	 *
	 * This prevents network caches (Varnish, CDN, etc.) from caching responses
	 * that were made by authenticated users, which could contain sensitive data.
	 *
	 * Uses AppContext->viewer which is set at Request creation and doesn't change
	 * even if wp_set_current_user(0) is called later.
	 *
	 * @param array $headers The headers to be sent with the response.
	 *
	 * @return array The modified headers.
	 */
	public function add_no_cache_headers_for_authenticated_requests( $headers ) {
		// Use the viewer from AppContext, which is set at Request creation
		// and doesn't change even if wp_set_current_user(0) is called later
		if ( $this->request && $this->request->app_context->viewer->exists() ) {
			$headers['Cache-Control'] = 'no-store';
		}

		return $headers;
	}

	/**
	 * Unique identifier for this request is normalized query string, operation and variables
	 *
	 * @param string $query_id queryId from the graphql query request
	 * @param string $query query string
	 * @param array $variables Variables sent with request or null
	 * @param string $operation_name Name of operation if specified on the request or null
	 *
	 * @return string|false unique id for this request or false if query not provided
	 */
	public function the_results_key( $query_id, $query, $variables = null, $operation_name = null ) {
		return $this->build_key( $query_id, $query, $variables, $operation_name );
	}


	/**
	 * Add a message to the extensions when a GraphQL request is returned from the GraphQL Object Cache
	 *
	 * @param mixed|array|object $response The response of the GraphQL Request
	 * @param \WPGraphQL\WPSchema   $schema    The schema object for the root query
	 * @param string     $operation_name The name of the operation
	 * @param string     $query_string     The query that GraphQL executed
	 * @param array|null $variables Variables to passed to your GraphQL request
	 * @param \WPGraphQL\Request    $request   Instance of the Request
	 * @param string|null $query_id The query id that GraphQL executed
	 *
	 * @return array|mixed
	 */
	public function add_cache_key_to_response_extensions(
		$response,
		$schema,
		$operation_name,
		$query_string,
		$variables,
		$request,
		$query_id
	) {
		$key = $this->the_results_key( $query_id, $query_string, $variables, $operation_name );
		if ( $key ) {
			$message = [];

			// If we know that the results were pulled from cache, add messaging
			if ( isset( $this->is_cached[ $key ] ) && true === $this->is_cached[ $key ] ) {
				$message = [
					'message'  => __( 'This response was not executed at run-time but has been returned from the GraphQL Object Cache', 'wp-graphql-smart-cache' ),
					'cacheKey' => $key,
				];
			}

			if ( is_array( $response ) ) {
				$response['extensions']['graphqlSmartCache']['graphqlObjectCache'] = $message;
			} if ( is_object( $response ) && property_exists( $response, 'extensions' ) ) {
				$response->extensions['graphqlSmartCache']['graphqlObjectCache'] = $message;
			}
		}

		// return the modified response with the graphqlSmartCache message in the extensions output
		return $response;
	}

	/**
	 * Look for a 'cached' response for this exact query, variables and operation name
	 *
	 * @param mixed|array|object $result   The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 * @param \WPGraphQL\Request            $request
	 *
	 * @return mixed|array|object|null  The response or null if not found in cache
	 */
	public function get_query_results_from_cache_cb( $result, Request $request ) {
		$this->request = $request;

		// Reset the cached is_object_cache_enabled value for each new request
		// This ensures we re-evaluate based on the current request's auth state
		$this->is_object_cache_enabled = null;

		// if caching is not enabled or the request is authenticated, bail early
		// right now we're not supporting GraphQL cache for authenticated requests.
		// Possibly in the future.
		if ( ! $this->is_object_cache_enabled() ) {
			return $result;
		}

		$root_operation = $request->get_query_analyzer()->get_root_operation();

		// For mutation, do not cache
		if ( ! empty( $root_operation ) && 'Query' !== $root_operation ) {
			return $result;
		}

		// Loop over each request and load the response. If any one are empty, not in cache, return so all get reloaded.
		if ( is_array( $request->params ) ) {
			$result = [];
			foreach ( $request->params as $req ) {
				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$response = $this->get_result( $req->queryId, $req->query, $req->variables, $req->operation );
				// If any one is null, return all are null.
				if ( null === $response ) {
					return null;
				}
				$result[] = $response;
			}
		} else {
			//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$result = $this->get_result( $request->params->queryId, $request->params->query, $request->params->variables, $request->params->operation );
		}
		return $result;
	}

	/**
	 * Unique identifier for this request is normalized query string, operation and variables
	 *
	 * @param string $query_id queryId from the graphql query request
	 * @param string $query_string query string
	 * @param array  $variables Variables sent with request or null
	 * @param string $operation_name Name of operation if specified on the request or null
	 *
	 * @return string|null The response or null if not found in cache
	 */
	public function get_result( $query_id, $query_string, $variables, $operation_name ) {
		$key = $this->the_results_key( $query_id, $query_string, $variables, $operation_name );
		if ( ! $key ) {
			return null;
		}

		$result = $this->get( $key );
		if ( false === $result ) {
			return null;
		}

		$this->is_cached[ $key ] = true;

		return $result;
	}

	/**
	 * Determine whether object cache is enabled
	 *
	 * @return bool
	 */
	protected function is_object_cache_enabled() {

		// Return cached value if already determined for this request.
		// This ensures consistent behavior even if WordPress auth state changes mid-request.
		if ( null !== $this->is_object_cache_enabled ) {
			return (bool) $this->is_object_cache_enabled;
		}

		// default to disabled
		$enabled = false;

		// if caching is enabled, respect it
		if ( Settings::caching_enabled() ) {
			$enabled = true;
		}

		// Check if the user is authenticated using AppContext->viewer.
		// This is more reliable than is_user_logged_in() because:
		// 1. AppContext->viewer is set once at Request creation and doesn't change
		// 2. WPGraphQL core may call wp_set_current_user(0) mid-request in has_authentication_errors(),
		// or even within individual resolvers, etc which would cause is_user_logged_in()
		// to return false even for authenticated requests
		// 3. Using AppContext is more "GraphQL-native" and consistent with how WPGraphQL handles auth
		if ( $this->request && $this->request->app_context->viewer->exists() ) {
			$enabled = false;
		}

		// @phpcs:ignore
		$this->is_object_cache_enabled = (bool) apply_filters( 'graphql_cache_is_object_cache_enabled', $enabled, $this->request );

		return $this->is_object_cache_enabled;
	}

	/**
	 * Determine whether a GraphQL response carries errors.
	 *
	 * Two decisions depend on this answer and they have to agree: whether the response
	 * may be written to the object cache, and whether it may be advertised to HTTP
	 * caches with a max-age. If either side disagreed, an error response would still
	 * outlive a purge. So there is one implementation and both callers use it.
	 *
	 * Handles a single result object, a single result that a `graphql_request_results`
	 * filter reshaped into an array, and a batch (an array of either).
	 *
	 * @param mixed $response The response being returned to the client. An array of
	 *                        results for a batch request, a single result otherwise.
	 *
	 * @return bool True when at least one operation in the response carries errors.
	 */
	public static function response_carries_errors( $response ) {
		if ( is_object( $response ) ) {
			// get_object_vars() rather than property_exists() + access: a response of an
			// unknown shape may hold a non-public `errors`, and reading that from outside
			// its class is a fatal error. Only public properties come back here.
			$properties = get_object_vars( $response );

			if ( isset( $properties['errors'] ) ) {
				return ! empty( $properties['errors'] );
			}

			// Nothing public to read. A response that exposes `errors` through a magic
			// accessor still has to count as errored, because missing them would
			// re-advertise the failure - the bug this change exists to fix. Reporting
			// errors we cannot read as present costs one extra `no-store`.
			//
			// isset() is not enough for this: it consults __isset(), which a response
			// need not define. Direct access is safe precisely because __get is defined
			// - that is what sends the read to the magic accessor instead of fataling
			// on an inaccessible property.
			if ( ! method_exists( $response, '__get' ) ) {
				return false;
			}

			// Read through the magic accessor, then test what came back. Testing the
			// property with `empty()` instead would consult __isset() first, which a
			// response need not define, and would report "no errors" without ever
			// reaching __get() -- the miss this branch exists to prevent.
			// @phpstan-ignore-next-line - `errors` is reached through __get(), which PHPStan cannot see.
			$magic_errors = $response->errors;

			return ! empty( $magic_errors );
		}

		if ( ! is_array( $response ) ) {
			return false;
		}

		// A single result in array shape.
		if ( array_key_exists( 'errors', $response ) ) {
			return ! empty( $response['errors'] );
		}

		// A batch. One `Cache-Control` header covers the whole response, so a single
		// failing operation is enough to make all of it unsafe to store.
		foreach ( $response as $single_response ) {
			if ( is_array( $single_response ) || is_object( $single_response ) ) {
				if ( self::response_carries_errors( $single_response ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * When a query response is being returned to the client, build map for each item and this query/queryId
	 * That way we will know what to invalidate on data change.
	 *
	 * @param \GraphQL\Executor\ExecutionResult $filtered_response The response after GraphQL Execution has been
	 *                                           completed and passed through filters
	 * @param \GraphQL\Executor\ExecutionResult $response          The raw, unfiltered response of the GraphQL
	 *                                           Execution
	 * @param \WPGraphQL\WPSchema $schema            The WPGraphQL Schema
	 * @param string          $operation_name         The name of the Operation
	 * @param string          $query             The query string
	 * @param array           $variables         The variables for the query
	 * @param Request         $request           The WPGraphQL Request object
	 * @param string|null     $query_id          The query id that GraphQL executed
	 *
	 * @return void
	 */
	public function save_query_results_to_cache_cb(
		$filtered_response,
		$response,
		$schema,
		$operation_name,
		$query,
		$variables,
		$request,
		$query_id
	) {

		// if caching is NOT enabled, or the request is authenticated, bail early
		// right now we're not supporting GraphQL cache for authenticated requests.
		//
		// Possibly in the future we'll have solutions for authenticated request caching
		//
		// There is no request-method check here: a GET request is cached in the object
		// cache like any other query. HTTP caching for GET is a separate layer, driven
		// by the Cache-Control directives that WPGraphQL\SmartCache\Document\MaxAge
		// adds - that is what caching clients such as Varnish or a CDN act on.
		if ( ! $this->is_object_cache_enabled() ) {
			return;
		}

		// A response carrying errors describes a failed execution, not a result.
		// Serving it back from cache would replay the failure for the full TTL, and an
		// error response carries no cache keys, so there is nothing to purge it by.
		if ( self::response_carries_errors( $filtered_response ) ) {
			return;
		}

		$root_operation = $request->get_query_analyzer()->get_root_operation();

		// For mutation, do not cache
		if ( ! empty( $root_operation ) && 'Query' !== $root_operation ) {
			return;
		}

		$key = $this->the_results_key( $query_id, $query, $variables, $operation_name );
		if ( ! $key ) {
			return;
		}

		// If we do not have a cached version, or it expired, save the results again with new expiration
		$cached_result = $this->get( $key );

		if ( false === $cached_result ) {
			$expiration = \get_graphql_setting( 'global_ttl', self::GLOBAL_DEFAULT_TTL, 'graphql_cache_section' );

			$this->save( $key, $filtered_response, $expiration );
		}
	}

	/**
	 * When an item changed and this callback is triggered to delete results we have cached for that list of nodes
	 * Related to the data type that changed.
	 *
	 * @param string $id An identifier for data stored in memory.
	 * @param mixed|array|object|null $nodes The graphql response or false
	 *
	 * @return void
	 */
	public function purge_nodes_cb( $id, $nodes ) {
		if ( is_array( $nodes ) && ! empty( $nodes ) ) {
			foreach ( $nodes as $request_key ) {
				$this->delete( $request_key );
			}

			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			graphql_debug( 'Graphql delete nodes', [ 'nodes' => $nodes ] );
		}
	}

	/**
	 * Purge the local cache results if enabled
	 *
	 * @return void
	 */
	public function purge_all_cb() {
		$this->purge_all();
	}
}
