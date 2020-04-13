<?php

class IsGraphqlHttpRequestTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Must match with the one in the codeception config
	 */
	private $host = 'wpgraphql.test';


	public function tearDown(): void {
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	public function testBasic() {
		$_SERVER['HTTP_HOST'] = $this->host;
		$_SERVER['REQUEST_URI'] = '/graphql';
		$this->assertEquals( true, is_graphql_http_request() );
	}

	/**
	 * Request from wp-graphqi comes to urls like
	 * 	https://wpgraphql.test/index.php?graphql
	 */
	public function testGraphiqlRequest() {
		$_SERVER['HTTP_HOST'] = $this->host;
		$_SERVER['REQUEST_URI'] = '/index.php';
		$_GET['graphql'] = '';
		$this->assertEquals( true, is_graphql_http_request() );
	}

	public function testUnknownPath() {
		$_SERVER['HTTP_HOST'] = $this->host;
		$_SERVER['REQUEST_URI'] = '/other';
		$this->assertEquals( false, is_graphql_http_request() );
	}

	public function testUnknownPathWithGraphqlString() {
		$_SERVER['HTTP_HOST'] = $this->host;
		$_SERVER['REQUEST_URI'] = '/other/graphql/ding';
		$this->assertEquals( false, is_graphql_http_request() );
	}

	public function testUnknownPathEndingWithGraphql() {
		$_SERVER['HTTP_HOST'] = $this->host;
		$_SERVER['REQUEST_URI'] = '/other/graphql';
		$this->assertEquals( false, is_graphql_http_request() );
	}

}
