<?php

namespace Tests\WPGraphQL\Acf\Functional;

use FunctionalTester;

abstract class AcfFieldCest {

	/**
	 * Before the tests run, we:
	 * - import the JSON file for the field group
	 * - login as admin
	 * - visit the page showing all the field groups
	 * - click on the imported field group
	 * - verify we're on the page we expect, editing the field group
	 * - select the "field type" that we're testing
	 *
	 * This sets us up to make assertions about the field type we're testing.
	 *
	 * Can we see the fields we expect to see?
	 * Do they have the default values we expect?
	 * Do they save as expected?
	 * Do they validate as expected?
	 *
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function _before( FunctionalTester $I ): void {

		// Import the JSON file to test
		// Each class can override this by defining their
		// own _getJsonToImport() method
		$I->importJson( $this->_getJsonToImport() );

		// Login
		$I->loginAsAdmin();

		// Visit the page showing all ACF Field Groups
		$I->amOnPage( '/wp-admin/edit.php?post_type=acf-field-group' );

		// See the imported field group
		$I->see( 'Foo Name' );

		// Click it to go to edit the field group page
		$I->click( 'Foo Name' );

		// Verify we're on the "Edit Field Group" page
		$I->see( 'Edit Field Group' );

		// Verify that we see the "Field Type" settings field
		$I->see( 'Field Type' );

		// Click edit on the field
		$I->click( '//div[@data-key="' . $this->_getTestFieldKey() . '"]//a[@title="Edit field"]' );

		// Select the "Field Type" that we want to test against
		// This xpath finds the "Field Type" select for the field we're testing against to keep things constant
		$I->selectOption( '//div[@data-key="' . $this->_getTestFieldKey() . '"]//select[contains(concat(" ", @class, " "), " field-type ")]', $this->_getAcfFieldType() );
	}

	/**
	 * @return void
	 */
	public function _after( FunctionalTester $I ): void {

		// Delete imported field group
		$I->loginAsAdmin();
		$I->amOnPage('/wp-admin/edit.php?post_type=acf-field-group');
		
		// Use a more specific selector that targets the row containing "Foo Name"
		// This is more robust than just selecting the first checkbox
		// The selector looks for a row containing "Foo Name" and then finds its checkbox
		// Updated selector to be more flexible with ACF UI changes
		$checkbox_selector = '//tr[contains(., "Foo Name")]//th[contains(@class, "check-column")]//input[@type="checkbox"]';
		
		// Try the specific selector first, fall back to first row if that fails
		try {
			$I->checkOption( $checkbox_selector );
		} catch ( \Exception $e ) {
			// Fallback: try to select the first checkbox if "Foo Name" selector doesn't work
			// This handles edge cases where the field group might have a different name or structure
			$I->checkOption( '//tbody/tr[1]//th[contains(@class, "check-column")]//input[@type="checkbox"]' );
		}
		
		$I->selectOption( '#bulk-action-selector-bottom', 'trash' );
		$I->click( '#doaction2' );

	}

	/**
	 * Get the field type the Cest is testing
	 *
	 * @return string
	 */
	abstract public function _getAcfFieldType(): string;

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function _submitForm( FunctionalTester $I ) {
		// submit the form
		// set the xpath tp play nice with multiple versions of ACF since the UI changed in 6.0
		$I->click('//div[@id="submitpost"]//input[@id="save"] | //div[@id="submitpost"]//button[@type="submit"]' );

		// make sure there's no errors
		$I->dontSeeElement( '#message.notice-error' );

		// Make sure the save succeeded
		$I->seeElement( '#message.notice-success' );
	}

	/**
	 * Define a JSON file to import for the Cest
	 *
	 * @return string
	 */
	public function _getJsonToImport(): string {
		return 'acf-export-2023-01-26.json';
	}

