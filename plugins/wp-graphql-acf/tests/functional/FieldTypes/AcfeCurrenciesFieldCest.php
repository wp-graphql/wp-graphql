<?php

class AcfeCurrenciesFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfeProFieldCest {

	public function _getAcfFieldType(): string {
		return 'acfe_currencies';
	}

	/**
	 * Override to use the correct field key for ACFE Currencies field
	 * from tests-acf-extended-pro-kitchen-sink.json
	 *
	 * @return string
	 */
	public function _getTestFieldKey(): string {
		return 'field_64387a09bb89f';
	}

}
