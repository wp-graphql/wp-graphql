<?php

class AcfeUserRolesFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_user_roles';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'UserRole',
		];
	}

	public function testFieldExists(): void {
		$field_types = acf_get_field_types();
		$this->assertTrue( array_key_exists( $this->get_field_type(), $field_types ) );
	}

	// Since user roles are not public
	// they will not be returned in a public query
	public function get_expected_clone_value(): array {
		return [];
	}

	public function get_clone_value_to_save(): array {
		return [ 'administrator', 'editor' ];
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestAcfeUserRoles {
			  name
			}
		}
		';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testAcfeUserRoles {
		    __typename
		    name
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return [
			'editor',
			'administrator'
		];
	}

	// User roles are not public, so this will return an empty array
	// @todo: test against auth request too?
	public function get_expected_block_fragment_response() {
		return [];
	}
}
