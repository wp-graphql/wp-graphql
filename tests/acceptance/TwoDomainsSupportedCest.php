<?php

class DifferentLocalDomainsSupportedCest {

	public function queryDifferentLocalDomainsTest(AcceptanceTester $I)
    {
		$domains = [
			'localhost',
			'wp.localhost',
			'wp2.localhost',
			'foo.bar',
			'foo.bar.localhost'
		];
		foreach( $domains as $hostname ) {
			$I->haveHttpHeader('Host', $hostname);
			$I->sendGet('graphql', [ 'query' => '{__typename}' ] );
			$I->seeResponseCodeIs(200);
			$I->seeResponseContainsJson([
				'data' => [
					'__typename' => 'RootQuery'
				]
			]);
		}
	}

}
