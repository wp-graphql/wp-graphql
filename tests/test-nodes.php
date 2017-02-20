<?php
/**
 * WPGraphQL Test Node Queries
 *
 * This tests that node queries can properly resolve with the correct __typename, and that each
 * type also has an {type}Id field which outputs the ID unique to the WordPress DB (or other unique identifier for
 * objects that don't have a specific "ID" in the WP database, such as Themes and Plugins)
 *
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_Node_Queries extends WP_UnitTestCase {

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
		$this->admin = $this->factory->user->create( [
			'role' => 'admin',
		] );
	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testPageNodeQuery
	 * @since 0.0.5
	 */
	public function testPageNodeQuery() {

		/**
		 * Set up the $args
		 */
		$args = array(
			'post_status'  => 'publish',
			'post_content' => 'Test page content',
			'post_title'   => 'Test Page Title',
			'post_type'    => 'page',
			'post_author'  => $this->admin,
		);

		/**
		 * Create the page
		 */
		$page_id = $this->factory->post->create( $args );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $page_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query { 
			node(id: \"{$global_id}\") { 
				__typename 
				...on page {
					pageId
				}
			} 
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'page',
					'pageId' => $page_id,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPostNodeQuery
	 * @since 0.0.5
	 */
	public function testPostNodeQuery() {

		$args = array(
			'post_status'  => 'publish',
			'post_content' => 'Test post content',
			'post_title'   => 'Test post Title',
			'post_type'    => 'post',
			'post_author'  => $this->admin,
		);

		$post_id = $this->factory->post->create( $args );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		$query = "
		query { 
			node(id: \"{$global_id}\") { 
				__typename
				... on post {
					postId
				}
			} 
		}";
		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'post',
					'postId' => $post_id,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testAttachmentNodeQuery
	 * @since 0.0.5
	 */
	public function testAttachmentNodeQuery() {

		$args = array(
			'post_status'  => 'inherit',
			'post_content' => 'Test attachment content',
			'post_title'   => 'Test attachment Title',
			'post_type'    => 'attachment',
			'post_author'  => $this->admin,
		);

		$attachment_id = $this->factory->post->create( $args );
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'attachment', $attachment_id );
		$query = "
		query { 
			node(id: \"{$global_id}\") { 
				__typename
				...on mediaItem {
					mediaItemId
				}
			} 
		}";
		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'mediaItem',
					'mediaItemId' => $attachment_id,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testPluginNodeQuery
	 * @since 0.0.5
	 */
	public function testPluginNodeQuery() {

		$plugin_name = 'Hello Dolly';
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'plugin', $plugin_name );
		$query = "
		query { 
			node(id: \"{$global_id}\") { 
				__typename
				... on plugin {
					name
				}
			} 
		}";
		
		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'plugin',
					'name' => $plugin_name,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testThemeNodeQuery
	 * @since 0.0.5
	 */
	public function testThemeNodeQuery() {

		$theme_slug = 'twentyseventeen';
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'theme', $theme_slug );
		$query = "
		query { 
			node(id: \"{$global_id}\") { 
				__typename 
				...on theme{ 
					slug 
				} 
			} 
		}";
		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'theme',
					'slug' => $theme_slug,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testUserNodeQuery
	 * @since 0.0.5
	 */
	public function testUserNodeQuery() {

		$user_args = array(
			'role'       => 'editor',
			'user_email' => 'graphqliscool@wpgraphql.com',
		);

		$user_id = $this->factory->user->create( $user_args );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		$query = "
		query { 
			node(id: \"{$global_id}\") { 
				__typename 
				...on user{ 
					userId 
				} 
			} 
		}
		";
		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'user',
					'userId' => $user_id,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testCommentNodeQuery
	 * @since 0.0.5
	 */
	public function testCommentNodeQuery() {

		$user_args = array(
			'role'       => 'editor',
			'user_email' => 'graphqliscool@wpgraphql.com',
		);

		$user_id = $this->factory->user->create( $user_args );

		$comment_args = array(
			'user_id'         => $user_id,
			'comment_content' => 'GraphQL is really awesome, dude!',
		);
		$comment_id = $this->factory->comment->create( $comment_args );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );
		$query = "
		query { 
			node(id: \"{$global_id}\") {
				__typename 
				...on comment{ 
					commentId 
				} 
			} 
		}
		";
		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'node' => [
					'__typename' => 'comment',
					'commentId' => $comment_id,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}
}
