<?php

namespace WPGraphQL;

use GraphQL\Error\FormattedError;
use GraphQL\Executor\ExecutionResult;
use WP_User;

/**
 * Class Router
 * This sets up the /graphql endpoint
 *
 * @package WPGraphQL
 * @since   0.0.1
 */
class Router {

	/**
	 * Sets the route to use as the endpoint
	 *
	 * @var string $route
	 */
	public static $route = 'graphql';

	/**
	 * Holds the Global Post for later resetting
	 *
	 * @var string
	 */
	protected static $global_post = '';

	/**
	 * Set the default status code to 200.
	 *
	 * @var int
	 */
	public static $http_status_code = 200;

	/**
	 * Router constructor.
	 *
	 * @since  0.0.1
	 */
	public function __construct() {

		/**
		 * Pass the route through a filter in case the endpoint /graphql should need to be changed
		 *
		 * @return string
		 * @since 0.0.1
		 */
		$filtered_endpoint = apply_filters( 'graphql_endpoint', null );
		$endpoint          = $filtered_endpoint ? $filtered_endpoint : get_graphql_setting( 'graphql_endpoint', 'graphql' );
		self::$route       = $endpoint;

		/**
		 * Create the rewrite rule for the route
		 *
		 * @since 0.0.1
		 */
		add_action( 'init', [ $this, 'add_rewrite_rule' ], 10 );

		/**
		 * Add the query var for the route
		 *
		 * @since 0.0.1
		 */
		add_filter( 'query_vars', [ $this, 'add_query_var' ], 1, 1 );

		/**
		 * Redirects the route to the graphql processor
		 *
		 * @since 0.0.1
		 */
		add_action( 'parse_request', [ $this, 'resolve_http_request' ], 10 );

		/**
		 * Adds support for application passwords
		 */
		add_filter( 'application_password_is_api_request', [ $this, 'is_api_request' ] );

	}

	/**
	 * Adds rewrite rule for the route endpoint
	 *
	 * @return void
	 * @since  0.0.1
	 * @uses   add_rewrite_rule()
	 */
	public static function add_rewrite_rule() {

		add_rewrite_rule(
			self::$route . '/?$',
			'index.php?' . self::$route . '=true',
			'top'
		);

	}

	/**
	 * Determines whether the request is an API request to play nice with
	 * application passwords and potential other WordPress core functionality
	 * for APIs
	 *
	 * @param bool $is_api_request Whether the request is an API request
	 *
	 * @return bool
	 */
	public function is_api_request( $is_api_request ) {
		return true === is_graphql_http_request() ? true : $is_api_request;
	}

	/**
	 * Adds the query_var for the route
	 *
	 * @param array $query_vars The array of whitelisted query variables.
	 *
	 * @return array
	 * @since  0.0.1
	 */
	public static function add_query_var( $query_vars ) {

		$query_vars[] = self::$route;

		return $query_vars;

	}

