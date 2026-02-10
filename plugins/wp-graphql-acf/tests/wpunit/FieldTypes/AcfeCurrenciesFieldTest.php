<?php

class AcfeCurrenciesFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_currencies';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'ACFE_Currency',
		];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testAcfeCurrencies {
		     __typename
            name
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return [ 'LRD' ];
	}

	public function get_expected_block_fragment_response() {
		return [
			[
				'__typename' => 'ACFE_Currency',
				'name' => 'Liberian dollar',
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

}
