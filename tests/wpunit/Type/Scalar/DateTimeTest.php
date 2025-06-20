<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\DateTime;

class DateTimeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
        \WPGraphQL::clear_schema();
        add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
    }

    public function tearDown(): void {
        remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
        \WPGraphQL::clear_schema();
        parent::tearDown();
    }

    public function register_test_fields(): void {
        register_graphql_field(
            'RootQuery',
            'testDateTimeField',
            [
                'type'    => 'DateTime',
                'resolve' => static function () {
                    return '2022-10-27 12:30:45';
                },
            ]
        );

        register_graphql_mutation( 'testDateTimeMutation', [
            'inputFields' => [
                'datetime' => [ 'type' => 'DateTime' ],
            ],
            'outputFields' => [
                'datetime' => [ 'type' => 'DateTime' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'datetime' => $input['datetime'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::serialize
     */
    public function testSerializeValidDateTime() {
        $this->assertEquals( '2023-10-27T10:30:00Z', DateTime::serialize( '2023-10-27 10:30:00' ) );
        $this->assertEquals( '2023-01-01T00:00:00Z', DateTime::serialize( '2023-01-01' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::serialize
     */
    public function testSerializeInvalidDateTime() {
        $this->assertNull( DateTime::serialize( 'not-a-date' ) );
        $this->assertNull( DateTime::serialize( '0000-00-00 00:00:00' ) );
        $this->assertNull( DateTime::serialize( null ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::parseValue
     */
    public function testParseValueValidDateTime() {
        $this->assertEquals( '2024-01-20T14:45:00Z', DateTime::parseValue( '2024-01-20T14:45:00Z' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::parseValue
     */
    public function testParseValueInvalidDateTime() {
        $this->expectException( Error::class );
        $this->expectExceptionMessage( 'Value is not a valid DateTime: &quot;2023-27-10T10:30:00Z&quot;' );
        DateTime::parseValue( '2023-27-10T10:30:00Z' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::parseValue
     */
    public function testParseValueNotString() {
        $this->expectException( Error::class );
        DateTime::parseValue( 12345 );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => '2024-01-20T14:45:00Z' ] );
        $this->assertEquals( '2024-01-20T14:45:00Z', DateTime::parseLiteral( $node ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\DateTime::register_scalar
     */
    public function testDateTimeRegisteredToSchema() {
        $result = $this->graphql( [
            'query' => '{ testDateTimeField }',
        ] );
        $this->assertEquals( '2022-10-27T12:30:45Z', $result['data']['testDateTimeField'] );
    }

    public function testMutationWithValidDateTime() {
        $mutation = '
		mutation ($datetime: DateTime!) {
			testDateTimeMutation(input: { datetime: $datetime }) {
				datetime
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'datetime' => '2024-01-20T14:45:00Z' ],
        ]);

        $this->assertEquals( '2024-01-20T14:45:00Z', $response['data']['testDateTimeMutation']['datetime'] );
    }

    public function testMutationWithInvalidDateTime() {
        $mutation = '
		mutation ($datetime: DateTime!) {
			testDateTimeMutation(input: { datetime: $datetime }) {
				datetime
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'datetime' => '20-01-2024 14:45:00' ],
        ]);

        $this->assertArrayHasKey( 'errors', $response );
    }
}