<?php


class RepeaterFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfProFieldCest {

	public function _getAcfFieldType(): string {
		return 'repeater';
	}

}
