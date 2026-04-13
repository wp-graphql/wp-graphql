<?php

class RadioFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'radio';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	/**
	 * Here we're intentionally saving an integer, and we'll assert that
	 * the response is a string as radios can have strings or integers as choices, so they
	 * are always cast in the schema as "String"
	 * @return int
	 */
	public function get_clone_value_to_save(): int {
		return 32;
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestRadio
		}
		';
	}

	/**
	 * A radio will return a string, even if the choices are integers
	 *
	 * @return string
	 */
	public function get_expected_clone_value(): string {
		return "32";
	}

}
