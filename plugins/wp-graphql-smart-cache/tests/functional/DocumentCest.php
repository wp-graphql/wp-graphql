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
		$query_alias = hash( 'sha256', $query );

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
		$I->wantTo( 'Refuse to save a graphql query under a hash that already identifies a different query' );

		$query = "{\n  __typename\n}\n";
		$query_hash = hash( 'sha256', $query );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query,
			'queryId' => $query_hash
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seeTermInDatabase( [ 'name' => $query_hash ] );

		// A different query sent with that hash as its id is refused; the id is not the hash of this query.
		$query_for_posts = "{ posts { nodes { id } } }";
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost('graphql', json_encode( [
			'query' => $query_for_posts,
			'queryId' => $query_hash
		] ) );
		$I->seeResponseContainsJson([
			'errors' => [
				0 => [
					'message' => 'This queryId has already been associated with another query "A Persisted Query"',
				],
			]
		]);
		$I->dontSeePostInDatabase( [
			'post_type' => 'graphql_document',
			'post_name' => hash( 'sha256', "{\n  posts {\n    nodes {\n      id\n    }\n  }\n}\n" ),
		] );

		// The original document still executes by its hash.
		$I->sendGet( 'graphql', [ 'queryId' => $query_hash ] );
		$I->seeResponseContainsJson( [
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
	}

}
