<?php

class TimePickerFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	public function _getAcfFieldType(): string {
		return 'time_picker';
	}

}
