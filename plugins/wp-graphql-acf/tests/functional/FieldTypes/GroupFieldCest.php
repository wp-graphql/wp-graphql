<?php

class GroupFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	public function _getAcfFieldType(): string {
		return 'group';
	}

}
