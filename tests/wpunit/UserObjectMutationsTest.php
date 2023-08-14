<?php

class UserObjectMutationsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $first_name;
	public $last_name;

	public $author;
	public $admin;
	public $subscriber;

	public function setUp(): void {
		// before
		parent::setUp();

		WPGraphQL::clear_schema();

		// Allow new users to be created for multisite tests
		update_option( 'add_new_users', true );
		add_filter( 'map_meta_cap', [ $this, 'filter_multisite_edit_user_capabilities' ], 1, 4 );
		remove_all_filters( 'enable_edit_any_user_configuration' );
		add_filter( 'enable_edit_any_user_configuration', '__return_true' );

		$this->first_name = 'Test';
		$this->last_name  = 'User';

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->factory()->post->create( [
			'post_author' => $this->author,
		] );

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

	}

	public function tearDown(): void {
		// your tear down methods here
		reset_phpmailer_instance();

		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	/**
	 * This filters the capabilities so that our test user can create/edit/delete users in
	 * multisite.
	 *
	 * @param $caps
	 * @param $cap
	 * @param $user_id
	 * @param $args
	 *
	 * @return mixed
	 */
	function filter_multisite_edit_user_capabilities( $caps, $cap, $user_id, $args ) {

		foreach ( $caps as $key => $capability ) {

			if ( 'do_not_allow' !== $capability ) {
				continue;
			}

			switch ( $cap ) {
				case 'edit_user':
				case 'edit_users':
					$caps[ $key ] = 'edit_users';
					break;
				case 'delete_user':
				case 'delete_users':
					$caps[ $key ] = 'delete_users';
					break;
				case 'create_users':
					$caps[ $key ] = $cap;
					break;
			}
		}

		return $caps;
	}

	public function createUserMutation( $args ) {

		$query = '
		mutation createUser($input:CreateUserInput!) {
			createUser(input:$input){
				user{
					firstName
					lastName
					roles {
						nodes {
							name
						}
					}
					email
					username
				}
			}
		}';

		$variables = [
			'input' => [
				'username'  => $args['username'],
				'email'     => $args['email'],
				'firstName' => $this->first_name,
				'lastName'  => $this->last_name,
				'roles'     => [
					'administrator',
				],
			],
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	public function testCreateUserObjectWithoutProperCapabilities() {

		/**
		 * Set the current user as the subscriber role so we
		 * can test the mutation and make sure they cannot create a post
		 * since they don't have proper permissions
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Run the mutation.
		 */
		$actual = $this->createUserMutation( [
			'username' => 'userDoesNotExist',
			'email'    => 'emailDoesNotExist@test.com',
		] );

		/**
		 * We're asserting that this will properly return an error
		 * because this user doesn't have permissions to create a user as a
		 * subscriber
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	public function testCreateUserObjectWithProperCapabilities() {

		wp_set_current_user( $this->admin );

		$username = 'rusercreatedbyadmin';
		$email    = 'UserCreatedByAdmin@test.com';

		$actual = $this->createUserMutation( [
			'username' => $username,
			'email'    => $email,
		] );

		codecept_debug( $actual );

		$expected = [
			'createUser' => [
				'user' => [
					'firstName' => $this->first_name,
					'lastName'  => $this->last_name,
					'roles'     => [
						'nodes' => [
							[
								'name' => 'administrator',
							],
						],
					],
					'email'     => $email,
					'username'  => $username,
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

	}

	public function testPreventDuplicateUsernames() {

		wp_set_current_user( $this->admin );

		$username = 'duplicateUsername';

		$this->factory->user->create( [
			'user_login' => $username,
		] );

		$second_user = $this->createUserMutation( [
			'username' => $username,
			'email'    => 'secondUsername@test.com',
		] );

		$this->assertEquals( $second_user['errors'][0]['message'], 'Sorry, that username already exists!' );

	}

	public function testPreventDuplicateEmails() {

		wp_set_current_user( $this->admin );

		$email = 'duplicateEmailAddress@test.com';

		$this->factory->user->create( [
			'user_email' => $email,
		] );

		$second_user = $this->createUserMutation( [
			'username' => 'testDuplicateEmail2',
			'email'    => $email,
		] );

		$this->assertEquals( $second_user['errors'][0]['message'], 'Sorry, that email address is already used!' );

	}

	public function testInvalidEmailAddress() {

		wp_set_current_user( $this->admin );

		$user = $this->createUserMutation( [
			'username' => 'testInvalidEmail',
			'email'    => 'notanemail',
		] );

		$this->assertEquals( $user['errors'][0]['message'], 'The email address you are trying to use is invalid' );

	}

	public function testUpdateUser() {

		wp_set_current_user( $this->admin );

		$user_login = 'test_user_update';
		$user_email = 'testUserUpdate@test.com';
		$user_role  = 'editor';
		$first_name = 'Test';
		$last_name  = 'User';

		$args = [
			'user_pass'  => null,
			'user_login' => $user_login,
			'user_email' => $user_email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'role'       => $user_role,
		];

		$user_id = $this->factory->user->create( $args );
		$guid    = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$user_object = get_user_by( 'ID', $user_id );

		$this->assertEquals( $user_object->user_login, $user_login );
		$this->assertEquals( $user_object->user_email, $user_email );
		$this->assertEquals( $user_object->roles[0], $user_role );
		$this->assertEquals( $user_object->first_name, $first_name );
		$this->assertEquals( $user_object->last_name, $last_name );

		$query = '
		mutation updateUser($input:UpdateUserInput!) {
			updateUser(input:$input){
				user{
					firstName
					lastName
					roles {
						nodes {
							name
						}
					}
					username
					email
					databaseId
					id
				}
			}
		}
		';

		$updated_email     = 'testUserUpdated@test.com';
		$updated_firstname = 'Testupdate';
		$updated_lastname  = 'Updatetest';

		// Test with no id
		$variables = [
			'input' => [
				'id'        => '',
				'email'     => $updated_email,
				'firstName' => $updated_firstname,
				'lastName'  => $updated_lastname,
				'roles'     => [
					'administrator',
				],
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with bad id
		$variables['input']['id'] = 999999;

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with database ID.
		$variables['input']['id'] = $user_id;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'updateUser' => [
				'user' => [
					'firstName'  => $updated_firstname,
					'lastName'   => $updated_lastname,
					'roles'      => [
						'nodes' => [
							[
								'name' => 'administrator',
							],
						],
					],
					'username'   => $user_login,
					'email'      => $updated_email,
					'databaseId' => $user_id,
					'id'         => $guid,
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

		// Test with global ID.
		$updated_email = 'testUserUpdated2@test.com';

		$variables['input'] = [
			'id'    => $guid,
			'email' => $updated_email,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEquals( $guid, $actual['data']['updateUser']['user']['id'] );
		$this->assertEquals( $user_id, $actual['data']['updateUser']['user']['databaseId'] );
		$this->assertEquals( $updated_email, $actual['data']['updateUser']['user']['email'] );
	}

	public function testDeleteUserWithoutCapability() {

		$username = 'user_to_delete_without_capability';

		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$query = '
		mutation deleteUser($input:DeleteUserInput!) {
			deleteUser(input:$input){
				user{
					username
					databaseId
					id
				}
			}
		}
		';

		$variables = [
			'input' => [
				'id' => $guid,
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 'Sorry, you are not allowed to delete users.', $actual['errors'][0]['message'] );

		$user_obj_after_delete = get_user_by( 'id', $user_id );

		/**
		 * Make sure the user didn't actually get deleted.
		 */
		$this->assertNotEquals( false, $user_obj_after_delete );

	}

	public function testDeleteUserWithCapability() {

		wp_set_current_user( $this->admin );

		$username = 'user_to_delete_with_capability';

		$query = '
		mutation deleteUser($input:DeleteUserInput!) {
			deleteUser(input:$input){
				deletedId
				user {
					username
					databaseId
					id
				}
			}
		}
		';

		// Test with no Id
		$variables = [
			'input' => [
				'id' => '',
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		// Test with bad Id
		$variables['input']['id'] = 999999;

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		// Test with databaseId
		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$variables['input']['id'] = $user_id;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'deleteUser' => [
				'deletedId' => $guid,
				'user'      => [
					'username'   => $username,
					'databaseId' => $user_id,
					'id'         => $guid,
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

		$user_obj_after_delete = get_user_by( 'id', $user_id );

		/**
		 * Make sure the user actually got deleted.
		 */
		$this->assertEquals( false, $user_obj_after_delete );

		// Test with global Id
		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$variables['input']['id'] = $guid;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'deleteUser' => [
				'deletedId' => $guid,
				'user'      => [
					'username'   => $username,
					'databaseId' => $user_id,
					'id'         => $guid,
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

		$user_obj_after_delete = get_user_by( 'id', $user_id );

		/**
		 * Make sure the user actually got deleted.
		 */
		$this->assertEquals( false, $user_obj_after_delete );
	}

	public function testDeleteUserWithReassign() {

		wp_set_current_user( $this->admin );

		$username = 'user_to_delete_with_reassign';

		$query = '
		mutation deleteUser($input:DeleteUserInput!) {
			deleteUser(input:$input){
				user {
					databaseId
				}
			}
		}
		';

		// Test with no Id
		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );

		$post = $this->factory()->post->create( [
			'post_author' => $user_id,
		]);

		// Test with bad id.
		$variables = [
			'input' => [
				'reassignId' => 999999,
				'id'         => $user_id,
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		// Test with databaseId
		$variables['input']['reassignId'] = $this->admin;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $user_id, $actual['data']['deleteUser']['user']['databaseId'] );

		$post_obj_after_delete = get_post( $post );

		// Make sure the user actually got reassigned.
		$this->assertEquals( $this->admin, $post_obj_after_delete->post_author );

		// Test with global Id
		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );
		wp_update_post( [
			'ID'          => $post,
			'post_author' => $user_id,
		] );

		$this->assertEquals( $user_id, get_post( $post )->post_author );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $this->admin );

		$variables['input']['id']         = $user_id;
		$variables['input']['reassignId'] = $guid;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( $user_id, $actual['data']['deleteUser']['user']['databaseId'] );

		$post_obj_after_delete = get_post( $post );

		// Make sure the user actually got reassigned.
		$this->assertEquals( $this->admin, $post_obj_after_delete->post_author );
	}

	public function testCreateUserWithExtraFields() {

		$username    = 'userwithextrafields';
		$email       = 'userWithExtraFields@test.com';
		$nicename    = 'user NiceName';
		$url         = 'http://wpgraphql.com';
		$date        = date( 'Y-m-d H:i:s' );
		$displayName = 'User Display Name';
		$nickname    = 'User Nickname';
		$description = 'User Description';
		$locale      = 'en';

		wp_set_current_user( $this->admin );

		$variables = [
			'input' => [
				'firstName'   => $this->first_name,
				'lastName'    => $this->last_name,
				'username'    => $username,
				'email'       => $email,
				'password'    => 'somePassword',
				'websiteUrl'  => $url,
				'nicename'    => $nicename,
				'displayName' => $displayName,
				'nickname'    => $nickname,
				'description' => $description,
				'registered'  => $date,
				'locale'      => $locale,
				'roles'       => [
					'administrator',
				],
			],
		];

		$query = '
		mutation createAndGetUser( $input:CreateUserInput! ) {
			createUser( input: $input ) {
				user {
					firstName
					lastName
					email
					username
					nicename
					name
					nickname
					description
					locale
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'createUser' => [
				'user' => [
					'firstName'   => $this->first_name,
					'lastName'    => $this->last_name,
					'email'       => $email,
					'username'    => $username,
					'nicename'    => strtolower( str_ireplace( ' ', '-', $nicename ) ),
					'name'        => $displayName,
					'nickname'    => $nickname,
					'description' => $description,
					'locale'      => $locale,
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

	}

	public function testCreateUserWithoutRoles() {

		$query = '
		mutation createUserWithoutRoles( $input:CreateUserInput! ) {
			createUser( input: $input ) {
				user {
					firstName
					lastName
					username
				}
			}
		}
		';

		$variables = [
			'input' => [
				'firstName' => $this->first_name,
				'lastName'  => $this->last_name,
				'username'  => 'createuserwithoutroles',
			],
		];

		wp_set_current_user( $this->admin );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$expected = [
			'createUser' => [
				'user' => [
					'firstName' => $this->first_name,
					'lastName'  => $this->last_name,
					'username'  => 'createuserwithoutroles',
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

	}

	public function testUpdateUserWithInvalidRole() {

		$query = '
		mutation updateUserWithInvalidRole( $input:UpdateUserInput! ) {
			updateUser( input: $input )	{
				user {
					id
					name
				}
			}
		}
		';

		$variables = [
			'input' => [
				'id'    => \GraphQLRelay\Relay::toGlobalId( 'user', $this->author ),
				'roles' => [
					'invalidRole',
				],
			],
		];

		wp_set_current_user( $this->admin );

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertTrue( ( 'Sorry, you are not allowed to give this the following role: invalidRole.' === $actual['errors'][0]['message'] ) || ( 'Internal server error' === $actual['errors'][0]['message'] ) );

	}

	public function registerUserMutation( $args ) {

		$query = '
		mutation registerUser($input:RegisterUserInput!) {
			registerUser(input:$input) {
				user {
					name
					slug
				}
			}
		}';

		$variables = [
			'input' => [
				'username' => $args['username'],
				'email'    => $args['email'],
			],
		];

		if ( ! empty( $args['password'] ) ) {
			$variables['input']['password'] = $args['password'];
		}

		return graphql( compact( 'query', 'variables' ) );
	}

	public function testRegisterUserWithRegistrationDisabled() {

		/**
		 * Disable new user registration.
		 */
		update_option( 'users_can_register', 0 );

		/**
		 * Run the mutation.
		 */
		$actual = $this->registerUserMutation( [
			'username' => 'userDoesNotExist',
			'email'    => 'emailDoesNotExist@test.com',
		] );

		/**
		 * We're asserting that this will properly return an error
		 * because registration is disabled.
		 */
		$this->assertNotEmpty( $actual['errors'] );

	}

	public function testRegisterUserWithRegistrationEnabled() {

		$username = 'userDoesNotExist';
		$email    = 'emailDoesNotExist@test.com';

		/**
		 * Enable new user registration.
		 */
		if ( is_multisite() ) {
			// multisite doesn't like uppercase letters in usernames 🤷‍️
			$username = strtolower( $username );
			update_site_option( 'registration', 'user' );
		} else {
			update_option( 'users_can_register', 1 );
		}

		/**
		 * Run the mutation.
		 */
		$actual = $this->registerUserMutation( [
			'username' => $username,
			'email'    => $email,
		] );

		$expected = [
			'registerUser' => [
				'user' => [
					'name' => $username,
					'slug' => strtolower( $username ),
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

	}

	public function resetUserPasswordMutation( $args ) {

		$query = '
		mutation resetUserPassword($input:ResetUserPasswordInput!) {
			resetUserPassword(input:$input){
				user {
					username
					email
					roles {
						nodes {
							name
						}
					}
				}
			}
		}';

		$variables = [
			'input' => [
				'key'      => $args['key'],
				'login'    => $args['login'],
				'password' => $args['password'],
			],
		];

		return $this->graphql( compact( 'query', 'variables' ) );

	}

	public function testResetUserPasswordWithInvalidLoginAndKey() {

		$args = [
			'login'    => 'invalidLogin',
			'key'      => 'invalidKey',
			'password' => 'newPassword123',
		];

		$actual = $this->resetUserPasswordMutation( $args );

		/**
		 * We're asserting that this will properly return an error
		 * because an invalid login and reset key were provided.
		 */
		$this->assertNotEmpty( $actual['errors'] );

	}

	public function testResetUserPasswordWithInvalidKey() {

		$user  = get_userdata( $this->subscriber );
		$login = $user->user_login;

		$args = [
			'key'      => 'invalidKey',
			'login'    => $login,
			'password' => 'newPassword123',
		];

		$actual = $this->resetUserPasswordMutation( $args );

		/**
		 * We're asserting that this will properly return an error
		 * because an invalid reset key was provided.
		 */
		$this->assertNotEmpty( $actual['errors'] );

	}

	public function testResetUserPasswordResponse() {

		$user         = get_userdata( $this->subscriber );
		$key          = get_password_reset_key( $user );
		$login        = $user->user_login;
		$email        = $user->user_email;
		$roles        = $user->roles;
		$new_password = 'newPassword123';

		$args = [
			'key'      => $key,
			'login'    => $login,
			'password' => $new_password,
		];

		// Ensure user is logged out
		wp_set_current_user( 0 );

		$actual = $this->resetUserPasswordMutation( $args );

		$role_nodes = [];
		foreach ( $roles as $role ) {
			$role_nodes[] = [
				'name' => $role,
			];
		}

		$expected = [
			'resetUserPassword' => [
				'user' => [
					'username' => $login,
					'email'    => $email,
					'roles'    => [
						'nodes' => $role_nodes,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );

	}

	public function testResetUserPassword() {

		/**
		 * Initialize old password to ensure it's different from
		 * what we're resetting it to.
		 */
		wp_set_password( 'oldPassword123', $this->subscriber );

		$user         = get_userdata( $this->subscriber );
		$key          = get_password_reset_key( $user );
		$login        = $user->user_login;
		$new_password = 'newPassword123';

		$args = [
			'key'      => $key,
			'login'    => $login,
			'password' => $new_password,
		];

		$this->resetUserPasswordMutation( $args );

		// Try to authenticate user using new password.
		wp_set_current_user( 0 );

		$authenticated_user   = wp_authenticate( $login, $new_password );
		$was_reset_successful = ! is_wp_error( $authenticated_user );

		/**
		 * Assert that password was successfully reset.
		 */
		$this->assertTrue( $was_reset_successful );

	}

	public function sendPasswordResetEmailMutation( $username ) {
		$query = '
		mutation sendPasswordResetEmail( $input:SendPasswordResetEmailInput! ) {
			sendPasswordResetEmail( input: $input ) {
				user {
					databaseId
				}
				success
			}
		}
		';

		$variables = [
			'input' => [
				'username' => $username,
			],
		];

		return $this->graphql( compact( 'query', 'variables' ) );
	}

	public function testSendPasswordResetEmailWithInvalidUsername() {
		$username = 'userDoesNotExist';
		// Run the mutation, passing in an invalid username.
		$actual = $this->sendPasswordResetEmailMutation( $username );

		// This should return succesful with a notice in the log.
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['sendPasswordResetEmail']['success'] );
		$this->assertEquals( "Invalid username.", $actual['extensions']['debug'][0]['message'] );
		$this->assertEmpty( $actual['data']['sendPasswordResetEmail']['user'] );
	}

	public function testSendPasswordResetEmailWithUsername() {
		$user     = get_userdata( $this->subscriber );
		$username = $user->user_login;

		// Test when user is logged out.
		wp_set_current_user( 0 );
		$actual = $this->sendPasswordResetEmailMutation( $username );

		// No debug message means this was a real success.
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['sendPasswordResetEmail']['success'] );
		$this->assertEmpty( $actual['extensions']['debug'] );
		$this->assertEmpty( $actual['data']['sendPasswordResetEmail']['user'] );

		// Test when user has permissions
		wp_set_current_user( $this->admin );

		$actual = $this->sendPasswordResetEmailMutation( $username );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['sendPasswordResetEmail']['success'] );
		// @todo deprecate.
		$this->assertEquals( $this->subscriber, $actual['data']['sendPasswordResetEmail']['user']['databaseId'] );
	}

	public function testSendPasswordResetEmailWithEmail() {
		$user  = get_userdata( $this->subscriber );
		$email = $user->user_email;
		// Run the mutation, passing in a valid email address.
		// Test when user is logged out.
		wp_set_current_user( 0 );
		$actual = $this->sendPasswordResetEmailMutation( $email );

		// No debug message means this was a real success.
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['sendPasswordResetEmail']['success'] );
		$this->assertEmpty( $actual['extensions']['debug'] );
		$this->assertEmpty( $actual['data']['sendPasswordResetEmail']['user'] );

		// Test when user has permissions
		wp_set_current_user( $this->admin );

		$actual = $this->sendPasswordResetEmailMutation( $email );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( true, $actual['data']['sendPasswordResetEmail']['success'] );
		// @todo deprecate.
		$this->assertEquals( $this->subscriber, $actual['data']['sendPasswordResetEmail']['user']['databaseId'] );
	}

	public function testSendPasswordResetEmailActivationKeyWithUsername() {
		$user     = get_userdata( $this->subscriber );
		$username = $user->user_login;
		$old_key  = $this->get_user_activation_key( $username );
		// Run the mutation, passing in a valid username.
		$this->sendPasswordResetEmailMutation( $username );
		$new_key = $this->get_user_activation_key( $username );
		/**
		 * Assert that the user activation key in the DB was updated.
		 */
		$this->assertNotEquals( $old_key, $new_key );
	}

	public function testSendPasswordResetEmailActivationKeyWithEmail() {
		$user     = get_userdata( $this->subscriber );
		$username = $user->user_login;
		$email    = $user->user_email;
		$old_key  = $this->get_user_activation_key( $username );
		// Run the mutation, passing in a valid email.
		$this->sendPasswordResetEmailMutation( $email );
		$new_key = $this->get_user_activation_key( $username );
		/**
		 * Assert that the user activation key in the DB was updated.
		 */
		$this->assertNotEquals( $old_key, $new_key );
	}

	public function get_user_activation_key( $username ) {
		global $wpdb;

		/**
		 * We can't use the WP_User object here, since it returns the cached
		 * activation key. Run a fresh DB query instead.
		 */
		return $wpdb->get_var( $wpdb->prepare( 'SELECT user_activation_key FROM wp_users WHERE user_login = %s', $username ) );
	}

	public function testSendPasswordResetEmailSentWithUsername() {
		$user     = get_userdata( $this->subscriber );
		$username = $user->user_login;
		// Run the mutation, passing in a valid username.
		$email_sent = $this->runSendPasswordResetEmailSentTest( $username );
		/**
		 * Assert that the password reset email was sent (did not fail).
		 */
		$this->assertTrue( $email_sent );
	}

	public function testSendPasswordResetEmailSentWithEmail() {
		$user  = get_userdata( $this->subscriber );
		$email = $user->user_email;
		// Run the mutation, passing in a valid email.
		$email_sent = $this->runSendPasswordResetEmailSentTest( $email );
		/**
		 * Assert that the password reset email was sent (did not fail).
		 */
		$this->assertTrue( $email_sent );
	}

	public function runSendPasswordResetEmailSentTest( $mutation_arg ) {
		$email_sent        = true;

		$update_email_sent = function ( $error ) use ( &$email_sent ) {
			codecept_debug( $error );
			$email_sent = false;
		};
		$update_email_from = function() {
			return 'valid-email@site.test';
		};

		add_action( 'wp_mail_failed', $update_email_sent );
		add_action( 'wp_mail_from', $update_email_from );

		$this->sendPasswordResetEmailMutation( $mutation_arg );

		remove_action( 'wp_mail_failed', $update_email_sent );
		remove_action( 'wp_mail_from', $update_email_from );

		return $email_sent;
	}

	public function testDisablePasswordChangedEmailsOnRegistration() {
		$was_password_change_email_sent = false;

		// If this filter is run, we know the "Password Changed" email is being sent.
		add_filter( 'password_change_email', function ( $pass_change_email ) use ( &$was_password_change_email_sent ) {
			$was_password_change_email_sent = true;
			return $pass_change_email;
		} );

		$this->registerUserMutation( [
			'username' => 'password-changed-email-test-user',
			'email'    => 'password-changed-email-test-user@example.com',
			'password' => 'password-changed-email-test-user-password',
		] );

		/**
		 * Assert that the "Password Changed" email was not sent when the user was registered.
		 */
		$this->assertFalse( $was_password_change_email_sent );
	}
}
