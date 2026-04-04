<?php

namespace Tests\WPGraphQL\Acf\Functional;

use FunctionalTester;

abstract class AcfFieldCest {

	/**
	 * Static flag to track if JSON has been imported for this test class
	 * This allows us to import once per test class instead of once per test
	 *
	 * @var array<string, bool>
	 */
	protected static $json_imported = [];

	/**
	 * Static cache to store field group post ID per test class
	 * This allows us to construct direct URLs instead of clicking through pages
	 *
	 * @var array<string, int>
	 */
	protected static $field_group_post_ids = [];

	/**
	 * Static cache to store field group titles per test class
	 * This allows us to look up the correct field group name from the JSON
	 *
	 * @var array<string, string>
	 */
	protected static $field_group_titles = [];


	/**
	 * Before the tests run, we:
	 * - import the JSON file for the field group (only once per test class)
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
		// Import the JSON file to test (only once per test class)
		// Each class can override this by defining their
		// own _getJsonToImport() method
		$test_class = get_class($this);
		$json_file = $this->_getJsonToImport();
		$import_key = $test_class . ':' . $json_file;
		
		if ( ! isset( self::$json_imported[$import_key] ) ) {
			$I->importJson( $json_file );
			self::$json_imported[$import_key] = true;
			
			// Extract field group title and key from JSON file for caching
			$field_group_data = $this->_getFieldGroupData( $json_file );
			if ( $field_group_data && isset( $field_group_data['title'] ) ) {
				self::$field_group_titles[$test_class] = $field_group_data['title'];
				
				// Cache the field group post ID for faster navigation
				// Use the field group key to find the correct one (more reliable than title)
				$field_group_id = null;
				if ( isset( $field_group_data['key'] ) ) {
					$field_group_id = $I->getFieldGroupPostIdByKey( $field_group_data['key'] );
				}
				// Fallback to title lookup if key lookup fails
				if ( ! $field_group_id ) {
					$field_group_id = $I->getFieldGroupPostId( $field_group_data['title'] );
				}
				if ( $field_group_id ) {
					self::$field_group_post_ids[$test_class] = $field_group_id;
				}
			}
		}

		// Note: We can't skip navigation entirely because each test may modify the field state.
		// However, we optimize by using direct URLs and reducing wait times.
		$field_type = $this->_getAcfFieldType();

		// Login
		$I->loginAsAdmin();

		// Try to use cached post ID to navigate directly to field group edit page
		// This skips the intermediate "list all field groups" page
		$field_group_id = self::$field_group_post_ids[$test_class] ?? null;
		if ( $field_group_id ) {
			// Navigate directly to the field group edit page
			$I->amOnPage( '/wp-admin/post.php?post=' . $field_group_id . '&action=edit' );
			
			// Check if we got redirected back to the list page
			$current_url = $I->grabFromCurrentUrl();
			if ( strpos( $current_url, 'edit.php?post_type=acf-field-group' ) !== false ) {
				// We were redirected - try clicking the field group link instead
				$field_group_title = self::$field_group_titles[$test_class] ?? 'Foo Name';
				$I->click( $field_group_title );
			}
		} else {
			// Fallback: navigate through the list page (slower)
			$I->amOnPage( '/wp-admin/edit.php?post_type=acf-field-group' );
			// Use cached field group title or fallback to "Foo Name"
			$field_group_title = self::$field_group_titles[$test_class] ?? 'Foo Name';
			$I->click( $field_group_title );
		}

		// Click edit on the field
		// Codeception functional tests handle page loads automatically
		
		// Verify we're on the field group edit page before trying to click the field
		// Try multiple ways to verify we're on the edit page (ACF UI may have changed)
		$on_edit_page = false;
		$edit_page_indicators = [
			'Edit Field Group',
			'Field Group',
			'Foo Name', // The field group title should be visible
		];
		
		foreach ( $edit_page_indicators as $indicator ) {
			try {
				$I->see( $indicator );
				$on_edit_page = true;
				break;
			} catch ( \Exception $e ) {
				continue;
			}
		}
		
		if ( ! $on_edit_page ) {
			// If we can't verify we're on the edit page, log details and try to continue anyway
			// Don't throw - just log and continue, as the page might be correct but UI changed
		}
		
		// Original check for backward compatibility
		try {
			$I->see( 'Edit Field Group' );
		} catch ( \Exception $e ) {
			// If we're not on the edit page, log the current URL for debugging
			$current_url = $I->grabFromCurrentUrl();
			codecept_debug( 'Not on field group edit page. Current URL: ' . $current_url );
			throw $e;
		}
		
		try {
			$I->click( '//div[@data-key="' . $this->_getTestFieldKey() . '"]//a[@title="Edit field"]' );
		} catch ( \Exception $e ) {
			throw $e;
		}

		// Select the "Field Type" that we want to test against
		// This xpath finds the "Field Type" select for the field we're testing against to keep things constant
		
		// First, check if the field is already the correct type
		// For ACF Extended fields, the field type might be locked and not changeable
		$page_source = $I->grabPageSource();
		$field_already_correct_type = false;
		// Check if the field type is already set correctly by looking for data-type attribute
		if ( preg_match('/data-key="' . preg_quote($this->_getTestFieldKey(), '/') . '"[^>]*data-type="' . preg_quote($field_type, '/') . '"/', $page_source) ) {
			$field_already_correct_type = true;
		}
		// Also check for type input value or selected option
		if ( ! $field_already_correct_type ) {
			// Check for input with name containing [type] and value matching
			if ( preg_match('/<input[^>]*name="[^"]*\[type\][^"]*"[^>]*value="' . preg_quote($field_type, '/') . '"/', $page_source) ) {
				$field_already_correct_type = true;
			}
			// Check for select option with value matching and selected attribute
			if ( ! $field_already_correct_type && preg_match('/<select[^>]*name="[^"]*\[type\][^"]*"[^>]*>.*?<option[^>]*value="' . preg_quote($field_type, '/') . '"[^>]*selected/i', $page_source) ) {
				$field_already_correct_type = true;
			}
		}
		
		// If field is already the correct type, skip selection
		if ( $field_already_correct_type ) {
			// Field is already correct type, skip selection
		} else {
			// Field type needs to be selected - try to find and use the field type select
			try {
				$field_type_select_selector = '//div[@data-key="' . $this->_getTestFieldKey() . '"]//select[contains(concat(" ", @class, " "), " field-type ")]';
				
				// Check if the select element exists (seeElement throws if not found)
				$I->seeElement( $field_type_select_selector );
				
				// Get available options
				$available_options = $I->grabMultiple( $field_type_select_selector . '//option/@value' );
				
				// Check if the field type is available
				if ( ! in_array( $field_type, $available_options, true ) ) {
					// Field type not available - this might be an ACF Extended field that's locked
					// Check again if field is correct type (might have been missed in initial check)
					$page_source_after = $I->grabPageSource();
					$field_still_correct = false;
					if ( preg_match('/data-key="' . preg_quote($this->_getTestFieldKey(), '/') . '"[^>]*data-type="' . preg_quote($field_type, '/') . '"/', $page_source_after) ) {
						$field_still_correct = true;
					}
					
					if ( $field_still_correct ) {
						// Field is already correct type, just not available in dropdown (locked field)
						// Skip selection
					} else {
						throw new \Exception( 'Field type "' . $field_type . '" not available in dropdown. Available types: ' . implode( ', ', $available_options ) );
					}
				} else {
					$I->selectOption( $field_type_select_selector, $field_type );
				}
			} catch ( \Exception $e ) {
				// Field type select not found or selection failed
				throw $e;
			}
		}

		// For ACF 6.1+, GraphQL fields are in a tab - click the GraphQL tab if it exists
		// Try multiple selectors to find the GraphQL tab button
		$graphql_tab_selectors = [
			'//div[@data-key="' . $this->_getTestFieldKey() . '"]//a[contains(@class, "acf-tab-button") and contains(text(), "GraphQL")]',
			'//div[@data-key="' . $this->_getTestFieldKey() . '"]//button[contains(@class, "acf-tab-button") and contains(text(), "GraphQL")]',
			'//div[@data-key="' . $this->_getTestFieldKey() . '"]//*[contains(@class, "acf-tab")]//a[contains(text(), "GraphQL")]',
		];

		$tab_clicked = false;
		foreach ( $graphql_tab_selectors as $selector ) {
			try {
				$I->seeElement( $selector );
				$I->click( $selector );
				// Codeception handles page loads automatically - no need for explicit wait
				$tab_clicked = true;
				break;
			} catch ( \Exception $e ) {
				// Try next selector
				continue;
			}
		}

		// If no tab was found/clicked, assume ACF < 6.1 where fields are always visible
	}

	/**
	 * Static flag to track if cleanup has been done for this test class
	 * This allows us to clean up once per test class instead of once per test
	 *
	 * @var array<string, bool>
	 */
	protected static $cleanup_done = [];

