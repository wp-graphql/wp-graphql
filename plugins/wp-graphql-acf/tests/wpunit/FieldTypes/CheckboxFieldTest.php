<?php

class CheckboxFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'checkbox';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'String',
		];
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	// the values are saved as a mix of strings and integers
	public function get_clone_value_to_save(): array {
		return [
			"one",
			2
		];
	}

	// The schema outputs the values as [String] because there can't be Scalar Unions
	public function get_expected_clone_value(): array {
		return [
			"one",
			"2"
		];
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestCheckbox
		}
		';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testCheckbox
		}
		';
	}

	public function get_block_data_to_store() {
		return [ 'one', 2 ];
	}

	public function get_expected_block_fragment_response() {
		return [ 'one', '2' ];
	}

}
