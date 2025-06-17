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
use WPGraphQL\Server\ValidationRules\QueryDepth;
use WPGraphQL\Server\ValidationRules\RequireAuthentication;
use WPGraphQL\Server\WPHelper;
use WPGraphQL\Utils\DebugLog;
use WPGraphQL\Utils\QueryAnalyzer;

/**
 * Class Request
 *
 * Proxies a request to graphql-php, applying filters and transforming request
 * data as needed.
 *
 * @package WPGraphQL
 *
 * phpcs:disable -- PHPStan annotation.
 * @phpstan-import-type RootValueResolver from \GraphQL\Server\ServerConfig
 * @phpstan-import-type SerializableResult from \GraphQL\Executor\ExecutionResult
 * phpcs:enable
 */
class Request {

	/**
	 * App context for this request.
	 *
	 * @var \WPGraphQL\AppContext
	 */
	public $app_context;

	/**
	 * Request data.
	 *
	 * @var array<string,mixed>|\GraphQL\Server\OperationParams
	 */
	public $data;

	/**
	 * Cached global post.
	 *
	 * @var ?\WP_Post
	 */
	public $global_post;

	/**
	 * Cached global wp_the_query.
	 *
	 * @var ?\WP_Query
	 */
	private $global_wp_the_query;

	/**
	 * GraphQL operation parameters for this request.
	 * Will be an array of OperationParams if this is a batch request.
	 *
	 * @var \GraphQL\Server\OperationParams|\GraphQL\Server\OperationParams[]
	 */
	public $params;

	/**
	 * Schema for this request.
	 *
	 * @var \WPGraphQL\WPSchema
	 */
	public $schema;

	/**
	 * Debug log for WPGraphQL Requests
	 *
	 * @var \WPGraphQL\Utils\DebugLog
	 */
	public $debug_log;

	/**
	 * The Type Registry the Schema is built with
	 *
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	public $type_registry;

	/**
	 * Validation rules for execution.
	 *
	 * @var array<string,\GraphQL\Validator\Rules\ValidationRule>
	 */
	protected $validation_rules;

	/**
	 * The default field resolver function. Default null
	 *
	 * @var callable|null
	 */
	protected $field_resolver;

	/**
	 * The root value of the request. Default null;
	 *
	 * @var mixed|RootValueResolver
	 */
	protected $root_value;

	/**
	 * @var \WPGraphQL\Utils\QueryAnalyzer
	 */
	protected $query_analyzer;

	/**
	 * Constructor
	 *
	 * @param array<string,mixed> $data The request data (for Non-HTTP requests).
	 *
	 * @return void
	 *
	 * @throws \Exception
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

		// Get the App Context
		$this->app_context = \WPGraphQL::get_app_context();

		$this->root_value       = $this->get_root_value();
		$this->validation_rules = $this->get_validation_rules();
		$this->field_resolver   = $this->get_field_resolver();

		// Inject the type registry into the app context.
		$this->app_context->type_registry = $this->type_registry;

		// The query analyzer tracks nodes, models, list types and more
		// to return in headers and debug messages to help developers understand
		// what was resolved, how to cache it, etc.
		$this->query_analyzer = new QueryAnalyzer( $this );
		$this->query_analyzer->init();
	}

	/**
	 * Get the instance of the Query Analyzer
	 */
	public function get_query_analyzer(): QueryAnalyzer {
		return $this->query_analyzer;
	}

	/**
	 * @return callable|null
	 */
	protected function get_field_resolver() {
		return $this->field_resolver;
	}

	/**
	 * Return the validation rules to use in the request
	 *
	 * @return array<string,\GraphQL\Validator\Rules\ValidationRule>
	 */
	protected function get_validation_rules(): array {
		$validation_rules = GraphQL::getStandardValidationRules();

		$validation_rules['require_authentication'] = new RequireAuthentication();
		$validation_rules['disable_introspection']  = new DisableIntrospection();
		$validation_rules['query_depth']            = new QueryDepth();

		/**
		 * Return the validation rules to use in the request
		 *
		 * @param array<string,\GraphQL\Validator\Rules\ValidationRule> $validation_rules The validation rules to use in the request
		 * @param \WPGraphQL\Request                                        $request          The Request instance
		 */
		return apply_filters( 'graphql_validation_rules', $validation_rules, $this );
	}

	/**
	 * Returns the root value to use in the request.
	 *
	 * @return mixed|RootValueResolver|null
	 */
	protected function get_root_value() {
		/**
		 * Set the root value based on what was passed to the request
		 */
		$root_value = is_array( $this->data ) && ! empty( $this->data['root_value'] ) ? $this->data['root_value'] : null;

		/**
		 * Return the filtered root value
		 *
		 * @param mixed|RootValueResolver $root_value The root value the Schema should use to resolve with. Default null.
		 * @param \WPGraphQL\Request      $request    The Request instance
		 */
		return apply_filters( 'graphql_root_value', $root_value, $this );
	}

