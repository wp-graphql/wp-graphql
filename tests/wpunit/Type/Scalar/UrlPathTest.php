<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\UrlPath;

class UrlPathTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testUrlPathField',
            [
                'type'    => 'UrlPath',
                'resolve' => static function () {
                    return '/valid-urlpath';
                },
            ]
        );

		register_graphql_field(
			'RootQuery',
			'testInvalidUrlPathField',
			[
				'type'    => 'UrlPath',
				'resolve' => static function () {
					return 'not-a-urlpath';
				},
			]
		);

        register_graphql_mutation( 'testUrlPathMutation', [
            'inputFields' => [
                'urlpath' => [ 'type' => 'UrlPath' ],
            ],
            'outputFields' => [
                'urlpath' => [ 'type' => 'UrlPath' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'urlpath' => $input['urlpath'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::serialize
     */
    public function testSerializeValidUrlPath() {
        $this->assertEquals( '/a-valid-urlpath', UrlPath::serialize( '/a-valid-urlpath' ) );
        $this->assertEquals( '/', UrlPath::serialize( '/' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::serialize
     */
    public function testSerializeInvalidUrlPath() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'Value is not a valid UrlPath: &quot;not-a-urlpath&quot;' );
        UrlPath::serialize( 'not-a-urlpath' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::serialize
     */
    public function testSerializeInvalidUrlPathFullURL() {
        $this->expectException( \GraphQL\Error\InvariantViolation::class );
        $this->expectExceptionMessage( 'Value is not a valid UrlPath: &quot;https://wpgraphql.com/&quot;' );
        UrlPath::serialize( 'https://wpgraphql.com/' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::serialize
     */
    public function testSerializeEmptyOrNull() {
        $this->assertNull( UrlPath::serialize( '' ) );
        $this->assertNull( UrlPath::serialize( null ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::parseValue
     */
    public function testParseValue() {
        $this->assertEquals( '/a-valid-urlpath', UrlPath::parseValue( '/a-valid-urlpath' ) );
    }

    public function testParseValueInvalidUrlPath() {
        $this->expectException( \GraphQL\Error\Error::class );
        $this->expectExceptionMessage( 'Value is not a valid UrlPath: &quot;not-a-urlpath&quot;' );
        UrlPath::parseValue( 'not-a-urlpath' );
    }

    public function testParseValueInvalidUrlPathFullURL() {
        $this->expectException( \GraphQL\Error\Error::class );
        $this->expectExceptionMessage( 'Value is not a valid UrlPath: &quot;https://wpgraphql.com/&quot;' );
        UrlPath::parseValue( 'https://wpgraphql.com/' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => '/a-valid-urlpath' ] );
        $this->assertEquals( '/a-valid-urlpath', UrlPath::parseLiteral( $node ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\UrlPath::register_scalar
     */
    public function testUrlPathRegisteredToSchema() {
        $result = $this->graphql( [
            'query' => '{ testUrlPathField }',
        ] );

        $this->assertEquals( '/valid-urlpath', $result['data']['testUrlPathField'] );
    }

    public function testMutationWithValidUrlPath() {
        $mutation = '
		mutation ($urlpath: UrlPath!) {
			testUrlPathMutation(input: { urlpath: $urlpath }) {
				urlpath
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'urlpath' => '/a-valid-urlpath' ],
        ]);

        $this->assertEquals( '/a-valid-urlpath', $response['data']['testUrlPathMutation']['urlpath'] );
    }

    public function testMutationWithInvalidUrlPath() {
        $mutation = '
		mutation ($urlpath: UrlPath!) {
			testUrlPathMutation(input: { urlpath: $urlpath }) {
				urlpath
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'urlpath' => 'not-a-valid-urlpath' ],
        ]);

        $this->assertArrayHasKey( 'errors', $response );
    }

	public function testQueryInvalidUrlPathField() {
		$query = '{ testInvalidUrlPathField }';
		$result = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'Value is not a valid UrlPath', $result['errors'][0]['extensions']['debugMessage'] );
	}
}