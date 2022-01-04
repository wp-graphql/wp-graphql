<?php

namespace WPGraphQL;

use Exception;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Server\OperationParams;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use WPGraphQL\Server\ValidationRules\DisableIntrospection;
use WP_Post;
use WP_Query;
use WPGraphQL\Server\ValidationRules\QueryDepth;
use WPGraphQL\Server\ValidationRules\RequireAuthentication;
use WPGraphQL\Server\WPHelper;
use WPGraphQL\Utils\DebugLog;

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
	 * @var AppContext
	 */
	public $app_context;

	/**
	 * Request data.
	 *
	 * @var mixed|array|OperationParams
	 */
	public $data;

	/**
	 * Cached global post.
	 *
	 * @var WP_Post
	 */
	public $global_post;

	/**
	 * Cached global wp_the_query.
	 *
	 * @var WP_Query
	 */
	private $global_wp_the_query;

	/**
	 * GraphQL operation parameters for this request. Can also be an array of
	 * OperationParams.
	 *
	 * @var mixed|array|OperationParams|OperationParams[]
	 */
	public $params;

	/**
	 * Schema for this request.
	 *
	 * @var WPSchema
	 */
	public $schema;

	/**
	 * Debug log for WPGraphQL Requests
	 *
	 * @var DebugLog
	 */
	public $debug_log;

	/**
	 * The Type Registry the Schema is built with
	 *
	 * @var Registry\TypeRegistry
	 */
	public $type_registry;

	/**
	 * Validation rules for execution.
	 *
	 * @var array
	 */
	protected $validation_rules;

	/**
	 * The default field resolver function. Default null
	 *
	 * @var mixed|callable|null
	 */
	protected $field_resolver;

	/**
	 * The root value of the request. Default null;
	 *
	 * @var mixed
	 */
	protected $root_value;

	/**
	 * Constructor
	 *
	 * @param array $data The request data (for non-HTTP requests).
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function __construct( array $data = [] ) {

		/**
		 * Whether it's a GraphQL Request (http or internal)
		 *
		 * @since 0.0.5
		 */
		if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
			define( 'GRAPHQL_REQUEST', true );
		}

		/**
		 * Filter "is_graphql_request" to return true
		 */
		\WPGraphQL::set_is_graphql_request( true );

		/**
		 * Action – intentionally with no context – to indicate a GraphQL Request has started.
		 * This is a great place for plugins to hook in and modify things that should only
		 * occur in the context of a GraphQL Request. The base class hooks into this action to
		 * kick off the schema creation, so types are not set up until this action has run!
		 */
		do_action( 'init_graphql_request' );

		// Start tracking debug log messages
		$this->debug_log = new DebugLog();

		// Set request data for passed-in (non-HTTP) requests.
		$this->data = $data;

		// Get the Type Registry
		$this->type_registry = \WPGraphQL::get_type_registry();

		// Get the Schema
		$this->schema = \WPGraphQL::get_schema();

		// Get the App Context
		$this->app_context = \WPGraphQL::get_app_context();

		$this->root_value       = $this->get_root_value();
		$this->validation_rules = $this->get_validation_rules();
		$this->field_resolver   = $this->get_field_resolver();

		/**
		 * Configure the app_context which gets passed down to all the resolvers.
		 *
		 * @since 0.0.4
		 */
		$app_context                = new AppContext();
		$app_context->viewer        = wp_get_current_user();
		$app_context->root_url      = get_bloginfo( 'url' );
		$app_context->request       = ! empty( $_REQUEST ) ? $_REQUEST : null; // phpcs:ignore
		$app_context->type_registry = $this->type_registry;
		$this->app_context          = $app_context;
	}

	/**
	 * @return null
	 */
	protected function get_field_resolver() {
		return null;
	}

	/**
	 * Return the validation rules to use in the request
	 *
	 * @return array
	 */
	protected function get_validation_rules() {

		$validation_rules = GraphQL::getStandardValidationRules();

		$validation_rules['require_authentication'] = new RequireAuthentication();
		$validation_rules['disable_introspection']  = new DisableIntrospection();
		$validation_rules['query_depth']            = new QueryDepth();

		/**
		 * Return the validation rules to use in the request
		 *
		 * @param array   $validation_rules The validation rules to use in the request
		 * @param Request $this             The Request instance
		 */
		return apply_filters( 'graphql_validation_rules', $validation_rules, $this );

	}

	/**
	 * Returns the root value to use in the request.
	 *
	 * @return mixed|null
	 */
	protected function get_root_value() {

		/**
		 * Set the root value based on what was passed to the request
		 */
		$root_value = isset( $this->data['root_value'] ) && ! empty( $this->data['root_value'] ) ? $this->data['root_value'] : null;

		/**
		 * Return the filtered root value
		 *
		 * @param mixed   $root_value The root value the Schema should use to resolve with. Default null.
		 * @param Request $this       The Request instance
		 */
		return apply_filters( 'graphql_root_value', $root_value, $this );
	}

	/**
	 * Apply filters and do actions before GraphQL execution
	 *
	 * @return void
	 * @throws Error
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

		if ( ! empty( $GLOBALS['wp_query'] ) ) {
			$this->global_wp_the_query = clone $GLOBALS['wp_the_query'];
		}

		/**
		 * If the request is a batch request it will come back as an array
		 */
		if ( is_array( $this->params ) ) {

			// If the request is a batch request, but batch requests are disabled,
			// bail early
			if ( ! $this->is_batch_queries_enabled() ) {
				throw new Error( __( 'Batch Queries are not supported', 'wp-graphql' ) );
			}

			$batch_limit = get_graphql_setting( 'batch_limit', 10 );
			$batch_limit = absint( $batch_limit ) ? absint( $batch_limit ) : 10;

			// If batch requests are enabled, but a limit is set and the request exceeds the limit
			// fail now
			if ( $batch_limit < count( $this->params ) ) {
				// translators: First placeholder is the max number of batch operations allowed in a GraphQL request. The 2nd placeholder is the number of operations requested in the current request.
				throw new Error( sprintf( __( 'Batch requests are limited to %1$d operations. This request contained %2$d', 'wp-graphql' ), absint( $batch_limit ), count( $this->params ) ) );
			}

			/**
			 * Execute batch queries
			 *
			 * @param OperationParams[] $params The operation params of the batch request
			 */
			do_action( 'graphql_execute_batch_queries', $this->params );

			// Process the batched requests
			array_walk( $this->params, [ $this, 'do_action' ] );

		} else {
			$this->do_action( $this->params );
		}

		/**
		 * This action runs before execution of a GraphQL request (regardless if it's a single or batch request)
		 *
		 * @param Request $request The instance of the Request being executed
		 */
		do_action( 'graphql_before_execute', $this );

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
	 * @return boolean
	 * @throws Exception
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
				throw new Exception( __( 'Cookie nonce is invalid', 'wp-graphql' ) );
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
	 * @param boolean $authentication_errors Whether there are authentication errors with the
	 *                                       request
	 *
	 * @return boolean
	 */
	protected function filtered_authentication_errors( $authentication_errors = false ) {

		/**
		 * If false, there are no authentication errors. If true, execution of the
		 * GraphQL request will be prevented and an error will be thrown.
		 *
		 * @param boolean $authentication_errors Whether there are authentication errors with the request
		 * @param Request $this                  Instance of the Request
		 */
		return apply_filters( 'graphql_authentication_errors', $authentication_errors, $this );
	}

	/**
	 * Performs actions and runs filters after execution completes
	 *
	 * @param mixed|array|object $response The response from execution. Array for batch requests,
	 *                                     single object for individual requests
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	private function after_execute( $response ) {

		/**
		 * If there are authentication errors, prevent execution and throw an exception.
		 */
		if ( false !== $this->has_authentication_errors() ) {
			throw new Exception( __( 'Authentication Error', 'wp-graphql' ) );
		}

		/**
		 * If the params and the $response are both arrays
		 * treat this as a batch request and map over the array to apply the
		 * after_execute_actions, otherwise apply them to the current response
		 */
		if ( is_array( $this->params ) && is_array( $response ) ) {
			$filtered_response = [];
			foreach ( $response as $key => $resp ) {
				$filtered_response[] = $this->after_execute_actions( $resp, (int) $key );
			}
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

		if ( ! empty( $this->global_wp_the_query ) ) {
			$GLOBALS['wp_the_query'] = $this->global_wp_the_query;
			wp_reset_query();
		}

		if ( ! empty( $this->global_post ) ) {
			$GLOBALS['post'] = $this->global_post;
			setup_postdata( $this->global_post );
		}

		/**
		 * Run an action after GraphQL Execution
		 *
		 * @param array   $filtered_response The response of the entire operation. Could be a single operation or a batch operation
		 * @param Request $this              Instance of the Request being executed
		 */
		do_action( 'graphql_after_execute', $filtered_response, $this );

		/**
		 * Return the filtered response
		 */
		return $filtered_response;

	}

	/**
	 * Apply filters and do actions after GraphQL execution
	 *
	 * @param mixed|array|object $response The response for your GraphQL request
	 * @param mixed|Int|null     $key      The array key of the params for batch requests
	 *
	 * @return array
	 */
	private function after_execute_actions( $response, $key = null ) {

		/**
		 * Determine which params (batch or single request) to use when passing through to the actions
		 */
		$query     = null;
		$operation = null;
		$variables = null;

		if ( $this->params instanceof OperationParams ) {
			$operation = $this->params->operation ?? '';
			$query     = $this->params->query ?? '';
			$variables = $this->params->variables ?? null;
		} elseif ( is_array( $this->params ) ) {

			$operation = $this->params[ $key ]->operation ?? '';
			$query     = $this->params[ $key ]->query ?? '';
			$variables = $this->params[ $key ]->variables ?? null;
		}

		/**
		 * Run an action. This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @param mixed|array $response  The response your GraphQL request
		 * @param WPSchema    $schema    The schema object for the root request
		 * @param string      $operation The name of the operation
		 * @param string      $query     The query that GraphQL executed
		 * @param array|null  $variables Variables to passed to your GraphQL query
		 * @param Request     $this      Instance of the Request
		 *
		 * @since 0.0.4
		 */
		do_action( 'graphql_execute', $response, $this->schema, $operation, $query, $variables, $this );

		/**
		 * Add the debug log to the request
		 */
		if ( ! empty( $response ) ) {
			if ( is_array( $response ) ) {
				$response['extensions']['debug'] = $this->debug_log->get_logs();
			} else {
				$response->extensions['debug'] = $this->debug_log->get_logs();
			}
		}

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
		 * @param array      $response  The response for your GraphQL query
		 * @param WPSchema   $schema    The schema object for the root query
		 * @param string     $operation The name of the operation
		 * @param string     $query     The query that GraphQL executed
		 * @param array|null $variables Variables to passed to your GraphQL request
		 * @param Request    $request   Instance of the Request
		 *
		 * @since 0.0.5
		 */
		$filtered_response = apply_filters( 'graphql_request_results', $response, $this->schema, $operation, $query, $variables, $this );

		/**
		 * Run an action after the response has been filtered, as the response is being returned.
		 * This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @param array      $filtered_response The filtered response for the GraphQL request
		 * @param array      $response          The response for your GraphQL request
		 * @param WPSchema   $schema            The schema object for the root request
		 * @param string     $operation         The name of the operation
		 * @param string     $query             The query that GraphQL executed
		 * @param array|null $variables         Variables to passed to your GraphQL query
		 * @param Request    $this              Instance of the Request
		 */
		do_action( 'graphql_return_response', $filtered_response, $response, $this->schema, $operation, $query, $variables, $this );

		/**
		 * Filter "is_graphql_request" back to false.
		 */
		\WPGraphQL::set_is_graphql_request( false );

		return $filtered_response;
	}

	/**
	 * Run action for a request.
	 *
	 * @param OperationParams $params OperationParams for the request.
	 *
	 * @return void
	 */
	private function do_action( OperationParams $params ) {

		/**
		 * Run an action for each request.
		 *
		 * @param string          $query     The GraphQL query
		 * @param string          $operation The name of the operation
		 * @param array           $variables Variables to be passed to your GraphQL request
		 * @param OperationParams $params    The Operation Params. This includes any extra params, such as extenions or any other modifications to the request body
		 */
		do_action( 'do_graphql_request', $params->query, $params->operation, $params->variables, $params );
	}

	/**
	 * Execute an internal request (graphql() function call).
	 *
	 * @return array
	 * @throws Exception
	 */
	public function execute() {

		$helper = new WPHelper();

		if ( ! $this->data instanceof OperationParams ) {
			$this->params = $helper->parseRequestParams( 'POST', $this->data, [] );
		} else {
			$this->params = $this->data;
		}

		if ( is_array( $this->params ) ) {
			return array_map( function ( $data ) {
				$this->data = $data;
				return $this->execute();
			}, $this->params );
		} elseif ( $this->params instanceof OperationParams ) {

			/**
			 * Initialize the GraphQL Request
			 */
			$this->before_execute();

			$response = apply_filters( 'pre_graphql_execute_request', null, $this );

			if ( null === $response ) {
				$result = \GraphQL\GraphQL::executeQuery(
					$this->schema,
					isset( $this->params->query ) ? $this->params->query : '',
					$this->root_value,
					$this->app_context,
					isset( $this->params->variables ) ? $this->params->variables : null,
					isset( $this->params->operation ) ? $this->params->operation : null,
					$this->field_resolver,
					$this->validation_rules
				);

				/**
				 * Return the result of the request
				 */
				$response = $result->toArray( $this->get_debug_flag() );
			}

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

		return [];
	}

	/**
	 * Execute an HTTP request.
	 *
	 * @return array
	 * @throws Exception
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
		$response = apply_filters( 'pre_graphql_execute_request', null, $this );

		/**
		 * If no cached response, execute the query
		 */
		if ( null === $response ) {
			$server   = $this->get_server();
			$response = $server->executeRequest( $this->params );
		}

		return $this->after_execute( $response );
	}

	/**
	 * Get the operation params for the request.
	 *
	 * @return OperationParams|OperationParams[]
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Returns the debug flag value
	 *
	 * @return int
	 */
	public function get_debug_flag() {
		$flag = DebugFlag::INCLUDE_DEBUG_MESSAGE;
		if ( 0 !== get_current_user_id() ) {
			// Flag 2 shows the trace data, which should require user to be logged in to see by default
			$flag = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
		}

		return true === \WPGraphQL::debug() ? $flag : DebugFlag::NONE;
	}

	/**
	 * Determines if batch queries are enabled for the server.
	 *
	 * Default is to have batch queries enabled.
	 *
	 * @return bool
	 */
	private function is_batch_queries_enabled() {

		$batch_queries_enabled = true;

		$batch_queries_setting = get_graphql_setting( 'batch_queries_enabled', 'on' );
		if ( 'off' === $batch_queries_setting ) {
			$batch_queries_enabled = false;
		}

		/**
		 * Filter whether batch queries are supported or not
		 *
		 * @param $batch_queries_enabled boolean Whether Batch Queries should be enabled
		 * @param OperationParams $params Request operation params
		 */
		return apply_filters( 'graphql_is_batch_queries_enabled', $batch_queries_enabled, $this->params );

	}

	/**
	 * Create the GraphQL server that will process the request.
	 *
	 * @return StandardServer
	 */
	private function get_server() {

		$debug_flag = $this->get_debug_flag();

		$config = new ServerConfig();
		$config
			->setDebugFlag( $debug_flag )
			->setSchema( $this->schema )
			->setContext( $this->app_context )
			->setValidationRules( $this->validation_rules )
			->setQueryBatching( $this->is_batch_queries_enabled() );

		if ( ! empty( $this->root_value ) ) {
			$config->setFieldResolver( $this->root_value );
		}

		if ( ! empty( $this->field_resolver ) ) {
			$config->setFieldResolver( $this->field_resolver );
		}

		/**
		 * Run an action when the server config is created. The config can be acted
		 * upon directly to override default values or implement new features, e.g.,
		 * $config->setValidationRules().
		 *
		 * @param ServerConfig    $config Server config
		 * @param OperationParams $params Request operation params
		 *
		 * @since 0.2.0
		 */
		do_action( 'graphql_server_config', $config, $this->params );

		return new StandardServer( $config );

	}
}
