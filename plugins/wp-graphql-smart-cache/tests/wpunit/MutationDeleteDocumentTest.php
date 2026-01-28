<?php

namespace WPGraphQL\SmartCache;

class MutationDeleteDocumentTest extends \Codeception\TestCase\WPTestCase {

    public $admin;

    public function setUp(): void {
        parent::setUp();

        \WPGraphQL::clear_schema();

        $this->admin = self::factory()->user->create( [
            'role' => 'administrator',
        ] );

        // Clean up any orphaned terms from previous test runs
        $terms = get_terms([
            'taxonomy' => 'graphql_query_alias',
            'hide_empty' => false,
        ]);
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                wp_delete_term( $term->term_id, 'graphql_query_alias' );
            }
        }
    }

    public function tearDown(): void {
        \WPGraphQL::clear_schema();

        parent::tearDown();
    }

    public function testDeleteDocumentMutation() {

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

        $query_string = "query my_query_0 { __typename }";
        $variables = [
            "input" => [
                "content" => $query_string,
                "alias"   => [ "one", "two" ],
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutation', $variables );

        codecept_debug( $actual );

        $alias_hash = $actual['data']['createGraphqlDocument']['graphqlDocument']['alias'][0];
        $this->assertContains( "one", $actual['data']['createGraphqlDocument']['graphqlDocument']['alias'] );

        $terms = get_terms([
          'taxonomy' => 'graphql_query_alias',
          'hide_empty' => false,
          'fields' => 'names',
        ]);
        codecept_debug( $terms );

        $this->assertContains( $alias_hash, $terms );

        $id = $actual['data']['createGraphqlDocument']['graphqlDocument']['id'];
        $query_hash = $actual['data']['createGraphqlDocument']['graphqlDocument']['alias'][0];

        // Update the same query with different query string
        $mutation = 'mutation MyMutationUpdate ($input: UpdateGraphqlDocumentInput!) {
            updateGraphqlDocument(input: $input) {
              graphqlDocument {
                content
                alias
              }
            }
          }
        ';

        $variables = [
            "input" => [
                "id" => $id,
                "content" => "query my_query_1 { __typename }",
            ]
        ];

        $actual = do_graphql_request( $mutation, 'MyMutationUpdate', $variables );

        codecept_debug( $actual );

        $this->assertContains( "one", $actual['data']['updateGraphqlDocument']['graphqlDocument']['alias'] );
        $this->assertNotContains( $query_hash, $actual['data']['updateGraphqlDocument']['graphqlDocument']['alias'] );

        $terms = get_terms([
          'taxonomy' => 'graphql_query_alias',
          'hide_empty' => false,
          'fields' => 'names',
        ]);
        codecept_debug( $terms );

        $this->assertNotContains( $alias_hash, $terms );

        // Delete the query
        $mutation = 'mutation MyMutationDelete( $delete: DeleteGraphqlDocumentInput! ) {
          deleteGraphqlDocument(input: $delete) {
            graphqlDocument {
              id
              content
              alias
              status
            }
          }
        }';
        $variables = [
          "delete" => [
            "id" => $id,
          ]
        ];
        $actual = do_graphql_request( $mutation, 'MyMutationDelete', $variables );
        codecept_debug( $actual );

        $terms = get_terms([
          'taxonomy' => 'graphql_query_alias',
          'hide_empty' => false,
          'fields' => 'names',
        ]);
        codecept_debug( $terms );
        $this->assertEmpty( $terms );
    }

}
