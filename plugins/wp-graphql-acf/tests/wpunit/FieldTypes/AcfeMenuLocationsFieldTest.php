<?php

class AcfeMenuLocationsFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_menu_locations';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'MenuLocationEnum',
		];
	}

//  @todo: Need to register menus for the test environment to be able to store/query for them
//	public function get_block_query_fragment() {
//		return '
//		fragment BlockQueryFragment on AcfTestGroup {
//		  testAcfeMenuLocations
//		}
//		';
//	}
//
//	public function get_block_data_to_store() {
//		return '';
//	}
//
//	public function get_expected_block_fragment_response() {
//		return '';
//	}

	public function testFieldExists(): void {
		$field_types = acf_get_field_types();
		if ( class_exists('ACFE_Pro') ) {
			$this->assertTrue( array_key_exists( $this->get_field_type(), $field_types ) );
		} else {
			$this->assertFalse( array_key_exists( $this->get_field_type(), $field_types ) );
		}
	}

}
