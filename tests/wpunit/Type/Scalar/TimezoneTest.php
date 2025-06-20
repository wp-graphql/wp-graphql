<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Timezone;

class TimezoneTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testTimezoneField',
            [
                'type'    => 'Timezone',
                'resolve' => static function () {
                    return 'America/New_York';
                },
            ]
        );

        register_graphql_mutation( 'testTimezoneMutation', [
            'inputFields' => [
                'timezone' => [ 'type' => 'Timezone' ],
            ],
            'outputFields' => [
                'timezone' => [ 'type' => 'Timezone' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'timezone' => $input['timezone'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Timezone::serialize
     */
    public function testSerializeValidTimezone() {
        $this->assertEquals( 'Europe/London', Timezone::serialize( 'Europe/London' ) );
        $this->assertEquals( 'UTC', Timezone::serialize( 'UTC' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Timezone::serialize
     */
    public function testSerializeInvalidTimezone() {
        $this->expectException( Error::class );
        Timezone::serialize( 'Mars/Olympus_Mons' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Timezone::parseValue
     */
    public function testParseValueValidTimezone() {
        $this->assertEquals( 'Australia/Sydney', Timezone::parseValue( 'Australia/Sydney' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Timezone::parseValue
     */
    public function testParseValueInvalidTimezone() {
        $this->expectException( Error::class );
        Timezone::parseValue( 'not-a-timezone' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Timezone::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => 'Asia/Tokyo' ] );
        $this->assertEquals( 'Asia/Tokyo', Timezone::parseLiteral( $node ) );
    }
}