<?php

class InstrumentSchemaTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * A deprecated field that has no description should still expose its
	 * deprecationReason in introspection.
	 *
	 * Regression: InstrumentSchema::wrap_fields() guarded the deprecationReason
	 * sanitization on is_string( $field->description ) (the wrong property), so a
	 * deprecated field without a description had its deprecationReason dropped to
	 * null.
	 *
	 * @throws \Exception
	 */
	public function testDeprecatedFieldWithoutDescriptionKeepsDeprecationReason() {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_field(
					'RootQuery',
					'instrumentSchemaDeprecatedNoDescription',
					[
						'type'              => 'String',
						'deprecationReason' => 'Deprecated for testing.',
						'resolve'           => static function () {
							return 'value';
						},
					]
				);
			}
		);

		$query = '
		{
			__type(name: "RootQuery") {
				fields(includeDeprecated: true) {
					name
					isDeprecated
					deprecationReason
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$field = null;
		foreach ( $actual['data']['__type']['fields'] as $f ) {
			if ( 'instrumentSchemaDeprecatedNoDescription' === $f['name'] ) {
				$field = $f;
				break;
			}
		}

		$this->assertNotNull( $field, 'The registered deprecated field should be present in introspection.' );
		$this->assertTrue( $field['isDeprecated'] );
		$this->assertSame( 'Deprecated for testing.', $field['deprecationReason'] );
	}
}
