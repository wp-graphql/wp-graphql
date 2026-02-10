<?php

class FileFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'file';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfMediaItemConnectionEdge';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testFile {
		    node {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return $this->imageId;
	}

	public function get_expected_block_fragment_response() {
		return [
			'node' => [
				'__typename' => 'MediaItem',
				'databaseId' => $this->imageId
			]
		];
	}


}
