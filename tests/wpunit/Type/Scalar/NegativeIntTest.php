<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\NegativeInt;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;

class NegativeIntTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
		\register_graphql_field( 'RootQuery', 'testNegativeInt', [
			'type' => 'NegativeInt',
			'resolve' => static function () {
				return -1;
			},
		]);

		\register_graphql_field( 'RootQuery', 'testInvalidNegativeInt', [
			'type' => 'NegativeInt',
			'resolve' => static function () {
				return 1;
			},
		]);

		\register_graphql_mutation( 'testNegativeIntMutation', [
			'inputFields' => [
				'int' => [ 'type' => 'NegativeInt' ],
			],
			'outputFields' => [
				'int' => [ 'type' => 'NegativeInt' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'int' => $input['int'] ];
			},
		]);
	}

	public function testSerializeValid() {
		$this->assertEquals( -1, NegativeInt::serialize( -1 ) );
		$this->assertEquals( -10, NegativeInt::serialize( -10 ) );
	}

	public function testSerializeInvalidZero() {
		$this->expectException( InvariantViolation::class );
		NegativeInt::serialize( 0 );
	}

	public function testSerializeInvalidPositive() {
		$this->expectException( InvariantViolation::class );
		NegativeInt::serialize( 1 );
	}

	public function testSerializeInvalidFloat() {
		$this->expectException( InvariantViolation::class );
		NegativeInt::serialize( -1.5 );
	}

	public function testSerializeInvalidString() {
		$this->expectException( InvariantViolation::class );
		NegativeInt::serialize( 'abc' );
	}

	public function testParseValueValid() {
		$this->assertEquals( -1, NegativeInt::parseValue( -1 ) );
		$this->assertEquals( -100, NegativeInt::parseValue( -100 ) );
	}

	public function testParseValueInvalidZero() {
		$this->expectException( Error::class );
		NegativeInt::parseValue( 0 );
	}

	public function testParseValueInvalidPositive() {
		$this->expectException( Error::class );
		NegativeInt::parseValue( 50 );
	}

	public function testParseValueInvalidFloat() {
		$this->expectException( Error::class );
		NegativeInt::parseValue( 1.5 );
	}

	public function testQuery() {
		$query = '{ testNegativeInt }';
		$response = $this->graphql( [ 'query' => $query ] );
		$this->assertEquals( -1, $response['data']['testNegativeInt'] );
	}

	public function testQueryInvalidNegativeInt() {
		$query = '{ testInvalidNegativeInt }';
		$result = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'cannot represent non-negative', $result['errors'][0]['extensions']['debugMessage'] );
	}

	public function testMutationWithValid() {
		$mutation = '
		mutation ($int: NegativeInt!) {
			testNegativeIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => -123 ],
		]);

		$this->assertEquals( -123, $response['data']['testNegativeIntMutation']['int'] );
	}

	public function testMutationWithInvalid() {
		$mutation = '
		mutation ($int: NegativeInt!) {
			testNegativeIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => 0 ],
		]);

		$this->assertArrayHasKey( 'errors', $response );
	}

	public function testParseValueInvalidNegativeInt() {
		$this->expectException( Error::class );
		NegativeInt::parseValue( 1 );
	}
}