	/**
	 * Returns true when the current request is a GraphQL request coming from the HTTP
	 *
	 * NOTE: This will only indicate whether the GraphQL Request is an HTTP request. Many features
	 * need to affect _all_ GraphQL requests, including internal requests using the `graphql()`
	 * function, so be careful how you use this to check your conditions.
	 *
	 * @return boolean
	 */
	public static function is_graphql_http_request() {

		/**
		 * Filter whether the request is a GraphQL HTTP Request. Default is null, as the majority
		 * of WordPress requests are NOT GraphQL requests (at least today that's true 😆).
		 *
		 * If this filter returns anything other than null, the function will return now and skip the
		 * default checks.
		 *
		 * @param boolean $is_graphql_http_request Whether the request is a GraphQL HTTP Request. Default false.
		 */
		$pre_is_graphql_http_request = apply_filters( 'graphql_pre_is_graphql_http_request', null );

		/**
		 * If the filter has been applied, return now before executing default checks
		 */
		if ( null !== $pre_is_graphql_http_request ) {
			return (bool) $pre_is_graphql_http_request;
		}

		// Default is false
		$is_graphql_http_request = false;

		// Support wp-graphiql style request to /index.php?graphql.
		if ( isset( $_GET[ self::$route ] ) ) {

			$is_graphql_http_request = true;

		} else {

			// Check the server to determine if the GraphQL endpoint is being requested
			if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {

				$host = wp_unslash( $_SERVER['HTTP_HOST'] );
				$uri  = wp_unslash( $_SERVER['REQUEST_URI'] );

				if ( ! is_string( $host ) ) {
					return false;
				}

				if ( ! is_string( $uri ) ) {
					return false;
				}

				$parsed_site_url    = parse_url( site_url( self::$route ), PHP_URL_PATH );
				$graphql_url        = ! empty( $parsed_site_url ) ? wp_unslash( $parsed_site_url ) : self::$route;
				$parsed_request_url = parse_url( $uri, PHP_URL_PATH );
				$request_url        = ! empty( $parsed_request_url ) ? wp_unslash( $parsed_request_url ) : '';

				// Determine if the route is indeed a graphql request
				$is_graphql_http_request = str_replace( '/', '', $request_url ) === str_replace( '/', '', $graphql_url );

			}
		}

		/**
		 * Filter whether the request is a GraphQL HTTP Request. Default is false, as the majority
		 * of WordPress requests are NOT GraphQL requests (at least today that's true 😆).
		 *
		 * The request has to "prove" that it is indeed an HTTP request via HTTP for
		 * this to be true.
		 *
		 * Different servers _might_ have different needs to determine whether a request
		 * is a GraphQL request.
		 *
		 * @param boolean $is_graphql_http_request Whether the request is a GraphQL HTTP Request. Default false.
		 */
		return apply_filters( 'graphql_is_graphql_http_request', $is_graphql_http_request );

	}

	/**
	 * DEPRECATED: Returns whether a request is a GraphQL Request. Deprecated
	 * because it's name is a bit misleading. This will only return if the request
	 * is a GraphQL request coming from the HTTP endpoint. Internal GraphQL requests
	 * won't be able to use this to properly determine if the request is a GraphQL request
	 * or not.
	 *
	 * @return boolean
	 * @deprecated 0.4.1 Use Router::is_graphql_http_request instead. This now resolves to it
	 */
	public static function is_graphql_request() {
		return self::is_graphql_http_request();
	}

	/**
	 * This resolves the http request and ensures that WordPress can respond with the appropriate
	 * JSON response instead of responding with a template from the standard WordPress Template
	 * Loading process
	 *
	 * @return void
	 * @throws \Exception Throws exception.
	 * @throws \Throwable Throws exception.
	 * @since  0.0.1
	 */
	public static function resolve_http_request() {

		/**
		 * Access the $wp_query object
		 */
		global $wp_query;

		/**
		 * Ensure we're on the registered route for graphql route
		 */
		if ( ! self::is_graphql_http_request() || is_graphql_request() ) {
			return;
		}

		/**
		 * Set is_home to false
		 */
		$wp_query->is_home = false;

		/**
		 * Whether it's a GraphQL HTTP Request
		 *
		 * @since 0.0.5
		 */
		if ( ! defined( 'GRAPHQL_HTTP_REQUEST' ) ) {
			define( 'GRAPHQL_HTTP_REQUEST', true );
		}

		/**
		 * Process the GraphQL query Request
		 */
		self::process_http_request();
	}

	/**
	 * Sends an HTTP header.
	 *
	 * @param string $key   Header key.
	 * @param string $value Header value.
	 *
	 * @return void
	 * @since  0.0.5
	 */
	public static function send_header( $key, $value ) {

		/**
		 * Sanitize as per RFC2616 (Section 4.2):
		 *
		 * Any LWS that occurs between field-content MAY be replaced with a
		 * single SP before interpreting the field value or forwarding the
		 * message downstream.
		 */
		$value = preg_replace( '/\s+/', ' ', $value );
		header( apply_filters( 'graphql_send_header', sprintf( '%s: %s', $key, $value ), $key, $value ) );
	}

