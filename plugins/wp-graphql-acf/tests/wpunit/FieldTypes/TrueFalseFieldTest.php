<?php

class TrueFalseFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {


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
		return 'true_false';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'Boolean';
	}

	public function get_data_to_store() {
		return 'true';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testTrueFalse
		}
		';
	}

	public function get_block_data_to_store() {
		return '1';
	}

	public function get_expected_block_fragment_response() {
		return true;
	}

	public function get_expected_clone_value(): bool {
		return 'true';
	}

	public function get_clone_value_to_save(): string {
		return $this->get_data_to_store();
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestTrueFalse
		}
		';
	}



}
