<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\URL;

class URLTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testURLField',
            [
                'type'    => 'URL',
                'resolve' => static function () {
                    return 'https://wpgraphql.com';
                },
            ]
        );

		register_graphql_field(
			'RootQuery',
			'testInvalidURLField',
			[
				'type'    => 'URL',
				'resolve' => static function () {
					return 'not-a-url';
				},
			]
		);

        register_graphql_mutation( 'testURLMutation', [
            'inputFields' => [
                'url' => [ 'type' => 'URL' ],
            ],
            'outputFields' => [
                'url' => [ 'type' => 'URL' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'url' => $input['url'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::serialize
     */
    public function testSerializeValidURL() {
        $this->assertEquals( 'https://wpgraphql.com', URL::serialize( 'https://wpgraphql.com' ) );
        $this->assertEquals( 'http://wpgraphql.com', URL::serialize( 'http://wpgraphql.com' ) );
        $this->assertEquals( 'ftp://wpgraphql.com', URL::serialize( 'ftp://wpgraphql.com' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::serialize
     */
    public function testSerializeInvalidURL() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'Value is not a valid URL: &quot;not-a-url&quot;' );
        URL::serialize( 'not-a-url' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::serialize
     */
    public function testSerializeUnsafeProtocol() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'Value is not a valid URL: &quot;javascript:alert(1)&quot;' );
        URL::serialize( 'javascript:alert(1)' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::serialize
     */
    public function testSerializeEmptyOrNull() {
        $this->assertNull( URL::serialize( '' ) );
        $this->assertNull( URL::serialize( null ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::parseValue
     */
    public function testParseValue() {
        $this->assertEquals( 'https://wpgraphql.com', URL::parseValue( 'https://wpgraphql.com' ) );
    }

    public function testParseValueInvalidURL() {
        $this->expectException( \GraphQL\Error\Error::class );
        $this->expectExceptionMessage( 'Value is not a valid URL: &quot;not-a-url&quot;' );
        URL::parseValue( 'not-a-url' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => 'https://wpgraphql.com' ] );
        $this->assertEquals( 'https://wpgraphql.com', URL::parseLiteral( $node ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URL::register_scalar
     */
    public function testURLRegisteredToSchema() {
        $result = $this->graphql( [
            'query' => '{ testURLField }',
        ] );

        $this->assertEquals( 'https://wpgraphql.com', $result['data']['testURLField'] );
    }

    public function testMutationWithValidURL() {
        $mutation = '
		mutation ($url: URL!) {
			testURLMutation(input: { url: $url }) {
				url
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'url' => 'https://wpgraphql.com' ],
        ]);

        $this->assertEquals( 'https://wpgraphql.com', $response['data']['testURLMutation']['url'] );
    }

    public function testMutationWithInvalidURL() {
        $mutation = '
		mutation ($url: URL!) {
			testURLMutation(input: { url: $url }) {
				url
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'url' => 'not-a-valid-url' ],
        ]);

        $this->assertArrayHasKey( 'errors', $response );
    }

	public function testQueryInvalidURLField() {
		$query = '{ testInvalidURLField }';
		$result = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'Value is not a valid URL', $result['errors'][0]['extensions']['debugMessage'] );
	}
}