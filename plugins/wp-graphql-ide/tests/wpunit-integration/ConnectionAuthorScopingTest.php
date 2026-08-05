<?php
/**
 * Regression coverage for https://github.com/wp-graphql/wp-graphql/issues/4117
 *
 * With Smart Cache active, its `graphql_document` post type is part of
 * `WPGraphQL::get_allowed_post_types()`, so any connection resolved with
 * post type `'any'` (core `contentNodes`, ACF relationship fields) carries
 * `graphql_document` in its `post_type` query arg. The IDE's old
 * `Access::scope_graphql_connections` filter matched that and forced
 * `author = get_current_user_id()` onto the whole query, so authenticated
 * requests silently dropped every post authored by someone else. Public
 * requests appeared fine only because WP_Query ignores `author = 0`.
 *
 * Per-user isolation of saved documents is enforced at the model layer
 * (`Access::restrict_post_visibility` on `graphql_data_is_private`), and
 * the IDE's own document list scopes itself client-side with an `author`
 * where arg — so no connection-level author scoping may leak into
 * unrelated queries.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE\Integration;

class ConnectionAuthorScopingTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var int
	 */
	private $author_id;

	/**
	 * @var int
	 */
	private $other_user_id;

	/**
	 * @var int
	 */
	private $post_id;

	public function setUp(): void {
		parent::setUp();

		$this->author_id = self::factory()->user->create( [ 'role' => 'author' ] );

		// The requesting user is an administrator so the failure mode can't
		// be confused with a capability restriction: admins can read
		// everything, yet the bug still blanked the results.
		$this->other_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$this->post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $this->author_id,
				'post_title'  => 'Published post by another author',
			]
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Run a `contentNodes` query (post type `'any'` under the hood) scoped
	 * to the fixture post and return the resolved database IDs.
	 *
	 * @return int[]
	 */
	private function query_content_node_ids(): array {
		$result = graphql(
			[
				'query'     => '
					query GetContentNodes($in: [ID!]) {
						contentNodes(where: { in: $in }) {
							nodes {
								databaseId
							}
						}
					}
				',
				'variables' => [ 'in' => [ $this->post_id ] ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $result );

		return array_column( $result['data']['contentNodes']['nodes'], 'databaseId' );
	}

	public function test_authenticated_content_nodes_query_includes_other_authors_posts() {
		wp_set_current_user( $this->other_user_id );

		$this->assertContains(
			$this->post_id,
			$this->query_content_node_ids(),
			'A published post must resolve for an authenticated user who is not its author (issue #4117).'
		);
	}

	public function test_public_content_nodes_query_includes_the_post() {
		wp_set_current_user( 0 );

		$this->assertContains(
			$this->post_id,
			$this->query_content_node_ids(),
			'A published post must resolve for unauthenticated requests.'
		);
	}

	public function test_other_users_documents_stay_private_at_the_model_layer() {
		// Deleting the connection-level author scoping must not reopen the
		// hole it was added for: another user's saved document has to stay
		// unreadable. The model-layer filter
		// (`Access::restrict_post_visibility`) is the enforcement point.
		$document_id = self::factory()->post->create(
			[
				'post_type'    => 'graphql_document',
				'post_status'  => 'publish',
				'post_author'  => $this->author_id,
				'post_title'   => 'Another users saved query',
				'post_content' => '{ __typename }',
			]
		);

		wp_set_current_user( $this->other_user_id );

		$result = graphql(
			[
				'query' => '
					query GetDocuments {
						graphqlDocuments(where: { stati: [PUBLISH, DRAFT] }) {
							nodes {
								databaseId
							}
						}
					}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertNotContains(
			$document_id,
			array_column( $result['data']['graphqlDocuments']['nodes'], 'databaseId' ),
			'A saved document authored by another user must never resolve in the connection.'
		);
	}
}
