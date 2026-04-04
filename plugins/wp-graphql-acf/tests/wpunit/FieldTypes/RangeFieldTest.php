<?php

class RangeFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'range';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'Float';
	}

	/**
	 * @return float
	 */
	public function get_clone_value_to_save(): float {
		return 2.5;
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestRange
		}
		';
	}

	/**
	 * @return float
	 */
	public function get_expected_clone_value(): float {
		return 2.5;
	}

}
