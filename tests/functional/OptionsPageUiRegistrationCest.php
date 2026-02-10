<?php

class OptionsPageUiRegistrationCest {

	public $acf_plugin_version;

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		$I->loginAsAdmin();

		$this->acf_plugin_version = $_ENV['ACF_VERSION'] ?? 'latest';

		// if the plugin version is before 6.2, we're not testing this functionality
		if ( ! isset( $_ENV['ACF_PRO'] ) || true !== (bool) $_ENV['ACF_PRO'] || version_compare( $this->acf_plugin_version, '6.2', 'lt' ) ) {
			$I->markTestSkipped( sprintf( 'Version "%s" does not include the ability to register custom post types, so we do not need to test the extensions of the feature', $this->acf_plugin_version ) );
		}

	}

	public function testOptionsPageCanBeRegisteredToShowInGraphql( FunctionalTester $I ) {
		$I->amOnPage( '/wp-admin/edit.php?post_type=acf-ui-options-page' );
		$I->see( 'Options Pages' );
		$I->click( '//div[@class="acf-headerbar"]//a[contains( @class, "acf-btn")]' );
		$I->see( "Add New Options Page" );

		$I->seeElement( '#acf-advanced-settings' );

		$I->checkOption( "#acf_ui_options_page-advanced_configuration" );

		$I->click( '//a[contains(@class, "acf-tab-button") and text()[normalize-space(.) = "GraphQL"]]' );

		// "Show in GraphQL" should default to false / unchecked for new post types.
		$I->dontSeeCheckboxIsChecked( 'Show in GraphQL' );

		// the graphql fields should be in the form
		$I->seeElement( "//div[@id='acf-advanced-settings']//div[contains(@class, 'acf-field-show-in-graphql')]" );
		$I->seeElement( "//div[@id='acf-advanced-settings']//div[contains(@class, 'acf-field-graphql-type-name')]");

		// Get the form values
		$graphql_type_name_value = $I->grabAttributeFrom( '//input[@name="acf_ui_options_page[graphql_type_name]"]', 'value' );

		// The graphql single/plural names should be empty by default when creating a new post type
		$I->assertEmpty( $graphql_type_name_value );


		// fill out the form
		$I->fillField( '//input[@name="acf_ui_options_page[page_title]"]', 'Test Options Page' );
		$I->fillField( '//input[@name="acf_ui_options_page[menu_slug]"]', 'test-options-page' );
		$I->selectOption( '//select[@name="acf_ui_options_page[parent_slug]"]', 'none' );

		$I->checkOption( 'Show in GraphQL' );
		$I->fillField( 'GraphQL Type Name', 'TestOptionsPage' );

		// Save the form
		$I->click( 'Save Changes' );

		// Check that the values saved as expected
		$page_title = $I->grabAttributeFrom( '//input[@name="acf_ui_options_page[page_title]"]', 'value' );
		$menu_slug = $I->grabAttributeFrom( '//input[@name="acf_ui_options_page[menu_slug]"]', 'value' );
		$parent_slug = $I->grabAttributeFrom( '//select[@name="acf_ui_options_page[parent_slug]"]', 'value' );
		$I->seeCheckboxIsChecked( 'Show in GraphQL' );
		$graphql_type_name_value = $I->grabAttributeFrom( '//input[@name="acf_ui_options_page[graphql_type_name]"]', 'value' );

		// Assert that the values filled in the fields are the values that were saved
		$I->assertSame( 'Test Options Page', $page_title );
		$I->assertSame( 'test-options-page', $menu_slug );
		$I->assertNull( $parent_slug );

		$I->assertSame( 'TestOptionsPage', $graphql_type_name_value );

		// navigate to the tools page
		$I->amOnPage( '/wp-admin/edit.php?post_type=acf-field-group&page=acf-tools' );

		$I->see( 'Select Options Pages' );
		$I->see( 'Test Options Page', '//div[@data-name="ui_options_page_keys"]' );
		$I->checkOption( 'Test Options Page' );
		$I->click( 'Generate PHP' );
		$I->seeElement( '//textarea[@id="acf-export-textarea"]');
		$I->see( "'show_in_graphql' => 1", '//textarea[@id="acf-export-textarea"]');
		$I->see( "'graphql_type_name' => 'TestOptionsPage'", '//textarea[@id="acf-export-textarea"]');

	}
}
