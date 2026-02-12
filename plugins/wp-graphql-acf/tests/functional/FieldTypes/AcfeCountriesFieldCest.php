<?php

class AcfeCountriesFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfeProFieldCest {

	public function _getAcfFieldType(): string {
		return 'acfe_countries';
	}

	/**
	 * Override to use the correct field key for ACFE Countries field
	 * from tests-acf-extended-pro-kitchen-sink.json
	 *
	 * @return string
	 */
	public function _getTestFieldKey(): string {
		return 'field_64387c3379587';
	}

}
