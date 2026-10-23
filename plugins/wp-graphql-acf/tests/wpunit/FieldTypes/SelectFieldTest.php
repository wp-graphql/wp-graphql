<?php

class SelectFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

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
		return 'select';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'LIST';
	}

	public function get_expected_field_of_type(): ?array {
		return [
			'name' => 'String',
		];
	}

	public function get_expected_field_resolve_type(): ?string {
		return null;
	}

	/**
	 * @return int
	 */
	public function get_clone_value_to_save(): int {
		return 2;
	}

	/**
	 * @return string
	 */
	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestSelect
		}
		';
	}

	/**
	 * @return string[]
	 */
	public function get_expected_clone_value(): array {
		return [ '2' ];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testSelect
		}
		';
	}

	public function get_block_data_to_store() {
		return 2;
	}

	public function get_expected_block_fragment_response() {
		return [ '2' ];
	}


	/**
	 * Registers a select field with "red : Bright Red" and "blue : Deep Blue" choices, stores a value
	 * for the published post, and returns what the field resolves to.
	 *
	 * @param string $return_format The return format ("value", "label" or "array").
	 * @param bool   $multiple      Whether the select allows multiple values.
	 * @param mixed  $value         The value to store.
	 *
	 * @return mixed
	 */
	private function resolve_select( string $return_format, bool $multiple, $value ) {
		$field_name = 'select_' . $return_format . ( $multiple ? '_multiple' : '_single' );
		$graphql    = \WPGraphQL\Utils\Utils::format_field_name( $field_name );

		$field_key = $this->register_acf_field(
			[
				'type'               => 'select',
				'name'               => $field_name,
				'graphql_field_name' => $graphql,
				'choices'            => [
					'red'  => 'Bright Red',
					'blue' => 'Deep Blue',
				],
				'multiple'           => $multiple ? 1 : 0,
				'return_format'      => $return_format,
			]
		);
		update_field( $field_key, $value, $this->published_post->ID );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { ' . $graphql . ' } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		return $actual['data']['post']['acfTestGroup'][ $graphql ];
	}

	/**
	 * Regression test for wp-graphql/wp-graphql#4561: with "Both (Array)", ACF formats a single
	 * choice as one [ 'value' => ..., 'label' => ... ] array, which was flattened into
	 * [ 'red', 'Bright Red' ].
	 */
	public function testSingleSelectWithArrayReturnFormatReturnsTheValue(): void {
		$this->assertSame( [ 'red' ], $this->resolve_select( 'array', false, 'red' ) );
	}

	public function testMultipleSelectWithArrayReturnFormatReturnsTheValues(): void {
		$this->assertSame( [ 'red', 'blue' ], $this->resolve_select( 'array', true, [ 'red', 'blue' ] ) );
	}

	public function testSelectWithLabelReturnFormatReturnsTheLabel(): void {
		$this->assertSame( [ 'Bright Red' ], $this->resolve_select( 'label', false, 'red' ) );
	}

	/**
	 * Inside a row, ACF hands radio fields over formatted too, so a radio with "Both (Array)" was
	 * flattened into a list for a String field.
	 */
	public function testRadioWithArrayReturnFormatInRepeaterReturnsTheValue(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$repeater_key = $this->register_acf_field(
			[
				'key'                => 'field_radio_array_repeater',
				'type'               => 'repeater',
				'name'               => 'radio_array_repeater',
				'graphql_field_name' => 'radioArrayRepeater',
				'sub_fields'         => [
					[
						'key'                => 'field_radio_array_repeater_radio',
						'type'               => 'radio',
						'name'               => 'radio',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'radio',
						'choices'            => [
							'red'  => 'Bright Red',
							'blue' => 'Deep Blue',
						],
						'return_format'      => 'array',
					],
				],
			]
		);
		update_field(
			$repeater_key,
			[ [ 'field_radio_array_repeater_radio' => 'blue' ] ],
			$this->published_post->ID
		);

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { radioArrayRepeater { radio } } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'blue', $actual['data']['post']['acfTestGroup']['radioArrayRepeater'][0]['radio'] );
	}
}
