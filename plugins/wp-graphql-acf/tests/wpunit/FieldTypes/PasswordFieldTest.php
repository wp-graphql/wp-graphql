<?php

class PasswordFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'password';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_expected_clone_value(): string {
		return 'test123';
	}

	public function get_clone_value_to_save(): string {
		return "test123";
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestPassword
		}
		';
	}
}
