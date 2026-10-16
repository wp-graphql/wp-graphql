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
		wp_set_current_user( 0 );
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
	/**
	 * A Subscriber is subject to "only allowed" mode.
	 *
	 * The rule used to be added only for logged-out requests, so any account at
	 * all, including one created by open registration, could run arbitrary
	 * queries against an endpoint the site had locked down.
	 */
	public function testSubscriberIsSubjectToOnlyAllowedMode() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_ALLOWED ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$result = graphql( [ 'query' => 'query notPersistedForSubscriber { __typename }' ] );
		$this->_assertError( $result, 'Not Found. Only pre-defined queries are allowed.' );
	}

	/**
	 * A Subscriber may not execute a document explicitly marked deny.
	 */
	public function testSubscriberCannotExecuteDeniedDocument() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_DENIED ] );

		$query_string = 'query deniedForSubscriber { __typename }';
		$this->_createAPersistedQuery( $query_string, Grant::DENY );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$result = graphql( [ 'query' => $query_string ] );
		$this->_assertError( $result, 'This query document has been blocked.' );
	}

	/**
	 * An administrator stays exempt, so ad-hoc queries in the IDE keep working.
	 * This is the behavior the logged-in check was presumably protecting.
	 */
	public function testAdministratorIsExemptFromGrantRules() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_ALLOWED ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$result = graphql( [ 'query' => 'query notPersistedForAdmin { __typename }' ] );
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertSame( 'RootQuery', $result['data']['__typename'] );
	}

	/**
	 * The exempting capability is filterable, so a site can widen or narrow who
	 * bypasses the grants without having to replace the rule.
	 */
	public function testGrantExemptionCapabilityIsFilterable() {
		add_option( 'graphql_persisted_queries_section', [ 'grant_mode' => Grant::GLOBAL_ALLOWED ] );

		$filter = static function () {
			return 'read';
		};
		add_filter( 'graphql_smart_cache_grant_exempt_capability', $filter );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$result = graphql( [ 'query' => 'query notPersistedForFilteredCap { __typename }' ] );

		remove_filter( 'graphql_smart_cache_grant_exempt_capability', $filter );

		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertSame( 'RootQuery', $result['data']['__typename'] );
	}

}
