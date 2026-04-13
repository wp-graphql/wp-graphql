<?php

class UrlFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'url';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_data_to_store() {
		return 'https://test.com/';
	}

	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testUrl
		}
		';
	}

	public function get_expected_block_fragment_response() {
		return $this->get_data_to_store();
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  ' . $this->get_formatted_clone_field_name() . '
		}
		';
	}

}
