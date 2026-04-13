<?php

class FlexibleContentFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'flexible_content';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'AcfTestGroupTestFlexibleContent_Layout',
		];
	}

}
