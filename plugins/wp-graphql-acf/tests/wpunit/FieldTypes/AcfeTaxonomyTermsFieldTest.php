<?php

class AcfeTaxonomyTermsFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfeFieldTestCase {

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
		return 'acfe_taxonomy_terms';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'TermNode',
		];
	}

	public function testFieldExists(): void {
		$field_types = acf_get_field_types();
		$this->assertTrue( array_key_exists( $this->get_field_type(), $field_types ) );
	}

	// Since user roles are not public
	// they will not be returned in a public query
	public function get_expected_clone_value(): array {
		return [
			[
				'__typename' => 'Category',
				'databaseId' => $this->category->term_id,
			],
			[
				'__typename' => 'Tag',
				'databaseId' => $this->tag->term_id,
			]
		];
	}

	public function get_clone_value_to_save(): array {
		return [ $this->category->term_id, $this->tag->term_id ];
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestAcfeTaxonomyTerms {
			  __typename
			  databaseId
			}
		}
		';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testAcfeTaxonomyTerms {
		    __typename
		    name
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return [
			$this->category->term_id,
			$this->tag->term_id,
		];
	}

	public function get_expected_block_fragment_response() {
		return [
			[
				'__typename' => 'Category',
				'name' => $this->category->name,
			],
			[
				'__typename' => 'Tag',
				'name' => $this->tag->name,
			],
		];
	}

}
