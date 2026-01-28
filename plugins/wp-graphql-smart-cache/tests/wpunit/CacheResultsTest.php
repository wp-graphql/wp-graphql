<?php

namespace WPGraphQL\SmartCache;

class CacheResultsTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		// Enable the local cache transient cache for these tests
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

        $this->user = self::factory()->user->create_and_get( [
            'role' => 'administrator',
        ] );
	}

	public function tearDown(): void {
		delete_option( 'graphql_cache_section' );

		parent::tearDown();
	}

	public function testMutationNotAuthenticatedDoesNotCacheResults() {
		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			''
		]);
		codecept_debug( "POST $post_id\n");

		// mutation to add comment to an existing post
		$mutation = 'mutation MyComment($input: CreateCommentInput!) {
			createComment(input: $input) {
				comment {
					id
					commentedOn {
						node {
							__typename
							id
						}
					}
					content
				}
			}
		}';

		$variables = [
			"input" => [
				"commentOn" => $post_id,
				"content" => "yoyo 4",
				"author" => $this->user->user_login,
				"authorEmail" => 'user@example.com'
			],
		];
		codecept_debug( $variables );

		$response = do_graphql_request( $mutation, 'MyComment', $variables );
		codecept_debug( $response );
		$this->assertEmpty( $response['extensions']['graphqlSmartCache']['graphqlObjectCache'] );

		$response = do_graphql_request( $mutation, 'MyComment', $variables );
		codecept_debug( $response );
		$this->assertEmpty( $response['extensions']['graphqlSmartCache']['graphqlObjectCache'] );

	}

}
