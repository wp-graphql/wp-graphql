<?php

/**
 * Who can read posts in custom statuses that aren't public.
 *
 * WordPress requires `edit_others_posts` to read someone else's post in any status that isn't
 * public. These tests cover statuses registered by plugins with `'public' => false` (including
 * `protected` ones) and statuses that are no longer registered, through every way of fetching
 * a single post or a list of posts.
 *
 * Each test sets one user. Switching users and calling clearSchema() inside one test registers
 * the schema twice, and the later queries fail in a way that looks like the user being blocked.
 */
class CustomPostStatusVisibilityTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var int
	 */
	private $admin;

	/**
	 * @var int
	 */
	private $editor;

	/**
	 * @var int
	 */
	private $author;

	/**
	 * @var int
	 */
	private $contributor;

	/**
	 * @var int
	 */
	private $subscriber;

	public function setUp(): void {
		parent::setUp();

		// Registered before the schema is built, so the `stati` enum includes them.
		register_post_status(
			'gqltest_pitch',
			[
				'label'  => 'GQL Test Pitch',
				'public' => false,
			]
		);
		register_post_status(
			'gqltest_protected',
			[
				'label'     => 'GQL Test Protected',
				'public'    => false,
				'protected' => true,
			]
		);

		$this->admin       = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor      = $this->factory()->user->create( [ 'role' => 'editor' ] );
		$this->author      = $this->factory()->user->create( [ 'role' => 'author' ] );
		$this->contributor = $this->factory()->user->create( [ 'role' => 'contributor' ] );
		$this->subscriber  = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->clearSchema();
	}

	public function tearDown(): void {
		unset( $GLOBALS['wp_post_statuses']['gqltest_pitch'], $GLOBALS['wp_post_statuses']['gqltest_protected'] );
		wp_set_current_user( 0 );
		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * Creates a post with recognizable content.
	 *
	 * @param string $status    The post status.
	 * @param int    $author_id The post author.
	 * @param string $post_type The post type.
	 */
	private function create_post( string $status, int $author_id, string $post_type = 'post' ): int {
		return $this->factory()->post->create(
			[
				'post_type'    => $post_type,
				'post_status'  => $status,
				'post_author'  => $author_id,
				'post_title'   => 'Custom status title',
				'post_content' => 'Custom status secret content',
			]
		);
	}

	/**
	 * Queries the post by database ID and returns `post`.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string,mixed>|null
	 */
	private function query_post( int $post_id ): ?array {
		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						post( id: $id, idType: DATABASE_ID ) {
							databaseId
							title
							content
							raw: content( format: RAW )
						}
					}
				',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		return $actual['data']['post'];
	}

	/**
	 * Asserts the post was returned with its content withheld.
	 *
	 * @param array<string,mixed>|null $post The queried post.
	 */
	private function assertContentWithheld( ?array $post ): void {
		$this->assertNotNull( $post, 'A user who can edit posts is still told the post exists.' );
		$this->assertNull( $post['content'], 'The content of someone else\'s post in a non-public status must be withheld.' );
		$this->assertNull( $post['raw'], 'The raw content of someone else\'s post in a non-public status must be withheld.' );
	}

	/**
	 * Asserts the post was returned in full.
	 *
	 * @param array<string,mixed>|null $post The queried post.
	 */
	private function assertContentReadable( ?array $post ): void {
		$this->assertNotNull( $post );
		$this->assertStringContainsString( 'Custom status secret content', (string) $post['content'] );
		$this->assertStringContainsString( 'Custom status secret content', (string) $post['raw'] );
	}

	public function testContributorCannotReadSomeoneElsesPostInNonPublicStatus(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertContentWithheld( $this->query_post( $post_id ) );
	}

	public function testAuthorCannotReadSomeoneElsesPostInNonPublicStatus(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->author );

		$this->assertContentWithheld( $this->query_post( $post_id ) );
	}

	public function testContributorCannotReadSomeoneElsesPostInProtectedStatus(): void {
		$post_id = $this->create_post( 'gqltest_protected', $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertContentWithheld( $this->query_post( $post_id ) );
	}

	public function testContributorCannotReadSomeoneElsesPostInUnregisteredStatus(): void {
		// A status left behind by a plugin that has since been deactivated.
		$post_id = $this->create_post( 'gqltest_unregistered', $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertContentWithheld( $this->query_post( $post_id ) );
	}

	public function testContributorCanReadOwnPostInNonPublicStatus(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->contributor );

		wp_set_current_user( $this->contributor );

		$this->assertContentReadable( $this->query_post( $post_id ) );
	}

	public function testEditorCanReadSomeoneElsesPostInNonPublicStatus(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->editor );

		$this->assertContentReadable( $this->query_post( $post_id ) );
	}

	public function testSubscriberCannotSeePostInNonPublicStatus(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->subscriber );

		$this->assertNull( $this->query_post( $post_id ) );
	}

	public function testContributorCannotReadSomeoneElsesPostThroughNode(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						node( id: $id ) {
							... on Post { databaseId content }
						}
					}
				',
				'variables' => [ 'id' => \GraphQLRelay\Relay::toGlobalId( 'post', (string) $post_id ) ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $post_id, $actual['data']['node']['databaseId'] );
		$this->assertNull( $actual['data']['node']['content'] );
	}

	public function testContributorCannotReadSomeoneElsesPostThroughContentNode(): void {
		$post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						contentNode( id: $id, idType: DATABASE_ID ) {
							... on Post { databaseId content }
						}
					}
				',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $post_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertNull( $actual['data']['contentNode']['content'] );
	}

	public function testContributorCannotReadSomeoneElsesPostsThroughConnection(): void {
		$others_post_id = $this->create_post( 'gqltest_pitch', $this->admin );
		$own_post_id    = $this->create_post( 'gqltest_pitch', $this->contributor );

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query' => '
					query {
						posts( first: 50, where: { stati: [ GQLTEST_PITCH ] } ) {
							nodes { databaseId content }
						}
					}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$content_by_id = array_column( $actual['data']['posts']['nodes'], 'content', 'databaseId' );

		$this->assertArrayHasKey( $others_post_id, $content_by_id );
		$this->assertNull( $content_by_id[ $others_post_id ], 'Someone else\'s post in the list must have its content withheld.' );
		$this->assertStringContainsString( 'Custom status secret content', (string) $content_by_id[ $own_post_id ], 'The contributor\'s own post keeps its content.' );
	}

	public function testContributorCannotSeePageInNonPublicStatus(): void {
		// Reading pages needs `edit_pages`, which contributors don't have.
		$page_id = $this->create_post( 'gqltest_pitch', $this->admin, 'page' );

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						page( id: $id, idType: DATABASE_ID ) { databaseId content }
					}
				',
				'variables' => [ 'id' => $page_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['page'] );
	}

	public function testPublishedPostIsUnaffected(): void {
		$post_id = $this->create_post( 'publish', $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertContentReadable( $this->query_post( $post_id ) );
	}

	public function testMediaItemOfPublishedPostIsUnaffected(): void {
		// Attachments use the `inherit` status and take their visibility from the parent.
		$parent_id     = $this->create_post( 'publish', $this->admin );
		$attachment_id = $this->factory()->attachment->create(
			[
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_title'   => 'Attached media title',
				'post_content' => 'Attached media description',
			]
		);

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						mediaItem( id: $id, idType: DATABASE_ID ) { databaseId description }
					}
				',
				'variables' => [ 'id' => $attachment_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $attachment_id, $actual['data']['mediaItem']['databaseId'] );
		$this->assertStringContainsString( 'Attached media description', (string) $actual['data']['mediaItem']['description'] );
	}
}
