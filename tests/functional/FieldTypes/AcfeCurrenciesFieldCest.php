<?php

class AcfeCurrenciesFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfeProFieldCest {

	public function _getAcfFieldType(): string {
		return 'acfe_currencies';
	}

}
