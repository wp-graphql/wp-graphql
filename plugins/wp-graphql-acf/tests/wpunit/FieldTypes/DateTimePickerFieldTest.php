<?php

class DateTimePickerFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'date_time_picker';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_expected_clone_value(): string {
		return '2023-06-22T00:09:00+00:00';
	}

	public function get_clone_value_to_save(): string {
		return "2023-06-22 00:09:02";
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestDateTimePicker
		}
		';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testDateTimePicker
		}
		';
	}

	public function get_block_data_to_store() {
		return '2023-09-28 01:02:03';
	}

	public function get_expected_block_fragment_response() {
		return '2023-09-28T01:02:00+00:00';
	}
}
