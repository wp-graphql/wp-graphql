<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\JSON;

class JSONTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	// Inspired by: https://github.com/graphql-hive/graphql-scalars/blob/master/tests/JSON.test.ts
	const FIXTURE = [
		'string' => 'string',
		'int'    => 3,
		'float'  => 3.14,
		'true'   => true,
		'false'  => false,
		'null'   => null,
		'object' => [
			'string' => 'string',
			'int'    => 3,
			'float'  => 3.14,
			'true'   => true,
			'false'  => false,
			'null'   => null,
		],
		'array'  => [ 'string', 3, 3.14, true, false, null ],
	];

	public function setUp(): void {
		parent::setUp();
		add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
	}

	public function tearDown(): void {
		remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
		parent::tearDown();
	}

	public function register_test_fields(): void {
		register_graphql_mutation( 'testJSONMutation', [
			'inputFields'         => [
				'json' => [ 'type' => 'JSON' ],
			],
			'outputFields'        => [
				'json' => [ 'type' => 'JSON' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'json' => $input['json'] ];
			},
		] );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::serialize
	 */
	public function testSerialize() {
		$this->assertEquals( self::FIXTURE, JSON::serialize( self::FIXTURE ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseValue
	 */
	public function testParseValue() {
		$this->assertEquals( self::FIXTURE, JSON::parseValue( self::FIXTURE ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseLiteral
	 */
	public function testParseLiteralString() {
		$json_string = json_encode( self::FIXTURE );
		$ast         = new StringValueNode( [ 'value' => $json_string ] );
		$this->assertEquals( self::FIXTURE, JSON::parseLiteral( $ast ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseLiteral
	 */
	public function testParseLiteralObject() {
		$ast = new ObjectValueNode([
			'fields' => new NodeList([
				new ObjectFieldNode([
					'name' => new NameNode(['value' => 'string']),
					'value' => new StringValueNode(['value' => 'hello']),
				]),
				new ObjectFieldNode([
					'name' => new NameNode(['value' => 'list']),
					'value' => new ListValueNode([
						'values' => new NodeList([
							new StringValueNode(['value' => 'one']),
							new IntValueNode(['value' => '2']),
						])
					]),
				]),
			])
		]);

		$expected = [
			'string' => 'hello',
			'list' => [ 'one', 2 ],
		];

		$this->assertEquals( $expected, JSON::parseLiteral( $ast ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseLiteral
	 */
	public function testParseLiteralThrowsOnInvalidJsonString() {
		$this->expectException( Error::class );
		$ast = new StringValueNode( [ 'value' => '{ "invalid" }' ] );
		JSON::parseLiteral( $ast );
	}

	public function testValidMutationWithJsonVariable() {
		$mutation = '
		mutation ($json: JSON!) {
			testJSONMutation(input: { json: $json }) {
				json
			}
		}
		';

		$response = $this->graphql( [
			'query'     => $mutation,
			'variables' => [ 'json' => self::FIXTURE ],
		] );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( self::FIXTURE, $response['data']['testJSONMutation']['json'] );
	}

	public function testValidMutationWithJsonObjectLiteral() {
		$mutation = '
		mutation {
			testJSONMutation(input: { json: { a: "literal", b: [1, "two"] } }) {
				json
			}
		}
		';

		$response = $this->graphql( [ 'query' => $mutation ] );
		$this->assertArrayNotHasKey( 'errors', $response );

		$expected = [
			'a' => 'literal',
			'b' => [ 1, 'two' ],
		];

		$this->assertEquals( $expected, $response['data']['testJSONMutation']['json'] );
	}
}