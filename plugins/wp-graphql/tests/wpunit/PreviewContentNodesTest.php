<?php
class PreviewContentNodesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $with_post_type;
	public $without_post_type;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->with_post_type    = 'with-revisions';
		$this->without_post_type = 'without-revisions';

		register_post_type(
			$this->with_post_type,
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithRevisionSupport',
				'graphql_plural_name' => 'allWithRevisionSupport',
				'supports'            => [ 'title', 'content', 'author', 'revisions' ],
				'public'              => true,
			]
		);

		register_post_type(
			$this->without_post_type,
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'WithoutRevisionSupport',
				'graphql_plural_name' => 'allWithoutRevisionSupport',
				'supports'            => [ 'title', 'content', 'author' ],
				'public'              => true,
			]
		);

		$this->clearSchema();

		wp_set_current_user( $this->admin );
	}

	public function tearDown(): void {
		unregister_post_type( $this->with_post_type );
		unregister_post_type( $this->without_post_type );
		parent::tearDown();
	}

	/**
	 * Ensure the post types are queryable in the schema
	 */
	public function testValidSchema() {

		$actual = $this->graphql(
			[
				'query' => '{allWithoutRevisionSupport{nodes{id}}}',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$actual = $this->graphql(
			[
				'query' => '{allWithRevisionSupport{nodes{id}}}',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function getPreviewQuery() {

		return '
		query PreviewContentNode( $id:ID! $asPreview: Boolean $idType: ContentNodeIdTypeEnum) {
			node: contentNode(id:$id idType:$idType asPreview:$asPreview) {
				__typename
				id
				databaseId
				isPreview
				status
				previewRevisionDatabaseId
				...on NodeWithTitle {
					title
				}
			}
		}
		';
	}

	/**
	 * When a new post is created in WordPress, it doesn't
	 * have an ID yet until it is saved.
	 *
	 * If a user were to click preview
	 * - WordPress would check if the post_type supports revisions
	 * - If so: create a revision, return that revision as the preview
	 * - If not: create a draft, return the draft as the revision
	 */
	public function testPreviewNewPostOfTypeWithRevisionSupport() {

		// user creates a new post, nothing exists in the database yet
		// user clicks "preview"
		// the post_type supports revisions
		// a draft is created
		// a revision is also created
		$draft_title = uniqid( 'preview:', true );
		$draft_id    = $this->factory()->post->create(
			[
				'post_type'   => $this->with_post_type,
				'post_status' => 'draft',
				'post_title'  => $draft_title,
				'post_author' => $this->admin,
			]
		);

		$draft_post = get_post( $draft_id );

		$revision_id = $this->factory()->post->create(
			[
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_title'  => $draft_title,
				'post_author' => $this->admin,
				'post_parent' => $draft_id,
			]
		);

		$database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => true,
				],
			]
		);

		$uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'     => get_preview_post_link( $draft_post ),
					'idType' => 'URI',
				],
			]
		);

		// $this->assertSame('testing', get_preview_post_link( $draft_post ));

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $uri_preview );

		// the revision id should be the id of the preview since the post_type supports revisions
		self::assertQuerySuccessful(
			$database_id_preview,
			[
				$this->expectedNode(
					'node',
					[
						$this->expectedField( '__typename', 'WithRevisionSupport' ),
						$this->expectedField( 'databaseId', $revision_id ),
						$this->expectedField( 'title', $draft_title ),
					]
				),
			]
		);

		self::assertQuerySuccessful(
			$uri_preview,
			[
				$this->expectedNode(
					'node',
					[
						$this->expectedField( '__typename', 'WithRevisionSupport' ),
						$this->expectedField( 'databaseId', $revision_id ),
						$this->expectedField( 'title', $draft_title ),
					]
				),
			]
		);

		$not_database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => false,
				],
			]
		);

		$not_uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_preview_post_link( $draft_post ),
					'idType'    => 'URI',
					// setting asPreview to false overrides the
					// ?preview=true query arg in the "uri" passed to the ID
					'asPreview' => false,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $not_database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $not_uri_preview );

		// the draft_id should be the id because we're not trying to preview the thing
		self::assertQuerySuccessful(
			$not_database_id_preview,
			[
				$this->expectedNode(
					'node',
					[
						$this->expectedField( '__typename', 'WithRevisionSupport' ),
						$this->expectedField( 'databaseId', $draft_id ),
						$this->expectedField( 'title', $draft_title ),
					]
				),
			]
		);

		self::assertQuerySuccessful(
			$not_uri_preview,
			[
				$this->expectedNode(
					'node',
					[
						$this->expectedField( '__typename', 'WithRevisionSupport' ),
						$this->expectedField( 'databaseId', $draft_id ),
						$this->expectedField( 'title', $draft_title ),
					]
				),
			]
		);

		// The preview and the not_preview nodes should not be the same. They're different entities.
		$this->assertNotSame( $database_id_preview['data']['node'], $not_database_id_preview['data']['node'] );
		$this->assertNotSame( $uri_preview['data']['node'], $not_uri_preview['data']['node'] );

		// but the titles should be the same, because that's the change we're previewing
		$this->assertSame( $database_id_preview['data']['node']['title'], $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $uri_preview['data']['node']['title'], $not_uri_preview['data']['node']['title'] );
	}

	public function testPreviewNewPostOfTypeWithoutRevisionSupport() {

		// user creates a new post, nothing exists in the database yet
		// user clicks "preview"
		// the post_type doesn't support revisions
		// a draft is created
		$draft_title = uniqid( 'preview:', true );
		$draft_id    = self::factory()->post->create(
			[
				'post_type'   => $this->without_post_type,
				'post_status' => 'draft',
				'post_title'  => $draft_title,
				'post_author' => $this->admin,
			]
		);

		$database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => true,
				],
			]
		);

		$uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $draft_id ),
					'idType'    => 'URI',
					'asPreview' => true,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertSame( 'WithoutRevisionSupport', $database_id_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_title, $database_id_preview['data']['node']['title'] );

		$this->assertArrayNotHasKey( 'errors', $uri_preview );
		$this->assertSame( 'WithoutRevisionSupport', $uri_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $uri_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_title, $uri_preview['data']['node']['title'] );

		$not_database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => false,
				],
			]
		);

		$not_uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $draft_id ),
					'idType'    => 'URI',
					'asPreview' => false,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $uri_preview );

		$this->assertSame( 'WithoutRevisionSupport', $not_database_id_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $not_database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_title, $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $database_id_preview['data']['node'], $not_database_id_preview['data']['node'] );

		$this->assertSame( 'WithoutRevisionSupport', $not_uri_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $not_uri_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_title, $not_uri_preview['data']['node']['title'] );
		$this->assertSame( $uri_preview['data']['node'], $not_uri_preview['data']['node'] );
	}

	/**
	 * If a user were to click preview on a new post or a draft:
	 * - WordPress would check if the post_type supports revisions
	 * - If so: create a revision and create/update a draft, use the draft as the preview
	 * - If not: create/update a draft, use the draft as the preview
	 *
	 * WPGraphQL uses the revision as the preview if it exists. If not, it
	 * returns the draft itself as the preview.
	 */
	public function testPreviewDraftPostOfTypeWithRevisionSupport() {

		// user creates a new post or edits an existing draft
		// user clicks "preview"
		// the draft is created or updated
		$draft_title = 'draft title test, yo';
		$draft_id    = $this->factory()->post->create(
			[
				'post_type'   => $this->with_post_type,
				'post_status' => 'draft',
				'post_title'  => $draft_title,
				'post_author' => $this->admin,
			]
		);

		// since the post type supports revisions, a revision is created
		$revision_id = $this->factory()->post->create(
			[
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_title'  => $draft_title,
				'post_author' => $this->admin,
				'post_parent' => $draft_id,
			]
		);

		$not_database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => false,
				],
			]
		);

		$not_uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $draft_id ),
					'idType'    => 'URI',
					'asPreview' => false,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $not_database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $not_uri_preview );

		$this->assertSame( 'WithRevisionSupport', $not_database_id_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $not_database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_title, $not_database_id_preview['data']['node']['title'] );
		$this->assertFalse( $not_database_id_preview['data']['node']['isPreview'] );
		// the preview id should be the id of the revision since the post_type supports revisions
		$this->assertSame( $revision_id, $not_database_id_preview['data']['node']['previewRevisionDatabaseId'] );

		$this->assertSame( 'WithRevisionSupport', $not_uri_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $not_uri_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_title, $not_uri_preview['data']['node']['title'] );
		$this->assertFalse( $not_uri_preview['data']['node']['isPreview'] );
		// the preview id should be the id of the revision since the post_type supports revisions
		$this->assertSame( $revision_id, $not_uri_preview['data']['node']['previewRevisionDatabaseId'] );

		$database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => true,
				],
			]
		);

		$uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $draft_id ),
					'idType'    => 'URI',
					'asPreview' => true,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $uri_preview );

		// The post type supports revisions, so the preview node should be
		// a different node than the draft itself
		$this->assertNotSame( $not_database_id_preview, $database_id_preview );
		$this->assertNotSame( $not_uri_preview, $uri_preview );
		$this->assertSame( 'WithRevisionSupport', $database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithRevisionSupport', $uri_preview['data']['node']['__typename'] );
		// the preview id should be the id of this node since the post_type supports revisions and we are previewing
		$this->assertSame( $revision_id, $database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $revision_id, $uri_preview['data']['node']['databaseId'] );
		$this->assertTrue( $database_id_preview['data']['node']['isPreview'] );
		$this->assertTrue( $uri_preview['data']['node']['isPreview'] );
		// but revisions don't have revisions
		$this->assertNull( $database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertNull( $uri_preview['data']['node']['previewRevisionDatabaseId'] );

		// and the titles (that we are previewing) should be the same
		$this->assertSame( $database_id_preview['data']['node']['title'], $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $uri_preview['data']['node']['title'], $not_uri_preview['data']['node']['title'] );
	}

	/**
	 * If a user were to click preview on a new post or a draft:
	 * - WordPress would check if the post_type supports revisions
	 * - If so: create a revision and create/update a draft, use the draft as the preview
	 * - If not: create/update a draft, use the draft as the preview
	 *
	 * WPGraphQL uses the revision as the preview if it exists. Otherwise, it
	 * returns the draft itself as the preview.
	 */
	public function testPreviewDraftPostOfTypeWithoutRevisionSupport() {

		// user creates a new post or edits an existing draft
		// user clicks "preview"
		// the draft is created or updated
		$draft_title = 'draft title test, yo';
		$draft_id    = $this->factory()->post->create(
			[
				'post_type'   => $this->without_post_type,
				'post_status' => 'draft',
				'post_title'  => $draft_title,
				'post_author' => $this->admin,
			]
		);

		// since the post type does not support revisions, a revision is not created

		$not_database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => false,
				],
			]
		);

		$not_uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $draft_id ),
					'idType'    => 'URI',
					'asPreview' => false,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $not_database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $not_uri_preview );

		$this->assertSame( 'WithoutRevisionSupport', $not_database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithoutRevisionSupport', $not_uri_preview['data']['node']['__typename'] );

		$this->assertSame( $draft_id, $not_database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_id, $not_uri_preview['data']['node']['databaseId'] );

		$this->assertSame( $draft_title, $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $draft_title, $not_uri_preview['data']['node']['title'] );
		// Drafts are always treated as previews in WPGraphQL.
		$this->assertTrue( $not_database_id_preview['data']['node']['isPreview'] );
		$this->assertTrue( $not_uri_preview['data']['node']['isPreview'] );
		// the post_type does not support revisions, so there shouldn't be a revision ID.
		$this->assertNull( $not_database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertNull( $not_uri_preview['data']['node']['previewRevisionDatabaseId'] );

		$database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $draft_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => true,
				],
			]
		);

		$uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $draft_id ),
					'idType'    => 'URI',
					'asPreview' => true,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $uri_preview );

		// The post type does not support revisions, so the preview node should be
		// the same node as the draft itself
		$this->assertSame( $not_database_id_preview, $database_id_preview );
		$this->assertSame( $not_uri_preview, $uri_preview );
		$this->assertSame( 'WithoutRevisionSupport', $database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithoutRevisionSupport', $uri_preview['data']['node']['__typename'] );
		$this->assertSame( $draft_id, $database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $draft_id, $uri_preview['data']['node']['databaseId'] );
		$this->assertTrue( $database_id_preview['data']['node']['isPreview'] );
		$this->assertTrue( $uri_preview['data']['node']['isPreview'] );
		// The post type does not support revisions, so there shouldn't be a revision ID.
		$this->assertNull( $database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertNull( $uri_preview['data']['node']['previewRevisionDatabaseId'] );

		// But the titles (that we are previewing) should be the same
		$this->assertSame( $database_id_preview['data']['node']['title'], $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $uri_preview['data']['node']['title'], $not_uri_preview['data']['node']['title'] );
	}

	/**
	 * If a user were to click preview on a published post, WordPress would create
	 * an autosave revision regardless of the post type's revision support. It will
	 * use the autosave as the preview.
	 *
	 * WPGraphQL uses the latest revision as the preview for published posts. This
	 * is usually the autosave created by clicking "Preview".
	 */
	public function testPreviewPublishedPostOfTypeWithRevisionSupport() {

		// we're starting with a published post
		$title        = 'published title';
		$published_id = $this->factory()->post->create(
			[
				'post_type'   => $this->with_post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $this->admin,
			]
		);

		// user changes title
		$new_title = 'new title';

		// User clicks preview
		// an autosave revision is created
		$revision_id = $this->factory()->post->create(
			[
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_title'  => $new_title,
				'post_author' => $this->admin,
				'post_parent' => $published_id,
			]
		);

		$not_database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $published_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => false,
				],
			]
		);

		$not_uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $published_id ),
					'idType'    => 'URI',
					'asPreview' => false,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $not_database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $not_uri_preview );

		$this->assertSame( 'WithRevisionSupport', $not_database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithRevisionSupport', $not_uri_preview['data']['node']['__typename'] );

		$this->assertSame( $published_id, $not_database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $published_id, $not_uri_preview['data']['node']['databaseId'] );

		$this->assertSame( $title, $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $title, $not_uri_preview['data']['node']['title'] );

		$this->assertFalse( $not_database_id_preview['data']['node']['isPreview'] );
		$this->assertFalse( $not_uri_preview['data']['node']['isPreview'] );

		// the preview id should be the id of the autosave revision
		$this->assertSame( $revision_id, $not_database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertSame( $revision_id, $not_uri_preview['data']['node']['previewRevisionDatabaseId'] );

		$database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $published_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => true,
				],
			]
		);

		$uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $published_id ),
					'idType'    => 'URI',
					'asPreview' => true,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $uri_preview );

		// The published node and preview node should be different nodes
		$this->assertNotSame( $database_id_preview, $not_database_id_preview );
		$this->assertNotSame( $uri_preview, $not_uri_preview );

		$this->assertSame( 'WithRevisionSupport', $database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithRevisionSupport', $uri_preview['data']['node']['__typename'] );

		// The autosave revision id should be returned since we are previewing
		$this->assertSame( $revision_id, $database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $revision_id, $uri_preview['data']['node']['databaseId'] );

		$this->assertTrue( $database_id_preview['data']['node']['isPreview'] );
		$this->assertTrue( $uri_preview['data']['node']['isPreview'] );

		// revisions don't have revisions
		$this->assertNull( $database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertNull( $uri_preview['data']['node']['previewRevisionDatabaseId'] );

		// the changed title should be returned for the preview
		$this->assertSame( $new_title, $database_id_preview['data']['node']['title'] );
		$this->assertSame( $new_title, $uri_preview['data']['node']['title'] );
	}

	/**
	 * If a user were to click preview on a published post, WordPress would create
	 * an autosave revision regardless of the post type's revision support. It will
	 * use the autosave as the preview.
	 *
	 * WPGraphQL uses the latest revision as the preview for published posts. This
	 * is usually the autosave created by clicking "Preview".
	 */
	public function testPreviewPublishedPostOfTypeWithoutRevisionSupport() {

		// we're starting with a published post
		$title        = 'published title';
		$published_id = $this->factory()->post->create(
			[
				'post_type'   => $this->without_post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $this->admin,
			]
		);

		// user changes title
		$new_title = 'new title';

		// User clicks preview
		// autosave revisions are always created for published posts
		// regardless of post type support
		$revision_id = $this->factory()->post->create(
			[
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_title'  => $new_title,
				'post_author' => $this->admin,
				'post_parent' => $published_id,
			]
		);

		$not_database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $published_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => false,
				],
			]
		);

		$not_uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $published_id ),
					'idType'    => 'URI',
					'asPreview' => false,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $not_database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $not_uri_preview );

		$this->assertSame( 'WithoutRevisionSupport', $not_database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithoutRevisionSupport', $not_uri_preview['data']['node']['__typename'] );

		$this->assertSame( $published_id, $not_database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $published_id, $not_uri_preview['data']['node']['databaseId'] );

		$this->assertSame( $title, $not_database_id_preview['data']['node']['title'] );
		$this->assertSame( $title, $not_uri_preview['data']['node']['title'] );

		$this->assertFalse( $not_database_id_preview['data']['node']['isPreview'] );
		$this->assertFalse( $not_uri_preview['data']['node']['isPreview'] );

		// the preview id should be the id of the autosave revision
		$this->assertSame( $revision_id, $not_database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertSame( $revision_id, $not_uri_preview['data']['node']['previewRevisionDatabaseId'] );

		$database_id_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => $published_id,
					'idType'    => 'DATABASE_ID',
					'asPreview' => true,
				],
			]
		);

		$uri_preview = $this->graphql(
			[
				'query'     => $this->getPreviewQuery(),
				'variables' => [
					'id'        => get_permalink( $published_id ),
					'idType'    => 'URI',
					'asPreview' => true,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $database_id_preview );
		$this->assertArrayNotHasKey( 'errors', $uri_preview );

		// The published node and preview node should be different nodes
		$this->assertNotSame( $database_id_preview, $not_database_id_preview );
		$this->assertNotSame( $uri_preview, $not_uri_preview );

		$this->assertSame( 'WithoutRevisionSupport', $database_id_preview['data']['node']['__typename'] );
		$this->assertSame( 'WithoutRevisionSupport', $uri_preview['data']['node']['__typename'] );

		$this->assertTrue( $database_id_preview['data']['node']['isPreview'] );
		$this->assertTrue( $uri_preview['data']['node']['isPreview'] );

		// The autosave revision id should be returned since we are previewing
		$this->assertSame( $revision_id, $database_id_preview['data']['node']['databaseId'] );
		$this->assertSame( $revision_id, $uri_preview['data']['node']['databaseId'] );

		// but revisions don't have revisions
		$this->assertNull( $database_id_preview['data']['node']['previewRevisionDatabaseId'] );
		$this->assertNull( $uri_preview['data']['node']['previewRevisionDatabaseId'] );

		// and the changed title should be returned for the preview
		$this->assertSame( $new_title, $database_id_preview['data']['node']['title'] );
		$this->assertSame( $new_title, $uri_preview['data']['node']['title'] );
	}
}
