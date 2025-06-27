<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\HTML;

class HTMLTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
        $this->clearSchema();
        /** @phpstan-ignore-next-line */
        \add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
    }

    public function tearDown(): void {
        /** @phpstan-ignore-next-line */
        \remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
        parent::tearDown();
    }

    public function register_test_fields(): void {
        /** @phpstan-ignore-next-line */
        \register_graphql_mutation( 'testHtmlMutation', [
            'inputFields'         => [
                'html' => [ 'type' => 'HTML' ],
            ],
            'outputFields'        => [
                'html' => [ 'type' => 'HTML' ],
            ],
            'mutateAndGetPayload' => static function ( $input ) {
                return [ 'html' => $input['html'] ];
            },
        ] );
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

    public function testMutationWithMismatchedTagsShouldFail() {
        $mutation = '
        mutation ($html: HTML!) {
            testHtmlMutation(input: { html: $html }) {
                html
            }
        }
        ';
        $invalid_html = '<b><i>mismatched tags</b></i>';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'html' => $invalid_html ],
        ]);

        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('Invalid HTML: The provided HTML is not well-formed.', $response['errors'][0]['message']);
    }

    public function testMutationWithCapitalizedTagsShouldPass() {
        $mutation = '
        mutation ($html: HTML!) {
            testHtmlMutation(input: { html: $html }) {
                html
            }
        }
        ';
        $valid_html_with_caps = '<P>This is a paragraph with <A HREF="https://wpgraphql.com">a link</A> and <STRONG>strong</STRONG> text.</P>';
        $expected_html = '<p>This is a paragraph with <a href="https://wpgraphql.com">a link</a> and <strong>strong</strong> text.</p>';

        $response = $this->graphql([
            'query' => $mutation,
            'variables' => [ 'html' => $valid_html_with_caps ],
        ]);

        $this->assertArrayNotHasKey('errors', $response);
        $this->assertEquals($expected_html, $response['data']['testHtmlMutation']['html']);
    }

}