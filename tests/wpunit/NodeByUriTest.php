<?php

class NodeByUriTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $post;
	public $page;
	public $user;

	public function setUp(): void {
		parent::setUp();
		// Set category base to empty string to avoid issues with the test

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		create_initial_taxonomies();

		register_post_type(
			'by_uri_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'CustomType',
				'graphql_plural_name' => 'CustomTypes',
				'public'              => true,
				'has_archive'         => true,
			]
		);

		register_taxonomy(
			'by_uri_tax',
			'by_uri_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'CustomTax',
				'graphql_plural_name' => 'CustomTaxes',
				'default_term'        => [
					'name' => 'Default Term',
					'slug' => 'default-term',
				],
			]
		);

		flush_rewrite_rules( true );

		$this->clearSchema();

		$this->user = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
	}

	public function tearDown(): void {
		wp_delete_user( $this->user );
		unregister_post_type( 'by_uri_cpt' );
		unregister_taxonomy( 'by_uri_tax' );

		$this->clearSchema();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		parent::tearDown();
	}

	public function getQuery(): string {
		return '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on DatabaseIdentifier {
					databaseId
				}
				uri
				isPostsPage
				isFrontPage
			}
		}
		';
	}

	/**
	 * Test Comment URIs
	 */
	public function testCommentByUri(): void {
		$post_one_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test commentByUri 1',
				'post_author' => $this->user,
			]
		);

		$post_two_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test commentByUri 2',
				'post_author' => $this->user,
			]
		);

		$comment_one_id = $this->factory()->comment->create(
			[
				'comment_post_ID'      => $post_one_id,
				'comment_content'      => 'Test comment 1',
				'comment_author'       => 'Test Author 1',
				'comment_author_email' => 'info@test-comment-author1.test',
			]
		);

		$comment_two_id = $this->factory()->comment->create(
			[
				'comment_post_ID'      => $post_one_id,
				'comment_content'      => 'Test comment 2',
				'comment_author'       => 'Test Author 2',
				'comment_author_email' => 'info@test-comment-author2.test',
			]
		);

		$comment_three_id = $this->factory()->comment->create(
			[
				'comment_post_ID'      => $post_two_id,
				'comment_content'      => 'Test comment 3',
				'comment_author'       => 'Test Author 3',
				'comment_author_email' => 'info@test-comment-author3.test',
			]
		);

		$child_comment_id = $this->factory()->comment->create(
			[
				'comment_post_ID' => $post_two_id,
				'comment_content' => 'Test child comment',
				'comment_author'  => 'Test Child Author',
				'comment_parent'  => $comment_three_id,
			]
		);

		$query = $this->getQuery();

		// Test bad URI should return null.
		$uri = '/2022/12/31/bad-uri/#comment-99999999';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'], 'nodeByUri should return null when no comment is found' );

		// Test Bad comment on good Post returns null.
		$uri = wp_make_link_relative( get_permalink( $post_one_id ) ) . '#comment-99999999';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'], 'nodeByUri should return null when no comment is found, even if the post is valid' );

		// Test Fake comment on good post returns Post.
		$uri = wp_make_link_relative( get_permalink( $post_one_id ) ) . '#comment-NaN';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Post', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_one_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( wp_make_link_relative( get_permalink( $post_one_id ) ), $actual['data']['nodeByUri']['uri'] );

		// Test with good comment on good post.
		$uri = wp_make_link_relative( get_comment_link( $comment_one_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Comment', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $comment_one_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test multiple comments on the same post.
		$uri = wp_make_link_relative( get_comment_link( $comment_two_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Comment', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $comment_two_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test comments on another post.
		$uri = wp_make_link_relative( get_comment_link( $comment_three_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Comment', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $comment_three_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Test child comment on another post.
		$uri = wp_make_link_relative( get_comment_link( $child_comment_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Comment', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $child_comment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );

		// Clean up
		wp_delete_comment( $comment_one_id, true );
		wp_delete_comment( $comment_two_id, true );
		wp_delete_comment( $comment_three_id, true );
		wp_delete_comment( $child_comment_id, true );
		wp_delete_post( $post_one_id, true );
		wp_delete_post( $post_two_id, true );
	}

	/**
	 * Test Post URIs
	 */
	public function testPostByUri(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test postByUri',
				'post_author' => $this->user,
			]
		);

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Post {
					databaseId
				}
				isContentNode
				isTermNode
				uri
			}
		}
		';

		$expected_graphql_type = ucfirst( get_post_type_object( 'post' )->graphql_single_name );

		// Test with bad URI
		$uri = '/2022/12/31/bad-uri/';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'], 'nodeByUri should return null when no post is found' );

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 *  uri => /{year}/{month}/{day}/test-postbyuri/
		 * 'page' => '',
		 * 'year => {year},
		 * 'monthnum' => {month},
		 * 'day' => {day},
		 * 'name' => 'test-postbyuri',
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $post_id, $actual );
		$this->assertTrue( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isTermNode'] );

		// A paged post should return the same results.
		$expected = $actual;

		$uri = $uri . user_trailingslashit( 1, 'single_paged' );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertEquals( $expected, $actual, 'Paged post should return the same results as the first page' );

		// A post with an anchor should return the same results.
		$uri = wp_make_link_relative( get_permalink( $post_id ) );
		// Test with /#anchor
		$uri = $uri . '#anchor';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertEquals( $expected, $actual, 'Post with anchor should return the same results as the first page' );

		// Test with #anchor
		$uri = str_replace( '/#', '#', $uri );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertEquals( $expected, $actual, 'Post with anchor should return the same results as the first page' );

		// Test with fixed base.
		$this->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		codecept_debug( $uri );

		// Querying without the base should return null.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => str_replace( '/blog', '', $uri ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'], 'nodeByUri should return null if no base was included' );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /blog/{year}/{month}/{day}/test-postbyuri/
		 * 'page' => '',
		 * 'year => {year},
		 * 'monthnum' => {month},
		 * 'day' => {day},
		 * 'name' => 'test-postbyuri',
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $post_id, $actual );
	}

	public function testPostWithSlugConflicts(): void {
		$post_args = [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Test postWithSlugConflictsByUri',
			'post_author' => $this->user,
		];

		$post_1_id = $this->factory()->post->create( $post_args );
		$post_2_id = $this->factory()->post->create( $post_args );

		$query = $this->getQuery();

		$expected_graphql_type = ucfirst( get_post_type_object( 'post' )->graphql_single_name );

		// Test first post resolution
		$uri = wp_make_link_relative( get_permalink( $post_1_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $post_1_id, $actual );

		// Test second post resolution
		$uri = wp_make_link_relative( get_permalink( $post_2_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $post_2_id, $actual );
	}

	/**
	 * Test Page URIs
	 */
	public function testPageByUri(): void {
		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test pageByUri',
				'post_author' => $this->user,
			]
		);

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Page {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		$expected_graphql_type = ucfirst( get_post_type_object( 'page' )->graphql_single_name );

		// Test with a bad URI.
		$uri = '/bad-uri/';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		// Test with valid uri.
		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /test-pagebyuri/
		 * page => '',
		 * pagename => 'test-pagebyuri',
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $page_id, $actual );
		$this->assertTrue( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isTermNode'] );

		// Test with fixed base.
		$this->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		// Test without base.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/not-real',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		// Test with unwanted base.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/blog' . $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'] );

		// Test with actual uri
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $page_id, $actual );
	}

	public function testPageWithIdenticalSlugs(): void {
		$parent_1_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Parent 1 Page',
				'post_author' => $this->user,
			]
		);
		$parent_2_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Parent 2 Page',
				'post_author' => $this->user,
			]
		);

		$child_page_args = [
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Test Child Page With Identical Slugs',
			'post_author' => $this->user,
		];

		$child_1_id = $this->factory()->post->create( $child_page_args + [ 'post_parent' => $parent_1_id ] );
		$child_2_id = $this->factory()->post->create( $child_page_args + [ 'post_parent' => $parent_2_id ] );

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				... on Page {
					databaseId
					uri
					parentDatabaseId
				}
			}
		}
		';

		$expected_graphql_type = ucfirst( get_post_type_object( 'page' )->graphql_single_name );

		// Test first child
		$uri = wp_make_link_relative( get_permalink( $child_1_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $child_1_id, $actual );
		$this->assertSame( $parent_1_id, $actual['data']['nodeByUri']['parentDatabaseId'] );

		// Test second child
		$uri = wp_make_link_relative( get_permalink( $child_2_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $child_2_id, $actual );
		$this->assertSame( $parent_2_id, $actual['data']['nodeByUri']['parentDatabaseId'] );
	}

	public function testPageWithUpdatedUri(): void {
		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page With Updated Uri',
				'post_author' => $this->user,
			]
		);

		$query = $this->getQuery();

		$expected_graphql_type = ucfirst( get_post_type_object( 'page' )->graphql_single_name );

		$original_uri = wp_make_link_relative( get_permalink( $page_id ) );

		// Update page slug
		$updated_slug = 'new-uri';
		wp_update_post(
			[
				'ID'        => $page_id,
				'post_name' => $updated_slug,
			]
		);

		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $page_id, $actual );

		// Test original uri should fail.
		codecept_debug( $original_uri );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $original_uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'], 'Original URI should not resolve to a node' );

		// Test page moved to child.
		$parent_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Parent Page',
				'post_author' => $this->user,
			]
		);
		wp_update_post(
			[
				'ID'          => $page_id,
				'post_parent' => $parent_id,
			]
		);

		// Check old uri doesnt work
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['nodeByUri'], 'Old URI should not resolve to a node' );

		// Check new uri.
		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $page_id, $actual );
	}

	/**
	 * Test Attachment URIs
	 */
	public function testAttachmentByUri() {
		$attachment_id = $this->factory()->attachment->create_object(
			[
				'file'           => 'example.jpg',
				'post_title'     => 'Example Image',
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => 0,
			]
		);

		$query = $this->getQuery();

		$uri = wp_make_link_relative( get_permalink( $attachment_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{slug}/
		 * page => ''
		 * pagename => {slug}
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'MediaItem', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $attachment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	public function testAttachmentWithParent() {
		$post_id       = $this->factory()->post->create(
			[
				'post_title'  => 'Example Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$attachment_id = $this->factory()->attachment->create_object(
			[
				'file'           => 'example.jpg',
				'post_title'     => 'Example Image',
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
			]
		);

		$query = $this->getQuery();

		$uri = wp_make_link_relative( get_permalink( $attachment_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}{monthnum}{day}/{postslug}/{slug}
		 * attachment => {slug}
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'MediaItem', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $attachment_id, $actual['data']['nodeByUri']['databaseId'] );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
	}

	/**
	 * Test CPT Uris
	 */
	public function testCptByUri(): void {
		$cpt_id = $this->factory()->post->create(
			[
				'post_type'   => 'by_uri_cpt',
				'post_status' => 'publish',
				'post_title'  => 'Test customPostTypeByUri',
				'post_author' => $this->user,
			]
		);

		$query = $this->getQuery();

		$expected_graphql_type = ucfirst( get_post_type_object( 'by_uri_cpt' )->graphql_single_name );

		$uri = wp_make_link_relative( get_permalink( $cpt_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /by_uri_cpt/test-customposttypebyuri/
		 * page => '',
		 * by_uri_cpt => test-customposttypebyuri,
		 * post_type => by_uri_cpt,
		 * name => test-customposttypebyuri,
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $cpt_id, $actual );
	}

	public function testCptWithIdenticalSlugs(): void {
		$post_args = [
			'post_status' => 'publish',
			'post_title'  => 'Test slug conflict',
			'post_author' => $this->user,
		];

		$post_id = $this->factory()->post->create( $post_args + [ 'post_type' => 'post' ] );
		$page_id = $this->factory()->post->create( $post_args + [ 'post_type' => 'page' ] );
		$cpt_id  = $this->factory()->post->create( $post_args + [ 'post_type' => 'by_uri_cpt' ] );

		$query = $this->getQuery();

		// Test post
		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		codecept_debug( $uri );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'Post', $post_id, $actual );

		// Test cpt
		$uri = wp_make_link_relative( get_permalink( $cpt_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri        => /by_uri_cpt/test-slug-conflict/
		 * page       => ''
		 * by_uri_cpt => test-slug-conflict
		 * post_type  => by_uri_cpt
		 * name       => test-slug-conflict
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, ucfirst( get_post_type_object( 'by_uri_cpt' )->graphql_single_name ), $cpt_id, $actual );

		// Test page
		$uri = wp_make_link_relative( get_permalink( $page_id ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() sets the following query vars:
		 * uri => test-slug-conflict
		 * page => ''
		 * pagename => 'test-slug-conflict'
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'Page', $page_id, $actual );
	}

	public function testHierarchicalCpt(): void {

		register_post_type(
			'test_hierarchical',
			[
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'query_var'           => true,
				'rewrite'             => [
					'slug'       => 'test_hierarchical',
					'with_front' => false,
				],
				'capability_type'     => 'page',
				'has_archive'         => false,
				'hierarchical'        => true,
				'menu_position'       => null,
				'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'page-attributes' ],
				'show_in_rest'        => true,
				'rest_base'           => 'test-hierarchical',
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testHierarchical',
				'graphql_plural_name' => 'testHierarchicals',
			]
		);

		flush_rewrite_rules( true );

		$parent = $this->factory()->post->create(
			[
				'post_type'    => 'test_hierarchical',
				'post_title'   => 'Test for HierarchicalCptNodesByUri',
				'post_content' => 'test',
				'post_status'  => 'publish',
			]
		);

		$child = $this->factory()->post->create(
			[
				'post_type'    => 'test_hierarchical',
				'post_title'   => 'Test child for HierarchicalCptNodesByUri',
				'post_content' => 'child',
				'post_parent'  => $parent,
				'post_status'  => 'publish',
			]
		);

		// Test all nodes return
		$query = '
		{
			testHierarchicals {
				nodes {
					id
					databaseId
					title
					uri
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );
		codecept_debug( wp_make_link_relative( get_permalink( $child ) ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$database_ids = wp_list_pluck( $actual['data']['testHierarchicals']['nodes'], 'databaseId' );

		$this->assertTrue( in_array( $child, $database_ids, true ) );
		$this->assertTrue( in_array( $parent, $database_ids, true ) );

		$query = $this->getQuery();

		$child_uri = wp_make_link_relative( get_permalink( $child ) );

		codecept_debug( $child_uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /test_hierarchical/test-for-hierarchicalcptnodesbyuri/test-child-for-hierarchicalcptnodesbyuri/
		 * page => ''
		 * test_hierarchical => test-for-hierarchicalcptnodesbyuri/test-child-for-hierarchicalcptnodesbyuri/
		 * 'post_type' => 'test_hierarchical'
		 * 'name' => 'test-for-hierarchicalcptnodesbyuri/test-child-for-hierarchicalcptnodesbyuri'
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $child_uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $child_uri, $actual['data']['nodeByUri']['uri'], 'Makes sure the uri of the node matches the uri queried with' );
		$this->assertSame( 'TestHierarchical', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $child, $actual['data']['nodeByUri']['databaseId'] );

		$parent_uri = wp_make_link_relative( get_permalink( $parent ) );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /test_hierarchical/test-for-hierarchicalcptnodesbyuri/
		 * page => ''
		 * test_hierarchical => test-for-hierarchicalcptnodesbyuri
		 * 'post_type' => 'test_hierarchical'
		 * 'name' => 'test-for-hierarchicalcptnodesbyuri'
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $parent_uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $parent_uri, $actual['data']['nodeByUri']['uri'], 'Makes sure the uri of the node matches the uri queried with' );
		$this->assertSame( 'TestHierarchical', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $parent, $actual['data']['nodeByUri']['databaseId'] );

		// cleanup.
		unregister_post_type( 'test_hierarchical' );
	}

	public function testCptArchiveUri(): void {
		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on ContentType {
					name
				}
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_post_type_archive_link( 'by_uri_cpt' ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /by_uri_cpt/
		 * post_type => by_uri_cpt
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'by_uri_cpt', $actual['data']['nodeByUri']['name'] );
	}

	public function testCptWithFront(): void {
		// Set the permalink structure to include a base
		$this->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		register_post_type(
			'test_with_front',
			[
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'query_var'           => true,
				'rewrite'             => [
					'slug'       => 'my-custom-slug',
					'with_front' => true,
				],
				'capability_type'     => 'page',
				'has_archive'         => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testWithFront',
				'graphql_plural_name' => 'testWithFronts',
			]
		);
		flush_rewrite_rules( true );
		$this->clearSchema();

		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'test_with_front',
				'post_title'   => 'Test for testCptWithNoFront',
				'post_content' => 'test',
				'post_status'  => 'publish',
			]
		);

		$query = $this->getQuery();

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		// Test the post.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'TestWithFront', $post_id, $actual );

		// Test the archive
		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on ContentType {
					name
				}
				uri
			}
		}
		';

		$uri = wp_make_link_relative( get_post_type_archive_link( 'test_with_front' ) );
		codecept_debug( $uri );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'test_with_front', $actual['data']['nodeByUri']['name'] );

		// cleanup
		unregister_post_type( 'test_with_front' );
	}

	public function testCptWithCustomRewriteRule(): void {
		add_rewrite_rule( 'custom_rewrite_rule/([^/]+)/?$', 'index.php?post_type=by_uri_cpt&p=$matches[1]', 'top' );
		flush_rewrite_rules( true );

		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'by_uri_cpt',
				'post_title'   => 'Test for testCptWithCustomRewriteRule',
				'post_content' => 'test',
				'post_status'  => 'publish',
			]
		);

		$query = $this->getQuery();

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /custom_rewrite_rule/{post_id}
		 * p   => {post_id}
		 * post_type => by_uri_cpt
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/custom_rewrite_rule/' . $post_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['nodeByUri'] );
		$this->assertSame( 'CustomType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $post_id, $actual['data']['nodeByUri']['databaseId'] );

		// The custom rule doesnt override the actual cpt permalink.
		$this->assertSame( wp_make_link_relative( get_permalink( $post_id ) ), $actual['data']['nodeByUri']['uri'] );
	}

	/**
	 * Test Category URIs
	 */
	public function testCategoryByUri() {
		$category_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Test categoryByUri',
			]
		);

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Category {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		$expected_graphql_type = ucfirst( get_taxonomy( 'category' )->graphql_single_name );

		$uri = wp_make_link_relative( get_category_link( $category_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /category/test-categorybyuri/
		 * category_name => test-categorybyuri,
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $category_id, $actual );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );
	}

	public function testUncategorizedCategoryByUri() {
		$category    = get_term_by( 'slug', 'uncategorized', 'category' );
		$category_id = $category->term_id;

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Category {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		$expected_graphql_type = ucfirst( get_taxonomy( 'category' )->graphql_single_name );

		$uri = wp_make_link_relative( get_category_link( $category_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /category/uncategorized/
		 * category_name => uncategorized
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $category_id, $actual );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );
	}

	/**
	 * Test Tag URIs
	 */
	public function testTagByUri() {
		$tag_id = $this->factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => 'Test tagByUri',
			]
		);

		$query = '
		query GET_NODE_BY_URI( $uri: String! ) {
			nodeByUri( uri: $uri ) {
				__typename
				...on Tag {
					databaseId
				}
				isTermNode
				isContentNode
				uri
			}
		}
		';

		$expected_graphql_type = ucfirst( get_taxonomy( 'post_tag' )->graphql_single_name );

		$uri = wp_make_link_relative( get_term_link( $tag_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /tag/test-tagbyuri/
		 * tag => test-tagbyuri,
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $tag_id, $actual );
		$this->assertFalse( $actual['data']['nodeByUri']['isContentNode'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isTermNode'] );
	}

	/**
	 * Test Post Format URIs
	 */
	public function testPostFormatByUri() {
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => 'Test postFormatByUri',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		set_post_format( $post_id, 'aside' );

		$query = $this->getQuery();

		$uri = wp_make_link_relative( get_post_format_link( 'aside' ) );

		codecept_debug( $uri );

		$term = get_term_by( 'slug', 'post-format-aside', 'post_format' );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /type/aside/
		 * post_format => post-format-aside
		 * post_type => [ post ]
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertValidURIResolution( $uri, 'PostFormat', $term->term_id, $actual );
	}

	/**
	 * Test Custom Tax term URIs
	 */
	public function testDefaultTaxTermByUri() {
		$term    = get_term_by( 'slug', 'default-term', 'by_uri_tax' );
		$term_id = $term->term_id;

		$query = $this->getQuery();

		$expected_graphql_type = ucfirst( get_taxonomy( 'by_uri_tax' )->graphql_single_name );

		$uri = wp_make_link_relative( get_term_link( $term_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /by_uri_tax/default-term/
		 * by_uri_tax => default-term
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $term_id, $actual );
	}

	public function testCustomTaxTermByUri() {
		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'by_uri_tax',
				'name'     => 'Test customTaxTermByUri',
			]
		);

		$query = $this->getQuery();

		$expected_graphql_type = ucfirst( get_taxonomy( 'by_uri_tax' )->graphql_single_name );

		$uri = wp_make_link_relative( get_term_link( $term_id ) );

		/**
		 * NodeResolver::parse_request() will generate the following query vars:
		 * uri => /by_uri_tax/test-customtaxtermbyuri/
		 * by_uri_tax => test-customtaxtermbyuri
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $term_id, $actual );
	}

	public function testCustomTaxTermWithIdenticalSlugs() {
		register_taxonomy(
			'identical_slugs_tax',
			'by_uri_cpt',
			[
				'hierarchical'        => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'identicalSlugType',
				'graphql_plural_name' => 'identicalSlugTypes',
				'public'              => true,
				'rewrite'             => [ 'hierarchical' => true ],
			]
		);

		flush_rewrite_rules( true );

		$category_term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Test identicalSlugs',
			]
		);

		$by_uri_term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'by_uri_tax',
				'name'     => 'Test identicalSlugs',
			]
		);

		$identical_slugs_term_1_id = $this->factory()->term->create(
			[
				'taxonomy' => 'identical_slugs_tax',
				'name'     => 'Test identicalSlugs',
			]
		);

		$_parent_id = $this->factory()->term->create(
			[
				'taxonomy' => 'identical_slugs_tax',
				'name'     => 'Test identicalSlugs Parent',
			]
		);

		$identical_slugs_term_2_child_id = $this->factory()->term->create(
			[
				'taxonomy' => 'identical_slugs_tax',
				'name'     => 'Test identicalSlugs',
				'parent'   => $_parent_id,
			]
		);

		$query = $this->getQuery();

		// Test category
		$uri = wp_make_link_relative( get_term_link( $category_term_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'Category', $category_term_id, $actual );

		// Test by_uri_tax
		$uri = wp_make_link_relative( get_term_link( $by_uri_term_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'CustomTax', $by_uri_term_id, $actual );

		// Test first identical_slugs_tax
		$uri = wp_make_link_relative( get_term_link( $identical_slugs_term_1_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'IdenticalSlugType', $identical_slugs_term_1_id, $actual );

		// Test child identical_slugs_tax
		$uri = wp_make_link_relative( get_term_link( $identical_slugs_term_2_child_id ) );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /identical_slugs_tax/test-identicalslugs-parent/test-identicalslugs-test-identicalslugs-parent/
	 * identical_slugs_tax => test-identicalslugs-parent/test-identicalslugs-test-identicalslugs-parent
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertValidURIResolution( $uri, 'IdenticalSlugType', $identical_slugs_term_2_child_id, $actual );

		// cleanup
		unregister_taxonomy( 'identical_slugs_tax' );
	}

	public function testHierarchicalCustomTaxTerm() {
		register_taxonomy(
			'test_hierarchical',
			'by_uri_cpt',
			[
				'hierarchical'        => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testHierarchicalType',
				'graphql_plural_name' => 'testHierarchicalTypes',
				'public'              => true,
				'rewrite'             => [ 'hierarchical' => true ],
			]
		);

		flush_rewrite_rules( true );

		$parent_id = $this->factory()->term->create(
			[
				'taxonomy' => 'test_hierarchical',
				'name'     => 'Test hierirchical parent',
			]
		);
		$child_id  = $this->factory()->term->create(
			[
				'taxonomy' => 'test_hierarchical',
				'name'     => 'Test hierirchical child',
				'parent'   => $parent_id,
			]
		);

		// Test all nodes return
		$query = '
		{
			testHierarchicalTypes {
				nodes {
					uri
					__typename
					...on TermNode {
						databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$database_ids = wp_list_pluck( $actual['data']['testHierarchicalTypes']['nodes'], 'databaseId' );

		$this->assertContains( $parent_id, $database_ids );
		$this->assertContains( $child_id, $database_ids );

		// Test parent node returns
		$query = $this->getQuery();

		$uri = wp_make_link_relative( get_term_link( $parent_id, 'test_hierarchical' ) );

		codecept_debug( $uri );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'TestHierarchicalType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $parent_id, $actual['data']['nodeByUri']['databaseId'] );

		// Test child node returns
		$uri = wp_make_link_relative( get_term_link( $child_id, 'test_hierarchical' ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::resolve_uri() generates the following query vars:
		 * uri => /test_hierarchical/test-hierirchical-parent/test-hierirchical-child/
		 * test_hierarchical => test-hierirchical-parent/test-hierirchical-child
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'TestHierarchicalType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( $child_id, $actual['data']['nodeByUri']['databaseId'] );

		// cleanup
		unregister_taxonomy( 'test_hierarchical' );
	}

	public function testCustomTaxTermWithFront() {
		// Set the permalink structure to include a base
		$this->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		register_taxonomy(
			'test_tax_with_front',
			'by_uri_cpt',
			[
				'hierarchical'        => false,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'testTaxWithFrontType',
				'graphql_plural_name' => 'testTaxWithFrontTypes',
				'public'              => true,
				'rewrite'             => [
					'slug'       => 'my-custom-term-slug',
					'with_front' => true,
				],
			]
		);
		flush_rewrite_rules();

		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'test_tax_with_front',
				'name'     => 'Test Tax With Front',
			]
		);

		$query = $this->getQuery();

		$expected_graphql_type = ucfirst( get_taxonomy( 'test_tax_with_front' )->graphql_single_name );

		$uri = wp_make_link_relative( get_term_link( $term_id, 'test_tax_with_front' ) );

		// Test the term.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, $expected_graphql_type, $term_id, $actual );

		// Cleanup.
		unregister_taxonomy( 'test_tax_with_front' );
	}

	/**
	 * @throws \Exception
	 */
	public function testAuthorByUri() {
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->user,
			]
		);

		$uri = wp_make_link_relative( get_author_posts_url( $this->user ) );

		codecept_debug( $uri );

		$query = $this->getQuery();

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /author/{user_name}/
		 * author_name => {user_name}
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertValidURIResolution( $uri, 'User', $this->user, $actual );
	}

	/**
	 * Test Date Archive Uris
	 */
	public function testDateYearArchiveByUri() {
		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on ContentType {
						name
					}
					uri
				}
			}
		';

		// Test year archive
		$uri = wp_make_link_relative( get_year_link( gmdate( 'Y' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}/
		 * year => {year}
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `date archives`. See https://github.com/wp-graphql/wp-graphql/issues/2191' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );
	}

	public function testDateMonthArchiveByUri() {
		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on ContentType {
						name
					}
					uri
				}
			}
		';

		// Test month archive
		$uri = wp_make_link_relative( get_month_link( gmdate( 'Y' ), gmdate( 'm' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}/{month}/
		 * year => {year}
		 * monthnum => {month}
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `date archives`. See https://github.com/wp-graphql/wp-graphql/issues/2191' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );
	}

	public function testDateDayArchiveByUri() {
		$query = '
			query GET_NODE_BY_URI( $uri: String! ) {
				nodeByUri( uri: $uri ) {
					__typename
					...on ContentType {
						name
					}
					uri
				}
			}
		';

		// Test day archive
		$uri = wp_make_link_relative( get_day_link( gmdate( 'Y' ), gmdate( 'm' ), gmdate( 'd' ) ) );

		codecept_debug( $uri );

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri => /{year}/{month}/{day}/
		 * year => {year}
		 * monthnum => {month}
		 * day => {day}
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->markTestIncomplete( 'resolve_uri() doesnt check for `date archives`. See https://github.com/wp-graphql/wp-graphql/issues/2191' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertSame( 'post', $actual['data']['nodeByUri']['name'] );
	}

	/**
	 * Tests the Home Page URI
	 */
	public function testHomePageByUri() {

		$title   = 'Home Test' . uniqid();
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);

		$query = '
		{
			nodeByUri(uri: "/") {
				__typename
				uri
				... on Page {
					title
					isPostsPage
					isFrontPage
				}
				... on ContentType {
					name
					isPostsPage
					isFrontPage
				}
			}
		}
		';

		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', 0 );
		update_option( 'show_on_front', 'posts' );

		/**
		 * For _all_ homepage queries, NodeResolver::parse_request() only generates the following query var:
		 * uri => /
		 */
		$actual = $this->graphql( [ 'query' => $query ] );

		// When the page_on_front, page_for_posts and show_on_front are all not set, the `/` uri should return
		// the post ContentType as the homepage node
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['nodeByUri'] );
		$this->assertSame( '/', $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isPostsPage'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );

		// if the "show_on_front" is set to page, but no page is specifically set, the
		// homepage should still be the Post ContentType
		update_option( 'show_on_front', 'page' );
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['nodeByUri'] );
		$this->assertSame( '/', $actual['data']['nodeByUri']['uri'] );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isPostsPage'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );

		// If the "show_on_front" and "page_on_front" value are both set,
		// the node should be the Page that is set
		update_option( 'page_on_front', $post_id );
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertSame( $title, $actual['data']['nodeByUri']['title'] );
		$this->assertSame( 'Page', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isFrontPage'] );
		$this->assertFalse( $actual['data']['nodeByUri']['isPostsPage'] );
	}

	public function testPageQueryWhenPageIsSetToHomePage() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);

		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'page' );

		$query = '
		{
			page( id:"/" idType: URI ) {
				__typename
				databaseId
				isPostsPage
				isFrontPage
				title
				uri
			}
		}
		';

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * post_type => page
		 * archive => ''
		 * nodeType => ContentNode
		 * uri = /
		 */
		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['page'] );
		$this->assertSame( $page_id, $actual['data']['page']['databaseId'] );
		$this->assertTrue( $actual['data']['page']['isFrontPage'] );
		$this->assertSame( '/', $actual['data']['page']['uri'] );

		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'posts' );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( null, $actual['data']['page'] );
	}

	/**
	 * Tests the Posts Archive Page URI
	 */
	public function testPageForPostsByUri() {

		$page_id = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Blog',
			]
		);

		update_option( 'page_for_posts', $page_id );
		update_option( 'show_on_front', 'page' );

		$query = $this->getQuery();

		/**
		 * NodeResolver::parse_request() generates the following query vars:
		 * uri: /blog
		 * page => ''
		 * pagename => 'blog'
		 */
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/blog',
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ContentType', $actual['data']['nodeByUri']['__typename'] );
		$this->assertTrue( $actual['data']['nodeByUri']['isPostsPage'] );

		delete_option( 'page_for_posts' );

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/blog',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Page', $actual['data']['nodeByUri']['__typename'] );
	}

	public function testExternalUriReturnsNull() {
		$query = $this->getQuery();

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => 'https://external-uri.com/path-to-thing',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( null, $actual['data']['nodeByUri'] );
	}

	public function testMediaWithExternalUriReturnsNull() {

		$query = '
		query Media( $uri: ID! ){
			mediaItem(id: $uri, idType: URI) {
				id
				title
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => 'https://icd.wordsinspace.net/wp-content/uploads/2020/10/955000_2-scaled.jpg',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( null, $actual['data']['mediaItem'] );

		$query = '
		query Media( $uri: ID! ){
			mediaItem(id: $uri, idType: SOURCE_URL) {
				id
				title
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => 'https://icd.wordsinspace.net/wp-content/uploads/2020/10/955000_2-scaled.jpg',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( null, $actual['data']['mediaItem'] );
	}

	public function testParseRequestFilterExecutesOnNodeByUriQueries() {

		$value = null;

		// value should be null
		$this->assertNull( $value );

		// value should NOT be instance of Wp class
		$this->assertNotInstanceOf( 'Wp', $value );

		// We hook into parse_request
		// set the value of $value to the value of the $wp argument
		// that comes through the filter
		add_action(
			'parse_request',
			static function ( WP $wp ) use ( &$value ) {
				if ( is_graphql_request() ) {
					$value = $wp;
				}
			}
		);

		$query = $this->getQuery();

		// execute a nodeByUri query
		graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/about',
				],
			]
		);

		codecept_debug( $value );

		// ensure the $value is now an instance of Wp class
		// as set by the filter in the node resolver
		$this->assertNotNull( $value );
		$this->assertInstanceOf( 'Wp', $value );
	}

	public function assertValidURIResolution( string $uri, string $expected_graphql_type, int $expected_database_id, array $actual ): void {
		codecept_debug( 'Validating URI: ' . $uri );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['nodeByUri'], 'The nodeByUri data should not be empty' );
		$this->assertSame( $expected_graphql_type, $actual['data']['nodeByUri']['__typename'], 'The __typename should match the expected type' );
		$this->assertSame( $expected_database_id, $actual['data']['nodeByUri']['databaseId'], 'The databaseId should match the expected ID' );
		$this->assertSame( $uri, $actual['data']['nodeByUri']['uri'], 'The uri should match the expected URI' );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2751
	 */
	public function testNodeByUriWithCustomPermalinkStructure() {

		$this->set_permalink_structure( '/posts/%postname%/' );

		$query = '
		query NodeByUri($uri:String!) {
			nodeByUri( uri: $uri ) {
				__typename
				uri
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/whatever (something non-existing)',
				],
			]
		);

		// The query should succeed
		self::assertQuerySuccessful(
			$actual,
			[
				// the query should return a null value as the uri
				// cannot be found
				$this->expectedField( 'nodeByUri', self::IS_NULL ),
			]
		);
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2751
	 */
	public function testNodeByUriWithCustomPermalinkStructureAndFrontPageSet() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);

		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'page' );

		$this->set_permalink_structure( '/posts/%postname%/' );

		$query = '
		query NodeByUri($uri:String!) {
			nodeByUri( uri: $uri ) {
				__typename
				uri
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/whatever (something non-existing)',
				],
			]
		);

		// The query should succeed
		self::assertQuerySuccessful(
			$actual,
			[
				// the query should return a null value as the uri
				// cannot be found
				$this->expectedField( 'nodeByUri', self::IS_NULL ),
			]
		);

		// cleanup
		update_option( 'page_on_front', 0 );
		update_option( 'show_on_front', 0 );
		wp_delete_post( $page_id, true );
	}

	public function testQueryPostsPageByUriReturnsExpectedUriOnNode() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);

		$page_for_posts = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Page for Posts',
			]
		);

		update_option( 'page_on_front', $page_id );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $page_for_posts );

		$query = '
		query GetPostsPage( $uri: String! ) {
			nodeByUri(uri: $uri) {
				__typename
				uri
			}
		}
		';

		$uri = parse_url( get_permalink( $page_for_posts ), PHP_URL_PATH );

		$variables = [
			'uri' => $uri,
		];

		codecept_debug(
			[
				'variables' => $variables,
			]
		);

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				// the query should return a null value as the uri
				// cannot be found
				$this->expectedField( 'nodeByUri.__typename', 'ContentType' ),
				$this->expectedField( 'nodeByUri.uri', $uri ),
			]
		);
	}

	public function testQueryPostBySlugWhenPermalinksAreSetCustom() {

		$this->set_permalink_structure( '/articles/%postname%/' );

		$slug = 'test-slug';

		$postId = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => 'post-title',
			]
		);
		codecept_debug(
			[
				'slug' => $slug,
				'link' => get_permalink( $postId ),
			]
		);

		$query = '
		query GetPostsBySlug( $id: ID! ) {
			post(id: $id, idType: SLUG) {
				__typename
				slug
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $slug,
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.__typename', 'Post' ),
				$this->expectedField( 'post.slug', $slug ),
			]
		);
	}

	public function testQueryPostsOfMultiplePostTypesBySameSlug() {

		$slug = 'duplicate-slug-test';

		/**
		 * Create an attachment with a post set as it's parent
		 */
		$image_description = 'some description';
		$attachment_id     = self::factory()->post->create(
			[
				'post_type'    => 'attachment',
				'post_parent'  => $this->post,
				'post_content' => $image_description,
				'post_name'    => $slug,
			]
		);

		$meta_data = [
			'width'      => 300,
			'height'     => 300,
			'file'       => 'example.jpg',
			'sizes'      => [
				'thumbnail' => [
					'file'       => 'example-thumbnail.jpg',
					'width'      => 150,
					'height'     => 150,
					'mime-type'  => 'image/jpeg',
					'source_url' => 'example-thumbnail.jpg',
				],
				'full'      => [
					'file'       => 'example-full.jpg',
					'width'      => 1500,
					'height'     => 1500,
					'mime-type'  => 'image/jpeg',
					'source_url' => 'example-full.jpg',
				],
			],
			'image_meta' => [
				'aperture'          => 0,
				'credit'            => 'some photographer',
				'camera'            => 'some camera',
				'caption'           => 'some caption',
				'created_timestamp' => strtotime( $this->current_date ),
				'copyright'         => 'Copyright WPGraphQL',
				'focal_length'      => 0,
				'iso'               => 0,
				'shutter_speed'     => 0,
				'title'             => 'some title',
				'orientation'       => 'some orientation',
				'keywords'          => [
					'keyword1',
					'keyword2',
				],
			],
		];

		update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta_data );

		$post = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_name'   => $slug,
				'post_status' => 'publish',
			]
		);

		$media_item =

		$query = '
		query PostAndPageBySlug($slug: ID!) {
			mediaItem(id: $slug, idType: SLUG) {
				__typename
				slug
			}
			post(id: $slug, idType: SLUG) {
				__typename
				slug
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'slug' => $slug,
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'mediaItem.slug', $slug ),
				$this->expectedField( 'mediaItem.__typename', 'MediaItem' ),
				$this->expectedField( 'post.slug', $slug ),
				$this->expectedField( 'post.__typename', 'Post' ),
			]
		);
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/3240
	 * @return void
	 */
	public function testQueryPostWithCustomPermalinkStructure() {

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_name'   => 'test-post',
			]
		);

		$this->set_permalink_structure( '/news/%postname%/' );

		$query = '
		query GetNodeByUri($uri: String!) {
		  nodeByUri(uri: $uri) {
		    __typename
		    ... on Page {
		      id
		      title
		      slug
		    }
		    ... on Post {
		      id
		      title
		      slug
		    }
		    uri
		  }
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/news/test-post',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'nodeByUri.__typename', 'Post' ),
				$this->expectedField( 'nodeByUri.slug', 'test-post' ),
			]
		);

		// query again with just the slug instead of full uri
		$query2 = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => 'test-post',
				],
			]
		);

		// The query should succeed, but should return null as no post should have been found.
		// WordPress will 404 here as there is no post at `/test` due to the permalink configuration,
		// so we should return null
		$this->assertArrayNotHasKey( 'errors', $query2 );
		$this->assertQuerySuccessful(
			$query2,
			[
				$this->expectedField( 'nodeByUri', self::IS_NULL ),
			]
		);

		wp_delete_post( $post_id, true );
	}
}
