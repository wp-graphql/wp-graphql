<?php

use GraphQLRelay\Relay;

class PostObjectConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;
	public $subscriber;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->subscriber       = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$this->created_post_ids = $this->create_posts();

		$this->app_context = new \WPGraphQL\AppContext();

	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'   => $this->admin,
			'post_content'  => 'Test page content',
			'post_excerpt'  => 'Test excerpt',
			'post_status'   => 'publish',
			'post_title'    => 'Test Post for PostObjectConnectionQueriesTest',
			'post_type'     => 'post',
			'post_date'     => $this->current_date,
			'has_password'  => false,
			'post_password' => null,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 *
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;

	}

	/**
	 * Creates several posts (with different timestamps) for use in cursor query tests
	 *
	 * @param   int $count Number of posts to create.
	 *
	 * @return array
	 */
	public function create_posts( $count = 6 ) {

		// Create posts
		$created_posts = [];
		for ( $i = 1; $i <= $count; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject(
				[
					'post_type'   => 'post',
					'post_date'   => $date,
					'post_status' => 'publish',
					'post_title'  => 'Test post for PostObjectConnectionQueriesTest ' . $i,
				]
			);
		}

		return $created_posts;

	}

	public function getQuery() {
		return '
		query postsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToPostConnectionWhereArgs ){
			posts( first:$first last:$last after:$after before:$before where:$where ) {
				pageInfo {
					hasNextPage
					hasPreviousPage
					startCursor
					endCursor
				}
				edges {
					cursor
					node {
						id
						databaseId
						title
						date
						content
						excerpt
					}
				}
				nodes {
					id
					databaseId
				}
			}
		}
		';
	}

	private function getReturnField( $data, $post, $field = '' ) {

		$data = ( isset( $data['data']['posts']['edges'][ $post ]['node'] ) ) ? $data['data']['posts']['edges'][ $post ]['node'] : null;

		if ( empty( $field ) ) {
			return $data;
		} elseif ( ! empty( $data ) ) {
			$data = $data[ $field ];
		}

		return $data;

	}

	public function testMaxQueryAmount() {
		// Create some additional posts to test a large query.
		$this->create_posts( 150 );

		$query = $this->getQuery();

		$variables = [
			'first' => 150,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertNotEmpty( $actual );

		/**
		 * The max that can be queried by default is 100 items
		 */
		$this->assertCount( 100, $actual['data']['posts']['edges'] );
		$this->assertTrue( $actual['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the filter to make sure it's capping the results properly
		 */
		add_filter(
			'graphql_connection_max_query_amount',
			function () {
				return 20;
			}
		);

		$variables = [
			'first' => 150,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		add_filter(
			'graphql_connection_max_query_amount',
			function () {
				return 100;
			}
		);

		$this->assertCount( 20, $actual['data']['posts']['edges'] );
		$this->assertTrue( $actual['data']['posts']['pageInfo']['hasNextPage'] );
	}

	/**
	 * Test to assert that the global post object is being set correctly
	 */
	public function testPostExcerptsAreDifferent() {

		$post_1_args = [
			'post_content' => 'Post content 1',
			'post_excerpt' => '',
		];

		$post_2_args = [
			'post_content' => 'Post content 2',
			'post_excerpt' => '',
		];

		$post_1_id = $this->createPostObject( $post_1_args );
		$post_2_id = $this->createPostObject( $post_2_args );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'in' => [ $post_1_id, $post_2_id ]
			]
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertNotEmpty( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$edges = $actual['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		$this->assertNotEquals( $edges[0]['node']['excerpt'], $edges[1]['node']['excerpt'] );
		$this->assertNotEquals( $edges[0]['node']['content'], $edges[1]['node']['content'] );

	}

	public function testPrivatePostsWithoutProperCaps() {

		$private_post = $this->createPostObject(
			[
				'post_status' => 'private',
			]
		);
		$public_post  = $this->createPostObject(
			[
				'post_status' => 'publish',
			]
		);

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'in' => [ $private_post, $public_post ],
				'stati' => [ 'PUBLISH', 'PRIVATE' ],
			]
		];

		wp_set_current_user( $this->subscriber );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertCount( 1, $actual['data']['posts']['edges'] );
		$this->assertNotEmpty( $this->getReturnField( $actual, 0, 'id' ) );
		$this->assertEmpty( $this->getReturnField( $actual, 1 ) );
	}

	public function testPrivatePostsWithProperCaps() {

		$post_args = [
			'post_title'  => 'Private post WITH caps for PostObjectConnectionQueriesTest',
			'post_status' => 'private',
			'post_author' => $this->subscriber,
		];

		$post_id = $this->createPostObject( $post_args );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'in' => [ $post_id ],
				'stati' => [ 'PUBLISH', 'PRIVATE' ],
			]
		];

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $post_args['post_title'], $this->getReturnField( $actual, 0, 'title' ) );

		// Test with single status
		$variables = [
			'where' => [
				'status' => 'PRIVATE',
			]
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['posts']['nodes'] );
		$this->assertEquals( $post_id, $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	public function testPrivatePostsForCurrentUser() {

		$post_args = [
			'post_title'  => 'Private post WITH caps',
			'post_status' => 'private',
			'post_author' => $this->subscriber,
		];

		$post_id = $this->createPostObject( $post_args );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'in' => [ $post_id ],
				'stati' => [ 'PUBLISH', 'PRIVATE' ],
			]
		];

		wp_set_current_user( $this->subscriber );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Since we're querying for a private post, we want to make sure a subscriber, even if they
		 * created the post, cannot access it.
		 *
		 * NOTE: Core handles this a bit different than the REST API.
		 *
		 * With core, a user can create a "private" post as an editor or admin, then the user can be
		 * demoted to a subscriber, and if that user is logged in, the subscriber can still visit
		 * the post and see the content on the front-end, but cannot visit the post in the back-end.
		 *
		 * The REST API however prevents subscribers (or non authenticated users) from querying
		 * posts with a "private" status at all.
		 *
		 * We're going in the direction of the REST API here. Where certain statuses can only be
		 * queried by users with certain capabilities.
		 */
		$this->assertEmpty( $actual['data']['posts']['edges'] );
		$this->assertEmpty( $actual['data']['posts']['nodes'] );

	}

	/**
	 * @dataProvider dataProviderUserVariance
	 */
	public function testRevisionWithoutProperCaps( $role, $show_revisions ) {

		$parent_post = $this->createPostObject( [] );
		$revision    = $this->createPostObject(
			[
				'post_type'   => 'revision',
				'post_parent' => $parent_post,
				'post_status' => 'inherit',
			]
		);

		$query = "
		{
			posts( where:{in:[\"{$parent_post}\"]}){
				edges{
					node{
						databaseId
						id
						title
						content
						revisions{
							edges {
								node{
									id
									databaseId
									title
									content
								}
							}
						}
					}
				}
			}
		}
		";

		wp_set_current_user( $this->{$role} );
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		$this->assertNotEmpty( $actual['data']['posts']['edges'] );

		if ( true === $show_revisions ) {
			$this->assertEquals( $revision, $actual['data']['posts']['edges'][0]['node']['revisions']['edges'][0]['node']['databaseId'] );
		} else {
			$this->assertEmpty( $actual['data']['posts']['edges'][0]['node']['revisions']['edges'] );
		}

	}

	/**
	 * @dataProvider dataProviderUserVariance
	 */
	public function testDraftPosts( $role, $show_draft ) {

		$public_post = $this->createPostObject( [] );
		$draft_args  = [
			'post_title'   => 'Draft Title for DraftPosts',
			'post_content' => 'Draft Post Content Here',
			'post_status'  => 'draft',
		];
		$draft_post  = $this->createPostObject( $draft_args );

		wp_set_current_user( $this->{$role} );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'in'    => [ $public_post, $draft_post ],
				'stati' => [ 'PUBLISH', 'DRAFT' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		if ( 'admin' === $role ) {

			$this->assertNotEmpty( $actual['data']['posts']['edges'] );

			/**
			 * The admin should have access to 2 posts, one public and one draft
			 */
			$this->assertCount( 2, $actual['data']['posts']['edges'] );
			$this->assertNotNull( $this->getReturnField( $actual, 1, 'id' ) );
			$content_field = $this->getReturnField( $actual, 1, 'content' );
			$excerpt_field = $this->getReturnField( $actual, 1, 'excerpt' );

			if ( true === $show_draft ) {
				$this->assertNotNull( $content_field );
				$this->assertNotNull( $excerpt_field );
			} else {
				$this->assertNull( $content_field );
				$this->assertNull( $excerpt_field );
			}
		} elseif ( 'subscriber' === $role ) {

			/**
			 * The subscriber should only have access to 1 post, the public one.
			 */
			$this->assertNotEmpty( $actual['data']['posts']['edges'] );
			$this->assertCount( 1, $actual['data']['posts']['edges'] );
		}

	}

	/**
	 * @dataProvider dataProviderUserVariance
	 */
	public function testTrashPosts( $role, $show_trash ) {

		$public_post = $this->createPostObject( [] );
		$draft_args  = [
			'post_title'   => 'Trash Title TrashPosts',
			'post_content' => 'Trash Post Content Here',
			'post_status'  => 'trash',
		];
		$draft_post  = $this->createPostObject( $draft_args );

		wp_set_current_user( $this->{$role} );

		$query = $this->getQuery();

		$variables = [
			'where' => [
				'in'    => [ $public_post, $draft_post ],
				'stati' => [ 'PUBLISH', 'TRASH' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		if ( 'admin' === $role ) {
			/**
			 * The admin should be able to see 2 posts, the public post and the trashed post
			 */
			$this->assertNotEmpty( $actual['data']['posts']['edges'] );
			$this->assertCount( 2, $actual['data']['posts']['edges'] );
			$this->assertNotNull( $this->getReturnField( $actual, 1, 'id' ) );
			$content_field = $this->getReturnField( $actual, 1, 'content' );
			$excerpt_field = $this->getReturnField( $actual, 1, 'excerpt' );

			if ( true === $show_trash ) {
				$this->assertNotNull( $content_field );
				$this->assertNotNull( $excerpt_field );
			} else {
				$this->assertNull( $content_field );
				$this->assertNull( $excerpt_field );
			}
		} elseif ( 'subscriber' === $role ) {
			/**
			 * The subscriber should only be able to see 1 post, the public one, not the trashed post.
			 */
			$this->assertNotEmpty( $actual['data']['posts']['edges'] );
			$this->assertCount( 1, $actual['data']['posts']['edges'] );
		}

	}

	public function dataProviderUserVariance() {
		return [
			[
				'subscriber',
				false,
			],
			[
				'admin',
				true,
			],
		];
	}

	/**
	 * @throws Exception
	 */
	public function testPrivatePostsNotReturnedToPublicUserInConnection() {

		$public_post_id = $this->factory()->post->create( [
			'post_type'   => 'Post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for PrivatePostsNotReturnedToPublicUserInConnection',
		] );

		$private_post_id = $this->factory()->post->create( [
			'post_type'   => 'Post',
			'post_status' => 'publish',
			'post_title'  => 'Private Post for PrivatePostsNotReturnedToPublicUserInConnection',
		] );

		update_post_meta( $private_post_id, '_private_key', true );

		$query = '
		{
			posts {
				nodes {
					id
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$nodes = $actual['data']['posts']['nodes'];

		$ids = [];
		foreach ( $nodes as $node ) {
			$ids[ $node['databaseId'] ] = $node;
		}

		$this->assertArrayHasKey( $private_post_id, $ids );

		/**
		 * Filter posts with a certain meta key to be private. These posts
		 * should NOT be returned as nodes at all. They should be stripped before
		 * nodes array is returned.
		 */
		add_filter( 'graphql_data_is_private', function ( $is_private, $model_name, $data, $visibility, $owner, $current_user ) {
			if ( 'PostObject' === $model_name ) {
				$is_private_meta = get_post_meta( $data->ID, '_private_key' );
				if ( isset( $is_private_meta ) && true === (bool) $is_private_meta ) {
					$is_private = true;
				}
			}
			return $is_private;
		}, 10, 6 );

		$actual = graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );

		codecept_debug( $actual );

		$nodes = $actual['data']['posts']['nodes'];

		$ids = [];
		foreach ( $nodes as $node ) {
			$ids[ $node['databaseId'] ] = $node;
		}

		$this->assertArrayNotHasKey( $private_post_id, $ids );

	}

	public function testSuppressFiltersThrowsException() {

		$this->clearSchema();

		add_filter( 'graphql_post_object_connection_query_args', function ( $args ) {
			$args['suppress_filters'] = true;
			return $args;
		} );

		$actual = graphql([
			'query' => '
			{
				posts {
					nodes {
						id
						title
					}
				}
			}
			',
		]);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1477
	 */
	public function testPostInArgumentWorksWithCursors() {

		$post_1 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for PostInArgumentWorksWithCursors',
		] );

		$post_2 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for PostInArgumentWorksWithCursors',
		] );

		$post_3 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for PostInArgumentWorksWithCursors',
		] );

		$post_4 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for PostInArgumentWorksWithCursors',
		] );

		$post_ids = [ $post_3, $post_2, $post_4, $post_1 ];

		$query = '
		query GetPostsByIds($post_ids: [ID] $first: Int, $after: String, $last: Int, $before: String) {
			posts(where: {in: $post_ids} first: $first, after: $after, last: $last, before: $before) {
				edges {
					cursor
					node {
						databaseId
					}
				}
			}
		}
		';

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'post_ids' => $post_ids,
				'after'    => null,
			],
		] );

		$actual_ids = [];

		codecept_debug( $post_ids );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( $post_ids, $actual_ids );

		$cursor = $actual['data']['posts']['edges'][1]['cursor'];

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'post_ids' => $post_ids,
				'after'    => $cursor,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_4, $post_1 ], $actual_ids );

		$cursor = $actual['data']['posts']['edges'][0]['cursor'];

		codecept_debug( $cursor );
		codecept_debug( base64_decode( $cursor ) );
		codecept_debug( 'line 932...' );

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'post_ids' => $post_ids,
				'before'   => $cursor,
				'last'     => 1,
			],
		]);

		codecept_debug( [
			'variables' => [
				'post_ids' => $post_ids,
				'before'   => $cursor,
				'last'     => 1,
			],
		]);
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_ids[1] ], $actual_ids );

	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1477
	 */
	public function testCustomPostConnectionWithSetIdsWorksWithCursors() {

		$post_1 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for CustomPostConnectionWithSetIdsWorksWithCursors',
		] );

		$post_2 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for CustomPostConnectionWithSetIdsWorksWithCursors',
		] );

		$post_3 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for CustomPostConnectionWithSetIdsWorksWithCursors',
		] );

		$post_4 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post for CustomPostConnectionWithSetIdsWorksWithCursors',
		] );

		$post_ids = [ $post_3, $post_2, $post_4, $post_1 ];

		register_graphql_connection([
			'connectionTypeName' => 'OrderbyDebug',
			'description'        => __( 'debugging', 'wp-graphql' ),
			'fromType'           => 'RootQuery',
			'toType'             => 'MediaItem',
			'fromFieldName'      => 'postOrderbyDebug',
			'resolve'            => function ( $root, $args, $context, $info ) use ( $post_ids ) {
				$args['where']['in'] = $post_ids;
				$resolver            = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $root, $args, $context, $info, 'post' );
				return $resolver->get_connection();
			},
		]);

		$query = '
		query GetPostsWithSpecificIdsInResolver($after:String $before:String) {
			posts: postOrderbyDebug(after:$after before:$before) {
				edges {
					cursor
					node {
						databaseId
					}
				}
			}
		}
		';

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'after'  => null,
				'before' => null,
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( $post_ids, $actual_ids );

		$cursor_from_first_query = $actual['data']['posts']['edges'][1]['cursor'];

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'after' => $cursor_from_first_query,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_ids[2], $post_ids[3] ], $actual_ids );

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'before' => $cursor_from_first_query,
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_ids[0] ], $actual_ids );

	}

	/**
	 * Test the scenario where a post is assigned to an author
	 * who is not a user on the site. This could happen for instance,
	 * if the user was deleted, but their posts were never trashed
	 * or assigned to another user.
	 */
	public function testQueryPostsWithOrphanedAuthorDoesntThrowErrors() {
		global $wpdb;

		$highest_user_id     = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY ID DESC limit 0,1" );
		$nonexistent_user_id = $highest_user_id + 1;

		// Create a new post assigned to a nonexistent user ID.
		$post_id = wp_insert_post( [
			'post_title'   => 'Post assigned to a non-existent user',
			'post_content' => 'Post assigned to a non-existent user',
			'post_status'  => 'publish',
			'post_author'  => $nonexistent_user_id,
		] );

		$query = '
		{
			posts(first: 5) {
				nodes {
					databaseId
					author {
						node {
						userId
						name
						}
					}
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertTrue( $post_id && ! is_wp_error( $post_id ) );
		$this->assertArrayNotHasKey( 'errors', $actual );

		// Verify that the ID of the first post matches the one we just created.
		$this->assertEquals( $post_id, $actual['data']['posts']['nodes'][0]['databaseId'] );

		// Verify that the 'author' field is set to null, since the user ID is invalid.
		$this->assertEquals( null, $actual['data']['posts']['nodes'][0]['author'] );

		wp_delete_post( $post_id, true );

	}

	/**
	 * @throws Exception
	 */
	public function testUriFieldAvailableForPublicQueries() {

		/**
		 * Create a password protected post
		 * so that we can query for it and make sure the link and uri fields are exposed
		 * to public requests.
		 *
		 * @see: https://github.com/wp-graphql/wp-graphql/issues/1338
		 */
		$post_id = $this->factory()->post->create([
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_password' => 'test',
			'post_title'    => 'Post with password',
			'post_content'  => 'Protected content',
			'post_author'   => $this->admin,
		]);

		$query = '
		query {
			posts(first: 1, where: {status: PUBLISH}) {
				nodes {
					databaseId
					uri
					link
				}
			}
		}
		';

		$actual = $this->graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post_id, $actual['data']['posts']['nodes'][0]['databaseId'] );
		$this->assertNotEmpty( $post_id, $actual['data']['posts']['nodes'][0]['uri'], 'Ensure the uri is not empty for public requests' );
		$this->assertNotEmpty( $post_id, $actual['data']['posts']['nodes'][0]['link'], 'Ensure the link field is not empty for public requests' );

	}

	public function testQueryPasswordProtectedPost() {

		$title   = 'Test Title for QueryPasswordProtectedPost' . uniqid();
		$content = 'Test Content for QueryPasswordProtectedPost' . uniqid();

		$this->factory()->post->create([
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_password' => 'publish',
			'post_content'  => $content,
			'post_title'    => $title,
		]);

		$query = '
		{
			posts {
				nodes {
					id
					title
					content
				}
			}
		}
		';

		wp_set_current_user( 0 );

		$actual = graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['posts']['nodes'][0]['content'] );
		// The content should be null for public users because no password was entered
		$this->assertSame( $title, $actual['data']['posts']['nodes'][0]['title'] );

		wp_set_current_user( $this->admin );

		$actual = graphql([
			'query' => $query,
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		// The content should be public for an admin
		$this->assertSame( apply_filters( 'the_content', $content ), $actual['data']['posts']['nodes'][0]['content'] );
		$this->assertSame( $title, $actual['data']['posts']['nodes'][0]['title'] );
	}

	public function testIsStickyFieldOnPost() {

		$sticky_post_id = $this->factory()->post->create([
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Sticky Post',
			'post_content' => 'Sticky post content',
			'post_author'  => $this->admin,
		]);

		$nonsticky_post_id = $this->factory()->post->create([
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Non-sticky Post',
			'post_content' => 'Non-sticky post content',
			'post_author'  => $this->admin,
		]);

		update_option( 'sticky_posts', [ $sticky_post_id ] );

		$query = '
		query testStickyPost($ids: [ID]) {
			posts(first: 2, where: { in: $ids }) {
				nodes {
					databaseId
					uri
					link
					isSticky
				}
			}
		}
		';

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => [
				'ids' => [
					$sticky_post_id,
					$nonsticky_post_id,
				],
			],
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['posts']['nodes'][0]['isSticky'] );
		$this->assertFalse( $actual['data']['posts']['nodes'][1]['isSticky'] );
	}

	public function testWhereArgs() {
		$query = $this->getQuery();

		// test id
		$variables = [
			'where' => [
				'id' => $this->created_post_ids[1],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test in with global + db id
		$global_id = Relay::toGlobalId( 'post', $this->created_post_ids[1] );

		$variables = [
			'where' => [
				'in' => [ $global_id, $this->created_post_ids[2] ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);

		// test notIn with global + db id
		$variables = [
			'where' => [
				'notIn' => [ $global_id, $this->created_post_ids[2] ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(4, $actual['data']['posts']['nodes']);

		// test name
		$post_one_name = get_post_field( 'post_name', $this->created_post_ids[1] );

		$variables = [
			'where' => [
				'name' => $post_one_name,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test nameIn
		$post_two_name = get_post_field( 'post_name', $this->created_post_ids[2] );

		$variables = [
			'where' => [
				'nameIn' => [ $post_one_name, $post_two_name ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);

		// test title
		$title = get_post_field( 'post_title', $this->created_post_ids[1] );

		$variables = [
			'where' => [
				'title' => $title,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	public function testAuthorWhereArgs() {
		$author_one_id = $this->factory()->user->create( [
			'role' => 'author',
			'user_nicename' => 'author-one',
		] );
		$author_two_id = $this->factory()->user->create( [
			'role' => 'author',
			'user_nicename' => 'author-two',
		] );

		$post_one_id = $this->factory()->post->create( [
			'post_author' => $author_one_id,
			'post_status' => 'publish',
		] );
		$post_two_id = $this->factory()->post->create( [
			'post_author' => $author_two_id,
			'post_status' => 'publish',
		] );

		$query = $this->getQuery();

		// test author
		$variables = [
			'where' => [
				'author' => $author_one_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $post_one_id, $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test authorName
		$variables = [
			'where' => [
				'authorName' => 'author-one',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $post_one_id, $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test authorIn with global + db id
		$author_one_global_id = Relay::toGlobalId( 'user', $author_one_id );

		$variables = [
			'where' => [
				'authorIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);

		// test authorNotIn with global + db id
		$variables = [
			'where' => [
				'authorNotIn' => [ $author_one_global_id, $author_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(6, $actual['data']['posts']['nodes']);
	}

	public function testCategoryWhereArgs() {
		$term_one_id = $this->factory()->term->create( [
			'taxonomy' => 'category',
			'name' => 'term-one',
		] );
		$term_two_id = $this->factory()->term->create( [
			'taxonomy' => 'category',
			'name' => 'term-two',
		] );

		wp_set_object_terms( $this->created_post_ids[1], [ $term_one_id ], 'category' );
		wp_set_object_terms( $this->created_post_ids[2], [ $term_two_id ], 'category' );

		$query = $this->getQuery();

		// test categoryId
		$variables = [
			'where' => [
				'categoryId' => $term_one_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test categoryName
		$variables = [
			'where' => [
				'categoryName' => 'term-one',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test categoryIn with global + db id
		$term_one_global_id = Relay::toGlobalId( 'term', $term_one_id );

		$variables = [
			'where' => [
				'categoryIn' => [ $term_one_global_id, $term_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);

		// test categoryNotIn with global + db id
		$variables = [
			'where' => [
				'categoryNotIn' => [ $term_one_global_id, $term_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(4, $actual['data']['posts']['nodes']);
	}

	public function testContentTypesWhereArgs() {
		$page_id = $this->factory()->post->create( [
			'post_type' => 'page',
			'post_status' => 'publish',
		] );

		$query ='
		query contentNodesQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToContentNodeConnectionWhereArgs ){
			contentNodes( first:$first last:$last after:$after before:$before where:$where ) {
				nodes {
					id
					databaseId
				}
			}
		}
		';

		// test contentTypes
		$variables = [
			'where' => [
				'contentTypes' => [ 'PAGE' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['contentNodes']['nodes']);
		$this->assertEquals( $page_id, $actual['data']['contentNodes']['nodes'][0]['databaseId'] );

		// test contentTypes with post + page
		$variables = [
			'where' => [
				'contentTypes' => [ 'POST', 'PAGE' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(7, $actual['data']['contentNodes']['nodes']);
	}

	public function testParentWhereArgs() {
		$query = $this->getQuery();

		$parent_one_id = $this->created_post_ids[1];
		$parent_two_id = $this->created_post_ids[2];

		$child_one_id = $this->factory()->post->create([
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Child Post',
			'post_content' => 'Child post content',
			'post_author'  => $this->admin,
			'post_parent'  => $parent_one_id,
		]);

		$child_two_id = $this->factory()->post->create([
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Child Post',
			'post_content' => 'Child post content',
			'post_author'  => $this->admin,
			'post_parent'  => $parent_two_id,
		]);

		// test Parent with for top-level posts
		$variables = [
			'where' => [
				'parent' => 0,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(6, $actual['data']['posts']['nodes']);

		// test parent with database Id
		$variables = [
			'where' => [
				'parent' => $parent_one_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $child_one_id, $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test parent with global Id
		$parent_one_global_id = Relay::toGlobalId( 'post', $parent_one_id );

		$variables = [
			'where' => [
				'parent' => $parent_one_global_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $child_one_id, $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test parentIn with global + db ID
		$variables = [
			'where' => [
				'parentIn' => [ $parent_one_global_id, $parent_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);

		// test parentNotIn with global + db ID
		$variables = [
			'where' => [
				'parentNotIn' => [ $parent_one_global_id, $parent_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(6, $actual['data']['posts']['nodes']);
	}

	public function testPasswordWhereArgs() {
		// Create a test post with a password
		$this->createPostObject(
			[
				'post_title'    => 'Password protected',
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => 'password',
			]
		);

		/**
		 * WP_Query posts with a password
		 */
		$wp_query_posts_with_password = new WP_Query(
			[
				'has_password' => true,
			]
		);

		$query = $this->getQuery();

		wp_set_current_user( $this->admin );

		/**
		 * GraphQL query posts that have a password
		 */
		$variables = [
			'where' => [
				'hasPassword' => true,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertNotEmpty( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$edges = $actual['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Loop through all the returned posts
		 */
		foreach ( $edges as $edge ) {

			/**
			 * Assert that all posts returned have a password, since we queried for
			 * posts using "has_password => true"
			 */
			$password = get_post( $edge['node']['databaseId'] )->post_password;
			$this->assertNotEmpty( $password );

		}

		// Test with specific password
		$variables = [
			'where' => [
				'password' => 'password',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertNotEmpty( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$edges = $actual['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Loop through all the returned posts
		 */
		foreach ( $edges as $edge ) {

			/**
			 * Assert that all posts returned have the correct password
			 */
			$password = get_post( $edge['node']['databaseId'] )->post_password;
			$this->assertEquals( $password, 'password' );
		}

	}

	public function testTagWhereArgs() {
		$query = $this->getQuery();

		$tag_one_id = $this->factory()->tag->create([
			'name' => 'test',
		]);
		$tag_two_id = $this->factory()->tag->create([
			'name' => 'test2',
		]);

		wp_set_object_terms( $this->created_post_ids[1], [ $tag_one_id ], 'post_tag' );
		wp_set_object_terms( $this->created_post_ids[2], [ $tag_two_id ], 'post_tag' );

		// test tag
		$variables = [
			'where' => [
				'tag' => 'test',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test tagId with database Id
		$variables = [
			'where' => [
				'tagId' => (string) $tag_one_id, // the input expects a type 'string'.
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test tagId with global Id
		$tag_one_global_id = \GraphQLRelay\Relay::toGlobalId( 'term', $tag_one_id );

		$variables = [
			'where' => [
				'tagId' => $tag_one_global_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test tagIn with global + db id
		$variables = [
			'where' => [
				'tagIn' => [ $tag_one_global_id, $tag_two_id ],
			],
		];

		
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);

		// test tagNotIn with global + db id
		$variables = [
			'where' => [
				'tagNotIn' => [ $tag_one_global_id,  $tag_two_id ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(4, $actual['data']['posts']['nodes']);

		// test tagSlugAnd
		$variables = [
			'where' => [
				'tagSlugAnd' => [ 'test', 'test2' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(0, $actual['data']['posts']['nodes']);

		// add second tag to first post
		wp_set_object_terms( $this->created_post_ids[1], [ $tag_one_id, $tag_two_id ], 'post_tag' );


		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(1, $actual['data']['posts']['nodes']);
		$this->assertEquals( $this->created_post_ids[1], $actual['data']['posts']['nodes'][0]['databaseId'] );

		// test tagSlugIn
		$variables = [
			'where' => [
				'tagSlugIn' => [ 'test', 'test2' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount(2, $actual['data']['posts']['nodes']);
	}

}
