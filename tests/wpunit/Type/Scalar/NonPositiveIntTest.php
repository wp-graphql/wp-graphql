<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\NonPositiveInt;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;

class NonPositiveIntTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
		\register_graphql_field( 'RootQuery', 'testNonPositiveInt', [
			'type' => 'NonPositiveInt',
			'resolve' => static function () {
				return 0;
			},
		]);

		\register_graphql_field( 'RootQuery', 'testInvalidNonPositiveInt', [
			'type' => 'NonPositiveInt',
			'resolve' => static function () {
				return 1;
			},
		]);

		\register_graphql_mutation( 'testNonPositiveIntMutation', [
			'inputFields' => [
				'int' => [ 'type' => 'NonPositiveInt' ],
			],
			'outputFields' => [
				'int' => [ 'type' => 'NonPositiveInt' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'int' => $input['int'] ];
			},
		]);
	}

	public function testSerializeValid() {
		$this->assertEquals( 0, NonPositiveInt::serialize( 0 ) );
		$this->assertEquals( -10, NonPositiveInt::serialize( -10 ) );
	}

	public function testSerializeInvalidPositive() {
		$this->expectException( InvariantViolation::class );
		NonPositiveInt::serialize( 1 );
	}

	public function testSerializeInvalidFloat() {
		$this->expectException( InvariantViolation::class );
		NonPositiveInt::serialize( -1.5 );
	}

	public function testSerializeInvalidString() {
		$this->expectException( InvariantViolation::class );
		NonPositiveInt::serialize( 'abc' );
	}

	public function testParseValueValid() {
		$this->assertEquals( 0, NonPositiveInt::parseValue( 0 ) );
		$this->assertEquals( -100, NonPositiveInt::parseValue( -100 ) );
	}

	public function testParseValueInvalidPositive() {
		$this->expectException( Error::class );
		NonPositiveInt::parseValue( 1 );
	}

	public function testParseValueInvalidFloat() {
		$this->expectException( Error::class );
		NonPositiveInt::parseValue( 1.5 );
	}

	public function testQuery() {
		$query = '{ testNonPositiveInt }';
		$response = $this->graphql( [ 'query' => $query ] );
		$this->assertEquals( 0, $response['data']['testNonPositiveInt'] );
	}

	public function testQueryInvalidNonPositiveInt() {
		$query = '{ testInvalidNonPositiveInt }';
		$result = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'cannot represent positive value', $result['errors'][0]['extensions']['debugMessage'] );
	}

	public function testMutationWithValid() {
		$mutation = '
		mutation ($int: NonPositiveInt!) {
			testNonPositiveIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => -123 ],
		]);

		$this->assertEquals( -123, $response['data']['testNonPositiveIntMutation']['int'] );
	}

	public function testMutationWithInvalid() {
		$mutation = '
		mutation ($int: NonPositiveInt!) {
			testNonPositiveIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => 1 ],
		]);

		$this->assertArrayHasKey( 'errors', $response );
	}
}