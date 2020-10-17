<?php

class PostObjectConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {
	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;
	public $subscriber;

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();

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
		WPGraphQL::clear_schema();
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
			'post_title'    => 'Test Title',
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
	 * @param  int $count Number of posts to create.
	 *
	 * @return array
	 */
	public function create_posts( $count = 20 ) {

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
					'post_title'  => $i,
				]
			);
		}

		return $created_posts;

	}

	public function postsQuery( $variables ) {

		$query = 'query postsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToPostConnectionWhereArgs ){
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
						postId
						title
						date
						content
						excerpt
					}
				}
				nodes {
				  id
				  postId
				}
			}
		}';

		return do_graphql_request( $query, 'postsQuery', $variables );

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

	public function testFirstPost() {

		/**
		 * Here we're querying the first post in our dataset
		 */
		$variables = [
			'first' => 1,
		];
		$results   = $this->postsQuery( $variables );

		codecept_debug( $results );

		/**
		 * Let's query the first post in our data set so we can test against it
		 */
		$first_post      = new WP_Query(
			[
				'posts_per_page' => 1,
			]
		);
		$first_post_id   = $first_post->posts[0]->ID;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $first_post_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $first_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );
		$this->assertEquals( $first_post_id, $results['data']['posts']['nodes'][0]['postId'] );
		$this->assertEquals( false, $results['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $results['data']['posts']['pageInfo']['hasNextPage'] );

		$this->forwardPagination( $expected_cursor );

	}

	public function testLastPost() {
		/**
		 * Here we're trying to query the last post in our dataset
		 */
		$variables = [
			'last' => 1,
		];
		$results   = $this->postsQuery( $variables );

		/**
		 * Let's query the last post in our data set so we can test against it
		 */
		$last_post    = new WP_Query(
			[
				'posts_per_page' => 1,
				'order'          => 'ASC',
			]
		);
		$last_post_id = $last_post->posts[0]->ID;

		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $last_post_id );

		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $last_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );
		$this->assertEquals( true, $results['data']['posts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $results['data']['posts']['pageInfo']['hasNextPage'] );

		$this->backwardPagination( $expected_cursor );

	}

	public function forwardPagination( $cursor ) {

		$variables = [
			'first' => 1,
			'after' => $cursor,
		];

		$results = $this->postsQuery( $variables );

		codecept_debug( $results );

		$second_post     = new WP_Query(
			[
				'posts_per_page' => 1,
				'paged'          => 2,
			]
		);
		$second_post_id  = $second_post->posts[0]->ID;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $second_post_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $second_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );
		$this->assertEquals( true, $results['data']['posts']['pageInfo']['hasPreviousPage'] );

	}

	public function backwardPagination( $cursor ) {

		$variables = [
			'last'   => 1,
			'before' => $cursor,
		];

		$results = $this->postsQuery( $variables );

		$second_to_last_post    = new WP_Query(
			[
				'posts_per_page' => 1,
				'paged'          => 2,
				'order'          => 'ASC',
			]
		);
		$second_to_last_post_id = $second_to_last_post->posts[0]->ID;
		$expected_cursor        = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $second_to_last_post_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['posts']['edges'] ) );
		$this->assertEquals( $second_to_last_post_id, $results['data']['posts']['edges'][0]['node']['postId'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['posts']['pageInfo']['endCursor'] );
		$this->assertEquals( true, $results['data']['posts']['pageInfo']['hasNextPage'] );

	}

	public function testMaxQueryAmount() {
		// Create some additional posts to test a large query.
		$this->create_posts( 150 );

		$variables = [
			'first' => 150,
		];
		$results   = $this->postsQuery( $variables );
		$this->assertNotEmpty( $results );

		/**
		 * The max that can be queried by default is 100 items
		 */
		$this->assertCount( 100, $results['data']['posts']['edges'] );
		$this->assertTrue( $results['data']['posts']['pageInfo']['hasNextPage'] );

		/**
		 * Test the filter to make sure it's capping the results properly
		 */
		add_filter(
			'graphql_connection_max_query_amount',
			function() {
				return 20;
			}
		);

		$variables = [
			'first' => 150,
		];
		$results   = $this->postsQuery( $variables );

		add_filter(
			'graphql_connection_max_query_amount',
			function() {
				return 100;
			}
		);

		$this->assertCount( 20, $results['data']['posts']['edges'] );
		$this->assertTrue( $results['data']['posts']['pageInfo']['hasNextPage'] );
	}

	public function testPostHasPassword() {
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

		/**
		 * GraphQL query posts that have a password
		 */
		$variables = [
			'where' => [
				'hasPassword' => true,
			],
		];

		wp_set_current_user( $this->admin );
		$request = $this->postsQuery( $variables );

		$this->assertNotEmpty( $request );
		$this->assertArrayNotHasKey( 'errors', $request );

		$edges = $request['data']['posts']['edges'];
		$this->assertNotEmpty( $edges );

		/**
		 * Loop through all the returned posts
		 */
		foreach ( $edges as $edge ) {

			/**
			 * Assert that all posts returned have a password, since we queried for
			 * posts using "has_password => true"
			 */
			$password = get_post( $edge['node']['postId'] )->post_password;
			$this->assertNotEmpty( $password );

		}

	}

	public function testPageWithChildren() {

		$parent_id = $this->factory->post->create(
			[
				'post_type' => 'page',
			]
		);

		$child_id = $this->factory->post->create(
			[
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			]
		);

		$global_id       = \GraphQLRelay\Relay::toGlobalId( 'post', $parent_id );
		$global_child_id = \GraphQLRelay\Relay::toGlobalId( 'post', $child_id );

		$query = '
		{
			page( id: "' . $global_id . '" ) {
				id
				pageId
				children {
					edges {
						node {
						    ...on Page {
							  id
							  pageId
							}
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * Make sure the query didn't return any errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );

		$parent = $actual['data']['page'];
		$child  = $parent['children']['edges'][0]['node'];

		/**
		 * Make sure the child and parent data matches what we expect
		 */
		$this->assertEquals( $global_id, $parent['id'] );
		$this->assertEquals( $parent_id, $parent['pageId'] );
		$this->assertEquals( $global_child_id, $child['id'] );
		$this->assertEquals( $child_id, $child['pageId'] );

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

		$request = $this->postsQuery( [ 'where' => [ 'in' => [ $post_1_id, $post_2_id ] ] ] );

		$this->assertNotEmpty( $request );
		$this->assertArrayNotHasKey( 'errors', $request );

		$edges = $request['data']['posts']['edges'];
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

		wp_set_current_user( $this->subscriber );
		$actual = $this->postsQuery(
			[
				'where' => [
					'in'    => [ $private_post, $public_post ],
					'stati' => [ 'PUBLISH', 'PRIVATE' ],
				],
			]
		);

		$this->assertCount( 1, $actual['data']['posts']['edges'] );
		$this->assertNotEmpty( $this->getReturnField( $actual, 0, 'id' ) );
		$this->assertEmpty( $this->getReturnField( $actual, 1 ) );

	}

	public function testPrivatePostsWithProperCaps() {

		$post_args = [
			'post_title'  => 'Private post WITH caps',
			'post_status' => 'private',
			'post_author' => $this->subscriber,
		];

		$post_id = $this->createPostObject( $post_args );

		wp_set_current_user( $this->admin );
		$actual = $this->postsQuery(
			[
				'where' => [
					'in'    => [ $post_id ],
					'stati' => [ 'PUBLISH', 'PRIVATE' ],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertEquals( $post_args['post_title'], $this->getReturnField( $actual, 0, 'title' ) );

	}

	public function testPrivatePostsForCurrentUser() {

		$post_args = [
			'post_title'  => 'Private post WITH caps',
			'post_status' => 'private',
			'post_author' => $this->subscriber,
		];

		$post_id = $this->createPostObject( $post_args );

		wp_set_current_user( $this->subscriber );
		$actual = $this->postsQuery(
			[
				'where' => [
					'in'    => [ $post_id ],
					'stati' => [ 'PUBLISH', 'PRIVATE' ],
				],
			]
		);

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
		        postId
		        id
		        title
		        content
		        revisions{
		          edges {
		            node{
		              id
		              postId
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
			$this->assertEquals( $revision, $actual['data']['posts']['edges'][0]['node']['revisions']['edges'][0]['node']['postId'] );
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
			'post_title'   => 'Draft Title',
			'post_content' => 'Draft Post Content Here',
			'post_status'  => 'draft',
		];
		$draft_post  = $this->createPostObject( $draft_args );

		wp_set_current_user( $this->{$role} );

		$actual = $this->postsQuery(
			[
				'where' => [
					'in'    => [ $public_post, $draft_post ],
					'stati' => [ 'PUBLISH', 'DRAFT' ],
				],
			]
		);

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
			'post_title'   => 'Trash Title',
			'post_content' => 'Trash Post Content Here',
			'post_status'  => 'trash',
		];
		$draft_post  = $this->createPostObject( $draft_args );

		wp_set_current_user( $this->{$role} );

		$actual = $this->postsQuery(
			[
				'where' => [
					'in'    => [ $public_post, $draft_post ],
					'stati' => [ 'PUBLISH', 'TRASH' ],
				],
			]
		);

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
			'post_type' => 'Post',
			'post_status' => 'publish',
			'post_title' => 'Public Post',
		] );

		$private_post_id = $this->factory()->post->create( [
			'post_type' => 'Post',
			'post_status' => 'publish',
			'post_title' => 'Private Post',
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
			$ids[$node['databaseId']] = $node;
		}

		$this->assertArrayHasKey( $private_post_id, $ids );

		/**
		 * Filter posts with a certain meta key to be private. These posts
		 * should NOT be returned as nodes at all. They should be stripped before
		 * nodes array is returned.
		 */
		add_filter( 'graphql_data_is_private', function( $is_private, $model_name, $data, $visibility, $owner, $current_user ) {
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
			$ids[$node['databaseId']] = $node;
		}

		$this->assertArrayNotHasKey( $private_post_id, $ids );


	}

	public function testSuppressFiltersThrowsException() {

		WPGraphQL::clear_schema();

		add_filter( 'graphql_post_object_connection_query_args', function( $args ) {
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
			'
		]);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1477
	 */
	public function testPostInArgumentWorksWithCursors() {

		$post_1 = $this->factory()->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Public Post',
		] );

		$post_2 = $this->factory()->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Public Post',
		] );

		$post_3 = $this->factory()->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Public Post',
		] );

		$post_4 = $this->factory()->post->create( [
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Public Post',
		] );

		$post_ids = [ $post_3, $post_2, $post_4, $post_1 ];

		$query = '
		query GetPostsByIds($post_ids: [ID] $after:String $before:String) {
		  posts(where: {in: $post_ids} after:$after before:$before) {
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
			'query' => $query,
			'variables' => [
				'post_ids' => $post_ids,
				'after' => null,
			]
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
			'query' => $query,
			'variables' => [
				'post_ids' => $post_ids,
				'after' => $cursor
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_4, $post_1 ], $actual_ids );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'post_ids' => $post_ids,
				'before' => $cursor
			]
		]);

		codecept_debug( [ 'variables' => [
			'post_ids' => $post_ids,
			'before' => $cursor
		] ]);
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_3 ], $actual_ids );

	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1477
	 */
	public function testCustomPostConnectionWithSetIdsWorksWithCursors() {

		$post_1 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post',
		] );

		$post_2 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post',
		] );

		$post_3 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post',
		] );

		$post_4 = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Post',
		] );

		$post_ids = [ $post_3, $post_2, $post_4, $post_1 ];

		register_graphql_connection([
			'connectionTypeName' => 'OrderbyDebug',
			'description' => __( 'debugging', 'wp-graphql' ),
			'fromType' => 'RootQuery',
			'toType' => 'MediaItem',
			'fromFieldName' => 'postOrderbyDebug',
			'resolve' => function( $root, $args, $context, $info ) use ( $post_ids ) {
				$args['where']['in'] = $post_ids;
				$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $root, $args, $context, $info, 'post' );
				return $resolver->get_connection();
			}
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
				'after'    => null,
				'before' => null,
			]
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
			'query' => $query,
			'variables' => [
				'after' => $cursor_from_first_query
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_ids[2], $post_ids[3] ], $actual_ids );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'before' => $cursor_from_first_query,
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$actual_ids = [];
		foreach ( $actual['data']['posts']['edges'] as $edge ) {
			$actual_ids[] = $edge['node']['databaseId'];
		}

		$this->assertSame( [ $post_ids[0] ], $actual_ids );


	}



}
