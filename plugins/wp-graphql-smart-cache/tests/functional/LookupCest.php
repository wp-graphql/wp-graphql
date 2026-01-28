<?php

class LookupCest {
	public function _before( FunctionalTester $I ) {
		// Make sure that is gone.
		$I->dontHavePostInDatabase( ['post_title' => 'Hello world!'] );
	}

	// no id/hash. expect an error that it doesn't exist
	public function queryIdThatDoesNotExistTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that does not exist in the database' );
		$query_hash = '1234';

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );

		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'PersistedQueryNotFound'
			]
		]);
	}

	// insert hash with empty query, expect error
	public function queryIdWithEmptyGraphqlStringTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that exists but has empty query in the database' );

		$query_hash = '1234';
		$I->haveTermInDatabase( $query_hash, 'graphql_query_alias' );
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => '',
			'tax_input' => [
				'graphql_query_alias' => [ $query_hash ]
			]
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );

		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'PersistedQueryNotFound'
			]
		]);

		// clean up
		$I->dontHavePostInDatabase( ['post_name' => $query_hash] );
		$I->dontHaveTermInDatabase( ['name' => $query_hash] );
	}

	// insert hash and query string that doesn't match. expect error
	public function queryIdWithQueryAliasStringTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a query alias saved as term taxonomy for the query, works' );

		$query = '{
			__typename
		}';

		// Make sure query hash we use doesn't match
		$query_hash = 'my-foo-query';
		$I->haveTermInDatabase( $query_hash, 'graphql_query_alias' );
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => $query,
			'tax_input' => [
				'graphql_query_alias' => [ $query_hash ]
			]
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );

        $I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		 ] );
 
		// clean up
		$I->dontHavePostInDatabase( ['post_name' => $query_hash] );
		$I->dontHaveTermInDatabase( ['name' => $query_hash] );
	}

	// insert hash and query string, expect empty result
	public function invalidQueryStringFailureTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with invalid query results in error' );

		$query = "{\n  posts {\n    nodes {\n      title";
		$query_hash = hash( 'sha256', $query );

		$I->haveTermInDatabase( $query_hash, 'graphql_query_alias' );
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => $query,
			'tax_input' => [
				'graphql_query_alias' => [ $query_hash ]
			]
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );

		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Syntax Error: Expected Name, found <EOF>'
			]
		]);

		// clean up
		$I->dontHavePostInDatabase( ['post_name' => $query_hash] );
		$I->dontHaveTermInDatabase( ['name' => $query_hash] );
	}

	// insert hash and query string, expect empty result
	public function queryIdWithGraphqlEmptyResultsTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with hash that results in no return content/posts' );

		$query = "{\n  posts {\n    nodes {\n      title\n    }\n  }\n}\n";
		$query_hash = hash( 'sha256', $query );

		$I->haveTermInDatabase( $query_hash, 'graphql_query_alias' );
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => $query,
			'tax_input' => [
				'graphql_query_alias' => [ $query_hash ]
			]
		] );
		
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );

		// https://codeception.com/docs/modules/REST.html#jsonpath
		$I->assertEmpty(
			$I->grabDataFromResponseByJsonPath("$.data.posts.nodes[*].title")
		);

		// clean up
		$I->dontHavePostInDatabase( ['post_name' => $query_hash] );
		$I->dontHaveTermInDatabase( ['name' => $query_hash] );
	}

	// insert hash, query string, posts. expect results
	public function queryIdWithGraphqlReturnsPostsTest( FunctionalTester $I ) {
		$I->wantTo( 'Query with a hash that results in posts from the database' );

		$query = "{\n  posts {\n    nodes {\n      title\n    }\n  }\n}\n";
		$query_hash = hash( 'sha256', $query );

		$I->haveTermInDatabase( $query_hash, 'graphql_query_alias' );
		$I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => $query,
			'tax_input' => [
				'graphql_query_alias' => [ $query_hash, 'test-query-using-alias-name' ]
			]
		] );

		$I->havePostInDatabase( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'foo',
			'post_content' => 'foo bar. biz bang.',
			'post_name'    => 'foo-slug',
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );

		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => [
							'title' => 'foo'
					]
				]
			]
		]);

		// Query using other accepted format
		$I->sendGet( 'graphql', [
			'extensions' => [
				"persistedQuery" => [
					"version" => 1,
					"sha256Hash" => $query_hash
				]
			]
		] );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => [
							'title' => 'foo'
					]
				]
			]
		]);

		// Query using alias query name saved in term
		$I->sendGet( 'graphql', [ 'queryId' => 'test-query-using-alias-name' ] );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => [
							'title' => 'foo'
					]
				]
			]
		]);

		// clean up
		$I->dontHavePostInDatabase( [ 'post_name' => $query_hash ] );
		$I->dontHavePostInDatabase( [ 'post_title' => 'foo' ] );
		$I->dontHaveTermInDatabase( ['name' => $query_hash] );
	}

}
