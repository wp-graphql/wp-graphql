<?php

namespace WPGraphQL\SmartCache;

class MutationCreateDocumentTest extends \Codeception\TestCase\WPTestCase {

    public $admin;

    public function setUp(): void {
        parent::setUp();

        \WPGraphQL::clear_schema();

        $this->admin = self::factory()->user->create( [
            'role' => 'administrator',
        ] );

    }

    public function tearDown(): void {
        \WPGraphQL::clear_schema();

        parent::tearDown();
    }

    public function testCreateDocumentMutationWithBadQueryStringFails() {

        wp_set_current_user( $this->admin );

        $mutation = 'mutation MyMutation($input: CreateGraphqlDocumentInput!) {
            createGraphqlDocument(input: $input) {
              graphqlDocument {
                id
                content
                alias
                status
                title
                slug
              }
            }
          }
        ';

        $query_string = "query my_query { __typename missing bracket ";
        $variables = [
            "input" => [
                "content" => $query_string,
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['errors'][0]['message'], "Invalid graphql query string \"$query_string\"" );
    }

    public function testCreateDocumentMutationWorks() {

        wp_set_current_user( $this->admin );

        $mutation = 'mutation MyMutation($input: CreateGraphqlDocumentInput!) {
            createGraphqlDocument(input: $input) {
              graphqlDocument {
                id
                content
                alias
                status
                title
                slug
                grant
              }
            }
          }
        ';

        $query_title = "cest test mutation";
        $variables = [
            "input" => [
                "content" => "query my_query_1 { __typename, uri, title }",
                "alias" => [
                    "foo",
                    "bar"
                ],
                "status" => "PUBLISH",
                "title" => $query_title,
                "slug" => "cest-00-slug"
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['createGraphqlDocument']['graphqlDocument']['title'], "cest test mutation" );
        $this->assertEquals( $actual['data']['createGraphqlDocument']['graphqlDocument']['status'], "publish" );
        $this->assertContains( "foo", $actual['data']['createGraphqlDocument']['graphqlDocument']['alias'] );
        $this->assertContains( "bar", $actual['data']['createGraphqlDocument']['graphqlDocument']['alias'] );
        $this->assertEquals( $actual['data']['createGraphqlDocument']['graphqlDocument']['grant'], "" );

        // Trying to create/save another document with same query string, should throw error
        $variables = [
            "input" => [
                "content" => "query my_query_1 { __typename, uri, title }",
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['errors'][0]['message'], "This query has already been associated with another query \"$query_title\"" );

        // Trying to create/save another document with alias that is already in use, should throw error
        $variables = [
            "input" => [
                "content" => "query my_query_2 { __typename, uri, title }",
                "alias" => [
                    "foo",
                ],
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['errors'][0]['message'], "Alias \"foo\" already in use by another query \"$query_title\"" );

    }

    public function testCreateDocumentMutationGrantAllowWorks() {

        wp_set_current_user( $this->admin );

        $mutation = 'mutation MyMutation($input: CreateGraphqlDocumentInput!) {
            createGraphqlDocument(input: $input) {
              graphqlDocument {
                id
                grant
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "content" => "query my_query_1 { __typename, uri, title }",
                "status" => "PUBLISH",
                "grant" => "allow"
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['createGraphqlDocument']['graphqlDocument']['grant'], "allow" );

        // Update the same query to deny
        $query_id = $actual['data']['createGraphqlDocument']['graphqlDocument']['id'];
        $mutation = 'mutation MyMutation($input: UpdateGraphqlDocumentInput!) {
            updateGraphqlDocument(input: $input) {
              graphqlDocument {
                grant
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "id" => $query_id,
                "grant" => "deny"
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['updateGraphqlDocument']['graphqlDocument']['grant'], "deny" );

        // Request with invalid grant value to invoke error
        $mutation = 'mutation MyMutation($input: UpdateGraphqlDocumentInput!) {
            updateGraphqlDocument(input: $input) {
              graphqlDocument {
                grant
              }
            }
          }
        ';

        $query_grant = "bad";
        $variables = [
            "input" => [
                "id" => $query_id,
                "grant" => $query_grant
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['errors'][0]['message'], "Invalid value for allow/deny grant: \"$query_grant\"" );
    }

    public function testCreateDocumentMutationMaxAgeHeaderWorks() {

        wp_set_current_user( $this->admin );

        $mutation = 'mutation MyMutation($input: CreateGraphqlDocumentInput!) {
            createGraphqlDocument(input: $input) {
              graphqlDocument {
                id
                maxAgeHeader
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "content" => "query my_query_1 { __typename, uri, title }",
                "status" => "PUBLISH",
                "maxAgeHeader" => 100
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['createGraphqlDocument']['graphqlDocument']['maxAgeHeader'], 100 );

        // Update the same query to deny
        $query_id = $actual['data']['createGraphqlDocument']['graphqlDocument']['id'];
        $mutation = 'mutation MyMutation($input: UpdateGraphqlDocumentInput!) {
            updateGraphqlDocument(input: $input) {
              graphqlDocument {
                maxAgeHeader
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "id" => $query_id,
                "maxAgeHeader" => 200
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['updateGraphqlDocument']['graphqlDocument']['maxAgeHeader'], 200 );

        // Request with invalid grant value to invoke error
        $mutation = 'mutation MyMutation($input: UpdateGraphqlDocumentInput!) {
            updateGraphqlDocument(input: $input) {
              graphqlDocument {
                maxAgeHeader
              }
            }
          }
        ';

        $query_age = -1;
        $variables = [
            "input" => [
                "id" => $query_id,
                "maxAgeHeader" => $query_age
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['errors'][0]['message'], "Invalid max age header value \"$query_age\". Must be greater than or equal to zero" );
    }

    public function testCreateDocumentMutationDescriptionWorks() {

        wp_set_current_user( $this->admin );

        $mutation = 'mutation MyMutation($input: CreateGraphqlDocumentInput!) {
            createGraphqlDocument(input: $input) {
              graphqlDocument {
                id
                description
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "content" => "query my_query_1 { __typename, uri, title }",
                "status" => "PUBLISH",
                "description" => "foo bar description"
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['createGraphqlDocument']['graphqlDocument']['description'], "foo bar description" );

        // Update the same query to deny
        $query_id = $actual['data']['createGraphqlDocument']['graphqlDocument']['id'];
        $mutation = 'mutation MyMutation($input: UpdateGraphqlDocumentInput!) {
            updateGraphqlDocument(input: $input) {
              graphqlDocument {
                description
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "id" => $query_id,
                "description" => "bix bang description"
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $this->assertEquals( $actual['data']['updateGraphqlDocument']['graphqlDocument']['description'], "bix bang description" );
    }
}
