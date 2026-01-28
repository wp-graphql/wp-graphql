<?php

/**
 * Test the wp-graphql settings page for cache
 */

class AdminSettingsCacheCest
{
	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_cache_section'  );
	}

	public function selectCacheSettingsTest( FunctionalTester $I ) {
		$I->loginAsAdmin();
		$I->amOnPage('/wp-admin/admin.php?page=graphql-settings#graphql_cache_section');

		// Save and see the selection after form submit
		$I->checkOption("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");
		$I->click('Save Changes');
		$I->seeCheckboxIsChecked("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");

		$I->uncheckOption("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");
		$I->click('Save Changes');
		$I->dontSeeCheckboxIsChecked("//input[@type='checkbox' and @name='graphql_cache_section[cache_toggle]']");
	}

	public function saveCacheTllExpirationTest( FunctionalTester $I ) {
		$I->loginAsAdmin();
		$I->amOnPage('/wp-admin/admin.php?page=graphql-settings#graphql_cache_section');

		// Save and see the selection after form submit
		$I->fillField("//input[@type='number' and @name='graphql_cache_section[global_ttl]']", 30);
		$I->click('Save Changes');
		$I->seeInField("//input[@type='number' and @name='graphql_cache_section[global_ttl]']", 30);

		// Invalid value, negative, doesn't save.
		$I->fillField("//input[@type='number' and @name='graphql_cache_section[global_ttl]']", -1);
		$I->click('Save Changes');
		$I->seeInField("//input[@type='number' and @name='graphql_cache_section[global_ttl]']", '');

		// Invalid value, negative, doesn't save.
		$I->fillField("//input[@type='number' and @name='graphql_cache_section[global_ttl]']", 0);
		$I->click('Save Changes');
		$I->seeInField("//input[@type='number' and @name='graphql_cache_section[global_ttl]']", 0);
	}

	public function purgeCacheCheckboxAndTimeTest( FunctionalTester $I ) {
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$I->loginAsAdmin();
		$I->amOnPage('/wp-admin/admin.php?page=graphql-settings#graphql_cache_section');

		$I->seeInField("//input[@type='text' and @name='graphql_cache_section[purge_all_timestamp]']", '');

		$I->checkOption("//input[@type='checkbox' and @name='graphql_cache_section[purge_all]']");
		$I->click('Save Changes');
		$I->seeInField("//input[@type='text' and @name='graphql_cache_section[purge_all_timestamp]']", gmdate('D, d M Y H:i T' ) );
		$I->dontSeeCheckboxIsChecked("//input[@type='checkbox' and @name='graphql_cache_section[purge_all]']");
	}
}
