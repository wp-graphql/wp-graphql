<?php

class NumberFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	public function _getAcfFieldType(): string {
		return 'number';
	}

}
