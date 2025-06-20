<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Time;

class TimeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testTimeField',
            [
                'type'    => 'Time',
                'resolve' => static function () {
                    return '14:30:00';
                },
            ]
        );

        register_graphql_mutation( 'testTimeMutation', [
            'inputFields' => [
                'time' => [ 'type' => 'Time' ],
            ],
            'outputFields' => [
                'time' => [ 'type' => 'Time' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'time' => $input['time'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Time::serialize
     */
    public function testSerializeValidTime() {
        $this->assertEquals( '23:59:59', Time::serialize( '23:59:59' ) );
        $this->assertEquals( '00:00:00', Time::serialize( '00:00:00' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Time::serialize
     */
    public function testSerializeInvalidTime() {
        $this->expectException( Error::class );
        Time::serialize( '25:00:00' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Time::parseValue
     */
    public function testParseValueValidTime() {
        $this->assertEquals( '12:00:00', Time::parseValue( '12:00:00' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Time::parseValue
     */
    public function testParseValueInvalidTime() {
        $this->expectException( Error::class );
        Time::parseValue( 'not-a-time' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Time::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => '08:30:15' ] );
        $this->assertEquals( '08:30:15', Time::parseLiteral( $node ) );
    }
}