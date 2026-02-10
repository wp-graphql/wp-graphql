<?php

class AcfeCountriesFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_countries';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'ACFE_Country',
		];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testAcfeCountries {
		     __typename
            name
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return [ 'td' ];
	}

	public function get_expected_block_fragment_response() {
		return [
			[
				'__typename' => 'ACFE_Country',
				'name' => 'Chad',
			]
		];
	}

	public function testFieldExists(): void {
		$field_types = acf_get_field_types();
		if ( class_exists('ACFE_Pro') ) {
			$this->assertTrue( array_key_exists( $this->get_field_type(), $field_types ) );
		} else {
			$this->assertFalse( array_key_exists( $this->get_field_type(), $field_types ) );
		}
	}

	public function get_clone_value_to_save() {
		return [ 'us' ];
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestAcfeCountries {
				__typename
				name
				code
			}
		}
		';
	}

	public function get_expected_clone_value() {
		return [
			[
				'__typename' => 'ACFE_Country',
				'name' => 'United States',
				'code' => 'us',
			],
		];
	}

}
