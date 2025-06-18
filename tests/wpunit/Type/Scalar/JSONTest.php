<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use WPGraphQL\Type\Scalar\JSON;

class JSONTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testJSONField',
            [
                'type'    => 'JSON',
                'resolve' => static function () {
                    return [
                        'string' => 'hello',
                        'int' => 123,
                        'float' => 1.23,
                        'bool' => true,
                        'null' => null,
                        'array' => [ 'nested' ],
                    ];
                },
            ]
        );

        register_graphql_mutation( 'testJSONMutation', [
            'inputFields' => [
                'json' => [ 'type' => 'JSON' ],
            ],
            'outputFields' => [
                'json' => [ 'type' => 'JSON' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'json' => $input['json'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\JSON::serialize
     */
    public function testSerialize() {
        $data = [ 'a' => 1, 'b' => 'two', 'c' => true ];
        $this->assertEquals( $data, JSON::serialize( $data ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\JSON::parseValue
     */
    public function testParseValue() {
        $data = [ 'a' => 1, 'b' => 'two', 'c' => true ];
        $this->assertEquals( $data, JSON::parseValue( $data ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\JSON::parseLiteral
     */
    public function testParseLiteral() {
        // Build a complex AST node
        $ast = new ObjectValueNode([
            'fields' => new NodeList([
                new ObjectFieldNode([
                    'name' => new NameNode(['value' => 'string']),
                    'value' => new StringValueNode(['value' => 'hello']),
                ]),
                new ObjectFieldNode([
                    'name' => new NameNode(['value' => 'int']),
                    'value' => new IntValueNode(['value' => '123']),
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
            'int' => 123,
            'list' => [ 'one', 2 ],
        ];

        $this->assertEquals( $expected, JSON::parseLiteral( $ast ) );
    }

    public function testQueryWithJSONField() {
        $result = $this->graphql( [
            'query' => '{ testJSONField }',
        ] );

        $expected = [
            'string' => 'hello',
            'int' => 123,
            'float' => 1.23,
            'bool' => true,
            'null' => null,
            'array' => [ 'nested' ],
        ];

        $this->assertEquals( $expected, $result['data']['testJSONField'] );
    }

    public function testMutationWithJSONVariable() {
        $mutation = '
		mutation ($json: JSON!) {
			testJSONMutation(input: { json: $json }) {
				json
			}
		}
		';

        $variables = [
            'json' => [
                'a' => 'variable',
                'b' => [ 'nested' ],
            ]
        ];

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => $variables,
        ]);

        $this->assertEquals( $variables['json'], $response['data']['testJSONMutation']['json'] );
    }

    public function testMutationWithJSONLiteral() {
		$mutation = '
		mutation {
			testJSONMutation(input: { json: { a: "literal", b: [1, "two"] } }) {
				json
			}
		}
		';

		$response = $this->graphql( [ 'query' => $mutation ] );

        $expected = [
            'a' => 'literal',
            'b' => [ 1, 'two' ],
        ];

		$this->assertEquals( $expected, $response['data']['testJSONMutation']['json'] );
	}
}