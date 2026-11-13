<?php

/**
 * Descriptions and deprecation reasons can be closures that are resolved lazily, but a plain
 * string is never called, even when it matches the name of a PHP function. PHP function names
 * are case-insensitive, so ordinary one-word descriptions like "Date", "Next" or "Time" name a
 * built-in function.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4690
 */
class DescriptionsNamedLikeFunctionsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Returns a description for the callable-array test.
	 */
	public static function lazy_description(): string {
		return 'A description from a callable array';
	}

	/**
	 * Registers types whose descriptions name PHP functions: date() and next() need arguments,
	 * and time() and count() return something other than the description.
	 */
	private function register_types( array $enum_values, array $field_config = [] ): void {
		add_action(
			'graphql_register_types',
			static function () use ( $enum_values, $field_config ) {
				register_graphql_enum_type(
					'FunctionNamedDescriptionEnum',
					[
						'description' => 'Time',
						'values'      => $enum_values,
					]
				);

				register_graphql_object_type(
					'FunctionNamedDescriptionObject',
					[
						'description' => 'Count',
						'fields'      => [
							'value' => [
								'type'        => 'FunctionNamedDescriptionEnum',
								'description' => 'Date',
								'resolve'     => static function () {
									return 'a';
								},
							],
						],
					]
				);

				register_graphql_field(
					'RootQuery',
					'functionNamedDescription',
					array_merge(
						[
							'type'        => 'FunctionNamedDescriptionObject',
							'description' => 'Date',
							'resolve'     => static function () {
								return [];
							},
						],
						$field_config
					)
				);
			}
		);
	}

	public function testIntrospectionReturnsDescriptionsThatNamePhpFunctions(): void {
		$this->register_types(
			[
				'A' => [
					'value'       => 'a',
					'description' => 'Next',
				],
				'B' => [
					'value'             => 'b',
					'description'       => 'Time',
					'deprecationReason' => 'Date',
				],
			],
			[ 'deprecationReason' => 'Next' ]
		);

		$actual = $this->graphql(
			[
				'query' => '
				{
					enumType: __type(name: "FunctionNamedDescriptionEnum") {
						description
						enumValues(includeDeprecated: true) { name description deprecationReason }
					}
					objectType: __type(name: "FunctionNamedDescriptionObject") {
						description
						fields { name description }
					}
					rootQuery: __type(name: "RootQuery") {
						fields(includeDeprecated: true) { name description deprecationReason }
					}
				}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'Time', $actual['data']['enumType']['description'] );
		$this->assertSame(
			[
				[
					'name'              => 'A',
					'description'       => 'Next',
					'deprecationReason' => null,
				],
				[
					'name'              => 'B',
					'description'       => 'Time',
					'deprecationReason' => 'Date',
				],
			],
			$actual['data']['enumType']['enumValues']
		);

		$this->assertSame( 'Count', $actual['data']['objectType']['description'] );
		$this->assertSame(
			[
				[
					'name'        => 'value',
					'description' => 'Date',
				],
			],
			$actual['data']['objectType']['fields']
		);

		$root_field = array_values(
			array_filter(
				$actual['data']['rootQuery']['fields'],
				static function ( $field ) {
					return 'functionNamedDescription' === $field['name'];
				}
			)
		);
		$this->assertSame(
			[
				[
					'name'              => 'functionNamedDescription',
					'description'       => 'Date',
					'deprecationReason' => 'Next',
				],
			],
			$root_field
		);
	}

	public function testQueriesResolveWhenDescriptionsNamePhpFunctions(): void {
		$this->register_types(
			[
				'A' => [
					'value'       => 'a',
					'description' => 'Next',
				],
			]
		);

		$actual = $this->graphql( [ 'query' => '{ functionNamedDescription { value } }' ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'A', $actual['data']['functionNamedDescription']['value'] );
	}

	public function testCallableDescriptionsAreStillResolved(): void {
		$this->register_types(
			[
				'A' => [
					'value'       => 'a',
					'description' => static function () {
						return 'A description from a closure';
					},
				],
				'B' => [
					'value'       => 'b',
					'description' => [ self::class, 'lazy_description' ],
				],
			],
			[
				'description'       => static function () {
					return 'A field description from a closure';
				},
				'deprecationReason' => [ self::class, 'lazy_description' ],
			]
		);

		$actual = $this->graphql(
			[
				'query' => '
				{
					enumType: __type(name: "FunctionNamedDescriptionEnum") {
						enumValues { name description }
					}
					rootQuery: __type(name: "RootQuery") {
						fields(includeDeprecated: true) { name description deprecationReason }
					}
				}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame(
			[
				[
					'name'        => 'A',
					'description' => 'A description from a closure',
				],
				[
					'name'        => 'B',
					'description' => 'A description from a callable array',
				],
			],
			$actual['data']['enumType']['enumValues']
		);

		$root_field = array_values(
			array_filter(
				$actual['data']['rootQuery']['fields'],
				static function ( $field ) {
					return 'functionNamedDescription' === $field['name'];
				}
			)
		);
		$this->assertSame( 'A field description from a closure', $root_field[0]['description'] );
		$this->assertSame( 'A description from a callable array', $root_field[0]['deprecationReason'] );
	}
}
