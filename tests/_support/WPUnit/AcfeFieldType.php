<?php

namespace Tests\WPGraphQL\Acf\WPUnit;

class AcfeFieldType extends \acf_field  {
	public function __construct( $name ) {
		$this->name = $name;
		$this->label = $name;
		parent::__construct();
	}
}
