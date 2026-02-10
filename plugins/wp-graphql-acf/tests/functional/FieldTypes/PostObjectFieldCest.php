<?php

class PostObjectFieldCest extends \Tests\WPGraphQL\Acf\Functional\AcfFieldCest {

	public function _getAcfFieldType(): string {
		return 'post_object';
	}

}
