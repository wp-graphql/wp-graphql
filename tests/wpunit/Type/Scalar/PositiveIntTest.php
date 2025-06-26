<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\PositiveInt;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;

class PositiveIntTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		\add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
	}

	public function tearDown(): void {
		\remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
		parent::tearDown();
	}

	public function register_test_fields(): void {
		\register_graphql_field( 'RootQuery', 'testPositiveInt', [
			'type' => 'PositiveInt',
			'resolve' => static function () {
				return 1;
			},
		]);

		\register_graphql_mutation( 'testPositiveIntMutation', [
			'inputFields' => [
				'int' => [ 'type' => 'PositiveInt' ],
			],
			'outputFields' => [
				'int' => [ 'type' => 'PositiveInt' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'int' => $input['int'] ];
			},
		]);
	}

	public function testSerializeValid() {
		$this->assertEquals( 1, PositiveInt::serialize( 1 ) );
		$this->assertEquals( 10, PositiveInt::serialize( 10 ) );
	}

	public function testSerializeInvalid() {
		$this->expectException( InvariantViolation::class );
		PositiveInt::serialize( 0 );

		$this->expectException( InvariantViolation::class );
		PositiveInt::serialize( -1 );

		$this->expectException( InvariantViolation::class );
		PositiveInt::serialize( 1.5 );

		$this->expectException( InvariantViolation::class );
		PositiveInt::serialize( 'abc' );
	}

	public function testParseValueValid() {
		$this->assertEquals( 1, PositiveInt::parseValue( 1 ) );
		$this->assertEquals( 100, PositiveInt::parseValue( 100 ) );
	}

	public function testParseValueInvalid() {
		$this->expectException( Error::class );
		PositiveInt::parseValue( 0 );

		$this->expectException( Error::class );
		PositiveInt::parseValue( -50 );

		$this->expectException( Error::class );
		PositiveInt::parseValue( -1.5 );
	}

	public function testQuery() {
		$query = '{ testPositiveInt }';
		$response = $this->graphql( [ 'query' => $query ] );
		$this->assertEquals( 1, $response['data']['testPositiveInt'] );
	}

	public function testMutationWithValid() {
		$mutation = '
		mutation ($int: PositiveInt!) {
			testPositiveIntMutation(input: { int: $int }) {
				int
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'int' => 123 ],
		]);

		$this->assertEquals( 123, $response['data']['testPositiveIntMutation']['int'] );
	}

	public function testMutationWithInvalid() {
		$mutation = '
		mutation ($int: PositiveInt!) {
			testPositiveIntMutation(input: { int: $int }) {
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
}