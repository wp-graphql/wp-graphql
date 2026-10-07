<?php
/**
 * The max age admin and filter for individual query documents.
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Utils;
use GraphQL\Server\RequestError;

class MaxAge {
	const TAXONOMY_NAME = 'graphql_document_http_maxage';

	/**
	 * The in-progress query(s)
	 *
	 * @var array
	 */
	public $query_ids = [];

	/**
	 * Whether any operation in the response currently being returned carries errors.
	 *
	 * Filled by {@see MaxAge::capture_response_errors_cb()} and read, then cleared,
	 * by {@see MaxAge::http_headers_cb()}. It accumulates rather than assigns because
	 * a batch request fires `graphql_return_response` once per operation, and one
	 * `Cache-Control` header is sent for the whole batch.
	 *
	 * @var bool
	 */
	protected $response_has_errors = false;

	/**
	 * The Request the error verdict above was gathered from.
	 *
	 * Null until a response has been captured, and cleared alongside
	 * {@see MaxAge::$response_has_errors} once the verdict has been consumed.
	 *
	 * @var \WPGraphQL\Request|null
	 */
	protected $response_request = null;

	/**
	 * @return void
	 */
	public function init() {
		register_taxonomy(
			self::TAXONOMY_NAME,
			Document::TYPE_NAME,
			[
				'description'        => __( 'HTTP Cache-Control max-age directive for a saved GraphQL document', 'wp-graphql-smart-cache' ),
				'labels'             => [
					'name' => __( 'Max-Age Header', 'wp-graphql-smart-cache' ),
				],
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => false,
				'show_admin_column'  => true,
				'show_in_menu'       => Settings::show_in_admin(),
				'show_ui'            => Settings::show_in_admin(),
				'show_in_quick_edit' => false,
				'meta_box_cb'        => [
					'WPGraphQL\SmartCache\Admin\Editor',
					'maxage_input_box_cb',
				],
				'show_in_graphql'    => false,
				// false because we register a field with different name
			]
		);

		add_action(
			'graphql_register_types',
			function () {
				$register_type_name = ucfirst( Document::GRAPHQL_NAME );
				$config             = [
					'type'        => 'Int',
					'description' => __( 'HTTP Cache-Control max-age directive for a saved GraphQL document', 'wp-graphql-smart-cache' ),
				];

				register_graphql_field( 'Create' . $register_type_name . 'Input', 'max_age_header', $config );
				register_graphql_field( 'Update' . $register_type_name . 'Input', 'max_age_header', $config );

				$config['resolve'] = function ( \WPGraphQL\Model\Post $post, $args, $context, $info ) {
					$term = get_the_terms( $post->ID, self::TAXONOMY_NAME );

					return is_array( $term ) && $term[0] instanceof \WP_Term ? $term[0]->name : null;
				};
				register_graphql_field( $register_type_name, 'max_age_header', $config );
			}
		);

		// From WPGraphql Router
		add_filter( 'graphql_response_headers_to_send', [ $this, 'http_headers_cb' ], 10, 1 );
		add_filter( 'pre_graphql_execute_request', [ $this, 'peek_at_executing_query_cb' ], 10, 2 );

		// `graphql_response_headers_to_send` only receives the headers, so the response
		// has to be inspected while we still have it. This runs before the headers are
		// built for every executed response.
		add_action( 'graphql_return_response', [ $this, 'capture_response_errors_cb' ], 10, 7 );

		add_filter( 'graphql_mutation_input', [ $this, 'graphql_mutation_filter' ], 10, 4 );
		add_action( 'graphql_mutation_response', [ $this, 'graphql_mutation_insert' ], 10, 6 );
	}

	/**
	 * This runs on post create/update
	 * Check the max age value is within limits
	 *
	 * @param array $input The mutation input args.
	 * @param \WPGraphQL\AppContext $context The AppContext object.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
	 * @param string $mutation_name The name of the mutation field.
	 *
	 * @return array
	 */
	public function graphql_mutation_filter( $input, $context, $info, $mutation_name ) {
		if ( ! in_array(
			$mutation_name,
			[
				'createGraphqlDocument',
				'updateGraphqlDocument',
			],
			true
		) ) {
			return $input;
		}

		if ( ! isset( $input['maxAgeHeader'] ) ) {
			return $input;
		}

		return $input;
	}

	/**
	 * This runs on post create/update
	 * Check the max age value is within limits
	 *
	 * @param array $post_object The Payload returned from the mutation.
	 * @param array $filtered_input The mutation input args, after being filtered by 'graphql_mutation_input'.
	 * @param array $input The unfiltered input args of the mutation
	 * @param \WPGraphQL\AppContext $context The AppContext object.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
	 * @param string $mutation_name The name of the mutation field.
	 *
	 * @return void
	 **/
	public function graphql_mutation_insert( $post_object, $filtered_input, $input, $context, $info, $mutation_name ) {
		if ( ! in_array(
			$mutation_name,
			[
				'createGraphqlDocument',
				'updateGraphqlDocument',
			],
			true
		) ) {
			return;
		}

		if ( ! isset( $filtered_input['maxAgeHeader'] ) || ! isset( $post_object['postObjectId'] ) ) {
			return;
		}

		$this->save( $post_object['postObjectId'], $filtered_input['maxAgeHeader'] );
	}

	/**
	 * Get the max age if it exists for a saved persisted query
	 *
	 * @param int $post_id
	 * @return \WP_Error|string|null
	 */
	public function get( $post_id ) {
		$item = get_the_terms( $post_id, self::TAXONOMY_NAME );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		if ( ! $item || ! property_exists( $item[0], 'name' ) ) {
			return null;
		}
		return $item[0]->name;
	}

	/**
	 * Verify the max age value is acceptable
	 *
	 * @param string $value
	 * @return bool
	 */
	public function valid( $value ) {
		return ( is_numeric( $value ) && $value >= 0 );
	}

	/**
	 * Save the data
	 *
	 * @param int $post_id
	 * @param string $value
	 * @return array|false|\WP_Error Array of term taxonomy IDs of affected terms. WP_Error or false on failure.
	 */
	public function save( $post_id, $value ) {
		if ( ! $this->valid( $value ) ) {
			// Translators: The placeholder is the max-age-header input value
			throw new RequestError( sprintf( __( 'Invalid max age header value "%s". Must be greater than or equal to zero', 'wp-graphql-smart-cache' ), $value ) );
		}

		// Pass the term as an array. wp_set_post_terms() treats a scalar "0" as
		// empty and would remove the term instead of storing a max-age of zero.
		return wp_set_post_terms( $post_id, [ (string) $value ], self::TAXONOMY_NAME );
	}

	/**
	 * @param mixed|array|object $result The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 * @param \WPGraphQL\Request $request
	 * @return mixed|array|object
	 */
	public function peek_at_executing_query_cb( $result, $request ) {
		// For batch request, params are an array for each query/queryId in the batch
		$params = [];
		if ( is_array( $request->params ) ) {
			$params = $request->params;
		} else {
			$params[] = $request->params;
		}

		foreach ( $params as $req ) {
			//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( isset( $req->queryId ) ) {
				//phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$this->query_ids[] = $req->queryId;
			} elseif ( isset( $req->query ) ) {
				$this->query_ids[] = Utils::generateHash( $req->query );
			}
		}

		return $result;
	}

	/**
	 * Record whether the response being returned to the client carries errors.
	 *
	 * `graphql_response_headers_to_send` hands us the headers and nothing else, so the
	 * response has to be inspected here, while the request still has it. Without this,
	 * an error response is advertised to shared caches with the configured max-age: it
	 * carries no `X-GraphQL-Keys` to purge, so a single failure (a
	 * `PersistedQueryNotFound` handshake error, a partial response, a resolver blowing
	 * up) is replayed to every other client until the TTL expires.
	 *
	 * Called once per operation: a batch request fires this action once for each
	 * operation in the batch, so the verdict is accumulated rather than replaced.
	 * {@see MaxAge::http_headers_cb()} consumes it, once, for the whole response.
	 *
	 * @param mixed|array<string,mixed>|object $filtered_response The filtered response for the GraphQL operation.
	 * @param mixed|array<string,mixed>|object $response          The raw response for the GraphQL operation.
	 * @param \WPGraphQL\WPSchema $schema                         The WPGraphQL schema.
	 * @param ?string $operation_name                              The name of the operation.
	 * @param ?string $query                                       The query that GraphQL executed.
	 * @param ?array<string,mixed> $variables                      The variables passed to the operation.
	 * @param \WPGraphQL\Request|null $request                     The request being executed.
	 *
	 * @return void
	 */
	public function capture_response_errors_cb(
		$filtered_response,
		$response = null,
		$schema = null,
		$operation_name = null,
		$query = null,
		$variables = null,
		$request = null
	) {
		if ( Results::response_carries_errors( $filtered_response ) ) {
			$this->response_has_errors = true;
		}

		$this->response_request = $request instanceof \WPGraphQL\Request ? $request : null;
	}

	/**
	 * Add the Cache-Control directive for this request's response.
	 *
	 * A response carrying errors is never advertised as cacheable: `no-store` is sent
	 * and the saved-document and global max-age lookup is skipped, so a configured
	 * max-age cannot override it. Any other response gets the max-age for its saved
	 * document, or the global one. Hosts that want error responses back to the previous
	 * behavior can restore it with the `graphql_cache_error_responses` filter.
	 *
	 * @param array $headers
	 * @return array
	 */
	public function http_headers_cb( $headers ) {
		// Consume the verdict gathered from `graphql_return_response`, then clear it.
		// Every executed response runs that action before headers are built, so the
		// flag is always current here. Clearing it now means a path that sends an
		// error response without ever running that action - the Router's 403 auth error
		// and OPTIONS early exits, and the 500 it sends when execution throws - cannot
		// inherit the verdict of an earlier request in the same process. Those keep
		// whatever Cache-Control the max-age lookup below produces, as they did before.
		$response_has_errors = $this->response_has_errors;
		$response_request    = $this->response_request;

		$this->response_has_errors = false;
		$this->response_request    = null;

		if ( $response_has_errors ) {
			/**
			 * Filters whether a response that carries errors is still advertised as
			 * cacheable with the configured max-age.
			 *
			 * By default an error response is sent with `Cache-Control: no-store`, so no
			 * cache stores it. That is the safe default: an error response carries no
			 * cache keys to invalidate, so a cached one can only be cleared by waiting out
			 * its TTL, and a transient failure - a `PersistedQueryNotFound` handshake
			 * error, a partially resolved response, a resolver error - is then replayed
			 * to every other client until then.
			 *
			 * Return true to send the configured max-age for error responses instead,
			 * which restores the behavior from before this filter existed. Anything
			 * falsy - including null, which is what a filter that ignores its input
			 * returns - keeps the safe default.
			 *
			 * @param bool                    $cache_error_responses Whether an error response should still be advertised as cacheable. Default false, meaning `Cache-Control: no-store` is sent.
			 * @param \WPGraphQL\Request|null $request              The request the errored response was produced for. Null when the response was captured from a `graphql_return_response` call that carried no request. On the Router's early exits the response is never observed, so this filter is not applied there at all.
			 *
			 * @return bool
			 *
			 * @hookGroup caching
			 * @since x-release-please-version
			 */
			$cache_error_responses = (bool) apply_filters( 'graphql_cache_error_responses', false, $response_request );

			if ( ! $cache_error_responses ) {
				return $this->forbid_storing( $headers );
			}
		}

		$age = null;

		// Look up this specific request query. If found and has an individual max-age setting, use it.
		// For batch queries, look up and use the smallest/shortest max-age selection.
		foreach ( $this->query_ids as $query_id ) {
			$post = Utils::getPostByTermName( $query_id, Document::TYPE_NAME, Document::ALIAS_TAXONOMY_NAME );
			if ( $post ) {
				// If this saved query has a specified max-age, use it. Make sure to keep the smallest value.
				$value = $this->get( $post->ID );
				if ( is_string( $value ) && $this->valid( $value ) ) {
					$value = intval( $value );
					$age   = ( null === $age ) ? $value : min( $age, $value );
				}
			}
		}

		if ( null === $age ) {
			// If not, use a global max-age setting if set.
			$age = get_graphql_setting( 'global_max_age', null, 'graphql_cache_section' );
		}

		// Cache-Control max-age directive should be a positive integer, no decimals.
		// A value of zero indicates that caching should be disabled.
		if ( $this->valid( $age ) ) {
			$age = intval( $age );
			if ( 0 === $age ) {
				$headers['Cache-Control'] = 'no-store';
			} else {
				$headers['Cache-Control'] = sprintf( 'max-age=%1$s, s-maxage=%1$s, must-revalidate', $age );
			}
		}

		return $headers;
	}

	/**
	 * Make sure no cache may store the response.
	 *
	 * An existing `Cache-Control` that already forbids storage is left as-is: whatever
	 * else is on it - the `private` the Router adds for preview-context requests, for
	 * instance - only narrows the audience further, so replacing the value would throw
	 * away a stronger directive. Any other value is replaced outright rather than
	 * merged, because `no-store` and a `max-age` in one header contradict each other and
	 * a shared cache only gets to act on the header as a whole.
	 *
	 * @param array $headers
	 * @return array
	 */
	protected function forbid_storing( $headers ) {
		$existing = isset( $headers['Cache-Control'] ) && is_string( $headers['Cache-Control'] )
			? $headers['Cache-Control']
			: '';

		// `no-store` is the only Cache-Control directive with this substring, so a
		// substring test is an exact test for the directive.
		if ( '' !== $existing && false !== stripos( $existing, 'no-store' ) ) {
			return $headers;
		}

		$headers['Cache-Control'] = 'no-store';

		return $headers;
	}
}
