<?php
namespace DFM\WPGraphQL;

use DFM\WPGraphQL;

/**
 * Class Router
 *
 * This sets up the /graphql endpoint
 *
 * @package DFM\WPGraphQL
 * @since 0.0.1
 */
class Router {

	/**
	 * route
	 *
	 * Sets the route to use as the endpoint
	 *
	 * @var string
	 */
	public $route = 'graphql';

	/**
	 * Router constructor.
	 *
	 * @since 0.0.1
	 * @access public
	 */
	public function __construct() {
		
		/**
		 * Pass the route through a filter in case the endpoint /graphql should need to be changed
		 *
		 * @since 0.0.1
		 */
		$this->route = apply_filters( 'DFM\GraphQL\route', 'graphql' );

		/**
		 * Create the rewrite rule for the route
		 *
		 * @since 0.0.1
		 */
		add_action( 'init', array( $this, 'add_rewrite_rule' ), 10 );

		/**
		 * Add the query var for the route
		 *
		 * @since 0.0.1
		 */
		add_filter( 'query_vars', array( $this, 'add_query_var' ), 10, 1 );

		/**
		 * Redirects the route to the graphql processor
		 *
		 * @since 0.0.1
		 */
		add_action( 'template_redirect', array( $this, 'graphql_loaded' ), 10 );

	}

	/**
	 * Adds rewrite rule for the route endpoint
	 *
	 * @since 0.0.1
	 * @access public
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
	 * @since 0.0.1
	 * @param $query_vars
	 * @return array
	 */
	public function add_query_var( $query_vars ) {

		$query_vars[] = $this->route;
		return $query_vars;

	}

	/**
	 *
	 */
	public function graphql_loaded() {

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
		 * Resolve the GRAPHQL Request
		 */
		$this->process_graphql_request();
		return;

	}

	/**
	 * Set the response headers
	 *
	 * @since 0.0.1
	 */
	public function set_headers() {

		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Headers: content-type' );
		header( 'Content-Type: application/json' );

	}

	/**
	 * This processes the graphql requests that come into the /graphql endpoint
	 *
	 * @todo: check to see if there are better ways of capturing the POST info and a better way of returning the response.
	 * This is a quick and dirty implementation, but I have a feeling there are "better" ways to do it
	 * (maybe check how the REST API, etc handle parsing requests and returning responses?)
	 *
	 * @since 0.0.1
	 * @access public
	 * @return mixed
	 */
	public function process_graphql_request() {

		/**
		 * Set the response headers
		 */
		$this->set_headers();

		$raw_body = file_get_contents( 'php://input' );
		$data = json_decode( $raw_body, true );

		if ( empty( $raw_body ) && ! empty( $_REQUEST ) ) {
			$data = $_REQUEST;
		}

		$payload = ! empty( $data['query'] ) ? $data['query'] : null;
		$variables = isset( $data['variables'] ) ? $data['variables'] : null;

		// If there's a query request
		if ( ! empty( $payload ) ) {

			// Process the payload
			try {

				$result = WPGraphQL::instance()->query( $payload, $variables );

			// Catch any exceptions and pass generate the message
			} catch (\Exception $exception) {

				$result = [
					'errors' => [
						'message' => $exception->getMessage(),
					],
				];

			}

			// Send the json result
			wp_send_json( $result );

		// If there's no query request, send the notice that the request must require a query
		} else {

			wp_send_json( __( 'Graphql queries must include a query. Try again.', 'dfm-graphql-endpoints' ) );

		}

	}

}