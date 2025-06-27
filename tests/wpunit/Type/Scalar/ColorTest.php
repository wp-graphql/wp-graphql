<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Color;

class ColorTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testColorField',
            [
                'type'    => 'Color',
                'resolve' => static function () {
                    return '#ff0000';
                },
            ]
        );

		register_graphql_field(
			'RootQuery',
			'testInvalidColorField',
			[
				'type'    => 'Color',
				'description' => 'A field that returns an invalid color value',
				'resolve' => static function () {
					return 'not-a-color';
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
     * @dataProvider normalized_colors_provider
     */
    public function testSerializeValidColor( string $color, string $expected ) {
        $this->assertEquals( $expected, Color::serialize( $color ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::serialize
     * @dataProvider invalid_colors
     */
    public function testSerializeInvalidColor( string $color ) {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        Color::serialize( $color );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Color::parseValue
     * @dataProvider normalized_colors_provider
     */
    public function testParseValueValidColor( string $color, string $expected ) {
        $this->assertEquals( $expected, Color::parseValue( $color ) );
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
     * @dataProvider normalized_colors_provider
     */
    public function testParseLiteral( string $color, string $expected ) {
        $node = new StringValueNode( [ 'value' => $color ] );
        $this->assertEquals( $expected, Color::parseLiteral( $node ) );
    }

    public function testQueryInvalidColorField() {
        $query = '{ testInvalidColorField }';
        $result = $this->graphql( [ 'query' => $query ] );

        $this->assertArrayHasKey( 'errors', $result );
        $this->assertStringContainsString( 'Value is not a valid Color', $result['errors'][0]['extensions']['debugMessage'] );
    }

    public function normalized_colors_provider(): array {
        return [
            [ '#ff0000', 'rgba(255,0,0,1)' ],
            [ '#f00', 'rgba(255,0,0,1)' ],
            [ 'rgb(255, 0, 0)', 'rgba(255,0,0,1)' ],
            [ 'rgba(255, 0, 0, 1)', 'rgba(255,0,0,1)' ],
            [ 'rgba(255,0,0,0.5)', 'rgba(255,0,0,0.5)' ],
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