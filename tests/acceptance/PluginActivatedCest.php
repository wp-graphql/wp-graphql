<?php

class PluginActivatedCest {
	public function seePluginActivated( AcceptanceTester $I ) {

		sleep( 5 );

		$I->loginAsAdmin();
		$I->amOnPluginsPage();
		$I->activatePlugin( 'wp-graphql' );
		$I->seePluginActivated( 'wp-graphql' );
	}
}
