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
		$json_string = json_encode( self::FIXTURE );
		$this->assertEquals( $json_string, JSON::serialize( self::FIXTURE ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::serialize
	 */
	public function testSerializeThrowsOnInvalid() {
		$this->expectException( Error::class );
		// Add a value that cannot be encoded to JSON, like a resource.
		JSON::serialize( fopen( 'php://memory', 'r' ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseValue
	 */
	public function testParseValue() {
		$json_string = json_encode( self::FIXTURE );
		$this->assertEquals( self::FIXTURE, JSON::parseValue( $json_string ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseValue
	 */
	public function testParseValueThrowsOnInvalidJson() {
		$this->expectException( Error::class );
		JSON::parseValue( '{ "invalid" }' );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\JSON::parseValue
	 */
	public function testParseValueThrowsOnNonString() {
		$this->expectException( Error::class );
		JSON::parseValue( 123 );
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

	/**
	 * @dataProvider invalidMutationProvider
	 */
	public function testInvalidMutations( $json_input ) {
		$mutation = '
		mutation ($json: JSON!) {
			testJSONMutation(input: { json: $json }) {
				json
			}
		}
		';

		$response = $this->graphql( [
			'query'     => $mutation,
			'variables' => [ 'json' => $json_input ],
		] );

		$this->assertArrayHasKey( 'errors', $response );
	}

	public function invalidMutationProvider() {
		return [
			'invalid json string' => [ '{"invalid": json' ],
			'unquoted string'     => [ 'invalid' ],
			'integer'             => [ 1 ],
			'float'               => [ 1.1 ],
			'boolean'             => [ true ],
		];
	}

	public function testValidMutationWithJsonStringVariable() {
		$mutation = '
		mutation ($json: JSON!) {
			testJSONMutation(input: { json: $json }) {
				json
			}
		}
		';

		$json_string = json_encode( self::FIXTURE );

		$response = $this->graphql( [
			'query'     => $mutation,
			'variables' => [ 'json' => $json_string ],
		] );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( $json_string, $response['data']['testJSONMutation']['json'] );
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

		$this->assertEquals( json_encode( $expected ), $response['data']['testJSONMutation']['json'] );
	}
}