<?php

class SelectFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'select';
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

	/**
	 * @return int
	 */
	public function get_clone_value_to_save(): int {
		return 2;
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestSelect
		}
		';
	}

	/**
	 * @return string[]
	 */
	public function get_expected_clone_value(): array {
		return [ '2' ];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testSelect
		}
		';
	}

	public function get_block_data_to_store() {
		return 2;
	}

	public function get_expected_block_fragment_response() {
		return [ '2' ];
	}

}
