<?php

class PostObjectFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'post_object';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfContentNodeConnection';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	/**
	 * @return int
	 */
	public function get_clone_value_to_save(): int {
		return $this->published_post->ID;
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestPostObject {
			  nodes {
			     __typename
			     databaseId
			  }
			}
		}
		';
	}

	/**
	 * @return array
	 */
	public function get_expected_clone_value(): array {
		return [
			'nodes' => [
				[
					'__typename' => 'Post',
					'databaseId' => $this->published_post->ID,
				]
			]
		];
	}

}
