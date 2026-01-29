<?php
/**
 * Test the allow/deny selection for individual query grant access.
 *
 * 		$option['graphql_persisted_queries_section'] = [
 *			'grant_mode' => 'public',
 *		];
 *
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document\Grant;

class AllowDenyQueryRulesTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	/**
	 * Set a persisted query with id.
	 *
	 * @param query_sting The graphql query
	 * @param grant Grant allow, deny, default, false
	 *
	 * @return string The query id for the query string
	 * @throws \GraphQL\Server\RequestError
	 * @throws \GraphQL\Error\SyntaxError
	 */
	public function _createAPersistedQuery( $query_string, $grant ) {
		$query_id = Utils::generateHash( $query_string );

		$persisted_query = new Document();
		$post_id = $persisted_query->save( $query_id, $query_string);

		$query_grant = new Grant();
		$query_grant->save( $post_id, $grant );

		return $query_id;
	}

	public function _assertError( $response, $message ) {
		$this->assertArrayNotHasKey( 'data', $response, 'Response has data but should have error instead' );
		$this->assertEquals( $response['errors'][0]['message'], $message, 'Response should have an error' );
	}

	public function testDeniedQueryWorksWhenNoGlobalSettingIsSet() {

		$this->assertSame( 1, 1 );

		delete_option( 'graphql_persisted_queries_section' );

		$post_id = self::factory()->post->create();

		$this->assertNotEmpty( $post_id );

		// Verify persisted query set as denied still works
		$query_string = '{ __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify a non-persisted query still works
		$non_persisted_query = 'query notPersisted { posts { nodes { slug uri } } }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );
	}

	public function testDeniedQueryWorksWhenWhenGlobalPublicIsSet() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_PUBLIC ] );
		$post_id = self::factory()->post->create();

		// Verify persisted query set as denied still works
		$query_string = 'query setAsDenied { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify a non-persisted query still works
		$non_persisted_query = 'query notPersisted { posts { nodes { slug uri } } }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );
	}

	public function testWhenGlobalOnlyAllowedIsSet() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_ALLOWED ] );
		$post_id = self::factory()->post->create();

		// Verify allowed query works
		$query_string = 'query setAsAllowed { posts { nodes { slug uri } } }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::ALLOW );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );

		// Verify denied query doesn't work
		$query_string = 'query setAsDenied { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This query document has been blocked.' );

		// Verify default query doesn't work
		$query_string = 'query setAsDefault { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::USE_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This query document has been blocked.' );

		// Verify no selection doesn't work
		$query_string = 'query setAsNone { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::NOT_SELECTED_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This query document has been blocked.' );

		// Verify a non-persisted query doesn't work
		$post_id = self::factory()->post->create();
		$non_persisted_query = 'query notPersisted { __typename }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->_assertError( $result, 'Not Found. Only pre-defined queries are allowed.' );
	}

	public function testWhenGlobalDenySomeIsSet() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_DENIED ] );
		$post_id = self::factory()->post->create();

		// Verify allowed query works
		$query_string = 'query setAsAllowed { posts { nodes { slug uri } } }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::ALLOW );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( sprintf("/?p=%d", $post_id), $result['data']['posts']['nodes'][0]['uri'] );

		// Verify denied query doesn't work
		$query_string = 'query setAsDenied { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::DENY );
		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This query document has been blocked.' );

		// Verify default query works
		$query_string = 'query setAsDefault { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::USE_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify no selection works
		$query_string = 'query setAsNone { __typename }';
		$query_id = $this->_createAPersistedQuery( $query_string, Grant::NOT_SELECTED_DEFAULT );
		$result = graphql( [ 'query' => $query_string ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );

		// Verify a non-persisted query still works
		$non_persisted_query = 'query notPersisted { __typename }';
		$result = graphql( [ 'query' => $non_persisted_query ] );
		$this->assertEquals( 'RootQuery', $result['data']['__typename'] );
	}
}
