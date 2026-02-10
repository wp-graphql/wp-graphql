<?php

class NumberFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}


	/**
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type(): string {
		return 'number';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'Float';
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  ' . $this->get_formatted_clone_field_name() . '
		}
		';
	}

	public function get_clone_value_to_save():int {
		return 123;
	}

	public function get_expected_clone_value():float {
		return 123;
	}

}
