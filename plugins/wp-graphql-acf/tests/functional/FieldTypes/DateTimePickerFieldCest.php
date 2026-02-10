<?php

class DateTimePickerFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	public function _getAcfFieldType(): string {
		return 'date_time_picker';
	}

}