	/**
	 * Sends an HTTP status code.
	 *
	 * @return void
	 */
	protected static function set_status() {
		status_header( self::$http_status_code );
	}

	/**
	 * Returns an array of headers to send with the HTTP response
	 *
	 * @return array
	 */
	protected static function get_response_headers() {

		/**
		 * Filtered list of access control headers.
		 *
		 * @param array $access_control_headers Array of headers to allow.
		 */
		$access_control_allow_headers = apply_filters(
			'graphql_access_control_allow_headers',
			[
				'Authorization',
				'Content-Type',
			]
		);

		$headers = [
			'Access-Control-Allow-Origin'  => '*',
			'Access-Control-Allow-Headers' => implode( ', ', $access_control_allow_headers ),
			'Access-Control-Max-Age'       => 600,
			// cache the result of preflight requests (600 is the upper limit for Chromium).
			'Content-Type'                 => 'application/json ; charset=' . get_option( 'blog_charset' ),
			'X-Robots-Tag'                 => 'noindex',
			'X-Content-Type-Options'       => 'nosniff',
		];

		if ( true === \WPGraphQL::debug() ) {
			$headers['X-hacker'] = __( 'If you\'re reading this, you should visit github.com/wp-graphql/wp-graphql and contribute!', 'wp-graphql' );
		}

		/**
		 * Send nocache headers on authenticated requests.
		 *
		 * @param bool $rest_send_nocache_headers Whether to send no-cache headers.
		 *
		 * @since 0.0.5
		 */
		$send_no_cache_headers = apply_filters( 'graphql_send_nocache_headers', is_user_logged_in() );
		if ( $send_no_cache_headers ) {
			foreach ( wp_get_nocache_headers() as $no_cache_header_key => $no_cache_header_value ) {
				$headers[ $no_cache_header_key ] = $no_cache_header_value;
			}
		}

		/**
		 * Filter the $headers to send
		 */
		return apply_filters( 'graphql_response_headers_to_send', $headers );
	}

	/**
	 * Set the response headers
	 *
	 * @return void
	 * @since  0.0.1
	 */
	public static function set_headers() {

		if ( false === headers_sent() ) {

			/**
			 * Set the HTTP response status
			 */
			self::set_status();

			/**
			 * Get the response headers
			 */
			$headers = self::get_response_headers();

			/**
			 * If there are headers, set them for the response
			 */
			if ( ! empty( $headers ) && is_array( $headers ) ) {

				foreach ( $headers as $key => $value ) {
					self::send_header( $key, $value );
				}
			}

			/**
			 * Fire an action when the headers are set
			 *
			 * @param array $headers The headers sent in the response
			 */
			do_action( 'graphql_response_set_headers', $headers );

		}
	}

	/**
	 * Retrieves the raw request entity (body).
	 *
	 * @return string Raw request data.
	 * @global string php://input Raw post data.
	 * @since  0.0.5
	 */
	public static function get_raw_data() {
		$input = file_get_contents( 'php://input' );

		return ! empty( $input ) ? $input : '';
	}


	/**
	 * This processes the graphql requests that come into the /graphql endpoint via an HTTP request
	 *
	 * @return mixed
	 * @throws \Exception Throws Exception.
	 * @throws \Throwable Throws Exception.
	 * @global WP_User $current_user The currently authenticated user.
	 * @since  0.0.1
	 */
	public static function process_http_request() {
		/* @var WP_User|null $current_user */
		global $current_user;

		if ( $current_user instanceof WP_User && ! $current_user->exists() ) {
			/*
			 * If there is no current user authenticated via other means, clear
			 * the cached lack of user, so that an authenticate check can set it
			 * properly.
			 *
			 * This is done because for authentications such as Application
			 * Passwords, we don't want it to be accepted unless the current HTTP
			 * request is a GraphQL API request, which can't always be identified early
			 * enough in evaluation.
			 *
			 * See serve_request in wp-includes/rest-api/class-wp-rest-server.php.
			 */
			$current_user = null;
		}

		/**
		 * This action can be hooked to to enable various debug tools,
		 * such as enableValidation from the GraphQL Config.
		 *
		 * @since 0.0.4
		 */
		do_action( 'graphql_process_http_request' );

		/**
		 * Respond to pre-flight requests.
		 *
		 * @see: https://apollographql.slack.com/archives/C10HTKHPC/p1507649812000123
		 * @see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS#Preflighted_requests
		 */
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			self::$http_status_code = 200;
			self::set_headers();
			exit;
		}

