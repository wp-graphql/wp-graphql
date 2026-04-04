<?php

class DatePickerFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'date_picker';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_expected_clone_value(): string {
		return '2023-06-22T00:00:00+00:00';
	}

	public function get_clone_value_to_save(): string {
		return "2023-06-22";
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestDatePicker
		}
		';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testDatePicker
		}
		';
	}

	public function get_block_data_to_store() {
		return '20230928';
	}

	public function get_expected_block_fragment_response() {
		return '2023-09-28T00:00:00+00:00';
	}

}
