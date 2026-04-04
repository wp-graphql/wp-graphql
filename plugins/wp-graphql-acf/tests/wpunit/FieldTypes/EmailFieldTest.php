<?php

class EmailFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'email';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  clonedTestEmail
		}
		';
	}

	public function get_clone_value_to_save():string {
		return 'test@example.com';
	}

	public function get_expected_clone_value():string {
		return 'test@example.com';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testEmail
		}
		';
	}

	public function get_block_data_to_store() {
		return 'test@example.com';
	}

	public function get_expected_block_fragment_response() {
		return 'test@example.com';
	}

}
