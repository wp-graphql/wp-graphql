<?php

use WPGraphQL\Request;

class RequestTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Create Request instance using example request data.
	 *
	 * @return Request
	 */
	private function create_example_request() {
		$request_data = [
			'operation' => 'TestQuery',
			'query'     => "
				query TestQuery {
					posts {
						nodes {
							id
							content
						}
					}
				}
			",
		];

		return new Request( $request_data );
	}

	public function testConstructor() {
		$this->assertEquals( 0, did_action( 'init_graphql_request' ) );

		$this->create_example_request();

		$this->assertEquals( true, GRAPHQL_REQUEST );
		$this->assertEquals( 1, did_action( 'init_graphql_request' ) );
	}

	/**
	 * Create an example request and verify that the request works and calls actions.
	 */
	public function testRequestExecution() {
		$this->factory->post->create();
		$request = $this->create_example_request();

		$this->assertEquals( 0, did_action( 'do_graphql_request' ) );
		$this->assertEquals( 0, did_action( 'graphql_execute' ) );
		$this->assertEquals( 0, did_action( 'graphql_return_response' ) );

		$results = $request->execute();

		$this->assertArrayHasKey( 'data', $results );
		$this->assertArrayNotHasKey( 'errors', $results );
		$this->assertEquals( 1, count( $results['data']['posts']['nodes'] ) );

		$this->assertEquals( 1, did_action( 'do_graphql_request' ) );
		$this->assertEquals( 1, did_action( 'graphql_execute' ) );
		$this->assertEquals( 1, did_action( 'graphql_return_response' ) );
	}

	/**
	 * Test that the request results can be filtered with graphql_request_results.
	 */
	public function testRequestResultsFilter() {
		$this->factory->post->create();
		$request = $this->create_example_request();

		add_filter( 'graphql_request_results', function() {
			return 'filtered response';
		} );

		$results = $request->execute();

		$this->assertEquals( 'filtered response', $results );
	}

	/**
	 * When passing invalid request data, the response should include an error.
	 */
	public function testRequestError() {
		$this->factory->post->create();

		$request = new Request( [ 'query' => 'query {}' ] );
		$results = $request->execute();

		$this->assertArrayHasKey( 'errors', $results );
		$this->assertArrayNotHasKey( 'data', $results );
		$this->assertEquals( 1, count( $results['errors'] ) );
	}

	/**
	 * The request should not clobber the global post since it can be called in the Loop.
	 */
	public function testRequestPreservesGlobalPost() {
		$GLOBALS['post'] = 'testing';

		$this->factory->post->create();
		$this->create_example_request()->execute();

		$this->assertEquals( 'testing', $GLOBALS['post'] );
	}

	/**
	 * The request should provide a public method to get the parsed operation params.
	 */
	public function testRequestCanGetOperationParams() {
		$this->factory->post->create();
		$request = $this->create_example_request();
		$operation_params = $request->get_params();

		// Operation params are null until query is executed.
		$this->assertEquals( null, $operation_params );

		$request->execute();

		$operation_params = $request->get_params();
		$this->assertEquals( 'GraphQL\Server\OperationParams', get_class( $operation_params ) );
	}

}
