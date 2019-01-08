<?php

namespace WPGraphQL;

use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use WPGraphQL\Server\WPHelper;

/**
 * Class Request
 *
 * Proxies a request to graphql-php, applying filters and transforming request
 * data as needed.
 *
 * @package WPGraphQL
 */
class Request {

	/**
	 * App context for this request.
	 *
	 * @var \WPGraphQL\AppContext
	 */
	private $app_context;

	/**
	 * Request data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Cached global post.
	 *
	 * @var WP_Post
	 */
	private $global_post;

	/**
	 * GraphQL operation parameters for this request. Can also be an array of
	 * OperationParams.
	 *
	 * @var OperationParams|OperationParams[]
	 */
	private $params;

	/**
	 * Schema for this request.
	 *
	 * @var \WPGraphQL\WPSchema
	 */
	private $schema;

	/**
	 * Constructor
	 *
	 * @param  array|null $data The request data (for non-HTTP requests).
	 * @return void
	 */
	public function __construct( $data = null ) {

		/**
		 * Whether it's a GraphQL Request (http or internal)
		 *
		 * @since 0.0.5
		 */
		if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
			define( 'GRAPHQL_REQUEST', true );
		}

		/**
		 * Action – intentionally with no context – to indicate a GraphQL Request has started.
		 * This is a great place for plugins to hook in and modify things that should only
		 * occur in the context of a GraphQL Request. The base class hooks into this action to
		 * kick off the schema creation, so types are not set up until this action has run!
		 */
		do_action( 'init_graphql_request' );

		// Set request data for passed-in (non-HTTP) requests.
		$this->data = $data;

		$this->schema = \WPGraphQL::get_schema();
		$this->app_context = \WPGraphQL::get_app_context();
	}

	/**
	 * Apply filters and do actions before GraphQL execution
	 */
	private function before_execute() {

		/**
		 * Store the global post so it can be reset after GraphQL execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 */
		if ( ! empty( $GLOBALS['post'] ) ) {
			$this->global_post = $GLOBALS['post'];
		}

		/**
		 * If the request is a batch request it will come back as an array
		 */
		if ( is_array( $this->params ) ) {
			array_walk( $this->params, [ $this, 'do_action' ] );
		} else {
			$this->do_action( $this->params );
		}
	}

	/**
	 * Apply filters and do actions after GraphQL execution
	 *
	 * @param array $response The response for your GraphQL request
	 */
	private function after_execute( $response ) {

		/**
		 * Run an action. This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @since 0.0.4
		 *
		 * @param array               $response       The response your GraphQL request
		 * @param \WPGraphQL\WPSchema $schema         The schema object for the root request
		 * @param string              $operation      The name of the operation
		 * @param string              $query          The query that GraphQL executed
		 * @param array|null          $variables      Variables to passed to your GraphQL query
		 */
		do_action( 'graphql_execute', $response, $this->schema, $this->params->operation, $this->params->query, $this->params->variables );

		/**
		 * Filter the $response of the GraphQL execution. This allows for the response to be filtered
		 * before it's returned, allowing granular control over the response at the latest point.
		 *
		 * POSSIBLE USAGE EXAMPLES:
		 * This could be used to ensure that certain fields never make it to the response if they match
		 * certain criteria, etc. For example, this filter could be used to check if a current user is
		 * allowed to see certain things, and if they are not, the $response could be filtered to remove
		 * the data they should not be allowed to see.
		 *
		 * Or, perhaps some systems want the response to always include some additional piece of data in
		 * every response, regardless of the request that was sent to it, this could allow for that
		 * to be hooked in and included in the $response.
		 *
		 * @since 0.0.5
		 *
		 * @param array               $response       The response for your GraphQL query
		 * @param \WPGraphQL\WPSchema $schema         The schema object for the root query
		 * @param string              $operation      The name of the operation
		 * @param string              $query          The query that GraphQL executed
		 * @param array|null          $variables      Variables to passed to your GraphQL request
		 */
		$filtered_response = apply_filters( 'graphql_request_results', $response, $this->schema, $this->params->operation, $this->params->query, $this->params->variables );

		/**
		 * Run an action after the response has been filtered, as the response is being returned.
		 * This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @param array               $filtered_response The filtered response for the GraphQL request
		 * @param array               $response          The response for your GraphQL request
		 * @param \WPGraphQL\WPSchema $schema            The schema object for the root request
		 * @param string              $operation         The name of the operation
		 * @param string              $query             The query that GraphQL executed
		 * @param array|null          $variables         Variables to passed to your GraphQL query
		 */
		do_action( 'graphql_return_response', $filtered_response, $response, $this->schema, $this->params->operation, $this->params->query, $this->params->variables );

		/**
		 * Reset the global post after execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 */
		if ( ! empty( $this->global_post ) ) {
			$GLOBALS['post'] = $this->global_post;
		}

		return $filtered_response;
	}

	/**
	 * Run action for a request.
	 *
	 * @param  OperationParams $params OperationParams for the request.
	 * @return void
	 */
	private function do_action( $params ) {
		/**
		 * Run an action for each request.
		 *
		 * @param string $query          The GraphQL query
		 * @param string $operation      The name of the operation
		 * @param string $variables      Variables to be passed to your GraphQL request
		 */
		do_action( 'do_graphql_request', $params->query, $params->operation, $params->variables );
	}

	/**
	 * Returns all registered Schemas
	 *
	 * @return array
	 */
	public function execute() {

		$helper = new WPHelper();
		$this->params = $helper->parseRequestParams( 'POST', $this->data, [] );

		/**
		 * Initialize the GraphQL Request
		 */
		$this->before_execute();

		$result = \GraphQL\GraphQL::executeQuery(
			$this->schema,
			$this->params->query,
			null,
			$this->app_context,
			$this->params->variables,
			$this->params->operation
		);

		/**
		 * Return the result of the request
		 */
		$response = $result->toArray( GRAPHQL_DEBUG );

		/**
		 * Ensure the response is returned as a proper, populated array. Otherwise add an error.
		 */
		if ( empty( $response ) || ! is_array( $response ) ) {
			$response = [
				'errors' => __( 'The GraphQL request returned an invalid response', 'wp-graphql' ),
			];
		}

		return $this->after_execute( $response );
	}

	/**
	 * Returns all registered Schemas
	 *
	 * @return array
	 */
	public function execute_http() {

		/**
		 * Parse HTTP request.
		 */
		$helper = new WPHelper();
		$this->params = $helper->parseHttpRequest();

		/**
		 * Initialize the GraphQL Request
		 */
		$this->before_execute();

		/**
		 * Get the response.
		 */
		$server = $this->get_server();
		$response = $server->executeRequest();

		return $this->after_execute( $response );
	}

	/**
	 * Get the operation params for the request.
	 *
	 * @return OperationParams
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Create the GraphQL server that will process the request.
	 *
	 * @return StandardServer
	 * @throws \GraphQL\Server\RequestError
	 */
	private function get_server() {

		$config = new ServerConfig();
		$config
			->setDebug( GRAPHQL_DEBUG )
			->setSchema( $this->schema )
			->setContext( $this->app_context )
			->setQueryBatching( true );

		$server = new StandardServer( $config );

		return $server;
	}
}
