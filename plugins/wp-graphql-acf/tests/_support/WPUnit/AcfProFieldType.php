<?php

namespace Tests\WPGraphQL\Acf\WPUnit;

class AcfProFieldType extends \acf_field  {
	public function __construct( $name ) {
		$this->name = $name;
		$this->label = $name;

		// if ACF PRO is not active, skip the test
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		parent::__construct();
	}
}
