<?php

class CustomPostTypeRegistrationCest {

	public $acf_plugin_version;

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		$I->loginAsAdmin();

		$this->acf_plugin_version = $_ENV['ACF_VERSION'] ?? 'latest';

		// if the plugin version is before 6.1, we're not testing this functionality
		if ( ! isset( $_ENV['ACF_PRO'] ) || true !== (bool) $_ENV['ACF_PRO'] || version_compare( $this->acf_plugin_version, '6.1', 'lt' ) ) {
			$I->markTestSkipped( sprintf( 'Version "%s" does not include the ability to register custom post types, so we do not need to test the extensions of the feature', $this->acf_plugin_version ) );
		}

	}


	public function testPostTypeCanBeRegisteredToShowInGraphql( FunctionalTester $I ) {

		$I->amOnPage( '/wp-admin/edit.php?post_type=acf-post-type' );
		$I->see( 'Post Types' );
		$I->click( '//div[@class="acf-headerbar"]//a[contains( @class, "acf-btn")]' );
		$I->see( "Add New Post Type" );

		$I->seeElement( '#acf-advanced-settings' );

		$I->checkOption( "#acf_post_type-advanced_configuration" );

		$I->click( '//a[contains(@class, "acf-tab-button") and text()[normalize-space(.) = "GraphQL"]]' );

		// "Show in GraphQL" should default to false / unchecked for new post types.
		$I->dontSeeCheckboxIsChecked( 'Show in GraphQL' );

		// the graphql fields should be in the form
		$I->seeElement( "//div[@id='acf-advanced-settings']//div[contains(@class, 'acf-field-show-in-graphql')]" );
		$I->seeElement( "//div[@id='acf-advanced-settings']//div[contains(@class, 'acf-field-graphql-single-name')]");
		$I->seeElement( "//div[@id='acf-advanced-settings']//div[contains(@class, 'acf-field-graphql-plural-name')]" );

		// Get the form values
		$graphql_single_name_value = $I->grabAttributeFrom( '//input[@name="acf_post_type[graphql_single_name]"]', 'value' );
		$graphql_plural_name_value = $I->grabAttributeFrom( '//input[@name="acf_post_type[graphql_plural_name]"]', 'value' );

		// The graphql single/plural names should be empty by default when creating a new post type
		$I->assertEmpty( $graphql_single_name_value );
		$I->assertEmpty( $graphql_plural_name_value );


		// fill out the form
		$I->fillField( '//input[@name="acf_post_type[labels][singular_name]"]', 'Test Type' );
		$I->fillField( '//input[@name="acf_post_type[labels][name]"]', 'Test Types' );
		$I->fillField( '//input[@name="acf_post_type[post_type]"]', 'test_type' );

		$I->checkOption( 'Show in GraphQL' );
		$I->fillField( 'GraphQL Single Name', 'testSingleName' );
		$I->fillField( 'GraphQL Plural Name', 'testPluralName' );

		// Save the form
		$I->click( 'Save Changes' );

		// Check that the values saved as expected
		$singular_label = $I->grabAttributeFrom( '//input[@name="acf_post_type[labels][singular_name]"]', 'value' );
		$plural_label = $I->grabAttributeFrom( '//input[@name="acf_post_type[labels][name]"]', 'value' );
		$post_type_key = $I->grabAttributeFrom( '//input[@name="acf_post_type[post_type]"]', 'value' );
		$I->seeCheckboxIsChecked( 'Show in GraphQL' );
		$graphql_single_name_value = $I->grabAttributeFrom( '//input[@name="acf_post_type[graphql_single_name]"]', 'value' );
		$graphql_plural_name_value = $I->grabAttributeFrom( '//input[@name="acf_post_type[graphql_plural_name]"]', 'value' );

		// Assert that the values filled in the fields are the values that were saved
		$I->assertSame( 'Test Type', $singular_label );
		$I->assertSame( 'Test Types', $plural_label );
		$I->assertSame( 'test_type', $post_type_key );

		$I->assertSame( 'testSingleName', $graphql_single_name_value );
		$I->assertSame( 'testPluralName', $graphql_plural_name_value );

		// navigate to the tools page
		$I->amOnPage( '/wp-admin/edit.php?post_type=acf-field-group&page=acf-tools' );

		$I->see( 'Select Post Types' );
		$I->see( 'Test Types', '//div[@data-name="post_type_keys"]' );
		$I->checkOption( 'Test Types' );
		$I->click( 'Generate PHP' );
		$I->seeElement( '//textarea[@id="acf-export-textarea"]');
		$I->see( "'show_in_graphql' => true", '//textarea[@id="acf-export-textarea"]');
		$I->see( "'graphql_single_name' => 'testSingleName'", '//textarea[@id="acf-export-textarea"]');
		$I->see( "'graphql_plural_name' => 'testPluralName'", '//textarea[@id="acf-export-textarea"]');

	}

}
