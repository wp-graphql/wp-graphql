<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Slug;

class SlugTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
            'testSlugField',
            [
                'type'    => 'Slug',
                'resolve' => static function () {
                    return 'valid-slug';
                },
            ]
        );

        register_graphql_mutation( 'testSlugMutation', [
            'inputFields' => [
                'slug' => [ 'type' => 'Slug' ],
            ],
            'outputFields' => [
                'slug' => [ 'type' => 'Slug' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'slug' => $input['slug'] ];
            },
        ]);
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::serialize
     */
    public function testSerializeValidSlug() {
        $this->assertEquals( 'a-valid-slug', Slug::serialize( 'a-valid-slug' ) );
        $this->assertEquals( 'slug-with-numbers-123', Slug::serialize( 'slug-with-numbers-123' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::serialize
     */
    public function testSerializeInvalidSlugWithSpaces() {
        $this->expectException( Error::class );
        $this->expectExceptionMessage( 'Value is not a valid slug: &quot;invalid slug&quot;' );
        Slug::serialize( 'invalid slug' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::serialize
     */
    public function testSerializeInvalidSlugWithUppercase() {
        $this->expectException( Error::class );
        $this->expectExceptionMessage( 'Value is not a valid slug: &quot;InvalidSlug&quot;' );
        Slug::serialize( 'InvalidSlug' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::serialize
     */
    public function testSerializeInvalidSlugWithSpecialChars() {
        $this->expectException( Error::class );
        $this->expectExceptionMessage( 'Value is not a valid slug: &quot;invalid!@#$&quot;' );
        Slug::serialize( 'invalid!@#$' );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::parseValue
     */
    public function testParseValue() {
        $this->assertEquals( 'a-valid-slug', Slug::parseValue( 'a-valid-slug' ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::parseLiteral
     */
    public function testParseLiteral() {
        $node = new StringValueNode( [ 'value' => 'a-valid-slug' ] );
        $this->assertEquals( 'a-valid-slug', Slug::parseLiteral( $node ) );
    }

    /**
     * @covers \WPGraphQL\Type\Scalar\Slug::register_scalar
     */
    public function testSlugRegisteredToSchema() {
        $result = $this->graphql( [
            'query' => '{ testSlugField }',
        ] );

        $this->assertEquals( 'valid-slug', $result['data']['testSlugField'] );
    }

    public function testMutationWithValidSlug() {
        $mutation = '
		mutation ($slug: Slug!) {
			testSlugMutation(input: { slug: $slug }) {
				slug
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'slug' => 'a-valid-slug' ],
        ]);

        $this->assertEquals( 'a-valid-slug', $response['data']['testSlugMutation']['slug'] );
    }

    public function testMutationWithInvalidSlug() {
        $mutation = '
		mutation ($slug: Slug!) {
			testSlugMutation(input: { slug: $slug }) {
				slug
			}
		}
		';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'slug' => 'An Invalid Slug' ],
        ]);

        $this->assertArrayHasKey( 'errors', $response );
    }
}