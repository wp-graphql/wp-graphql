<?php

/**
 * Who can read posts in custom statuses that aren't public.
 *
 * Reading someone else's post in full requires `edit_others_posts` (or the post type's own
 * equivalent) in any status that isn't public, the same way WordPress treats drafts and pending
 * posts. These tests cover statuses registered by plugins with `'public' => false` (including
 * `protected` ones) and statuses that are no longer registered, through `post`, `node`,
 * `contentNode` and the `posts` connection's `status` and `stati` arguments, plus media attached
 * to such posts and a post type with its own capabilities.
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

	/**
	 * Can edit their own books, but not other users' books.
	 *
	 * @var int
	 */
	private $book_writer;

	/**
	 * Can edit other users' books.
	 *
	 * @var int
	 */
	private $book_editor;

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
		register_post_status(
			'gqltest_live',
			[
				'label'  => 'GQL Test Live',
				'public' => true,
			]
		);

		register_post_type(
			'gqltest_book',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'GqlTestBook',
				'graphql_plural_name' => 'GqlTestBooks',
				'capability_type'     => [ 'gqltest_book', 'gqltest_books' ],
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'editor', 'author' ],
			]
		);
		add_role(
			'gqltest_book_writer',
			'GQL Test Book Writer',
			[
				'read'               => true,
				'edit_gqltest_books' => true,
			]
		);
		add_role(
			'gqltest_book_editor',
			'GQL Test Book Editor',
			[
				'read'                      => true,
				'edit_gqltest_books'        => true,
				'edit_others_gqltest_books' => true,
			]
		);

		$this->admin       = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor      = $this->factory()->user->create( [ 'role' => 'editor' ] );
		$this->author      = $this->factory()->user->create( [ 'role' => 'author' ] );
		$this->contributor = $this->factory()->user->create( [ 'role' => 'contributor' ] );
		$this->subscriber  = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->book_writer = $this->factory()->user->create( [ 'role' => 'gqltest_book_writer' ] );
		$this->book_editor = $this->factory()->user->create( [ 'role' => 'gqltest_book_editor' ] );

		$this->clearSchema();
	}

	public function tearDown(): void {
		unset( $GLOBALS['wp_post_statuses']['gqltest_pitch'], $GLOBALS['wp_post_statuses']['gqltest_protected'], $GLOBALS['wp_post_statuses']['gqltest_live'] );
		unregister_post_type( 'gqltest_book' );
		remove_role( 'gqltest_book_writer' );
		remove_role( 'gqltest_book_editor' );
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

	/**
	 * Attaches a media item with a recognizable description to a post.
	 *
	 * @param int $parent_id The post the media item is attached to.
	 * @param int $author_id The media item's author.
	 */
	private function create_media_item( int $parent_id, int $author_id ): int {
		$media_id = $this->factory()->attachment->create(
			[
				'post_parent'    => $parent_id,
				'post_author'    => $author_id,
				'post_title'     => 'Attached media title',
				'post_content'   => 'Attached media description',
				'post_excerpt'   => 'Attached media caption',
				'post_mime_type' => 'image/jpeg',
			]
		);

		// Gives the media item a file URL.
		update_post_meta( $media_id, '_wp_attached_file', 'gqltest-media.jpg' );

		return $media_id;
	}

	/**
	 * Queries the media item by database ID and returns `mediaItem`.
	 *
	 * @param int $media_id The media item ID.
	 *
	 * @return array<string,mixed>|null
	 */
	private function query_media_item( int $media_id ): ?array {
		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						mediaItem( id: $id, idType: DATABASE_ID ) {
							databaseId
							isRestricted
							description
							caption
							sourceUrl
						}
					}
				',
				'variables' => [ 'id' => $media_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		return $actual['data']['mediaItem'];
	}

	/**
	 * Asserts the media item was returned with its fields withheld.
	 *
	 * @param array<string,mixed>|null $media_item The queried media item.
	 */
	private function assertMediaWithheld( ?array $media_item ): void {
		$this->assertNotNull( $media_item, 'Media attached to content the user is told exists is still returned.' );
		$this->assertTrue( $media_item['isRestricted'] );
		$this->assertNull( $media_item['description'] );
		$this->assertNull( $media_item['caption'] );
		$this->assertNull( $media_item['sourceUrl'] );
	}

	/**
	 * Asserts the media item was returned in full.
	 *
	 * @param array<string,mixed>|null $media_item The queried media item.
	 */
	private function assertMediaReadable( ?array $media_item ): void {
		$this->assertNotNull( $media_item );
		$this->assertFalse( $media_item['isRestricted'] );
		$this->assertStringContainsString( 'Attached media description', (string) $media_item['description'] );
		$this->assertStringContainsString( 'Attached media caption', (string) $media_item['caption'] );
		$this->assertNotEmpty( $media_item['sourceUrl'] );
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

	public function testContributorCannotReadSomeoneElsesPostsThroughConnectionStatusArgument(): void {
		$others_post_id = $this->create_post( 'gqltest_pitch', $this->admin );

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query' => '
					query {
						posts( first: 50, where: { status: GQLTEST_PITCH } ) {
							nodes { databaseId content }
						}
					}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$content_by_id = array_column( $actual['data']['posts']['nodes'], 'content', 'databaseId' );

		$this->assertArrayHasKey( $others_post_id, $content_by_id );
		$this->assertNull( $content_by_id[ $others_post_id ] );
	}

	public function testPostInPublicCustomStatusIsReadable(): void {
		$post_id = $this->create_post( 'gqltest_live', $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertContentReadable( $this->query_post( $post_id ) );
	}

	public function testBookWriterCannotReadSomeoneElsesBookInNonPublicStatus(): void {
		// The post type's own `edit_others_gqltest_books`, not `edit_others_posts`, decides this.
		$book_id = $this->create_post( 'gqltest_pitch', $this->admin, 'gqltest_book' );

		wp_set_current_user( $this->book_writer );

		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						contentNode( id: $id, idType: DATABASE_ID ) {
							... on GqlTestBook { databaseId content }
						}
					}
				',
				'variables' => [ 'id' => $book_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $book_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertNull( $actual['data']['contentNode']['content'] );
	}

	public function testBookEditorCanReadSomeoneElsesBookInNonPublicStatus(): void {
		$book_id = $this->create_post( 'gqltest_pitch', $this->admin, 'gqltest_book' );

		wp_set_current_user( $this->book_editor );

		$actual = $this->graphql(
			[
				'query'     => '
					query ( $id: ID! ) {
						contentNode( id: $id, idType: DATABASE_ID ) {
							... on GqlTestBook { databaseId content }
						}
					}
				',
				'variables' => [ 'id' => $book_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertStringContainsString( 'Custom status secret content', (string) $actual['data']['contentNode']['content'] );
	}

	public function testContributorCannotReadMediaOnSomeoneElsesPostInNonPublicStatus(): void {
		$media_id = $this->create_media_item( $this->create_post( 'gqltest_pitch', $this->admin ), $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertMediaWithheld( $this->query_media_item( $media_id ) );
	}

	public function testContributorCannotReadMediaOnSomeoneElsesDraft(): void {
		$media_id = $this->create_media_item( $this->create_post( 'draft', $this->admin ), $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertMediaWithheld( $this->query_media_item( $media_id ) );
	}

	public function testContributorCannotReadMediaOnSomeoneElsesPostThroughConnection(): void {
		$media_id = $this->create_media_item( $this->create_post( 'gqltest_pitch', $this->admin ), $this->admin );

		wp_set_current_user( $this->contributor );

		$actual = $this->graphql(
			[
				'query' => '
					query {
						mediaItems( first: 50 ) {
							nodes { databaseId description }
						}
					}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$description_by_id = array_column( $actual['data']['mediaItems']['nodes'], 'description', 'databaseId' );

		$this->assertArrayHasKey( $media_id, $description_by_id );
		$this->assertNull( $description_by_id[ $media_id ] );
	}

	public function testContributorCanReadMediaOnOwnPostInNonPublicStatus(): void {
		// Uploaded by an Administrator, attached to the contributor's own post.
		$media_id = $this->create_media_item( $this->create_post( 'gqltest_pitch', $this->contributor ), $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertMediaReadable( $this->query_media_item( $media_id ) );
	}

	public function testEditorCanReadMediaOnSomeoneElsesPostInNonPublicStatus(): void {
		$media_id = $this->create_media_item( $this->create_post( 'gqltest_pitch', $this->admin ), $this->admin );

		wp_set_current_user( $this->editor );

		$this->assertMediaReadable( $this->query_media_item( $media_id ) );
	}

	public function testMediaOnPasswordProtectedPostIsUnaffected(): void {
		// The media follows its post's status, not its password.
		$parent_id = $this->factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_author'   => $this->admin,
				'post_password' => 'secret',
			]
		);
		$media_id  = $this->create_media_item( $parent_id, $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertMediaReadable( $this->query_media_item( $media_id ) );
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
		$media_id = $this->create_media_item( $this->create_post( 'publish', $this->admin ), $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertMediaReadable( $this->query_media_item( $media_id ) );
	}

	public function testUnattachedMediaItemIsUnaffected(): void {
		$media_id = $this->create_media_item( 0, $this->admin );

		wp_set_current_user( $this->contributor );

		$this->assertMediaReadable( $this->query_media_item( $media_id ) );
	}
}
