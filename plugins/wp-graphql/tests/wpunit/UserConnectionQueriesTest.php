<?php

class UserConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $current_time;
	public $current_date;

	public function setUp(): void {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		parent::setUp();

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		$this->delete_users();
	}

	public function tearDown(): void {
		$this->delete_users();
		parent::tearDown();
	}

	public function set_permalink_structure( $structure = '' ) {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );
		$wp_rewrite->flush_rules();
	}

	/**
	 * Deletes all users that were created using create_users()
	 */
	public function delete_users() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}users WHERE ID <> %d",
				[ 0 ]
			)
		);
	}

	public function createUserObject( $args = [] ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'role' => 'subscriber',
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		return $this->factory()->user->create( $args );
	}


	/**
	 * @throws \Exception
	 */
	public function testWithPublishedPosts() {
		/**
		 * Create a user
		 */
		$email   = 'test@test.com';
		$user_id = $this->createUserObject(
			[
				'user_email' => $email,
				'role'       => 'administrator',
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_author' => $user_id,
				'post_type'   => 'post',
			]
		);

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$query = '
			query {
				users(first:1) {
					edges{
						node{
							id
							databaseId
							email
						}
					}
				}
			}
		';

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'users' => [
				'edges' => [
					[
						'node' => [
							'id'         => $global_id,
							'databaseId' => $user_id,
							'email'      => $email,
						],
					],
				],
			],
		];

		// Test as logged out user.
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );
		// The user email should be null.
		$this->assertNull( $actual['data']['users']['edges'][0]['node']['email'] );

		// Test after post is deleted.
		wp_delete_post( $post_id );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );

		// Test as logged in user.
		wp_set_current_user( $user_id );
		$actual = $this->graphql( compact( 'query' ) );
		$this->assertSame( $expected, $actual['data'] );
	}

	public function testQueryAllUsersAsAdmin() {
		$user_1 = [
			'user_email' => 'user1@email.com',
			'user_login' => 'user1',
			'user_url'   => 'https://test1.com',
			'first_name' => 'User1',
			'last_name'  => 'Test',
		];

		$user_2 = [
			'user_email' => 'user2@email.com',
			'user_login' => 'user2',
			'user_url'   => 'https://test2.com',
			'first_name' => 'User2',
			'last_name'  => 'Test',
		];

		$admin     = $this->createUserObject( [ 'role' => 'administrator' ] );
		$user_1_id = $this->createUserObject( $user_1 );
		$user_2_id = $this->createUserObject( $user_2 );

		wp_set_current_user( $admin );

		$query = '
		query {
			users(first:3) {
				edges{
					node{
						databaseId
						username
						email
						firstName
						lastName
						url
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertCount( 3, $actual['data']['users']['edges'] );
	}

	public function testQueryAllUsersAsSubscriber() {

		$user_1 = [
			'user_email'  => 'user1@email.com',
			'user_login'  => 'aaaa_subscriber',
			'user_url'    => 'https://test1.com',
			'first_name'  => 'User1',
			'last_name'   => 'Test',
			'role'        => 'subscriber',
			'description' => 'User 1 Test',
		];

		$user_2 = [
			'user_email'  => 'user2@email.com',
			'user_login'  => 'aaaa_subscriber2',
			'user_url'    => 'https://test2.com',
			'first_name'  => 'User2',
			'last_name'   => 'Test',
			'role'        => 'subscriber',
			'description' => 'User 2 test',
		];

		$user_1_id = $this->createUserObject( $user_1 );
		$user_2_id = $this->createUserObject( $user_2 );

		/**
		 * Create posts for users so they are only restricted instead of private
		 */
		$this->factory()->post->create(
			[
				'post_author' => $user_1_id,
			]
		);
		$this->factory()->post->create(
			[
				'post_author' => $user_2_id,
			]
		);

		wp_set_current_user( $user_2_id );

		$query = '
		query {
			users(first:2) {
				edges{
					node{
						databaseId
						username
						email
						firstName
						lastName
						url
						description
						isRestricted
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 2, count( $actual['data']['users']['edges'] ) );
	}

	public function testPageInfoQueryFilterByAdmin() {

		/**
		 * Let's create a few admins and 1 subscriber so we can test our "where" arg is working
		 */
		$this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$subscriber = $this->factory->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$query = '
		query{
			users(first:2 where: {role:ADMINISTRATOR}){
				pageInfo{
					hasNextPage
				}
				edges{
					node{
						id
					}
				}
			}
		}
		';

		// Test as subscriber.
		wp_set_current_user( $subscriber );

		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Results should be empty for a non-authenticated request because the
		 * users have no published posts and are not considered public
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users'] );

		// Test as admin.
		wp_set_current_user( $admin );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'hasNextPage', $actual['data']['users']['pageInfo'] );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
		$this->assertCount( 2, $actual['data']['users']['edges'] );
	}


	public function testPageInfoQueryFilterBySubscriberAsAdmin() {

		$this->delete_users();

		/**
		 * Let's create 2 admins and 1 subscriber so we can test our "where" arg is working
		 */
		$this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$subscriber = $this->factory->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$query = '
		query{
			users(first:2 where: {role:SUBSCRIBER}){
				pageInfo{
					hasNextPage
				}
				edges{
					node{
						id
					}
				}
			}
		}
		';

		wp_set_current_user( $admin );

		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Now let's make sure the subscriber role query worked
		 */
		$this->assertNotEmpty( $actual['data']['users']['pageInfo'] );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
		$this->assertCount( 1, $actual['data']['users']['edges'] );
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws \Exception
	 */
	public function testFilterByRole() {

		$subscriber = $this->createUserObject( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber );

		$query = '
		query getUsers($where:RootQueryToUserConnectionWhereArgs){
			users(where:$where){
				edges{
					node{
						userId
						name
					}
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'where' => [
						'role' => 'ADMINISTRATOR',
					],
				],
			]
		);

		/**
		 * The query should return errors because the user is a subscriber and cannot filter by role
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['users'] );
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws \Exception
	 */
	public function testFilterByRoleIn() {

		$subscriber = $this->createUserObject( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber );

		$query = '
		query getUsers($where:RootQueryToUserConnectionWhereArgs){
			users(where:$where){
				edges{
					node{
						userId
						name
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'where' => [
						'roleIn' => [ 'ADMINISTRATOR', 'SUBSCRIBER' ],
					],
				],
			]
		);

		/**
		 * The query should return errors because the user is a subscriber and cannot filter by role
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['users'] );
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws \Exception
	 */
	public function testFilterByRoleNotIn() {

		$subscriber = $this->createUserObject( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $subscriber );

		$query = '
		query getUsers($where:RootQueryToUserConnectionWhereArgs){
			users(where:$where){
				edges{
					node{
						userId
						name
					}
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'where' => [
						'roleNotIn' => [ 'ADMINISTRATOR' ],
					],
				],
			]
		);

		/**
		 * The query should return errors because the user is a subscriber and cannot filter by role
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['users'] );
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws \Exception
	 */
	public function testAdminFilterUsersByRole() {

		$admin = $this->createUserObject( [ 'role' => 'administrator' ] );

		wp_set_current_user( $admin );

		$query = '
		query getUsers($where: RootQueryToUserConnectionWhereArgs) {
			users(where: $where) {
				edges {
					node {
						userId
						name
					}
				}
			}
		}

		';

		codecept_debug( get_user_by( 'id', get_current_user_id() ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'where' => [
						'role' => 'ADMINISTRATOR',
					],
				],
			]
		);

		/**
		 * The query should not have any errors because admins have "list_users" cap and can
		 * filter users by role
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws \Exception
	 */
	public function testAdminFilterUsersByRoleIn() {

		$admin = $this->createUserObject( [ 'role' => 'administrator' ] );

		wp_set_current_user( $admin );

		$query = '
		query getUsers($where: RootQueryToUserConnectionWhereArgs) {
			users(where: $where) {
				edges {
					node {
						userId
						name
					}
				}
			}
		}

		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'where' => [
						'roleIn' => [ 'ADMINISTRATOR' ],
					],
				],
			]
		);

		/**
		 * The query should not have any errors because admins have "list_users" cap and can
		 * filter users by role
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws \Exception
	 */
	public function testAdminFilterUsersByRoleNotIn() {

		$admin = $this->createUserObject( [ 'role' => 'administrator' ] );

		wp_set_current_user( $admin );

		$query = '
		query getUsers($where: RootQueryToUserConnectionWhereArgs) {
			users(where: $where) {
				edges {
					node {
						userId
						name
					}
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'where' => [
						'roleNotIn' => [ 'SUBSCRIBER' ],
					],
				],
			]
		);

		/**
		 * The query should not have any errors because admins have "list_users" cap and can
		 * filter users by role
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
	}

	public function testQueryUsersAsPublicUserShouldReturnOnlyPublishedAuthors() {

		$this->delete_users();

		$alphabet          = range( 'A', 'Z' );
		$published_users   = [];
		$unpublished_users = [];
		foreach ( $alphabet as $letter ) {
			$unpublished_users[] = $this->factory()->user->create(
				[
					'user_login' => 'unpublished_' . $letter,
					'user_email' => $letter . '_unpublishded@example.com',
				]
			);
			$author_id           = $this->factory()->user->create(
				[
					'user_login' => 'published_' . $letter,
					'user_email' => $letter . '_published@example.com',
					'role'       => 'administrator',
				]
			);

			$published_users[] = $author_id;
			$this->factory()->post->create(
				[
					'post_status' => 'publish',
					'post_title'  => $letter . '_Post for UserQueryWithComments',
					'post_author' => $author_id,
					'role'        => 'administrator',
				]
			);
		}

		$query = '
		{
			users(first:100) {
				nodes {
					databaseId
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$ids = wp_list_pluck( $actual['data']['users']['nodes'], 'databaseId' );

		// There should be 26 users. One published user for each letter of the alphabet.
		$this->assertEquals( 26, count( $ids ) );

		foreach ( $ids as $id ) {
			$this->assertTrue( in_array( $id, $published_users, true ) );
			$this->assertTrue( ! in_array( $id, $unpublished_users, true ) );
		}

		$query = '
		{
			users(last:100) {
				nodes {
					databaseId
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$ids = wp_list_pluck( $actual['data']['users']['nodes'], 'databaseId' );

		// There should be 26 users. One published user for each letter of the alphabet.
		$this->assertEquals( 26, count( $ids ) );
		foreach ( $ids as $id ) {
			$this->assertTrue( in_array( $id, $published_users, true ) );
			$this->assertTrue( ! in_array( $id, $unpublished_users, true ) );
		}

		$query = '
		{
			users(last:10) {
				nodes {
					databaseId
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$ids = wp_list_pluck( $actual['data']['users']['nodes'], 'databaseId' );

		// There should be 10 users.
		$this->assertEquals( 10, count( $ids ) );
		foreach ( $ids as $id ) {
			$this->assertTrue( in_array( $id, $published_users, true ) );
			$this->assertTrue( ! in_array( $id, $unpublished_users, true ) );
		}

		codecept_debug( $published_users );

		// Query as an admin
		wp_set_current_user( absint( $published_users[0] ) );

		$query = '
		{
			users(first:100) {
				nodes {
					databaseId
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$ids = wp_list_pluck( $actual['data']['users']['nodes'], 'databaseId' );
		// There should be 52 users. One published and one unpublished user for each letter of the alphabet.
		$this->assertEquals( 52, count( $ids ) );

		$query = '
		{
			users(last:100) {
				nodes {
					databaseId
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$ids = wp_list_pluck( $actual['data']['users']['nodes'], 'databaseId' );
		// There should be 52 users. One published and one unpublished user for each letter of the alphabet.
		$this->assertEquals( 52, count( $ids ) );

		$this->delete_users();
	}

	public function testWithHasPublishedPostsFilter() {
		$user_id = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$query = '
			query UsersWithPublishedPosts( $hasPublishedPosts: [ContentTypeEnum] ){
				users(first:1 where: { hasPublishedPosts: $hasPublishedPosts } ) {
					edges{
						node{
							databaseId
						}
					}
				}
			}
		';

		/**
		 * Test page.
		 */
		$page_id = $this->factory()->post->create(
			[
				'post_author' => $user_id,
				'post_type'   => 'page',
			]
		);

		// Test we get a user when filtered by page.
		$variables = [
			'hasPublishedPosts' => [ 'PAGE' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );

		// Test we dont get a user when filtered by post.
		$variables = [
			'hasPublishedPosts' => [ 'POST' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );

		// Test we still dont get the user when logged in.
		wp_set_current_user( $user_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );

		/**
		 * Posts.
		 */

		wp_set_current_user( 0 );

		$post_id = $this->factory()->post->create(
			[
				'post_author' => $user_id,
				'post_type'   => 'post',
			]
		);

		// Test we get a user when filtered by post.
		$variables = [
			'hasPublishedPosts' => [ 'POST' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );

		// Test we still get a user when there is no page.
		wp_delete_post( $page_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );

		/**
		 * Attachments
		 */
		$attachment_id = $this->factory()->attachment->create(
			[
				'post_author' => $user_id,
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_title'  => 'Test attachment for PostTypeQueryForMedia',
				'post_parent' => $post_id,
			]
		);

		// Filter by attachment
		$variables = [
			'hasPublishedPosts' => [ 'ATTACHMENT' ],
		];

		// An attachment should not return a user.
		wp_set_current_user( $user_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );

		// Delete the post and ensure the the attachment still doesnt return a user.
		wp_delete_post( $post_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );

		$variables = [
			'hasPublishedPosts' => [ 'POST' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );
	}

	public function testWithSearchColumns() {
		$admin_id = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$user_one_id = $this->factory()->user->create(
			[
				'user_login' => 'keyword',
			]
		);

		$user_two_id = $this->factory()->user->create(
			[
				'user_email' => 'keyword@test.test',
			]
		);

		$user_three_id = $this->factory()->user->create(
			[
				'user_url' => 'https://keyword.com',
			]
		);

		$query = '
			query UsersWithSearchColumns( $search: String, $searchColumns: [UsersConnectionSearchColumnEnum] ){
				users(first:100 where: { search: $search, searchColumns: $searchColumns } ) {
					edges{
						node{
							databaseId
						}
					}
				}
			}
		';

		// Test search by user_login
		$variables = [
			'search'        => 'keyword',
			'searchColumns' => 'LOGIN',
		];

		wp_set_current_user( $admin_id );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['users']['edges'] );
		$this->assertEquals( $user_one_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );

		// Test search by user_email
		$variables = [
			'search'        => 'keyword',
			'searchColumns' => 'EMAIL',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['users']['edges'] );

		$this->assertEquals( $user_two_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );

		// Test search by user_url
		$variables = [
			'search'        => 'keyword',
			'searchColumns' => 'URL',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['users']['edges'] );

		$this->assertEquals( $user_three_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );

		// Test search by all columns
		$variables = [
			'search'        => 'keyword',
			'searchColumns' => [ 'LOGIN', 'EMAIL', 'URL' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 3, $actual['data']['users']['edges'] );

		$this->assertEquals( $user_one_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $user_two_id, $actual['data']['users']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $user_three_id, $actual['data']['users']['edges'][2]['node']['databaseId'] );

		// Test search by two columns
		$variables = [
			'search'        => 'keyword',
			'searchColumns' => [ 'LOGIN', 'EMAIL' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['users']['edges'] );

		$this->assertEquals( $user_one_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $user_two_id, $actual['data']['users']['edges'][1]['node']['databaseId'] );

		// And a different two columns
		$variables = [
			'search'        => 'keyword',
			'searchColumns' => [ 'NICENAME', 'URL' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['users']['edges'] );

		$this->assertEquals( $user_one_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $user_three_id, $actual['data']['users']['edges'][1]['node']['databaseId'] );

		// Test bad keyword returns no results
		$variables = [
			'search'        => 'badkeyword',
			'searchColumns' => [ 'LOGIN', 'EMAIL', 'URL' ],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users']['edges'] );

		// Cleanup.
		wp_delete_user( $admin_id );
		wp_delete_user( $user_one_id );
		wp_delete_user( $user_two_id );
		wp_delete_user( $user_three_id );
	}
}
