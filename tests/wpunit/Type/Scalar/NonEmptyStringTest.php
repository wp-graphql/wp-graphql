<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\NonEmptyString;

class NonEmptyStringTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testNonEmptyStringField',
            [
                'type'    => 'NonEmptyString',
                'resolve' => static function () {
                    return 'this is not empty';
                },
            ]
        );

		register_graphql_field(
			'RootQuery',
			'testInvalidNonEmptyStringField',
			[
				'type'    => 'NonEmptyString',
				'resolve' => static function () {
					return '';
				},
			]
		);

        register_graphql_mutation( 'testNonEmptyStringMutation', [
            'inputFields' => [
                'value' => [ 'type' => 'NonEmptyString' ],
            ],
            'outputFields' => [
                'value' => [ 'type' => 'NonEmptyString' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'value' => $input['value'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::serialize
     */
    public function testSerialize() {
        $this->assertEquals( 'test', NonEmptyString::serialize( 'test' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::serialize
     */
    public function testSerializeEmptyString() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'NonEmptyString cannot be empty.' );
        NonEmptyString::serialize( '' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::serialize
     */
    public function testSerializeWhitespaceString() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'NonEmptyString cannot be empty.' );
        NonEmptyString::serialize( '   ' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::serialize
     */
    public function testSerializeNonString() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'NonEmptyString must be a string. Received: 123' );
        NonEmptyString::serialize( 123 );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::parseValue
     */
    public function testParseValue() {
        $this->assertEquals( 'test', NonEmptyString::parseValue( 'test' ) );
    }

    public function testParseValueEmptyString() {
        $this->expectException( \GraphQL\Error\Error::class );
        $this->expectExceptionMessage( 'NonEmptyString cannot be empty.' );
        NonEmptyString::parseValue( '' );
    }

    public function testParseValueWhitespaceString() {
        $this->expectException( \GraphQL\Error\Error::class );
        $this->expectExceptionMessage( 'NonEmptyString cannot be empty.' );
        NonEmptyString::parseValue( '   ' );
    }

    public function testParseValueNonString() {
        $this->expectException( \GraphQL\Error\Error::class );
        $this->expectExceptionMessage( 'NonEmptyString must be a string. Received: 123' );
        NonEmptyString::parseValue( 123 );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => 'test' ] );
        $this->assertEquals( 'test', NonEmptyString::parseLiteral( $node ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\NonEmptyString::register_scalar
     */
    public function testNonEmptyStringRegisteredToSchema() {
        $result = $this->graphql( [
            'query' => '{ testNonEmptyStringField }',
        ] );

        $this->assertEquals( 'this is not empty', $result['data']['testNonEmptyStringField'] );
    }

    public function testMutationWithValidString() {
		$mutation = '
		mutation ($value: NonEmptyString!) {
			testNonEmptyStringMutation(input: { value: $value }) {
				value
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'value' => 'not-empty' ],
		]);

		$this->assertEquals( 'not-empty', $response['data']['testNonEmptyStringMutation']['value'] );
	}

	public function testMutationWithEmptyString() {
		$mutation = '
		mutation ($value: NonEmptyString!) {
			testNonEmptyStringMutation(input: { value: $value }) {
				value
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'value' => '' ],
		]);

		$this->assertArrayHasKey( 'errors', $response );
	}

	public function testQueryInvalidNonEmptyStringField() {
		$query = '{ testInvalidNonEmptyStringField }';
		$result = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'NonEmptyString cannot be empty.', $result['errors'][0]['extensions']['debugMessage'] );
	}
}