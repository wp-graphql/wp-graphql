<?php

class MutationsCest {

	public $post_id;

	public function _before( FunctionalTester $I ) {
		$this->post_id = $I->havePostInDatabase( [ 'post_title' => 'test post' ] );
	}

	public function mutationShouldNotBeCachedTest( FunctionalTester $I ) {

		$I->wantTo( 'Execute a mutation without it being cached' );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$query = '
		mutation CreateComment($input: CreateCommentInput!) {
		  createComment(input: $input) {
		    clientMutationId
		  }
		}
		';

		$clientMutationId = uniqid( 'test', true );

		$variables = [
			'input' => [
				'clientMutationId' => $clientMutationId,
				'commentOn' => $this->post_id,
				'author' => 'test author',
			],
		];

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			'query' => $query,
			'variables' => $variables
		] ) );

		$I->seeResponseContainsJson([
			'data' => [
				'createComment' => [
					'clientMutationId' => $clientMutationId,
				],
			],
		]);

		$cache_response = $I->grabDataFromResponseByJsonPath( '$.extensions.graphqlSmartCache.graphqlObjectCache' );

		codecept_debug($cache_response[0]);

		$I->assertEmpty( $cache_response[0] );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			'query' => $query,
			'variables' => $variables,
		] ) );

		// this fails because there is already same comment
		$error = $I->grabDataFromResponseByJsonPath( '$.errors..message' )[0];
		$I->assertEquals( $error, "Duplicate comment detected; it looks as though you&#8217;ve already said that!" );

		// If this is empty, then the response was _not_ served by the cache.
		$cache_response = $I->grabDataFromResponseByJsonPath( '$.extensions.graphqlSmartCache.graphqlObjectCache' );

		$I->assertEmpty( $cache_response[0] );

	}

}