	/**
	 * Apply filters and do actions before GraphQL execution
	 *
	 * @throws \GraphQL\Error\Error
	 */
	private function before_execute(): void {

		/**
		 * Store the global post so that it can be reset after GraphQL execution
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
				throw new Error( esc_html__( 'Batch Queries are not supported', 'wp-graphql' ) );
			}

			$batch_limit = get_graphql_setting( 'batch_limit', 10 );
			$batch_limit = absint( $batch_limit ) ? absint( $batch_limit ) : 10;

			// If batch requests are enabled, but a limit is set and the request exceeds the limit
			// fail now
			if ( $batch_limit < count( $this->params ) ) {
				// translators: First placeholder is the max number of batch operations allowed in a GraphQL request. The 2nd placeholder is the number of operations requested in the current request.
				throw new Error( sprintf( esc_html__( 'Batch requests are limited to %1$d operations. This request contained %2$d', 'wp-graphql' ), absint( $batch_limit ), count( $this->params ) ) );
			}

			/**
			 * Execute batch queries
			 *
			 * @param \GraphQL\Server\OperationParams[] $params The operation params of the batch request
			 */
			do_action( 'graphql_execute_batch_queries', $this->params );

			// Process the batched requests
			array_walk( $this->params, [ $this, 'do_action' ] );
		} else {
			$this->do_action( $this->params );
		}

		// Get the Schema
		$this->schema = \WPGraphQL::get_schema();

		/**
		 * This action runs before execution of a GraphQL request (regardless if it's a single or batch request)
		 *
		 * @param \WPGraphQL\Request $request The instance of the Request being executed
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
	 * @return bool
	 * @throws \Exception
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
		}

		/**
		 * If the user is not logged in, determine if there's a nonce
		 */
		$nonce = null;

		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = $_REQUEST['_wpnonce']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = $_SERVER['HTTP_X_WP_NONCE']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( null === $nonce ) {
			// No nonce at all, so act as if it's an unauthenticated request.
			wp_set_current_user( 0 );

			return $this->filtered_authentication_errors( $authentication_errors );
		}

		// Check the nonce.
		$result = wp_verify_nonce( $nonce, 'wp_rest' );

