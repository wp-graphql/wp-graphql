<?php

/**
 * Test the wp-graphql settings page for global max age header.
 */

class DocumentMaxAgeCest {
	public function _before( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
	}

	public function _runQuery( FunctionalTester $I ) {
		$query = "query { __typename }";
		$I->sendPost('graphql', [
			'query'         => $query,
		] );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
	}

	public function queryShowsMaxAgeTest( FunctionalTester $I ) {
		$I->wantTo( 'See my custom max-age directive in response for a graphql query' );

		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
		$this->_runQuery( $I );
		$I->dontSeeHttpHeader( 'Cache-Control' );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => null ] );
		$this->_runQuery( $I );
		$I->dontSeeHttpHeader( 'Cache-Control' );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => -1 ] );
		$this->_runQuery( $I );
		$I->dontSeeHttpHeader( 'Cache-Control' );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 30 ] );
		$this->_runQuery( $I );
		$I->seeHttpHeader( 'Cache-Control', 'max-age=30, s-maxage=30, must-revalidate' );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 10.5 ] );
		$this->_runQuery( $I );
		$I->seeHttpHeader( 'Cache-Control', 'max-age=10, s-maxage=10, must-revalidate' );

		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 0 ] );
		$this->_runQuery( $I );
		$I->seeHttpHeader( 'Cache-Control', 'no-store' );
	}

	public function batchQueryDefaultMaxAgeTest( FunctionalTester $I ) {
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => 444 ] );

		$query =
			[
				[	"query" => "query { __typename }" ],
				[	"query" => "{ posts { nodes { title content } } }" ],
			]
		;

		$I->sendPost('graphql', $query );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
		$I->seeHttpHeader( 'Cache-Control', 'max-age=444, s-maxage=444, must-revalidate' );
	}

}
