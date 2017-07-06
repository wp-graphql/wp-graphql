<?php

class WPGraphQL_Test_Router extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function testRouteEndpoint() {
		/**
		 * Test that the default route is set to "graphql"
		 */
		$this->assertEquals( 'graphql', apply_filters( 'graphql_endpoint', \WPGraphQL\Router::$route ) );
	}

	/**
	 * Test to make sure that the rewrite rules properly include the graphql route
	 */
	public function testGraphQLRewriteRule() {
		global $wp_rewrite;
		$route = apply_filters( 'graphql_endpoint', \WPGraphQL\Router::$route );
		$this->assertArrayHasKey( $route . '/?$', $wp_rewrite->extra_rules_top );
	}

	public function testAddQueryVar() {
		$query_vars = [];
		$actual = \WPGraphQL\Router::add_query_var( $query_vars );
		$this->assertEquals( $actual, [ apply_filters( 'graphql_endpoint', \WPGraphQL\Router::$route ) ] );
	}

	public function testGetRawData() {

		$router = new \WPGraphQL\Router();

		global $HTTP_RAW_POST_DATA;
		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		$actual = $router->get_raw_data();
		$this->assertEquals( $actual, $HTTP_RAW_POST_DATA );
	}

	/**
	 * This tests the WPGraphQL Router resolving HTTP requests.
	 */
	public function testResolveRequest() {

		/**
		 * Create a test a query
		 */
		$this->factory->post->create([
			'post_title' => 'test',
			'post_status' => 'publish',
		]);

		/**
		 * Filter the request data
		 */
		add_filter( 'graphql_request_data', function( $data ) {
			$data['query'] = 'query{ posts{ edges{ node{ id } } } }';
			return $data;
		} );

		/**
		 * Set the query var to "graphql" so we can mock like we're visiting the endpoint via
		 */
		set_query_var( 'graphql', true );
		$GLOBALS['wp']->query_vars['graphql'] = true;

		/**
		 * Instantiate the router
		 */
		$router = new \WPGraphQL\Router();

		/**
		 * Process the request using our filtered data
		 */
		$router::resolve_http_request();

		/**
		 * Make sure the constant gets defined when it's a GraphQL Request
		 */
		$this->assertTrue( defined( 'GRAPHQL_HTTP_REQUEST' ) );
		$this->assertEquals( true, GRAPHQL_HTTP_REQUEST );

		/**
		 * Make sure the actions we expect to be firing are firing
		 */
		$this->assertNotFalse( did_action( 'graphql_process_http_request' ) );
		$this->assertNotFalse( did_action( 'graphql_process_http_request_response' ) );

	}

}
