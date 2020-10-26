<?php

class UserObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $current_time;
	public $current_date;

	public function setUp(): void {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		parent::setUp();

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
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
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}users WHERE ID <> %d",
			array( 1 )
		) );
		$this->created_user_ids = [ 1 ];
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
		$user_id = $this->factory()->user->create( $args );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $user_id;

	}

	/**
	 * testUserQuery
	 *
	 * This tests creating a single user with data and retrieving said user via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testUserQuery() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject(
			[
				'user_email' => 'test@test.com',
			]
		);
		$user    = get_user_by( 'id', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				avatar {
					size
				}
				capKey
				capabilities
				comments {
					edges {
						node {
							commentId
						}
					}
				}
				description
				email
				extraCapabilities
				firstName
				id
				lastName
				locale
				mediaItems {
					edges {
						node {
							mediaItemId
						}
					}
				}
				name
				nickname
				pages {
					edges {
						node {
							pageId
						}
					}
				}
				posts {
					edges {
						node {
							postId
						}
					}
				}
				registeredDate
				roles {
				  nodes {
				    name
				  }
				}
				slug
				url
				userId
				username
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );


		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'user' => [
				'avatar'            => [
					'size' => 96,
				],
				'capKey'            => 'wp_capabilities',
				'capabilities'      => [ 'read', 'level_0', 'subscriber' ],
				'comments'          => [
					'edges' => [],
				],
				'description'       => null,
				'email'             => 'test@test.com',
				'extraCapabilities' => [ 'read', 'level_0', 'subscriber' ],
				'firstName'         => null,
				'id'                => $global_id,
				'lastName'          => null,
				'locale'            => 'en_US',
				'mediaItems'        => [
					'edges' => [],
				],
				'name'              => $user->data->display_name,
				'nickname'          => $user->nickname,
				'pages'             => [
					'edges' => [],
				],
				'posts'             => [
					'edges' => [],
				],
				'registeredDate'    => date( 'c', strtotime( $user->user_registered ) ),
				'roles'             => [
					'nodes' => [
						[
							'name' => 'subscriber'
						]
					],
				],
				'slug'              => $user->data->user_nicename,
				'url'               => null,
				'userId'            => $user_id,
				'username'          => $user->data->user_login,
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testUserQueryWithComments
	 *
	 * This tests a single user with comments connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithComments() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$comment_id = $this->factory()->comment->create( [ 'user_id' => $user_id ] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				comments {
					edges {
						node {
							commentId
						}
					}
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
			'user' => [
				'comments' => [
					'edges' => [
						[
							'node' => [
								'commentId' => $comment_id,
							],
						],
					],
				],
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testUserQueryWithPosts
	 *
	 * This tests a single user with posts connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithPosts() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory->post->create( [ 'post_author' => $user_id ] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				posts {
					edges {
						node {
							postId
						}
					}
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
			'user' => [
				'posts' => [
					'edges' => [
						[
							'node' => [
								'postId' => $post_id,
							],
						],
					],
				],
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testUserQueryWithPages
	 *
	 * This tests a single user with pages connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithPages() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory()->post->create( [
			'post_author' => $user_id,
			'post_type'   => 'page'
		] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				pages {
					edges {
						node {
							pageId
						}
					}
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
			'user' => [
				'pages' => [
					'edges' => [
						[
							'node' => [
								'pageId' => $post_id,
							],
						],
					],
				],
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testUserQueryWithMedia
	 *
	 * This tests a single user with mediaItems connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithMedia() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory->post->create( [
			'post_author' => $user_id,
			'post_type'   => 'attachment'
		] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				mediaItems {
					edges {
						node {
							mediaItemId
						}
					}
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
			'user' => [
				'mediaItems' => [
					'edges' => [
						[
							'node' => [
								'mediaItemId' => $post_id,
							],
						],
					],
				],
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testUserQueryWhereUserDoesNotExist
	 *
	 * Tests a query for non existant user.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWhereUserDoesNotExist() {
		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', 'doesNotExist' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				userId
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected =  [
			'user' => null,
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testUsersQueryWithNoPublishedPosts() {

		/**
		 * Set the current user to nobody (unauthenticated)
		 */
		wp_set_current_user( 0 );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		query {
			users(first:1) {
				edges{
				  node{
				    id
				    userId
				    email
				  }
				}
			}
		}';

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * The user has no published posts, so there should be no results publicly
		 * returned to an unauthenticated user
		 */
		$this->assertEmpty( $actual['data']['users']['edges'] );

	}

	/**
	 * @throws Exception
	 */
	public function testUserQueryWithPublishedPosts() {

		$this->delete_users();

		/**
		 * Create a user
		 */
		$email   = 'test@test.com';
		$user_id = $this->createUserObject(
			[
				'user_email' => $email,
				'role'       => 'administrator'
			]
		);

		$post_id = $this->factory()->post->create( [
			'post_author' => $user_id,
			'post_type'   => 'attachment'
		] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
		'users' => [
				'edges' => [
					[
						'node' => [
							'id'     => $global_id,
							'userId' => $user_id,
							'email'  => $email,
						],
					],
				],
			],
		];

		/**
		 * Set the current user to the created user we're querying and
		 * try the query again
		 */
		wp_set_current_user( $user_id );

		$query = '
		query {
			users(first:1) {
				edges{
				  node{
				    id
				    userId
				    email
				  }
				}
			}
		}';

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		/**
		 * The authenticated user should see their own user in the result
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

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

		$user_1_id = $this->createUserObject( $user_1 );
		$user_2_id = $this->createUserObject( $user_2 );
		$admin     = $this->createUserObject( [ 'role' => 'administrator' ] );

		wp_set_current_user( $admin );

		$query = '
		query {
			users(first:2) {
				edges{
				  node{
				    userId
				    username
				    email
				    firstName
				    lastName
				    url
				  }
				}
			}
		}';

		$actual = do_graphql_request( $query );

		$expected = [
			'users' => [
				'edges' => [
					[
						'node' => [
							'userId'    => $user_2_id,
							'username'  => $user_2['user_login'],
							'email'     => $user_2['user_email'],
							'firstName' => $user_2['first_name'],
							'lastName'  => $user_2['last_name'],
							'url'       => $user_2['user_url'],
						],
					],
					[
						'node' => [
							'userId'    => $user_1_id,
							'username'  => $user_1['user_login'],
							'email'     => $user_1['user_email'],
							'firstName' => $user_1['first_name'],
							'lastName'  => $user_1['last_name'],
							'url'       => $user_1['user_url'],
						],
					],
				],
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

	}

	public function testQueryAllUsersAsSubscriber() {

		$user_1 = [
			'user_email'  => 'user1@email.com',
			'user_login'  => 'user1',
			'user_url'    => 'https://test1.com',
			'first_name'  => 'User1',
			'last_name'   => 'Test',
			'role'        => 'subscriber',
			'description' => 'User 1 Test',
		];

		$user_2 = [
			'user_email'  => 'user2@email.com',
			'user_login'  => 'user2',
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
		$this->factory()->post->create( [
			'post_author' => $user_1_id,
		] );
		$this->factory()->post->create( [
			'post_author' => $user_2_id,
		] );

		wp_set_current_user( $user_2_id );

		$query = '
		query {
			users(first:2) {
				edges{
				  node{
				    userId
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

		$actual = do_graphql_request( $query );

		$expected = [
			'users' => [
				'edges' => [
					[
						'node' => [
							'userId'       => $user_2_id,
							'username'     => $user_2['user_login'],
							'email'        => $user_2['user_email'],
							'firstName'    => $user_2['first_name'],
							'lastName'     => $user_2['last_name'],
							'url'          => $user_2['user_url'],
							'description'  => $user_2['description'],
							'isRestricted' => false,
						],
					],
					[
						'node' => [
							'userId'       => $user_1_id,
							'username'     => null,
							'email'        => null,
							'firstName'    => $user_1['first_name'],
							'lastName'     => $user_2['last_name'],
							'url'          => null,
							'description'  => $user_1['description'],
							'isRestricted' => true,
						],
					],
				],
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

	}

	public function testPageInfoQueryAsSubscriber() {

		/**
		 * Let's create 2 admins and 1 subscriber so we can test our "where" arg is working
		 */
		$this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

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

		wp_set_current_user( $subscriber );

		$actual = do_graphql_request( $query );

		codecept_debug( $actual );
		/**
		 * Results should be empty for a non-authenticated request because the
		 * users have no published posts and are not considered public
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['users'] );

	}

	public function testPageInfoQueryAsAdmin() {

		/**
		 * Let's create 2 admins and 1 subscriber so we can test our "where" arg is working
		 */
		$this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

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

		wp_set_current_user( $admin );

		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'hasNextPage', $actual['data']['users']['pageInfo'] );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
		$this->assertCount( 2, $actual['data']['users']['edges'] );

	}


	public function testPageInfoQueryFilterBySubscriberAsAdmin() {

		$this->delete_users();

		/**
		 * Let's create 2 admins and 1 subscriber so we can test our "where" arg is working
		 */
		$this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

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

		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		/**
		 * Now let's make sure the subscriber role query worked
		 */
		$this->assertNotEmpty( $actual['data']['users']['pageInfo'] );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
		$this->assertCount( 1, $actual['data']['users']['edges'] );

	}

	public function dataProviderUserHasPosts() {
		return [
			[
				'has_posts' => true,
			],
			[
				'has_posts' => false,
			]
		];
	}

	/**
	 * Test to make sure only users with list_users capability can filter users by role
	 * using GraphQL user connections
	 *
	 * @throws Exception
	 */
	public function testFilterUsersByRole() {

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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'where' => [
					'role' => 'ADMINISTRATOR'
				]
			]
		] );

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
	 * @throws Exception
	 */
	public function testFilterUsersByRoleIn() {

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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'where' => [
					'roleIn' => [ 'ADMINISTRATOR', 'SUBSCRIBER' ]
				]
			]
		] );


		codecept_debug( $actual );

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
	 * @throws Exception
	 */
	public function testFilterUsersByRoleNotIn() {

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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'where' => [
					'roleNotIn' => [ 'ADMINISTRATOR' ]
				]
			]
		] );

		codecept_debug( $actual );

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
	 * @throws Exception
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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'where' => [
					'role' => 'ADMINISTRATOR'
				]
			]
		] );

		codecept_debug( $actual );

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
	 * @throws Exception
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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'where' => [
					'roleIn' => [ 'ADMINISTRATOR' ]
				]
			]
		] );

		codecept_debug( $actual );

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
	 * @throws Exception
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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'where' => [
					'roleNotIn' => [ 'SUBSCRIBER' ]
				]
			]
		] );

		/**
		 * The query should not have any errors because admins have "list_users" cap and can
		 * filter users by role
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );


	}

	/**
	 * @throws Exception
	 */
	public function testGetUserByIdTypesAsAdmin() {

		$user_data = [
			'role'          => 'administrator',
			'user_email'    => 'testGetUserByEmail@example.com',
			'user_login'    => 'testGetUserByEmail',
			'user_nicename' => 'nicename',
			'display_name'  => 'display',
			'first_name'    => 'first_name',
			'last_name'     => 'last_name',
		];

		$admin = $this->factory()->user->create_and_get( $user_data );

		/**
		 * Create one post by this author to query by URI
		 */
		$this->factory()->post->create( [
			'post_type'   => 'post',
			'post_author' => $admin->ID,
			'post_status' => 'publish'
		] );

		wp_set_current_user( $admin->ID );

		$uri = str_ireplace( home_url(), '', get_author_posts_url( $admin->ID ) );
		codecept_debug( $uri );

		$query = '
		{
		  userByDatabaseIdString: user(id: "' . $admin->ID . '", idType: DATABASE_ID) {
		    ...UserFields
		  }
		  userByDatabaseIdInt: user(id: ' . absint( $admin->ID ) . ', idType: DATABASE_ID) {
		    ...UserFields
		  }
		  userByEmail: user(id: "' . $admin->user_email . '", idType: EMAIL) {
		    ...UserFields
		  }
		  userById: user(id: "' . \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ) . '") {
		    ...UserFields
		  }
		  userByIdWithType: user(id: "' . \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ) . '", idType: ID) {
		    ...UserFields
		  }
		  userBySlug: user(id: "' . $admin->user_nicename . '", idType: SLUG) {
		    ...UserFields
		  }
		  userByUri: user(id: "' . $uri . '", idType: URI) {
		    ...UserFields
		  }
		  userByUsername: user(id: "' . $admin->user_login . '", idType: USERNAME) {
		    ...UserFields
		  }
		}
		
		fragment UserFields on User {
		  id
		  userId
		  username
		  slug
		  uri
		  email
		}
		';

		$expected_user = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ),
			'userId' => $admin->ID,
			'username' => $admin->user_login,
			'slug' => $admin->user_nicename,
			'uri' => str_ireplace( home_url(), '', get_author_posts_url( $admin->ID ) ),
			'email' => $admin->user_email,
		];

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $user_data['user_email'],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString']);
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt']);
		$this->assertSame( $expected_user, $actual['data']['userByEmail']);
		$this->assertSame( $expected_user, $actual['data']['userById']);
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType']);
		$this->assertSame( $expected_user, $actual['data']['userBySlug']);
		$this->assertSame( $expected_user, $actual['data']['userByUri']);
		$this->assertSame( $expected_user, $actual['data']['userByUsername']);

		wp_set_current_user( 0 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $user_data['user_email'],
			],
		] );

		codecept_debug( $actual );

		// As a public user, the email and username should not be returned when querying
		// for a user. Our expectation is null for these fields.
		$expected_user['username'] = null;
		$expected_user['email'] = null;

		/**
		 * A subscriber doesn't have permission to query a user
		 * by email so there SHOULD be errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString']);
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt']);
		$this->assertSame( $expected_user, $actual['data']['userById']);
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType']);
		$this->assertSame( $expected_user, $actual['data']['userBySlug']);
		$this->assertSame( $expected_user, $actual['data']['userByUri']);

		// Cannot query user by email as a non-authed user
		$this->assertNull( $actual['data']['userByEmail']);

		// Cannot query user by username as a non-authed user
		$this->assertNull( $actual['data']['userByUsername']);

	}

	/**
	 * @throws Exception
	 */
	public function testGetUserByIdTypesAsPublicUser() {

		$user_data = [
			'role'          => 'administrator',
			'user_email'    => 'testGetUserByEmail@example.com',
			'user_login'    => 'testGetUserByEmail',
			'user_nicename' => 'nicename',
			'display_name'  => 'display',
			'first_name'    => 'first_name',
			'last_name'     => 'last_name',
		];

		$admin = $this->factory()->user->create_and_get( $user_data );

		/**
		 * Create one post by this author to query by URI
		 */
		$this->factory()->post->create( [
			'post_type'   => 'post',
			'post_author' => $admin->ID,
			'post_status' => 'publish'
		] );

		$uri = str_ireplace( home_url(), '', get_author_posts_url( $admin->ID ) );
		codecept_debug( $uri );

		$query = '
		{
		  userByDatabaseIdString: user(id: "' . $admin->ID . '", idType: DATABASE_ID) {
		    ...UserFields
		  }
		  userByDatabaseIdInt: user(id: ' . absint( $admin->ID ) . ', idType: DATABASE_ID) {
		    ...UserFields
		  }
		  userByEmail: user(id: "' . $admin->user_email . '", idType: EMAIL) {
		    ...UserFields
		  }
		  userById: user(id: "' . \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ) . '") {
		    ...UserFields
		  }
		  userByIdWithType: user(id: "' . \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ) . '", idType: ID) {
		    ...UserFields
		  }
		  userBySlug: user(id: "' . $admin->user_nicename . '", idType: SLUG) {
		    ...UserFields
		  }
		  userByUri: user(id: "' . $uri . '", idType: URI) {
		    ...UserFields
		  }
		  userByUsername: user(id: "' . $admin->user_login . '", idType: USERNAME) {
		    ...UserFields
		  }
		}
		
		fragment UserFields on User {
		  id
		  userId
		  username
		  slug
		  uri
		  email
		}
		';

		$expected_user = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ),
			'userId' => $admin->ID,
			'username' => $admin->user_login,
			'slug' => $admin->user_nicename,
			'uri' => str_ireplace( home_url(), '', get_author_posts_url( $admin->ID ) ),
			'email' => $admin->user_email,
		];

		wp_set_current_user( 0 );

		$actual = graphql( [
			'query'     => $query,
		] );

		codecept_debug( $actual );

		// As a public user, the email and username should not be returned when querying
		// for a user. Our expectation is null for these fields.
		$expected_user['username'] = null;
		$expected_user['email'] = null;

		/**
		 * A subscriber doesn't have permission to query a user
		 * by email so there SHOULD be errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString']);
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt']);
		$this->assertSame( $expected_user, $actual['data']['userById']);
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType']);
		$this->assertSame( $expected_user, $actual['data']['userBySlug']);
		$this->assertSame( $expected_user, $actual['data']['userByUri']);

		// Cannot query user by email as a non-authed user
		$this->assertNull( $actual['data']['userByEmail']);

		// Cannot query user by username as a non-authed user
		$this->assertNull( $actual['data']['userByUsername']);

	}

	/**
	 * @throws Exception
	 */
	public function testGetUserByIdTypesAsSubscriber() {

		$subscriber_data = [
			'role'          => 'subscriber',
			'user_email'    => 'testGetUserByEmail_subscriber@example.com',
			'user_login'    => 'testGetUserByEmail_subscriber',
			'user_nicename' => 'subscriber_nicename',
			'display_name'  => 'subscriber_display',
			'first_name'    => 'subscriber_first_name',
			'last_name'     => 'subscriber_last_name',
		];

		$subscriber = $this->factory()->user->create_and_get( $subscriber_data );

		/**
		 * Create one post by this author to query by URI
		 */
		$this->factory()->post->create( [
			'post_type'   => 'post',
			'post_author' => $subscriber->ID,
			'post_status' => 'publish'
		] );

		wp_set_current_user( $subscriber->ID );

		$uri = str_ireplace( home_url(), '', get_author_posts_url( $subscriber->ID ) );
		codecept_debug( $uri );

		$query = '
		{
		  userByDatabaseIdString: user(id: "' . $subscriber->ID . '", idType: DATABASE_ID) {
		    ...UserFields
		  }
		  userByDatabaseIdInt: user(id: ' . absint( $subscriber->ID ) . ', idType: DATABASE_ID) {
		    ...UserFields
		  }
		  userByEmail: user(id: "' . $subscriber->user_email . '", idType: EMAIL) {
		    ...UserFields
		  }
		  userById: user(id: "' . \GraphQLRelay\Relay::toGlobalId( 'user', $subscriber->ID ) . '") {
		    ...UserFields
		  }
		  userByIdWithType: user(id: "' . \GraphQLRelay\Relay::toGlobalId( 'user', $subscriber->ID ) . '", idType: ID) {
		    ...UserFields
		  }
		  userBySlug: user(id: "' . $subscriber->user_nicename . '", idType: SLUG) {
		    ...UserFields
		  }
		  userByUri: user(id: "' . $uri . '", idType: URI) {
		    ...UserFields
		  }
		  userByUsername: user(id: "' . $subscriber->user_login . '", idType: USERNAME) {
		    ...UserFields
		  }
		}
		
		fragment UserFields on User {
		  id
		  userId
		  username
		  slug
		  uri
		  email
		}
		';

		$actual = graphql( [
			'query'     => $query,
		] );

		codecept_debug( $actual );

		$expected_user = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'user', $subscriber->ID ),
			'userId' => $subscriber->ID,
			'username' => $subscriber->user_login,
			'slug' => $subscriber->user_nicename,
			'uri' => str_ireplace( home_url(), '', get_author_posts_url( $subscriber->ID ) ),
			'email' => $subscriber->user_email,
		];

		/**
		 * A subscriber doesn't have permission to query a user
		 * by email so there SHOULD be errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString'] );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt'] );
		$this->assertSame( $expected_user, $actual['data']['userById'] );
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType'] );
		$this->assertSame( $expected_user, $actual['data']['userBySlug'] );
		$this->assertSame( $expected_user, $actual['data']['userByUri'] );
		$this->assertSame( $expected_user, $actual['data']['userByEmail'] );
		$this->assertSame( $expected_user, $actual['data']['userByUsername'] );

	}

}
