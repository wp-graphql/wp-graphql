<?php

class UserFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'user';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfUserConnection';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	public function get_data_to_store() {
		return [ $this->admin->ID ];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testUser {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return $this->admin->ID;
	}

	public function get_expected_block_fragment_response() {
		return [
			'nodes' => [
				[
					'__typename' => 'User',
					'databaseId' => $this->admin->ID
				]
			]
		];
	}

	public function get_query_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  testUser {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}';
	}

	public function get_expected_value() {
		return [
			'nodes' => [
				[
					'__typename' => 'User',
					'databaseId' => $this->admin->ID,
				]
			]
		];
	}

	public function get_clone_value_to_save() {
		return $this->get_data_to_store();
	}

	public function get_expected_clone_value() {
		return [
			'nodes' => [
				[
					'__typename' => 'User',
					'databaseId' => $this->admin->ID,
				]
			]
		];
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestUser {
			  nodes {
			    __typename
			    databaseId
			  }
			}
		}
		';
	}


}
