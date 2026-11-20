<?php

/**
 * RootQuery entry points for CommentAuthor (#1757).
 */
class CommentAuthorQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/** @var int */
	public $admin;

	/** @var int */
	public $post_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Post for CommentAuthorQueries',
				'post_author' => $this->admin,
			]
		);
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Create an approved guest comment (user_id = 0).
	 *
	 * @param array<string,mixed> $args Comment args.
	 */
	private function create_guest_comment( array $args = [] ): int {
		$defaults = [
			'comment_post_ID'      => $this->post_id,
			'comment_author'       => 'Guest Author',
			'comment_author_email' => 'guest@example.com',
			'comment_author_url'   => 'https://example.com/guest',
			'comment_content'      => 'Guest comment content',
			'comment_approved'     => '1',
			'user_id'              => 0,
			'comment_type'         => 'comment',
		];

		return (int) $this->factory()->comment->create( array_merge( $defaults, $args ) );
	}

	public function testCommentAuthorByDatabaseId(): void {
		$comment_id = $this->create_guest_comment();

		$query = '
			query GetCommentAuthor( $id: ID! ) {
				commentAuthor( id: $id, idType: DATABASE_ID ) {
					__typename
					databaseId
					name
					url
				}
			}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => (string) $comment_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'CommentAuthor', $actual['data']['commentAuthor']['__typename'] );
		$this->assertSame( $comment_id, $actual['data']['commentAuthor']['databaseId'] );
		$this->assertSame( 'Guest Author', $actual['data']['commentAuthor']['name'] );
		$this->assertSame( 'https://example.com/guest', $actual['data']['commentAuthor']['url'] );
	}

	public function testCommentAuthorByGlobalId(): void {
		$comment_id = $this->create_guest_comment(
			[
				'comment_author' => 'Global Guest',
			]
		);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment_author', (string) $comment_id );

		$query = '
			query GetCommentAuthor( $id: ID! ) {
				commentAuthor( id: $id, idType: ID ) {
					databaseId
					name
				}
			}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $global_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $comment_id, $actual['data']['commentAuthor']['databaseId'] );
		$this->assertSame( 'Global Guest', $actual['data']['commentAuthor']['name'] );
	}

	public function testCommentAuthorReturnsNullForMissingComment(): void {
		$query = '
			query GetCommentAuthor( $id: ID! ) {
				commentAuthor( id: $id, idType: DATABASE_ID ) {
					databaseId
				}
			}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => '99999999',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['commentAuthor'] );
	}

	public function testCommentAuthorReturnsNullForUserComments(): void {
		$comment_id = (int) $this->factory()->comment->create(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'User comment',
				'comment_approved' => '1',
				'user_id'          => $this->admin,
				'comment_author'   => get_userdata( $this->admin )->display_name,
			]
		);

		$query = '
			query GetCommentAuthor( $id: ID! ) {
				commentAuthor( id: $id, idType: DATABASE_ID ) {
					databaseId
				}
			}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => (string) $comment_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['commentAuthor'] );
	}

	public function testCommentAuthorsConnectionReturnsGuestAuthors(): void {
		$guest_one = $this->create_guest_comment(
			[
				'comment_author' => 'Guest One',
			]
		);
		$guest_two = $this->create_guest_comment(
			[
				'comment_author'       => 'Guest Two',
				'comment_author_email' => 'guest2@example.com',
			]
		);

		// User comment should not appear in commentAuthors.
		$this->factory()->comment->create(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'User comment',
				'comment_approved' => '1',
				'user_id'          => $this->admin,
			]
		);

		$query = '
			query {
				commentAuthors(first: 10) {
					nodes {
						__typename
						databaseId
						name
					}
				}
			}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$ids = wp_list_pluck( $actual['data']['commentAuthors']['nodes'], 'databaseId' );
		$this->assertContains( $guest_one, $ids );
		$this->assertContains( $guest_two, $ids );

		foreach ( $actual['data']['commentAuthors']['nodes'] as $node ) {
			$this->assertSame( 'CommentAuthor', $node['__typename'] );
		}
	}
}
