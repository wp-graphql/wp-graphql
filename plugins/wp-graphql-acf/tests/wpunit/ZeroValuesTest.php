<?php

/**
 * Regression tests for wp-graphql/wp-graphql#4540: a stored 0 (or "0") is a value, not an empty
 * field, so it should resolve as 0 rather than null.
 *
 * Field values went through PHP's empty() in several places (the row lookups, the final "empty
 * becomes null" step, the select/checkbox resolver), and number fields were cast with
 * `(float) $value ?: null`, all of which treat 0 and "0" as empty.
 */
class ZeroValuesTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * Queries the given selection on the published post's AcfTestGroup.
	 *
	 * @param string $selection The selection on the AcfTestGroup type.
	 */
	private function query_test_group( string $selection ): array {
		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { ' . $selection . ' } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		return $actual['data']['post']['acfTestGroup'];
	}

	public function testNumberZeroResolvesAsZero(): void {
		$field_key = $this->register_acf_field(
			[
				'type'               => 'number',
				'name'               => 'zero_number',
				'graphql_field_name' => 'zeroNumber',
			]
		);
		update_field( $field_key, 0, $this->published_post->ID );

		$group = $this->query_test_group( 'zeroNumber' );

		$this->assertNotNull( $group['zeroNumber'] );
		$this->assertEquals( 0, $group['zeroNumber'] );
	}

	public function testTextZeroResolvesAsZero(): void {
		$field_key = $this->register_acf_field(
			[
				'type'               => 'text',
				'name'               => 'zero_text',
				'graphql_field_name' => 'zeroText',
			]
		);
		update_field( $field_key, '0', $this->published_post->ID );

		$group = $this->query_test_group( 'zeroText' );

		$this->assertSame( '0', $group['zeroText'] );
	}

	public function testZeroInARepeaterRowResolvesAsZero(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$repeater_key = $this->register_acf_field(
			[
				'key'                => 'field_zero_repeater',
				'type'               => 'repeater',
				'name'               => 'zero_repeater',
				'graphql_field_name' => 'zeroRepeater',
				'sub_fields'         => [
					[
						'key'                => 'field_zero_repeater_number',
						'type'               => 'number',
						'name'               => 'number',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'number',
					],
					[
						'key'                => 'field_zero_repeater_text',
						'type'               => 'text',
						'name'               => 'text',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'text',
					],
				],
			]
		);
		update_field(
			$repeater_key,
			[
				[
					'field_zero_repeater_number' => 0,
					'field_zero_repeater_text'   => '0',
				],
			],
			$this->published_post->ID
		);

		$group = $this->query_test_group( 'zeroRepeater { number text }' );

		$this->assertNotNull( $group['zeroRepeater'][0]['number'] );
		$this->assertEquals( 0, $group['zeroRepeater'][0]['number'] );
		$this->assertSame( '0', $group['zeroRepeater'][0]['text'] );
	}

	public function testSelectWithAZeroChoiceResolves(): void {
		$field_key = $this->register_acf_field(
			[
				'type'               => 'select',
				'name'               => 'zero_select',
				'graphql_field_name' => 'zeroSelect',
				'choices'            => [
					'0' => 'Zero',
					'1' => 'One',
				],
				'return_format'      => 'value',
			]
		);
		update_field( $field_key, '0', $this->published_post->ID );

		$group = $this->query_test_group( 'zeroSelect' );

		$this->assertSame( [ '0' ], $group['zeroSelect'] );
	}

	/**
	 * A number field with no value still resolves as null.
	 */
	public function testEmptyNumberStillResolvesAsNull(): void {
		$field_key = $this->register_acf_field(
			[
				'type'               => 'number',
				'name'               => 'empty_number',
				'graphql_field_name' => 'emptyNumber',
			]
		);
		update_field( $field_key, '', $this->published_post->ID );

		$group = $this->query_test_group( 'emptyNumber' );

		$this->assertNull( $group['emptyNumber'] );
	}

	/**
	 * A text field with no value still resolves as null.
	 */
	public function testEmptyTextStillResolvesAsNull(): void {
		$field_key = $this->register_acf_field(
			[
				'type'               => 'text',
				'name'               => 'empty_text',
				'graphql_field_name' => 'emptyText',
			]
		);
		update_field( $field_key, '', $this->published_post->ID );

		$group = $this->query_test_group( 'emptyText' );

		$this->assertNull( $group['emptyText'] );
	}
}
