<?php

use WPGraphQL\Server\WPHelper;

class WPHelperTest extends \Codeception\TestCase\WPTestCase {

	private function get_example_params() {
		return [
			'operation' => 'TestQuery',
			'query'     => "
				query TestQuery {
					posts {
						nodes {
							id
						}
					}
				}
			",
			'variables' => [
				'foo' => 'bar',
			],
		];
	}

	/**
	 * Test parsing of empty request params to verify that we are proxying to parent class.
	 */
	public function testParseEmptyRequestParams() {
		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'POST', [], [] );

		$this->assertEquals( null, $params->operation );
		$this->assertEquals( null, $params->query );
		$this->assertEquals( null, $params->queryId );
		$this->assertEquals( null, $params->variables );
	}

	/**
	 * Test parsing of POST request params.
	 */
	public function testParsePostRequestParams() {
		$body_params = $this->get_example_params();

		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'POST', $body_params, [] );

		$this->assertEquals( $body_params['operation'], $params->operation );
		$this->assertEquals( $body_params['query'], $params->query );
		$this->assertEquals( null, $params->queryId );
		$this->assertEquals( $body_params['variables'], $params->variables );
	}

	/**
	 * Test parsing of GET request params.
	 */
	public function testParseGetRequestParams() {
		$query_params = $this->get_example_params();

		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'GET', [], $query_params );

		$this->assertEquals( $query_params['operation'], $params->operation );
		$this->assertEquals( $query_params['query'], $params->query );
		$this->assertEquals( null, $params->queryId );
		$this->assertEquals( $query_params['variables'], $params->variables );
	}

	/**
	 * Test parsing of batched POST request params.
	 */
	public function testParseBatchPostRequestParams() {
		$body_params = [
			$this->get_example_params(),
			$this->get_example_params(),
		];

		$helper = new WPHelper();
		$batched_params = $helper->parseRequestParams( 'POST', $body_params, [] );

		$this->assertEquals( true, is_array( $batched_params ) );
		foreach( $batched_params as $index => $params ) {
			$this->assertEquals( $body_params[ $index ]['operation'], $params->operation );
			$this->assertEquals( $body_params[ $index ]['query'], $params->query );
			$this->assertEquals( null, $params->queryId );
			$this->assertEquals( $body_params[ $index ]['variables'], $params->variables );
		}
	}

	/**
	 * Test the Apollo method of passing a persisted query ID on POST.
	 */
	public function testApolloPersistedQueryIdOnPost() {
		$body_params = [
			'extensions' => [
				'persistedQuery' => [
					'sha256Hash' => 'fake hash',
				],
			],
			'operation'  => 'ApolloPersistedQueryIdTest',
		];

		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'POST', $body_params, [] );

		$this->assertEquals( $body_params['operation'], $params->operation );
		$this->assertEquals( null, $params->query );
		$this->assertEquals( 'fake hash', $params->queryId );
		$this->assertEquals( null, $params->variables );
	}

	/**
	 * Test the Apollo method of passing a persisted query ID on GET.
	 */
	public function testApolloPersistedQueryIdOnGet() {
		$query_params = [
			'extensions' => '{"persistedQuery":{"sha256Hash":"fake hash"}}',
			'operation'  => 'ApolloPersistedQueryIdTest',
		];

		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'GET', [], $query_params );

		$this->assertEquals( $query_params['operation'], $params->operation );
		$this->assertEquals( null, $params->query );
		$this->assertEquals( 'fake hash', $params->queryId );
		$this->assertEquals( null, $params->variables );
	}

	/**
	 * Test that POST params can be filtered with graphql_request_data.
	 */
	public function testRequestPostDataFilter() {
		$body_params = $this->get_example_params();

		add_filter( 'graphql_request_data', function() {
			return [ 'query' => 'fake post' ];
		} );

		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'POST', $body_params, [] );

		$this->assertEquals( 'fake post', $params->query );
	}

	/**
	 * Test that GET params can be filtered with graphql_request_data.
	 */
	public function testRequestGetDataFilter() {
		$query_params = $this->get_example_params();

		add_filter( 'graphql_request_data', function() {
			return [ 'query' => 'fake get' ];
		} );

		$helper = new WPHelper();
		$params = $helper->parseRequestParams( 'GET', [], $query_params );

		$this->assertEquals( 'fake get', $params->query );
	}

}
