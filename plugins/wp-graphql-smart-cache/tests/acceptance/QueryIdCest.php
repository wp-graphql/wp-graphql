<?php

class QueryIdCest
{
	public function queryIdThatDoesNotExistTest(AcceptanceTester $I)
	{
		$I->sendGet('graphql', [ 'queryId' => '1234' ] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'PersistedQueryNotFound'
			]
		]);
	}

	// If send empty string and a query id that does not exist, will get not found error
	public function sendEmptyQueryStringWithQueryIdIsErrorTest(AcceptanceTester $I)
	{
		$I->haveHttpHeader('Content-Type', 'application/json');
		$I->sendPOST('graphql', json_encode([
			'query' => '',
			'queryId' => 'alias-does-not-exist'
		]));
		$I->seeResponseContainsJson([
			'errors' => [
				[
					'message' => 'PersistedQueryNotFound'
				]
			]
		]);
	}

	public function saveQueryAndHashTest(AcceptanceTester $I)
	{
		$I->haveHttpHeader('Content-Type', 'application/json');
		$I->sendPOST('graphql', json_encode([
			'query' => '{__typename}',
			'queryId' => '8d8f7365e9e86fa8e3313fcaf2131b801eafe9549de22373089cf27511858b39'
		]));
		$I->seeResponseContainsJson([
		   'data' => [
			   '__typename' => 'RootQuery'
		   ]
		]);

		$I->haveHttpHeader('Content-Type', 'application/json');
		$I->sendPOST('graphql', json_encode([
			'query' => '{ __typename }',
			'extensions' => [
				"persistedQuery" => [
					"version" => 1,
					"sha256Hash" => "8d8f7365e9e86fa8e3313fcaf2131b801eafe9549de22373089cf27511858b39"
				]
			]
		]));
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery'
			]
		]);
	}
 
}
