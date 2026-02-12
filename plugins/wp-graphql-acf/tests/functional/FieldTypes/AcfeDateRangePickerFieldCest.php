<?php

class AcfeDateRangePickerFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfeProFieldCest {

	public function _getAcfFieldType(): string {
		return 'acfe_date_range_picker';
	}

	/**
	 * Override to use the correct field key for ACFE Date Range Picker field
	 * from tests-acf-extended-pro-kitchen-sink.json
	 *
	 * @return string
	 */
	public function _getTestFieldKey(): string {
		return 'field_6449a1432046d';
	}

}