		if ( ! $result ) {
			throw new Exception( esc_html__( 'Cookie nonce is invalid', 'wp-graphql' ) );
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
	 * @param bool $authentication_errors Whether there are authentication errors with the request.
	 *
	 * @return bool
	 */
	protected function filtered_authentication_errors( $authentication_errors = false ) {

		/**
		 * If false, there are no authentication errors. If true, execution of the
		 * GraphQL request will be prevented and an error will be thrown.
		 *
		 * @param bool $authentication_errors Whether there are authentication errors with the request
		 * @param \WPGraphQL\Request $request Instance of the Request
		 */
		return apply_filters( 'graphql_authentication_errors', $authentication_errors, $this );
	}

	/**
	 * Performs actions and runs filters after execution completes
	 *
	 * @template T from (SerializableResult|SerializableResult[])|(\GraphQL\Executor\ExecutionResult|array<int,\GraphQL\Executor\ExecutionResult>)
	 *
	 * @param T $response The response from execution.  Array for batch requests, single object for individual requests.
	 *
	 * @return T
	 *
	 * @throws \Exception
	 */
	private function after_execute( $response ) {

		/**
		 * If there are authentication errors, prevent execution and throw an exception.
		 */
		if ( false !== $this->has_authentication_errors() ) {
			throw new Exception( esc_html__( 'Authentication Error', 'wp-graphql' ) );
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
			$GLOBALS['wp_the_query'] = $this->global_wp_the_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query
		}

		if ( ! empty( $this->global_post ) ) {
			$GLOBALS['post'] = $this->global_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $this->global_post );
		}

		/**
		 * Run an action after GraphQL Execution
		 *
		 * @param mixed[]            $filtered_response The response of the entire operation. Could be a single operation or a batch operation
		 * @param \WPGraphQL\Request $request           Instance of the Request being executed
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
	 * @param mixed|array<string,mixed>|object $response The response for your GraphQL request
	 * @param int|null                         $key      The array key of the params for batch requests
	 *
	 * @return mixed|array<string,mixed>|object
	 */
	private function after_execute_actions( $response, $key = null ) {

		/**
		 * Determine which params (batch or single request) to use when passing through to the actions
		 */
		$query     = null;
		$operation = null;
		$variables = null;
		$query_id  = null;

		if ( $this->params instanceof OperationParams ) {
			$operation = $this->params->operation;
			$query     = $this->params->query;
			$query_id  = $this->params->queryId;
			$variables = $this->params->variables;
		} elseif ( is_array( $this->params ) ) {
			$operation = $this->params[ $key ]->operation ?? '';
			$query     = $this->params[ $key ]->query ?? '';
			$query_id  = $this->params[ $key ]->queryId ?? null;
			$variables = $this->params[ $key ]->variables ?? null;
		}

		/**
		 * Run an action. This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @param mixed|array<string,mixed>|object $response  The response your GraphQL request
		 * @param \WPGraphQL\WPSchema              $schema    The schema object for the root request
		 * @param ?string                          $operation The name of the operation
		 * @param ?string                          $query     The query that GraphQL executed
		 * @param ?array<string,mixed>             $variables Variables to passed to your GraphQL query
		 * @param \WPGraphQL\Request               $request   Instance of the Request
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
		 * @param mixed|array<string,mixed>|object $response  The response for your GraphQL query
		 * @param \WPGraphQL\WPSchema              $schema    The schema object for the root request
		 * @param ?string                          $operation The name of the operation
		 * @param ?string                          $query     The query that GraphQL executed
		 * @param ?array<string,mixed>             $variables Variables to passed to your GraphQL query
		 * @param \WPGraphQL\Request               $request   Instance of the Request
		 * @param ?string                          $query_id  The query id that GraphQL executed
		 *
		 * @since 0.0.5
		 */
		$filtered_response = apply_filters( 'graphql_request_results', $response, $this->schema, $operation, $query, $variables, $this, $query_id );

		/**
		 * Run an action after the response has been filtered, as the response is being returned.
		 * This is a good place for debug tools to hook in to log things, etc.
		 *
		 * @param mixed|array<string,mixed>|object $filtered_response The filtered response for the GraphQL request
		 * @param mixed|array<string,mixed>|object $response          The response for your GraphQL request
		 * @param \WPGraphQL\WPSchema              $schema            The schema object for the root request
		 * @param ?string                          $operation         The name of the operation
		 * @param ?string                          $query             The query that GraphQL executed
		 * @param ?array<string,mixed>             $variables         Variables to passed to your GraphQL query
		 * @param \WPGraphQL\Request               $request           Instance of the Request
		 * @param ?string                          $query_id          The query id that GraphQL executed
		 */
		do_action( 'graphql_return_response', $filtered_response, $response, $this->schema, $operation, $query, $variables, $this, $query_id );

		/**
		 * Filter "is_graphql_request" back to false.
		 */
		\WPGraphQL::set_is_graphql_request( false );

		return $filtered_response;
	}

	/**
	 * Run action for a request.
	 *
	 * @param \GraphQL\Server\OperationParams $params OperationParams for the request.
	 */
	private function do_action( OperationParams $params ): void {

		/**
		 * Run an action for each request.
		 *
		 * @param ?string                         $query     The GraphQL query
		 * @param ?string                         $operation The name of the operation
		 * @param ?array<string,mixed>            $variables Variables to be passed to your GraphQL request
		 * @param \GraphQL\Server\OperationParams $params    The Operation Params. This includes any extra params,
		 *                                                   such as extensions or any other modifications to the request body
		 */
		do_action( 'do_graphql_request', $params->query, $params->operation, $params->variables, $params );
	}

	/**
	 * Execute an internal request (graphql() function call).
	 *
	 * @return mixed[]
	 * @phpstan-return SerializableResult|SerializableResult[]|mixed[]
	 * @throws \Exception
	 */
	public function execute() {
		$helper = new WPHelper();

		if ( ! $this->data instanceof OperationParams ) {
			$this->params = $helper->parseRequestParams( 'POST', $this->data, [] );
		} else {
			$this->params = $this->data;
		}

		if ( is_array( $this->params ) ) {
			return array_map(
				function ( $data ) {
					$this->data = $data;
					return $this->execute();
				},
				$this->params
			);
		}

		// If $this->params isn't an array or an OperationParams instance, then something probably went wrong.
		if ( ! $this->params instanceof OperationParams ) {
			throw new \Exception( 'Invalid request params.' );
		}

		/**
		 * Initialize the GraphQL Request
		 */
		$this->before_execute();

		/**
		 * Filter this to be anything other than null to short-circuit the request.
		 *
		 * @param ?SerializableResult $response
		 * @param self               $request
		 */
		$response = apply_filters( 'pre_graphql_execute_request', null, $this );

		if ( null === $response ) {
			/**
			 * @var \GraphQL\Server\OperationParams $params
			 */
			$params = $this->params;

			/**
			 * Allow the query string to be determined by a filter. Ex, when params->queryId is present, query can be retrieved.
			 *
			 * @param string                          $query
			 * @param \GraphQL\Server\OperationParams $params
			 */
			$query = apply_filters(
				'graphql_execute_query_params',
				$params->query ?? '',
				$params
			);

			$result = GraphQL::executeQuery(
				$this->schema,
				$query,
				$this->root_value,
				$this->app_context,
				$params->variables ?? null,
				$params->operation ?? null,
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

	/**
	 * Execute an HTTP request.
	 *
	 * @return SerializableResult|(\GraphQL\Executor\ExecutionResult|array<int,\GraphQL\Executor\ExecutionResult>)
	 * @throws \Exception
	 */
	public function execute_http() {
		if ( ! $this->is_valid_http_content_type() ) {
			return $this->get_invalid_content_type_response();
		}

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
	 * Validates the content type for HTTP POST requests
	 */
	private function is_valid_http_content_type(): bool {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return true;
		}

		$content_type = $this->get_content_type();
		if ( empty( $content_type ) ) {
			return false;
		}

		$is_valid = 0 === stripos( $content_type, 'application/json' );

		/**
		 * Allow graphql to validate custom content types for HTTP POST requests
		 *
		 * @param bool $is_valid Whether the content type is valid
		 * @param string $content_type The content type header value that was received
		 *
		 * @since 2.1.0
		 */
		return (bool) apply_filters( 'graphql_is_valid_http_content_type', $is_valid, $content_type );
	}

	/**
	 * Gets the content type from the request headers
	 */
	private function get_content_type(): string {
		if ( isset( $_SERVER['CONTENT_TYPE'] ) ) {
			return sanitize_text_field( $_SERVER['CONTENT_TYPE'] );
		}

		if ( isset( $_SERVER['HTTP_CONTENT_TYPE'] ) ) {
			return sanitize_text_field( $_SERVER['HTTP_CONTENT_TYPE'] );
		}

		return '';
	}

	/**
	 * Returns the error response for invalid content type
	 *
	 * @return array{errors: array{array{message: string}}}
	 */
	private function get_invalid_content_type_response(): array {
		$content_type = $this->get_content_type();

		/**
		 * Filter the status code to return when the content type is invalid
		 *
		 * @param int    $status_code The status code to return. Default 415.
		 * @param string $content_type The content type header value that was received.
		 */
		$filtered_status_code = apply_filters( 'graphql_invalid_content_type_status_code', 415, $content_type );

		// Set the status code to the filtered value if it's a valid status code.
		if ( is_numeric( $filtered_status_code ) ) {
			$filtered_status_code = (int) $filtered_status_code;

			if ( $filtered_status_code > 100 && $filtered_status_code < 599 ) {
				Router::$http_status_code = $filtered_status_code;
			}
		}

		return [
			'errors' => [
				[
					// translators: %s is the content type header value that was received
					'message' => sprintf( esc_html__( 'HTTP POST requests must have Content-Type: application/json header. Received: %s', 'wp-graphql' ), $content_type ),
				],
			],
		];
	}

	/**
	 * Get the operation params for the request.
	 *
	 * @return \GraphQL\Server\OperationParams|\GraphQL\Server\OperationParams[]
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
	 */
	private function is_batch_queries_enabled(): bool {
		$batch_queries_enabled = true;

		$batch_queries_setting = get_graphql_setting( 'batch_queries_enabled', 'on' );
		if ( 'off' === $batch_queries_setting ) {
			$batch_queries_enabled = false;
		}

		/**
		 * Filter whether batch queries are supported or not
		 *
		 * @param bool                                                              $batch_queries_enabled Whether Batch Queries should be enabled
		 * @param \GraphQL\Server\OperationParams|\GraphQL\Server\OperationParams[] $params Request operation params
		 */
		return (bool) apply_filters( 'graphql_is_batch_queries_enabled', $batch_queries_enabled, $this->params );
	}

	/**
	 * Create the GraphQL server that will process the request.
	 */
	private function get_server(): StandardServer {
		$debug_flag = $this->get_debug_flag();

		$config = new ServerConfig();
		$config
			->setDebugFlag( $debug_flag )
			->setSchema( $this->schema )
			->setContext( $this->app_context )
			->setValidationRules( $this->validation_rules )
			->setQueryBatching( $this->is_batch_queries_enabled() );

		if ( ! empty( $this->root_value ) ) {
			$config->setRootValue( $this->root_value );
		}

		if ( ! empty( $this->field_resolver ) ) {
			$config->setFieldResolver( $this->field_resolver );
		}

		/**
		 * Run an action when the server config is created. The config can be acted
		 * upon directly to override default values or implement new features, e.g.,
		 * $config->setValidationRules().
		 *
		 * @param \GraphQL\Server\ServerConfig                                      $config Server config
		 * @param \GraphQL\Server\OperationParams|\GraphQL\Server\OperationParams[] $params Request operation params
		 *
		 * @since 0.2.0
		 */
		do_action( 'graphql_server_config', $config, $this->params );

		return new StandardServer( $config );
	}
}
