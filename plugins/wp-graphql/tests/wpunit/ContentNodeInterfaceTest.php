<?php

class ContentNodeInterfaceTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		wp_set_current_user( $this->admin );
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function tearDown(): void {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * @param string $structure
	 */
	public function set_permalink_structure( $structure = '' ) {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );
		$wp_rewrite->flush_rules();
	}

	/**
	 * @throws \Exception
	 */
	public function testContentNodeExists() {
		$query  = '
		{
			__type(name: "ContentNode") {
				name
				kind
			}
		}
		';
		$actual = graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'ContentNode', $actual['data']['__type']['name'] );
		$this->assertEquals( 'INTERFACE', $actual['data']['__type']['kind'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryContentNodesOfManyTypes() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page for QueryContentNodesOfManyTypes',
				'post_author' => $this->admin,
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post for QueryContentNodesOfManyTypes',
				'post_author' => $this->admin,
			]
		);

		$query = '
		{
			contentNodes(first:2) {
				nodes {
					__typename
					id
					databaseId
					contentTypeName
					...on NodeWithTitle {
						title
					}
					...on Post {
						postId
					}
					...on Page {
						pageId
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

		$this->assertEquals( 'Post', $actual['data']['contentNodes']['nodes'][0]['__typename'] );
		$this->assertEquals( 'post', $actual['data']['contentNodes']['nodes'][0]['contentTypeName'] );
		$this->assertEquals( $post_id, $actual['data']['contentNodes']['nodes'][0]['databaseId'] );
		$this->assertEquals( $post_id, $actual['data']['contentNodes']['nodes'][0]['postId'] );

		$this->assertEquals( 'Page', $actual['data']['contentNodes']['nodes'][1]['__typename'] );
		$this->assertEquals( 'page', $actual['data']['contentNodes']['nodes'][1]['contentTypeName'] );
		$this->assertEquals( $page_id, $actual['data']['contentNodes']['nodes'][1]['databaseId'] );
		$this->assertEquals( $page_id, $actual['data']['contentNodes']['nodes'][1]['pageId'] );
	}

	/**
	 * @return string
	 */
	public function contentNodeQuery() {
		return '
		query TestContentNode( $postId: ID! $pageId: ID! $postIdType: ContentNodeIdTypeEnum $pageIdType: ContentNodeIdTypeEnum ){
			post: contentNode(id: $postId, idType: $postIdType, contentType: POST) {
				...ContentFields
			}
			page: contentNode(id: $pageId, idType: $pageIdType, contentType: PAGE) {
				...ContentFields
			}
		}

		fragment ContentFields on ContentNode {
			__typename
			id
			...on NodeWithTitle {
					title
				}
			slug
			uri
			... on Post {
				postId
			}
			... on Page {
				pageId
			}
		}
		';
	}

	/**
	 * @throws \Exception
	 */
	public function testContentNodeFieldByDatabaseId() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page for ContentNodeFieldByDatabaseId',
				'post_author' => $this->admin,
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post for testContentNodeFieldByDatabaseId',
				'post_author' => $this->admin,
			]
		);

		/**
		 * Test when IDs are cast as strings
		 */
		$actual = $this->graphql(
			[
				'query'     => $this->contentNodeQuery(),
				'variables' => [
					'postIdType' => 'DATABASE_ID',
					'postId'     => (string) $post_id,
					'pageIdType' => 'DATABASE_ID',
					'pageId'     => (string) $page_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'Post', $actual['data']['post']['__typename'] );
		$this->assertEquals( 'Page', $actual['data']['page']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['post']['postId'] );
		$this->assertEquals( $page_id, $actual['data']['page']['pageId'] );

		/**
		 * Test when IDs are cast as integers
		 */
		$actual = $this->graphql(
			[
				'query'     => $this->contentNodeQuery(),
				'variables' => [
					'postIdType' => 'DATABASE_ID',
					'postId'     => (int) $post_id,
					'pageIdType' => 'DATABASE_ID',
					'pageId'     => (int) $page_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'Post', $actual['data']['post']['__typename'] );
		$this->assertEquals( 'Page', $actual['data']['page']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['post']['postId'] );
		$this->assertEquals( $page_id, $actual['data']['page']['pageId'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testContentNodeFieldByUri() {
		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page for ContentNodeFieldByUri',
				'post_author' => $this->admin,
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post for ContentNodeFieldByUri',
				'post_author' => $this->admin,
			]
		);

		$variables = [
			'postIdType' => 'URI',
			'postId'     => parse_url( get_permalink( $post_id ) )['path'],
			'pageIdType' => 'URI',
			'pageId'     => parse_url( get_permalink( $page_id ) )['path'],
		];

		codecept_debug( $variables );

		$actual = $this->graphql(
			[
				'query'     => $this->contentNodeQuery(),
				'variables' => $variables,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'Post', $actual['data']['post']['__typename'] );
		$this->assertEquals( 'Page', $actual['data']['page']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['post']['postId'] );
		$this->assertEquals( $page_id, $actual['data']['page']['pageId'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testContentNodeFieldById() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page for ContentNodeFieldById',
				'post_author' => $this->admin,
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post for ContentNodeFieldById',
				'post_author' => $this->admin,
			]
		);

		$variables = [
			'postIdType' => 'ID',
			'postId'     => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
			'pageIdType' => 'ID',
			'pageId'     => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
		];

		codecept_debug( $variables );

		$actual = $this->graphql(
			[
				'query'     => $this->contentNodeQuery(),
				'variables' => $variables,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'Post', $actual['data']['post']['__typename'] );
		$this->assertEquals( 'Page', $actual['data']['page']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['post']['postId'] );
		$this->assertEquals( $page_id, $actual['data']['page']['pageId'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testContentNodeFieldByQueryArgUri() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test Page for ContentNodeFieldByQueryArgUri ',
				'post_author' => $this->admin,
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post for ContentNodeFieldByQueryArgUri',
				'post_author' => $this->admin,
			]
		);

		$variables = [
			'postIdType' => 'ID',
			'postId'     => \GraphQLRelay\Relay::toGlobalId( 'post', $post_id ),
			'pageIdType' => 'ID',
			'pageId'     => \GraphQLRelay\Relay::toGlobalId( 'post', $page_id ),
		];

		codecept_debug( $variables );

		$actual = $this->graphql(
			[
				'query'     => $this->contentNodeQuery(),
				'variables' => $variables,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'Post', $actual['data']['post']['__typename'] );
		$this->assertEquals( 'Page', $actual['data']['page']['__typename'] );
		$this->assertEquals( $post_id, $actual['data']['post']['postId'] );
		$this->assertEquals( $page_id, $actual['data']['page']['pageId'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testContentNodeFieldBySlug() {

		$post_types = [
			'by_slug_book' => [
				'public'              => false,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'book',
				'graphql_plural_name' => 'books',
				'label'               => 'By Slug Books',
			],
			'by_slug_test' => [
				'public'              => false,
				'publicly_queryable'  => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'test',
				'graphql_plural_name' => 'tests',
				'label'               => 'By Slug Tests',
			],
			'by_slug_cat'  => [
				'public'              => false,
				'publicly_queryable'  => false,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'cat',
				'graphql_plural_name' => 'cats',
				'label'               => 'By Slug Cats',
			],
		];

		foreach ( $post_types as $post_type => $args ) {
			register_post_type( $post_type, $args );
		}

		$post_id_book = $this->factory()->post->create(
			[
				'post_type'   => 'by_slug_book',
				'post_status' => 'publish',
				'post_title'  => 'Test Book for ContentNodeFieldBySlug',
				'post_author' => $this->admin,
			]
		);

		$post_id_test = $this->factory()->post->create(
			[
				'post_type'   => 'by_slug_test',
				'post_status' => 'publish',
				'post_title'  => 'Test Test for ContentNodeFieldBySlug',
				'post_author' => $this->admin,
			]
		);

		$post_id_cat = $this->factory()->post->create(
			[
				'post_type'   => 'by_slug_cat',
				'post_status' => 'publish',
				'post_title'  => 'Test Cat for ContentNodeFieldBySlug',
				'post_author' => $this->admin,
			]
		);

		$query = '
			{
				book(id: "test-book-for-contentnodefieldbyslug", idType: SLUG) {
					__typename
					bookId
				},
				test(id: "test-test-for-contentnodefieldbyslug", idType: SLUG) {
					__typename
					testId
				},
				cat(id: "test-cat-for-contentnodefieldbyslug", idType: SLUG) {
					__typename
					catId
				}
			}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'Book', $actual['data']['book']['__typename'] );
		$this->assertEquals( $post_id_book, $actual['data']['book']['bookId'] );
		$this->assertEquals( 'Test', $actual['data']['test']['__typename'] );
		$this->assertEquals( $post_id_test, $actual['data']['test']['testId'] );
		$this->assertEquals( 'Cat', $actual['data']['cat']['__typename'] );
		$this->assertEquals( $post_id_cat, $actual['data']['cat']['catId'] );

		// Set the user to a public user
		wp_set_current_user( 0 );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		// The book and cat should be empty, because they're not publicly_queryable post types
		// and this test is from a public user
		$this->assertEmpty( $actual['data']['book'] );
		$this->assertEmpty( $actual['data']['cat'] );

		// The test should return data, because it's a publicly_queryable post type
		$this->assertEquals( 'Test', $actual['data']['test']['__typename'] );
		$this->assertEquals( $post_id_test, $actual['data']['test']['testId'] );

		foreach ( $post_types as $post_type => $args ) {
			unregister_post_type( $post_type, $args );
		}
	}
}
