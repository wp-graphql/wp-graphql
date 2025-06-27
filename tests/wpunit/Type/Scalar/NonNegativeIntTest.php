<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\NonNegativeInt;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;

class NonNegativeIntTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		add_filter( 'graphql_debug', '__return_true', 99999 );
		$this->clearSchema();
		\add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
	}

	public function tearDown(): void {
		\remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
		remove_filter( 'graphql_debug', '__return_true', 99999 );
		parent::tearDown();
	}

	public function register_test_fields(): void {
		\register_graphql_field( 'RootQuery', 'testNonNegativeInt', [
			'type' => 'NonNegativeInt',
			'resolve' => static function () {
				return 0;
			},
		]);

		\register_graphql_field( 'RootQuery', 'testInvalidNonNegativeInt', [
			'type' => 'NonNegativeInt',
			'resolve' => static function () {
				return -1;
			},
		]);

		\register_graphql_mutation( 'testNonNegativeIntMutation', [
			'inputFields' => [
				'int' => [ 'type' => 'NonNegativeInt' ],
			],
			'outputFields' => [
				'int' => [ 'type' => 'NonNegativeInt' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'int' => $input['int'] ];
			},
		]);
	}

	public function testSerializeValid() {
		$this->assertEquals( 0, NonNegativeInt::serialize( 0 ) );
		$this->assertEquals( 10, NonNegativeInt::serialize( 10 ) );
	}

	public function testSerializeInvalidNegative() {
		$this->expectException( InvariantViolation::class );
		NonNegativeInt::serialize( -1 );
	}

	public function testSerializeInvalidFloat() {
		$this->expectException( InvariantViolation::class );
		NonNegativeInt::serialize( 1.5 );
	}

	public function testSerializeInvalidString() {
		$this->expectException( InvariantViolation::class );
		NonNegativeInt::serialize( 'abc' );
	}

	public function testParseValueValid() {
		$this->assertEquals( 0, NonNegativeInt::parseValue( 0 ) );
		$this->assertEquals( 100, NonNegativeInt::parseValue( 100 ) );
	}

	public function testParseValueInvalidNegative() {
		$this->expectException( Error::class );
		NonNegativeInt::parseValue( -50 );
	}

	public function testParseValueInvalidFloat() {
		$this->expectException( Error::class );
		NonNegativeInt::parseValue( -1.5 );
	}

	public function testQuery() {
		$query = '{ testNonNegativeInt }';
		$response = $this->graphql( [ 'query' => $query ] );
		$this->assertEquals( 0, $response['data']['testNonNegativeInt'] );
	}

	public function testQueryInvalidNonNegativeInt() {
		$query = '{ testInvalidNonNegativeInt }';
		$result = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'cannot represent negative value', $result['errors'][0]['extensions']['debugMessage'] );
	}

	public function testMutationWithValid() {
		$mutation = '
		mutation ($int: NonNegativeInt!) {
			testNonNegativeIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => 123 ],
		]);

		$this->assertEquals( 123, $response['data']['testNonNegativeIntMutation']['int'] );
	}

	public function testMutationWithInvalid() {
		$mutation = '
		mutation ($int: NonNegativeInt!) {
			testNonNegativeIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => -123 ],
		]);

		$this->assertArrayHasKey( 'errors', $response );
	}
}