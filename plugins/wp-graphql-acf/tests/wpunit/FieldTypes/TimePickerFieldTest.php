<?php

class TimePickerFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'time_picker';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_data_to_store() {
		return "00:34:00";
	}


	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	public function get_expected_clone_value(): string {
		return '12:34 am';
	}

	public function get_clone_value_to_save(): string {
		return $this->get_data_to_store();
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestTimePicker
		}
		';
	}

}
