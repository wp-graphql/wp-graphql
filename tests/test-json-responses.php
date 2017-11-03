<?php
/**
 * Test case for Basic Router responses.
 *
 * @group ajax
 */
class WPGraphQL_JSON_Responses extends WP_Ajax_UnitTestCase {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}
	/**
	 * [testResolveHttpRequestWithEmptyQuery description]
	 *
	 * @group ajax
	 * @return [type] [description]
	 */
	public function testResolveHttpRequestWithEmptyQuery() {
		/**
		 * Filter the request data
		 */
		add_filter( 'graphql_request_data', function( $data ) {
			$data['query'] = null;
			$data['variables'] = null;
			$data['operationName'] = null;
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

		add_action( 'wp_ajax_graphql_resolve_http_request', [ '\WPGraphQL\Router', 'resolve_http_request' ] );
		/**
		 * Process the request using our filtered data
		 */
		try {
			$this->_handleAjax( 'graphql_resolve_http_request' );
		} catch ( WPAjaxDieContinueException $e ) {}
		/**
		 * Make sure the constant gets defined when it's a GraphQL Request
		 */
		$this->assertTrue( defined( 'GRAPHQL_HTTP_REQUEST' ) );
		$this->assertEquals( true, GRAPHQL_HTTP_REQUEST );

		/**
		 * Make sure the actions we expect to be firing are firing
		 */
		$this->assertNotFalse( did_action( 'graphql_resolve_http_request' ) );
		$this->assertNotFalse( did_action( 'graphql_process_http_request_response' ) );
		/**
		 * Pull out last AJAX response received by this test class.
		 *
		 * @var string
		 */
		$this->assertTrue( isset( $this->_last_response ) );
		$result = $this->_last_response;
		$expected = '{"errors":[{"message":"GraphQL requests must be a POST or GET Request with a valid query","category":"user"}]}';
		$this->assertSame( $expected, $result );

	}

}
