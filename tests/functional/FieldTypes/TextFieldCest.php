<?php

class TextFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	// Run these steps before each test
	public function _before( FunctionalTester $I ): void {
		parent::_before( $I );
	}

	/**
	 * @return string
	 */
	public function _getAcfFieldType(): string {
		return 'text';
	}

}

// - graphql_non_null
//   - ui should be a checkbox
//   - default value should be unchecked
//   - field description should educate users about the impact of this change
//     - i.e. changing this field can cause breaking changes to behavior
//   - changing the value and saving shows the changed value when page reloads
