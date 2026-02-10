<?php

class FlexibleContentFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfProFieldCest {

	public function _getAcfFieldType(): string {
		return 'flexible_content';
	}

}