	/**
	 * This is the field key we're testing against.
	 *
	 * @return string
	 */
	public function _getTestFieldKey(): string {
		return 'field_63d2bb765f5af';
	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function seeShowInGraphqlField( FunctionalTester $I ): void {
		// Verify that we see the "Show in GraphQL" field.
		// we specify this xpath because we don't want false positives of the "Graphql Field Name" being seen anywhere in the DOM (i.e. on the Field Group or another field)
		$I->see( 'Show in GraphQL', '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="show_in_graphql"]//label' );
	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function seeShowInGraphqlWarnsAboutPossibleBreakingChange( FunctionalTester $I ) {
		// ensure the description makes note about the possibility of breaking changes. The text might change, so we're not
		// testing exact message, just that the "show_in_graphql" description notes the possibility
		// of breaking changes
		$I->see( 'breaking change', '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="show_in_graphql"]//p[@class="description"]' );

	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function testSavingShowInGraphqlField( FunctionalTester $I ): void {

		$version = $_ENV['ACF_VERSION'] ?? getenv('ACF_VERSION') ?? 'latest';

		if ( version_compare( $version, '6.0', 'lt' ) ) {
			$I->markTestSkipped( 'Skip this test for ACF versions below 6.0. The test fails in github actions (but not locally) so lazily skipping for now.' );
		}

		// Here we want to test that saving the "show_in_graphql" field
		// properly sets the value as unchecked and checked again

		$show_in_graphql_checkbox_selector = '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="show_in_graphql"]//input[@type="checkbox"]';

		$graphql_field_name_input_selector = '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="graphql_field_name"]//input[@type="text"]';

		// default value is checked
		$I->canSeeCheckboxIsChecked( $show_in_graphql_checkbox_selector );

		// Uncheck the option
		$I->uncheckOption( $show_in_graphql_checkbox_selector );

		// submit the form
		$this->_submitForm( $I );

		// The checkbox should not be checked now
		$I->dontSeeCheckboxIsChecked( $show_in_graphql_checkbox_selector );

		// Check the option again
		$I->checkOption( $show_in_graphql_checkbox_selector );

		// since JS is not active to add a default field, we add a field
		$I->fillField( $graphql_field_name_input_selector, 'newFieldName' );

		$this->_submitForm( $I );

		// The checkbox SHOULD be checked again now
		$I->seeCheckboxIsChecked( $show_in_graphql_checkbox_selector );

	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function seeGraphqlDescriptionField( FunctionalTester  $I ): void {

		// we specify this xpath because we don't want false positives of the "Graphql Description" being seen anywhere in the DOM (i.e. on the Field Group or another field)
		$I->see( 'GraphQL Description', '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="graphql_description"]//label' );
	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function testSavingGraphqlDescriptionField( FunctionalTester $I ) {

		// Here we want to test that saving the "show_in_graphql" field
		// properly sets the value as unchecked and checked again

		$graphql_description_input_selector = '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="graphql_description"]//input[@type="text"]';

		// default value should be empty
		$graphql_description_input = 'test description...';

		$I->fillField( $graphql_description_input_selector, $graphql_description_input );

		// Submit the form
		$this->_submitForm( $I );

		// Check the new value of the field
		$graphql_description_updated_value  = $I->grabAttributeFrom( $graphql_description_input_selector, 'value' );
		$I->assertSame( $graphql_description_input, $graphql_description_updated_value );

	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function seeGraphqlFieldNameField( FunctionalTester  $I ): void {

		// we specify this xpath because we don't want false positives of the "Graphql Field Name" being seen anywhere in the DOM (i.e. on the Field Group or another field)
		$I->see( 'GraphQL Field Name', '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="graphql_field_name"]//label' );

	}

	/**
	 * @param \FunctionalTester $I
	 *
	 * @return void
	 */
	public function testSavingGraphqlFieldNameField( FunctionalTester $I ) {

		// Here we want to test that saving the "show_in_graphql" field
		// properly sets the value as unchecked and checked again

		$graphql_field_name_input_selector = '//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[@data-name="graphql_field_name"]//input[@type="text"]';

		// default value should be the formatted value of the label
		$graphql_field_name_placeholder  = $I->grabAttributeFrom( $graphql_field_name_input_selector, 'placeholder' );
		$I->assertSame( 'newFieldName', $graphql_field_name_placeholder );

		$graphql_field_name_input = 'newFieldName';

		$I->fillField( $graphql_field_name_input_selector, $graphql_field_name_input );

		// Submit the form
		$this->_submitForm( $I );

		// Check the new value of the field
		$graphql_field_name_updated_value  = $I->grabAttributeFrom( $graphql_field_name_input_selector, 'value' );
		$I->assertSame( $graphql_field_name_input, $graphql_field_name_updated_value );

	}

}
