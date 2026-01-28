<?php

class DocumentCest {
	public function _before( FunctionalTester $I ) {
		// Make sure that is gone.
		$I->dontHavePostInDatabase(['post_title' => 'Hello world!']);

		// clean up and persisted queries terms in the taxonomy
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias'] );

		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section'  );
	}

	public function saveQueryWithWhereClauseTest( FunctionalTester $I ) {
		$I->wantTo( 'Save a graphql query containing a where clause and double quotes' );

		$query = "{ posts(where: {tag: \"bees\"}) { nodes { id title uri content } } }";
		$query_alias = 'test-save-query-alias';

		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_alias
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'posts' => [
					'nodes' => []
				]
			]
		]);
		$I->seeTermInDatabase( [ 'name' => $query_alias ] );
	}

	public function saveMultipleOperationQueryWithQueryTitleTest( FunctionalTester $I ) {
		$I->wantTo( 'Save a named graphql query' );

		$query = "query my_query_1 {\n  __typename\n}\n\nquery my_query_2 {\n  __typename\n}\n";
		$query_hash = hash( 'sha256', $query );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query'         => $query,
			'queryId'       => $query_hash,
			'operationName' => 'my_query_1',
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $query_hash,
			'post_content' => $query,
			'post_title'    => 'my_query_1, my_query_2',
		] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query'         => $query,
			'queryId'       => $query_hash,
			'operationName' => 'my_query_2',
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);

		// Taxonomies should not be public visible
		$I->amOnPage( "wp-sitemap-taxonomies-graphql_query_alias-1.xml" );
		$I->seePageNotFound();
	}

	public function saveQueryWithAliasNameSavesTest( FunctionalTester $I ) {
		$I->wantTo( 'Save a graphql query with a query id/hash that does not match, saves as alias' );

		$query = "{\n  __typename\n}\n";

		// Make sure query hash we use doesn't match
		$query_hash = hash( 'sha256', $query );
		$query_alias = 'test-save-query-creates-alias';

		$I->dontSeeTermInDatabase( [ 'name' => 'graphql_query_alias' ] );
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_alias
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seePostInDatabase( [
			'post_name' => $query_hash,
		] );
		$I->seeTermInDatabase( [ 'name' => $query_hash ] );
		$I->seeTermInDatabase( [ 'name' => $query_alias ] );
	}

	public function saveQueryWithInvalidIdFailsTest( FunctionalTester $I ) {
		$I->wantTo( 'Save a graphql query that is invalid, should return error' );

		$query = "{\n  __typename";
		$query_hash = hash( 'sha256', $query );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_hash
		] ) );
		$I->seeResponseContainsJson([
			'errors' => [
				0 => [
					'message' => 'Syntax Error: Expected Name, found <EOF>'
				]
			]
		]);
		$I->dontSeePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_name'    => $query_hash,
			'post_content' => $query,
		] );
	}

	public function saveQueryWithExistingTermForHashTest( FunctionalTester $I ) {
		$I->wantTo( 'Save a graphql query where the hash for the query exists as a term already' );

		$query = "{\n  __typename\n}\n";
		$query_hash = hash( 'sha256', $query );

		// Save this query with an hash for another valid query as alias
		$query_for_posts = "
		{
			posts {
			  nodes {
				id
			  }
			}
		  }
		";
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query_for_posts,
			'queryId' => $query_hash
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'posts' => [
					'nodes' => []
				]
			]
		]);
		$I->seeTermInDatabase( [ 'name' => $query_hash ] );

		// Query with this persisted hash works, but not with the query hash we expected.
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => []
				]
			]
		]);

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_hash
		] ) );
		$I->seeResponseContainsJson([
			'errors' => [
				0 => [
					'message' => 'This queryId has already been associated with another query "A Persisted Query"',
				],
			]
		]);
	}

	public function saveQueryUsingExistingAliasTest( FunctionalTester $I ) {
		$I->wantTo( 'Error when save a graphql query using existing query alias' );

		// Set up some content
		$I->havePostInDatabase( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'foo',
			'post_content' => 'foo bar. biz bang.',
			'post_name'    => 'foo-slug',
		] );

		// Save a query with an alias
		$query = "{ posts { nodes { __typename content } } }";
		$query_alias = 'query_posts_with_content';

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_alias
		] ) );
		$I->seeResponseContainsJson( [
			'data' => [
				'posts' => [
					'nodes' => [
							'__typename' => 'Post',
							'content' => "<p>foo bar. biz bang.</p>\n",
					]
				]
			]
		]);
		$I->seeTermInDatabase( [ 'name' => $query_alias ] );

		// Save a different query using an alias for the first query with content
		$query = "{ posts { nodes { slug uri } } }";

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_alias
		] ) );
		$I->seeResponseContainsJson([
			'errors' => [
				0 => [
					'message' => 'This queryId has already been associated with another query "A Persisted Query"',
				],
			]
		]);

		// clean up
		$I->dontHavePostInDatabase( [ 'post_title' => 'foo' ] );
	}

}