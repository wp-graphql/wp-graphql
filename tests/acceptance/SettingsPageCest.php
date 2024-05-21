<?php

class SettingsPageCest {
	public function seePluginActivated( AcceptanceTester $I ) {
		$I->loginAsAdmin();

		// visit admin page "admin.php?page=graphql-settings"
		$I->amOnPage( 'admin.php?page=graphql-settings' );

		$I->see( 'WPGraphQL General Settings' );
		$I->see( 'Enable GraphiQL IDE' );
	}
}
