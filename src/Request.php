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
	 * @access private
	 */
	private $app_context;

	/**
	 * Request data.
	 *
	 * @var array
	 * @access private
	 */
	private $data;

	/**
	 * Cached global post.
	 *
	 * @var \WP_Post
	 * @access private
	 */
	private $global_post;

	/**
	 * GraphQL operation parameters for this request. Can also be an array of
	 * OperationParams.
	 *
	 * @var OperationParams|OperationParams[]
	 * @access private
	 */
	private $params;

	/**
	 * Schema for this request.
	 *
	 * @var \WPGraphQL\WPSchema
	 * @access public
	 */
	public $schema;

	/**
	 * Constructor
	 *
	 * @param  array|null $data The request data (for non-HTTP requests).
	 *
	 * @return void
	 *
	 * @access public
	 *
	 * @throws \Exception
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

		// Get the Schema
		$this->schema = \WPGraphQL::get_schema();

		// Get the App Context
		$this->app_context = \WPGraphQL::get_app_context();

		/**
		 * Configure the app_context which gets passed down to all the resolvers.
		 *
		 * @since 0.0.4
		 */
		$app_context           = new AppContext();
		$app_context->viewer   = wp_get_current_user();
		$app_context->root_url = get_bloginfo( 'url' );
		$app_context->request  = ! empty( $_REQUEST ) ? $_REQUEST : null; // phpcs:ignore
		$this->app_context     = $app_context;
	}

	/**
	 * Apply filters and do actions before GraphQL execution
	 *
	 * @access private
	 *
	 * @return void
	 */
	private function before_execute() {

		/**
		 * Filter "is_graphql_request" to return true
		 */
		\WPGraphQL::__set_is_graphql_request( true );

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
	 * Checks authentication errors.
	 *
	 * False will mean there are no detected errors and
	 * execution will continue.
	 *
	 * Anything else (true, WP_Error, thrown exception, etc) will prevent execution of the GraphQL
	 * request.
	 *
	 * @access protected
	 * @throws \Exception
	 *
	 * @return boolean
	 */
	protected function has_authentication_errors() {

		/**
		 * Bail if this is not an HTTP request.
		 *
		 * Auth for internal requests will happen
		 * via WordPress internals.
		 */
		if ( ! is_graphql_http_request() ) {
			return false;
		}

		/**
		 * Access the global $wp_rest_auth_cookie
		 */
		global $wp_rest_auth_cookie;

		/**
		 * Default state of the authentication errors
		 */
		$authentication_errors = false;

		/**
		 * Is cookie authentication NOT being used?
		 *
		 * If we get an auth error, but the user is still logged in, another auth mechanism
		 * (JWT, oAuth, etc) must have been used.
		 */
		if ( true !== $wp_rest_auth_cookie && is_user_logged_in() ) {

			/**
			 * Return filtered authentication errors
			 */
			return $this->filtered_authentication_errors( $authentication_errors );

			/**
			 * If the user is not logged in, determine if there's a nonce
			 */
		} else {

			$nonce = null;

			if ( isset( $_REQUEST['_wpnonce'] ) ) {
				$nonce = $_REQUEST['_wpnonce'];
			} elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
				$nonce = $_SERVER['HTTP_X_WP_NONCE'];
			}

			if ( null === $nonce ) {
				// No nonce at all, so act as if it's an unauthenticated request.
				wp_set_current_user( 0 );

				return $this->filtered_authentication_errors( $authentication_errors );
			}

			// Check the nonce.
			$result = wp_verify_nonce( $nonce, 'wp_rest' );

			if ( ! $result ) {
				throw new \Exception( __( 'Cookie nonce is invalid', 'wp-graphql' ) );
			}
		}

		/**
		 * Return the filtered authentication errors
		 */
		return $this->filtered_authentication_errors( $authentication_errors );
	}

	/**
	 * Filter Authentication errors. Allows plugins that authenticate to hook in and prevent
	 * execution if Authentication errors exist.
	 *
	 * @param boolean $authentication_errors Whether there are authentication errors with the request
	 *
	 * @access protected
	 * @return boolean
	 */
	protected function filtered_authentication_errors( $authentication_errors = false ) {

		/**
		 * If false, there are no authentication errors. If true, execution of the
		 * GraphQL request will be prevented and an error will be thrown.
		 *
		 * @param boolean $authentication_errors Whether there are authentication errors with the request
		 * @param Request $this Instance of the Request
		 */
		return apply_filters( 'graphql_authentication_errors', $authentication_errors, $this );
	}

	/**
	 * Performs actions and runs filters after execution completes
	 *
	 * @param mixed|array|object $response The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 * @return array
	 * @access private
	 *
	 * @throws \Exception
	 */
	private function after_execute( $response ) {

		/**
		 * If there are authentication errors, prevent execution and throw an exception.
		 */
		if ( false !== $this->has_authentication_errors() ) {
			throw new \Exception( __( 'Authentication Error', 'wp-graphql' ) );
		}

		/**
		 * If the params and the $response are both arrays
		 * treat this as a batch request and map over the array to apply the
		 * after_execute_actions, otherwise apply them to the current response
		 */
		if ( is_array( $this->params ) && is_array( $response ) ) {
			$filtered_response = array_map( [ $this, 'after_execute_actions' ], $response );
		} else {
			$filtered_response = $this->after_execute_actions( $response, null );
		}

		/**
		 * Reset the global post after execution
		 *
		 * This allows for a GraphQL query to be used in the middle of post content, such as in a Shortcode
		 * without disrupting the flow of the post as the global POST before and after GraphQL execution will be
		 * the same.
		 *
		 * We cannot use wp_reset_postdata here because it just resets the post from the global query which can
		 * be anything the because the resolvers themself can set it to whatever. So we just manually reset the
		 * post with setup_postdata we cached before this request.
		 */
		if ( ! empty( $this->global_post ) ) {
			$GLOBALS['post'] = $this->global_post;
			setup_postdata( $this->global_post );
		}

		/**
		 * Return the filtered response
		 */
		return $filtered_response;

	}

	/**
	 * Apply filters and do actions after GraphQL execution
	 *
	 * @param array          $response The response for your GraphQL request
	 * @param mixed|Int|null $key      The array key of the params for batch requests
	 *
	 *                                 @access private
	 *
	 * @return array
	 */
	private function after_execute_actions( $response, $key = null ) {

		/**
		 * Determine which params (batch or single request) to use when passing through to the actions
		 */
		$params = null;

		if ( ! $key && $this->params ) {
			$params = $this->params;
		} elseif ( is_array( $this->params ) && isset( $this->params[ $key ] ) ) {
			$params = $this->params[ $key ];
		}

		$operation = isset( $params->operation ) ? $params->operation : '';
		$query     = isset( $params->query ) ? $params->query : '';
		$variables = isset( $params->variables ) ? $params->variables : null;

		/**
		 * Run an action. This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @since 0.0.4
		 *
		 * @param array               $response  The response your GraphQL request
		 * @param \WPGraphQL\WPSchema $schema    The schema object for the root request
		 * @param string              $operation The name of the operation
		 * @param string              $query     The query that GraphQL executed
		 * @param array|null          $variables Variables to passed to your GraphQL query
		 */
		do_action( 'graphql_execute', $response, $this->schema, $operation, $query, $variables );

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
		 * @param array               $response  The response for your GraphQL query
		 * @param \WPGraphQL\WPSchema $schema    The schema object for the root query
		 * @param string              $operation The name of the operation
		 * @param string              $query     The query that GraphQL executed
		 * @param array|null          $variables Variables to passed to your GraphQL request
		 */
		$filtered_response = apply_filters( 'graphql_request_results', $response, $this->schema, $operation, $query, $variables );

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
		do_action( 'graphql_return_response', $filtered_response, $response, $this->schema, $operation, $query, $variables );

		/**
		 * Filter "is_graphql_request" back to false.
		 */
		\WPGraphQL::__set_is_graphql_request( false );

		return $filtered_response;
	}

	/**
	 * Run action for a request.
	 *
	 * @param  OperationParams $params OperationParams for the request.
	 *
	 *                                 @access private
	 *
	 * @return void
	 */
	private function do_action( $params ) {
		/**
		 * Run an action for each request.
		 *
		 * @param string          $query     The GraphQL query
		 * @param string          $operation The name of the operation
		 * @param string          $variables Variables to be passed to your GraphQL request
		 * @param OperationParams $params    The Operation Params. This includes any extra params, such as extenions or any other modifications to the request body
		 */
		do_action( 'do_graphql_request', $params->query, $params->operation, $params->variables, $params );
	}

	/**
	 * Execute an internal request (graphql() function call).
	 *
	 * @access public
	 * @return array
	 * @throws \Exception
	 */
	public function execute() {

		$helper       = new WPHelper();
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

		/**
		 * If the request is a batch request it will come back as an array
		 */
		return $this->after_execute( $response );
	}

	/**
	 * Execute an HTTP request.
	 *
	 * @access public
	 * @return array
	 * @throws \Exception
	 */
	public function execute_http() {

		/**
		 * Parse HTTP request.
		 */
		$helper       = new WPHelper();
		$this->params = $helper->parseHttpRequest();

		/**
		 * Initialize the GraphQL Request
		 */
		$this->before_execute();

		/**
		 * Get the response.
		 */
		$server   = $this->get_server();
		$response = $server->executeRequest( $this->params );

		return $this->after_execute( $response, $this->params );
	}

	/**
	 * Get the operation params for the request.
	 *
	 * @access public
	 * @return OperationParams
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Create the GraphQL server that will process the request.
	 *
	 * @return StandardServer
	 */
	private function get_server() {

		$config = new ServerConfig();
		$config
			->setDebug( GRAPHQL_DEBUG )
			->setSchema( $this->schema )
			->setContext( $this->app_context )
			->setQueryBatching( true );

		/**
		 * Run an action when the server config is created. The config can be acted
		 * upon directly to override default values or implement new features, e.g.,
		 * $config->setValidationRules().
		 *
		 * @since 0.2.0
		 *
		 * @param ServerConfig    $config Server config
		 * @param OperationParams $params Request operation params
		 */
		do_action( 'graphql_server_config', $config, $this->params );

		$server = new StandardServer( $config );

		return $server;
	}
}
