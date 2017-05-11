<?php
namespace WPGraphQL;

use GraphQL\Error\FormattedError;

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
	 * @access public
	 */
	public static $route = 'graphql';

	/**
	 * Router constructor.
	 *
	 * @since  0.0.1
	 * @access public
	 */
	public function __construct() {

		/**
		 * Pass the route through a filter in case the endpoint /graphql should need to be changed
		 *
		 * @since 0.0.1
		 * @return string
		 */
		self::$route = apply_filters( 'graphql_endpoint', 'graphql' );

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
		add_filter( 'query_vars', [ $this, 'add_query_var' ], 10, 1 );

		/**
		 * Redirects the route to the graphql processor
		 *
		 * @since 0.0.1
		 */
		add_action( 'template_redirect', [ $this, 'resolve_http_request' ], 100 );

	}

	/**
	 * Adds rewrite rule for the route endpoint
	 *
	 * @uses   add_rewrite_rule()
	 * @since  0.0.1
	 * @access public
	 * @return void
	 */
	public function add_rewrite_rule() {

		add_rewrite_rule(
			self::$route . '/?$',
			'index.php?' . self::$route . '=true',
			'top'
		);

	}

	/**
	 * Adds the query_var for the route
	 *
	 * @param array $query_vars The array of whitelisted query variables
	 *
	 * @access public
	 * @since  0.0.1
	 * @return array
	 */
	public function add_query_var( $query_vars ) {

		$query_vars[] = self::$route;

		return $query_vars;

	}

	/**
	 * This resolves the http request and ensures that WordPress can respond with the appropriate
	 * JSON response instead of responding with a template from the standard WordPress Template
	 * Loading process
	 *
	 * @since  0.0.1
	 * @access public
	 * @return void
	 */
	public function resolve_http_request() {

		/**
		 * Access the $wp_query object
		 */
		global $wp_query;

		/**
		 * Ensure we're on the registered route for graphql route
		 */
		if ( ! $wp_query->get( self::$route ) ) {
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
		$this->process_http_request();

		return;

	}

	/**
	 * Sends an HTTP header.
	 *
	 * @since  0.0.5
	 * @access public
	 *
	 * @param string $key   Header key.
	 * @param string $value Header value.
	 */
	public function send_header( $key, $value ) {
		/*
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
	 * @since  0.0.5
	 * @access protected
	 *
	 * @param int $code HTTP status.
	 */
	protected function set_status( $code ) {
		status_header( $code );
	}

	/**
	 * Set the response headers
	 *
	 * @param int $http_status The status code to send as a header
	 *
	 * @since  0.0.1
	 * @access public
	 * @return void
	 */
	public function set_headers( $http_status ) {

		$this->set_status( $http_status );
		$this->send_header( 'Access-Control-Allow-Origin', '*' );
		$this->send_header( 'Access-Control-Allow-Headers', 'content-type' );
		$this->send_header( 'Content-Type', 'application/json ; charset=' . get_option( 'blog_charset' ) );
		$this->send_header( 'X-Robots-Tag', 'noindex' );
		$this->send_header( 'X-Content-Type-Options', 'nosniff' );
		$this->send_header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type' );
		$this->send_header( 'X-hacker', __( 'If you\'re reading this, you should visit github.com/wp-graphql and contribute!', 'wp-graphql' ) );

		/**
		 * Send nocache headers on authenticated requests.
		 *
		 * @since 0.0.5
		 *
		 * @param bool $rest_send_nocache_headers Whether to send no-cache headers.
		 */
		$send_no_cache_headers = apply_filters( 'graphql_send_nocache_headers', is_user_logged_in() );
		if ( $send_no_cache_headers ) {
			foreach ( wp_get_nocache_headers() as $header => $header_value ) {
				$this->send_header( $header, $header_value );
			}
		}

	}

	/**
	 * Retrieves the raw request entity (body).
	 *
	 * @since  0.0.5
	 * @access public
	 * @global string $HTTP_RAW_POST_DATA Raw post data.
	 * @return string Raw request data.
	 */
	public static function get_raw_data() {

		global $HTTP_RAW_POST_DATA;

		/*
		 * A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
		 * but we can do it ourself.
		 */
		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;

	}

	/**
	 * This processes the graphql requests that come into the /graphql endpoint via an HTTP request
	 *
	 * @since  0.0.1
	 * @access public
	 * @return mixed
	 */
	public function process_http_request() {

		/**
		 * This action can be hooked to to enable various debug tools,
		 * such as enableValidation from the GraphQL Config.
		 *
		 * @since 0.0.4
		 */
		do_action( 'graphql_process_http_request' );

		/**
		 * Start the $response array to return for the response content
		 *
		 * @since 0.0.5
		 */
		$response        = [];
		$graphql_results = [];

		try {

			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				$response['errors'] = __( 'WPGraphQL requires POST requests', 'wp-graphql' );
			}

			if ( ! isset( $_SERVER['CONTENT_TYPE'] ) || false === strpos( $_SERVER['CONTENT_TYPE'], 'application/json' ) ) {
				$response['errors'] = __( 'WPGraphQL requires POST requests', 'wp-graphql' );
			}

			/**
			 * Retrieve the raw data from the request and encode it to JSON
			 *
			 * @since 0.0.5
			 */
			$data = json_decode( $this->get_raw_data(), true );

			/**
			 * If the $data is empty, catch an error.
			 */
			if ( empty( $data ) ) {
				$response['errors'] = __( 'GraphQL Queries must be a POST Request with a valid query', 'wp-graphql' );
			}

			$request        = isset( $data['query'] ) ? $data['query'] : '';
			$operation_name = isset( $data['operationName'] ) ? $data['operationName'] : '';
			$variables      = isset( $data['variables'] ) ? $data['variables'] : '';

			/**
			 * Process the GraphQL request
			 *
			 * @since 0.0.5
			 */
			$graphql_results = do_graphql_request( $request, $operation_name, $variables );

			/**
			 * Ensure the $graphql_request is returned as a proper, populated array,
			 * otherwise add an error to the result
			 */
			if ( ! empty( $graphql_results ) && is_array( $graphql_results ) ) {
				$response = $graphql_results;
			} else {
				$response['errors'] = __( 'The GraphQL request returned an invalid response', 'wp-graphql' );
			}

			/**
			 * Set the status code to 200
			 */
			$http_status_code = 200;

		} catch ( \Exception $error ) {

			/**
			 * If there are errors, set the status to 500
			 * and format the captured errors to be output properly
			 *
			 * @since 0.0.4
			 */
			$http_status_code = 500;
			if ( defined( 'GRAPHQL_DEBUG' ) && true === GRAPHQL_DEBUG ) {
				$response['extensions']['exception'] = FormattedError::createFromException( $error );
			} else {
				$response['errors'] = [ FormattedError::create( 'Unexpected error' ) ];
			}
		} // End try().

		/**
		 * Run an action after the HTTP Response is ready to be sent back. This might be a good place for tools
		 * to hook in to track metrics, such as how long the process took from `graphql_process_http_request`
		 * to here, etc.
		 *
		 * @since 0.0.5
		 */
		do_action( 'graphql_process_http_request_response', $response, $graphql_results );

		/**
		 * Set the response headers
		 */
		$this->set_headers( $http_status_code );
		wp_send_json( $response );

	}

}
