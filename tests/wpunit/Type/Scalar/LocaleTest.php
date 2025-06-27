<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Locale;

class LocaleTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
		add_filter( 'graphql_debug', '__return_true', 99999 );
        \WPGraphQL::clear_schema();
        add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
    }

    public function tearDown(): void {
        remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
		remove_filter( 'graphql_debug', '__return_true', 99999 );
        \WPGraphQL::clear_schema();
        parent::tearDown();
    }

    public function register_test_fields(): void {
        register_graphql_field(
            'RootQuery',
            'testLocaleField',
            [
                'type'    => 'Locale',
                'resolve' => static function () {
                    return 'en_US';
                },
            ]
        );

		register_graphql_field(
			'RootQuery',
			'testInvalidLocaleField',
			[
				'type'    => 'Locale',
				'resolve' => static function () {
					return 'not-a-locale';
				},
			]
		);

        register_graphql_mutation( 'testLocaleMutation', [
            'inputFields' => [
                'locale' => [ 'type' => 'Locale' ],
            ],
            'outputFields' => [
                'locale' => [ 'type' => 'Locale' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'locale' => $input['locale'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Locale::serialize
     */
    public function testSerializeValidLocale() {
        $this->assertEquals( 'en_US', Locale::serialize( 'en_US' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Locale::serialize
     */
    public function testSerializeInvalidLocale() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        Locale::serialize( 'en-US' ); // Invalid format
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Locale::parseValue
     */
    public function testParseValueValidLocale() {
        $this->assertEquals( 'en_US', Locale::parseValue( 'en_US' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Locale::parseValue
     */
    public function testParseValueInvalidLocale() {
        $this->expectException( Error::class );
        Locale::parseValue( 'not-a-locale' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Locale::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => 'en_US' ] );
        $this->assertEquals( 'en_US', Locale::parseLiteral( $node ) );
    }

	public function testQueryInvalidLocaleField() {
		$query = '{ testInvalidLocaleField }';
		$result = graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'Value is not a valid Locale', $result['errors'][0]['extensions']['debugMessage'] );
	}
}