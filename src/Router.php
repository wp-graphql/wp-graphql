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
	public $route = 'graphql';

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
		$this->route = apply_filters( 'graphql_endpoint', 'graphql' );

		/**
		 * Create the rewrite rule for the route
		 * @since 0.0.1
		 */
		add_action( 'init', [ $this, 'add_rewrite_rule' ], 10 );

		/**
		 * Add the query var for the route
		 * @since 0.0.1
		 */
		add_filter( 'query_vars', [ $this, 'add_query_var' ], 10, 1 );

		/**
		 * Redirects the route to the graphql processor
		 * @since 0.0.1
		 */
		add_action( 'template_redirect', [ $this, 'resolve_http_request' ], 10 );

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
			$this->route . '/?$',
			'index.php?' . $this->route . '=true',
			'top'
		);

	}

	/**
	 * Adds the query_var for the route
	 *
	 * @param array $query_vars The array of whitelisted query variables
	 * @access public
	 * @since  0.0.1
	 * @return array
	 */
	public function add_query_var( $query_vars ) {

		$query_vars[] = $this->route;

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
		if ( ! $wp_query->get( $this->route ) ) {
			return;
		}

		/**
		 * Set is_home to false
		 */
		$wp_query->is_home = false;

		/**
		 * Whether it's a GraphQL HTTP Request
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
	 * Set the response headers
	 *
	 * @param int $http_status The status code to send as a header
	 * @since  0.0.1
	 * @access public
	 * @return void
	 */
	public function set_headers( $http_status ) {

		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Headers: content-type' );
		header( 'Content-Type: application/json', true, $http_status );

	}

	/**
	 * This processes the graphql requests that come into the /graphql endpoint via an HTTP request
	 *
	 * @todo   : This needs to be re-worked to be a little more robust. Probably would be good to
	 *         check out the REST API implementation on processing and responding to HTTP requests.
	 * @since  0.0.1
	 * @access public
	 * @return mixed
	 */
	public function process_http_request() {

		/**
		 * This action can be hooked to to enable various debug tools,
		 * such as enableValidation from the GraphQL Config.
		 * @since 0.0.4
		 */
		do_action( 'graphql_process_http_request' );

		try {

			if ( isset( $_SERVER['CONTENT_TYPE'] ) && strpos( $_SERVER['CONTENT_TYPE'], 'application/json' ) !== false ) {
				$raw = file_get_contents( 'php://input' ) ? : '';
				$data = json_decode( $raw, true );
			}

			if ( empty( $data ) ) {
				$result['errors'] = __( 'GraphQL Queries must be a POST Request with a valid query', 'wp-graphql' );
			}

			/**
			 * Process the GraphQL request
			 * @since 0.0.5
			 */
			$result = do_graphql_request( $data['query'], $data['variables'] );

			/**
			 * Set the status code to 200
			 */
			$http_status = 200;

		} catch ( \Exception $error ) {

			/**
			 * If there are errors, set the status to 500
			 * and format the captured errors to be output properly
			 * @since 0.0.4
			 */
			$http_status = 500;
			if ( defined( 'GRAPHQL_DEBUG' ) && true === GRAPHQL_DEBUG ) {
				$result['extensions']['exception'] = FormattedError::createFromException( $error );
			} else {
				$result['errors'] = [ FormattedError::create( 'Unexpected error' ) ];
			}
		}

		/**
		 * Set the response headers
		 */
		$this->set_headers( $http_status );
		wp_send_json( $result );

	}

}
