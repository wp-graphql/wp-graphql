<?php

class RangeFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	public function _getAcfFieldType(): string {
		return 'range';
	}

}
