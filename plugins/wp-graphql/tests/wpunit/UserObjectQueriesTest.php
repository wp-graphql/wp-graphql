<?php

class UserObjectQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
		 * Create the user.
		 */
		return $this->factory()->user->create( $args );
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

		// Run the query publicly should return null.
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['user'] );

		/**
		 * Run the GraphQL query as an authenticated user.
		 */
		wp_set_current_user( $user_id );
		$actual = $this->graphql( compact( 'query' ) );

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
							'name' => 'subscriber',
						],
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
	 * This tests a single user with comments connection.
	 *
	 * @since 0.0.5
	 */
	public function testWithComments() {
		/**
		 * Create an admin user.
		 */
		$admin_id = $this->createUserObject(
			[
				'role' => 'administrator',
			]
		);

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Post for UserQueryWithComments',
				'post_author' => $admin_id,
			]
		);

		$comment_id = $this->factory()->comment->create(
			[
				'user_id'         => $user_id,
				'comment_post_ID' => $post_id,
			]
		);

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
				comments {
					edges {
						node {
							commentId
						}
					}
				}
			}
		}";

		// Test that a logged out query still returns null.
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['user'] );

		// Test that the user can see their own comments.
		wp_set_current_user( $user_id );

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

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * This tests a single user with posts connection.
	 *
	 * @since 0.0.5
	 */
	public function testWithPosts() {
		$user_args = [
			'description' => 'This is a test user.',
			'first_name'  => 'Test',
			'nickname'    => 'test', // private
			'user_url'    => 'https://example.com',
		];

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject( $user_args );
		$user    = get_user_by( 'id', $user_id );

		$post_id = $this->factory->post->create( [ 'post_author' => $user_id ] );

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
				description
				email
				extraCapabilities
				firstName
				id
				locale
				name
				nickname
				posts {
					edges {
						node {
							databaseId
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
				username
			}
		}";

		// Run the graphql query as a logged out user.
		$actual = $this->graphql( compact( 'query' ) );

		$expected = [
			'user' => [
				'avatar'            => [
					'size' => 96,
				],
				'capKey'            => null,
				'capabilities'      => null,
				'description'       => $user_args['description'],
				'email'             => null,
				'extraCapabilities' => null,
				'firstName'         => $user_args['first_name'],
				'id'                => $global_id,
				'locale'            => null,
				'name'              => $user->data->display_name,
				'nickname'          => null,
				'posts'             => [
					'edges' => [
						[
							'node' => [
								'databaseId' => $post_id,
							],
						],
					],
				],
				'registeredDate'    => null,
				'roles'             => null,
				'slug'              => $user->data->user_nicename,
				'url'               => $user_args['user_url'],
				'username'          => null,
			],
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

		// Run the graphql query after the post is deleted.
		wp_delete_post( $post_id );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['user'] );
	}

	/**
	 * This tests a single user with pages connection.
	 *
	 * @since 0.0.5
	 */
	public function testWithPages() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory()->post->create(
			[
				'post_author' => $user_id,
				'post_type'   => 'page',
			]
		);

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
		$actual = $this->graphql( compact( 'query' ) );

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

		// Test when the page is deleted.
		wp_delete_post( $post_id );

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['user'] );
	}

	/**
	 * This tests a single user with mediaItems connection.
	 *
	 * @since 0.0.5
	 */
	public function testWithMedia() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory->post->create(
			[
				'post_author' => $user_id,
				'post_type'   => 'attachment',
			]
		);

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
		 * Run the GraphQL query unauthenticated.
		 */
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['user'] );

		// Try again as an authenticated user.
		wp_set_current_user( $user_id );

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

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Tests a query for non existent user.
	 *
	 * @since 0.0.5
	 */
	public function testWhereUserDoesNotExist() {
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
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'user' => null,
		];

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function dataProviderHasPosts() {
		return [
			[
				'has_posts' => true,
			],
			[
				'has_posts' => false,
			],
		];
	}

	/**
	 * @throws \Exception
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
		$this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_author' => $admin->ID,
				'post_status' => 'publish',
			]
		);

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
			'id'       => \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ),
			'userId'   => $admin->ID,
			'username' => $admin->user_login,
			'slug'     => $admin->user_nicename,
			'uri'      => str_ireplace( home_url(), '', get_author_posts_url( $admin->ID ) ),
			'email'    => $admin->user_email,
		];

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $user_data['user_email'],
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString'] );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt'] );
		$this->assertSame( $expected_user, $actual['data']['userByEmail'] );
		$this->assertSame( $expected_user, $actual['data']['userById'] );
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType'] );
		$this->assertSame( $expected_user, $actual['data']['userBySlug'] );
		$this->assertSame( $expected_user, $actual['data']['userByUri'] );
		$this->assertSame( $expected_user, $actual['data']['userByUsername'] );

		wp_set_current_user( 0 );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $user_data['user_email'],
				],
			]
		);

		// As a public user, the email and username should not be returned when querying
		// for a user. Our expectation is null for these fields.
		$expected_user['username'] = null;
		$expected_user['email']    = null;

		/**
		 * A subscriber doesn't have permission to query a user
		 * by email so there SHOULD be errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString'] );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt'] );
		$this->assertSame( $expected_user, $actual['data']['userById'] );
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType'] );
		$this->assertSame( $expected_user, $actual['data']['userBySlug'] );
		$this->assertSame( $expected_user, $actual['data']['userByUri'] );

		// Cannot query user by email as a non-authed user
		$this->assertNull( $actual['data']['userByEmail'] );

		// Cannot query user by username as a non-authed user
		$this->assertNull( $actual['data']['userByUsername'] );
	}

	/**
	 * @throws \Exception
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
		$this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_author' => $admin->ID,
				'post_status' => 'publish',
			]
		);

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
			'id'       => \GraphQLRelay\Relay::toGlobalId( 'user', $admin->ID ),
			'userId'   => $admin->ID,
			'username' => $admin->user_login,
			'slug'     => $admin->user_nicename,
			'uri'      => str_ireplace( home_url(), '', get_author_posts_url( $admin->ID ) ),
			'email'    => $admin->user_email,
		];

		wp_set_current_user( 0 );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// As a public user, the email and username should not be returned when querying
		// for a user. Our expectation is null for these fields.
		$expected_user['username'] = null;
		$expected_user['email']    = null;

		/**
		 * A subscriber doesn't have permission to query a user
		 * by email so there SHOULD be errors
		 */
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdString'] );
		$this->assertSame( $expected_user, $actual['data']['userByDatabaseIdInt'] );
		$this->assertSame( $expected_user, $actual['data']['userById'] );
		$this->assertSame( $expected_user, $actual['data']['userByIdWithType'] );
		$this->assertSame( $expected_user, $actual['data']['userBySlug'] );
		$this->assertSame( $expected_user, $actual['data']['userByUri'] );

		// Cannot query user by email as a non-authed user
		$this->assertNull( $actual['data']['userByEmail'] );

		// Cannot query user by username as a non-authed user
		$this->assertNull( $actual['data']['userByUsername'] );
	}

	/**
	 * @throws \Exception
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
		$this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_author' => $subscriber->ID,
				'post_status' => 'publish',
			]
		);

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

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$expected_user = [
			'id'       => \GraphQLRelay\Relay::toGlobalId( 'user', $subscriber->ID ),
			'userId'   => $subscriber->ID,
			'username' => $subscriber->user_login,
			'slug'     => $subscriber->user_nicename,
			'uri'      => str_ireplace( home_url(), '', get_author_posts_url( $subscriber->ID ) ),
			'email'    => $subscriber->user_email,
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


	public function testQueryNonUserAsUserReturnsNull() {
		$query = '
		query userByUri($uri: ID!) {
			user(id: $uri, idType: URI) {
				... on User {
					databaseId
				}
			}
		}
		';

		wp_set_current_user( $this->admin );

		// Test page.
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['user'] );

		// Test term.
		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
			]
		);

		$uri = wp_make_link_relative( get_term_link( $term_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['user'] );

		// Test User.
		$uri = wp_make_link_relative( get_author_posts_url( $this->admin ) );

		// Test post type archive
		$uri = wp_make_link_relative( get_post_type_archive_link( 'post' ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['user'] );
	}
}
