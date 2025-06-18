<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Color;

class ColorTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testColorField',
            [
                'type'    => 'Color',
                'resolve' => static function () {
                    return '#ff0000';
                },
            ]
        );

        register_graphql_mutation( 'testColorMutation', [
            'inputFields' => [
                'color' => [ 'type' => 'Color' ],
            ],
            'outputFields' => [
                'color' => [ 'type' => 'Color' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'color' => $input['color'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::serialize
     * @dataProvider valid_colors
     */
    public function testSerializeValidColor( string $color ) {
        $this->assertEquals( $color, Color::serialize( $color ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::serialize
     * @dataProvider invalid_colors
     */
    public function testSerializeInvalidColor( string $color ) {
        $this->expectException( Error::class );
        Color::serialize( $color );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::parseValue
     * @dataProvider valid_colors
     */
    public function testParseValueValidColor( string $color ) {
        $this->assertEquals( $color, Color::parseValue( $color ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::parseValue
     * @dataProvider invalid_colors
     */
    public function testParseValueInvalidColor( string $color ) {
        $this->expectException( Error::class );
        Color::parseValue( $color );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::parseLiteral
     * @dataProvider valid_colors
     */
    public function testParseLiteral( string $color ) {
        $node = new StringValueNode( [ 'value' => $color ] );
        $this->assertEquals( $color, Color::parseLiteral( $node ) );
    }

    public function valid_colors(): array {
        return [
            [ '#ff0000' ],
            [ '#f00' ],
            [ 'rgb(255, 0, 0)' ],
            [ 'rgba(255, 0, 0, 1)' ],
            [ 'rgba(255,0,0,0.5)' ],
        ];
    }

    public function invalid_colors(): array {
        return [
            [ 'ff0000' ],
            [ '#1234' ],
            [ 'rgb(255,0)' ],
            [ 'rgba(255,0,0)' ],
            [ 'not-a-color' ],
        ];
    }
}