		$query          = '';
		$operation_name = '';
		$variables      = [];
		$request        = new Request();

		try {

			$response = $request->execute_http();

			// Get the operation params from the request.
			$params         = $request->get_params();
			$query          = isset( $params->query ) ? $params->query : '';
			$operation_name = isset( $params->operation ) ? $params->operation : '';
			$variables      = isset( $params->variables ) ? $params->variables : null;

		} catch ( \Exception $error ) {

			/**
			 * If there are errors, set the status to 500
			 * and format the captured errors to be output properly
			 *
			 * @since 0.0.4
			 */
			self::$http_status_code = 500;

			/**
			 * Filter thrown GraphQL errors
			 *
			 * @param array              $errors   Formatted errors object.
			 * @param Exception          $error    Thrown error.
			 * @param \WPGraphQL\Request $request  WPGraphQL Request object.
			 */
			$response['errors'] = apply_filters(
				'graphql_http_request_response_errors',
				[ FormattedError::createFromException( $error, $request->get_debug_flag() ) ],
				$error,
				$request
			);
		} // End try().

		// Previously there was a small distinction between the response and the result, but
		// now that we are delegating to Request, just send the response for both.
		$result = $response;

		if ( false === headers_sent() ) {
			self::prepare_headers( $response, $result, $query, $operation_name, $variables );
		}

		/**
		 * Run an action after the HTTP Response is ready to be sent back. This might be a good place for tools
		 * to hook in to track metrics, such as how long the process took from `graphql_process_http_request`
		 * to here, etc.
		 *
		 * @param array  $response       The GraphQL response
		 * @param array  $result         The result of the GraphQL Query
		 * @param string $operation_name The name of the operation
		 * @param string $query          The request that GraphQL executed
		 * @param array  $variables      Variables to passed to your GraphQL query
		 * @param mixed  $status_code    The status code for the response
		 *
		 * @since 0.0.5
		 */
		do_action( 'graphql_process_http_request_response', $response, $result, $operation_name, $query, $variables, self::$http_status_code );

		/**
		 * Send the response
		 */
		wp_send_json( $response );

	}

	/**
	 * Prepare headers for response
	 *
	 * @param mixed|array|ExecutionResult $response        The response of the GraphQL Request.
	 * @param mixed|array|ExecutionResult $graphql_results The results of the GraphQL execution.
	 * @param string                      $query           The GraphQL query.
	 * @param string                      $operation_name  The operation name of the GraphQL
	 *                                                     Request.
	 * @param mixed|array|null            $variables       The variables applied to the GraphQL
	 *                                                     Request.
	 * @param mixed|WP_User|null          $user            The current user object.
	 *
	 * @return void
	 */
	protected static function prepare_headers( $response, $graphql_results, string $query, string $operation_name, $variables, $user = null ) {

		/**
		 * Filter the $status_code before setting the headers
		 *
		 * @param int     $status_code     The status code to apply to the headers
		 * @param array   $response        The response of the GraphQL Request
		 * @param array   $graphql_results The results of the GraphQL execution
		 * @param string  $query           The GraphQL query
		 * @param string  $operation_name  The operation name of the GraphQL Request
		 * @param array   $variables       The variables applied to the GraphQL Request
		 * @param WP_User $user            The current user object
		 */
		self::$http_status_code = apply_filters( 'graphql_response_status_code', self::$http_status_code, $response, $graphql_results, $query, $operation_name, $variables, $user );

		/**
		 * Set the response headers
		 */
		self::set_headers();

	}
}
