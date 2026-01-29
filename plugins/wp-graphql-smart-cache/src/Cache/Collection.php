<?php
/**
 * When processing a GraphQL query, collect nodes based on the query and url they are part of.
 * When content changes for nodes, invalidate and trigger actions that allow caches to be
 * invalidated for nodes, queries, urls.
 */

namespace WPGraphQL\SmartCache\Cache;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Type\Schema;
use WPGraphQL\Request;
use WPGraphQL\SmartCache\Admin\Settings;

class Collection extends Query {

	/**
	 * Initialize the cache collection
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'graphql_return_response', [ $this, 'save_query_mapping_cb' ], 10, 8 );
		parent::init();
	}


	/**
	 * @param string $key     The identifier to the list
	 * @param string $content to add
	 *
	 * @return array The unique list of content stored
	 */
	public function store_content( $key, $content ) {
		$data   = $this->get( $key );
		$data[] = $content;
		$data   = array_unique( $data );
		$this->save( $key, $data );

		return $data;
	}

	/**
	 * When a query response is being returned to the client, build map for each item and this
	 * query/queryId That way we will know what to invalidate on data change.
	 *
	 * @param ExecutionResult $filtered_response The response after GraphQL Execution has been
	 *                                           completed and passed through filters
	 * @param ExecutionResult $response          The raw, unfiltered response of the GraphQL
	 *                                           Execution
	 * @param Schema          $schema            The WPGraphQL Schema
	 * @param string          $operation         The name of the Operation
	 * @param string          $query             The query string
	 * @param array           $variables         The variables for the query
	 * @param Request         $request           The WPGraphQL Request object
	 * @param string|null     $query_id          The query id that GraphQL executed
	 *
	 * @return void
	 */
	public function save_query_mapping_cb(
		$filtered_response,
		$response,
		$schema,
		$operation,
		$query,
		$variables,
		$request,
		$query_id
	) {

		// If cache maps are not enabled, do nothing
		if ( ! Settings::cache_maps_enabled() ) {
			return;
		}

		// Set the request so build_key() can access AppContext->viewer for the user ID
		$this->request = $request;

		$request_key = $this->build_key( $query_id, $query, $variables, $operation );

		if ( false === $request_key ) {
			return;
		}

		// get the runtime nodes from the query analyzer
		$runtime_nodes = $request->get_query_analyzer()->get_runtime_nodes() ?: [];
		$list_types    = $request->get_query_analyzer()->get_list_types() ?: [];

		/**
		 * Save the cache response
		 *
		 * @param string  $request_key   The unique key for the request, generated from the query, variables and operation name
		 * @param ?string $query_id      The query ID for the query document
		 * @param string  $query         The query string being executed
		 * @param ?array  $variables     Variables passed to the request
		 * @param ?string $operation     The name of the operation to execute
		 * @param array   $runtime_nodes Nodes that have been resolved at runtime
		 * @param array   $list_types    Types that have been requested during execution
		 */
		do_action( 'wpgraphql_cache_save_request', $request_key, $query_id, $query, $variables, $operation, $runtime_nodes, $list_types );

		// Save/add the node ids for this query.  When one of these change in the future, we can purge the query
		foreach ( $runtime_nodes as $node_id ) {
			$this->store_content( (string) $node_id, $request_key );
		}

		// For each connection resolver, store the list types associated with this graphql query request
		if ( ! empty( $list_types ) ) {
			$list_types = array_unique( $list_types );
			foreach ( $list_types as $type_name ) {
				$this->store_content( $type_name, $request_key );
			}
		}
	}
}
