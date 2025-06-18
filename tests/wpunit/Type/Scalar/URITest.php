<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\URI;

class URITest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testURIField',
            [
                'type'    => 'URI',
                'resolve' => static function () {
                    return '/valid-uri';
                },
            ]
        );

        register_graphql_mutation( 'testURIMutation', [
            'inputFields' => [
                'uri' => [ 'type' => 'URI' ],
            ],
            'outputFields' => [
                'uri' => [ 'type' => 'URI' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'uri' => $input['uri'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::serialize
     */
    public function testSerializeValidURI() {
        $this->assertEquals( '/a-valid-uri', URI::serialize( '/a-valid-uri' ) );
        $this->assertEquals( '/', URI::serialize( '/' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::serialize
     */
    public function testSerializeInvalidURI() {
        $this->expectException( Error::class );
        $this->expectExceptionMessage( 'Value is not a valid URI: &quot;not-a-uri&quot;' );
        URI::serialize( 'not-a-uri' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::serialize
     */
    public function testSerializeInvalidURIFullURL() {
        $this->expectException( Error::class );
        $this->expectExceptionMessage( 'Value is not a valid URI: &quot;https://wpgraphql.com/&quot;' );
        URI::serialize( 'https://wpgraphql.com/' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::serialize
     */
    public function testSerializeEmptyOrNull() {
        $this->assertNull( URI::serialize( '' ) );
        $this->assertNull( URI::serialize( null ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::parseValue
     */
    public function testParseValue() {
        $this->assertEquals( '/a-valid-uri', URI::parseValue( '/a-valid-uri' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => '/a-valid-uri' ] );
        $this->assertEquals( '/a-valid-uri', URI::parseLiteral( $node ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\URI::register_scalar
     */
    public function testURIRegisteredToSchema() {
        $result = $this->graphql( [
            'query' => '{ testURIField }',
        ] );

        $this->assertEquals( '/valid-uri', $result['data']['testURIField'] );
    }

    public function testMutationWithValidURI() {
        $mutation = '
		mutation ($uri: URI!) {
			testURIMutation(input: { uri: $uri }) {
				uri
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'uri' => '/a-valid-uri' ],
        ]);

        $this->assertEquals( '/a-valid-uri', $response['data']['testURIMutation']['uri'] );
    }

    public function testMutationWithInvalidURI() {
        $mutation = '
		mutation ($uri: URI!) {
			testURIMutation(input: { uri: $uri }) {
				uri
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'uri' => 'not-a-valid-uri' ],
        ]);

        $this->assertArrayHasKey( 'errors', $response );
    }
}