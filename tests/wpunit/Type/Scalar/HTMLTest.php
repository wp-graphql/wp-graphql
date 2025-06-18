<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\HTML;

class HTMLTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
        $this->clearSchema();
        add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
    }

    public function tearDown(): void {
        remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
        parent::tearDown();
    }

    public function register_test_fields(): void {
        register_graphql_mutation( 'testHtmlMutation', [
            'inputFields' => [
                'html' => [ 'type' => 'HTML' ],
            ],
            'outputFields' => [
                'html' => [ 'type' => 'HTML' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'html' => $input['html'] ];
            },
        ]);
    }

    public function testSerializeRemovesUnsafeHtml() {
        $unsafe_html = '<p>This is safe.</p><script>alert("xss");</script>';
        $safe_html = '<p>This is safe.</p>';
        $this->assertEquals( $safe_html, HTML::serialize( $unsafe_html ) );
    }

    public function testParseValueRemovesUnsafeHtml() {
        $unsafe_html = 'This has an <a href="#" onclick="alert(\'danger\')">unsafe link</a>.';
        $safe_html = 'This has an <a href="#">unsafe link</a>.';
        $this->assertEquals( $safe_html, HTML::parseValue( $unsafe_html ) );
    }

    public function testMutationWithUnsafeHtml() {
        $mutation = '
        mutation ($html: HTML!) {
            testHtmlMutation(input: { html: $html }) {
                html
            }
        }
        ';
        $unsafe_html = '<b>Bold and <iframe src="evil.com"></iframe></b>';
        $expected_html = '<b>Bold and </b>';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'html' => $unsafe_html ],
        ]);

        $this->assertArrayNotHasKey( 'errors', $response );
        $this->assertEquals( $expected_html, $response['data']['testHtmlMutation']['html'] );
    }

    public function testSerializeAllowsAllowedHtml() {
        $allowed_html = '<p>This is a paragraph with <a href="https://wpgraphql.com">a link</a> and <strong>strong</strong> text.</p>';
        $this->assertEquals( $allowed_html, HTML::serialize( $allowed_html ) );
    }

}