	/**
	 * @return void
	 */
	public function _after( FunctionalTester $I ): void {
		// Delete imported field group (only once per test class, at the end)
		// Skip cleanup if already done for this class
		$test_class = get_class($this);
		$cleanup_key = $test_class;

		if ( isset( self::$cleanup_done[$cleanup_key] ) ) {
			return;
		}

		$I->loginAsAdmin();
		$I->amOnPage('/wp-admin/edit.php?post_type=acf-field-group');
		
		// Use cached field group title or fallback to "Foo Name"
		$field_group_title = self::$field_group_titles[$test_class] ?? 'Foo Name';
		
		// Use a more specific selector that targets the row containing the field group title
		$checkbox_selector = '//tr[contains(., "' . $field_group_title . '")]//th[contains(@class, "check-column")]//input[@type="checkbox"]';
		
		// Try the specific selector first, fall back to first row if that fails
		try {
			$I->checkOption( $checkbox_selector );
		} catch ( \Exception $e ) {
			// Fallback: try to select the first checkbox if the field group title selector doesn't work
			// This handles edge cases where the field group might have a different name or structure
			$I->checkOption( '//tbody/tr[1]//th[contains(@class, "check-column")]//input[@type="checkbox"]' );
		}
		
		$I->selectOption( '#bulk-action-selector-bottom', 'trash' );
		$I->click( '#doaction2' );

		// Mark cleanup as done for this class
		self::$cleanup_done[$cleanup_key] = true;
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
	 * Extract the field group data (title and key) from a JSON file
	 * 
	 * @param string $json_file The JSON file path relative to tests/_data/
	 * @return array|null The field group data (title, key) or null if not found
	 */
	protected function _getFieldGroupData( string $json_file ): ?array {
		$json_path = __DIR__ . '/../../_data/' . $json_file;
		if ( ! file_exists( $json_path ) ) {
			return null;
		}
		
		$json_content = file_get_contents( $json_path );
		$json_data = json_decode( $json_content, true );
		
		if ( ! is_array( $json_data ) || empty( $json_data ) ) {
			return null;
		}
		
		// Handle array of field groups (most common case)
		if ( isset( $json_data[0] ) && is_array( $json_data[0] ) ) {
			return [
				'title' => $json_data[0]['title'] ?? null,
				'key' => $json_data[0]['key'] ?? null,
			];
		}
		
		// Handle single field group object
		if ( isset( $json_data['title'] ) ) {
			return [
				'title' => $json_data['title'],
				'key' => $json_data['key'] ?? null,
			];
		}
		
		return null;
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
