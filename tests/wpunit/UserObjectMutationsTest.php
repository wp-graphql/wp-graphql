<?php

class UserObjectMutationsTest extends \Codeception\TestCase\WPTestCase {

	public $first_name;
	public $last_name;
	public $client_mutation_id;

	public $author;
	public $admin;
	public $subscriber;

	public function setUp() {
		// before
		parent::setUp();

		// Allow new users to be created for multisite tests
		update_option( 'add_new_users', true );
		add_filter( 'map_meta_cap', [ $this, 'filter_multisite_edit_user_capabilities' ], 1, 4 );
		remove_all_filters( 'enable_edit_any_user_configuration' );
		add_filter( 'enable_edit_any_user_configuration', '__return_true' );

		$this->client_mutation_id = 'someUniqueId';
		$this->first_name         = 'Test';
		$this->last_name          = 'User';

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

	}

	public function tearDown() {
		// your tear down methods here

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

			if ( $capability != 'do_not_allow' ) {
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

		$mutation = '
		mutation createUser($input:CreateUserInput!) {
		  createUser(input:$input){
			clientMutationId
			user{
			  firstName
			  lastName
			  roles
			  email
			  username
			}
		  }
		}';

		$variables = [
			'input' => [
				'clientMutationId' => $this->client_mutation_id,
				'username'         => $args['username'],
				'email'            => $args['email'],
				'firstName'        => $this->first_name,
				'lastName'         => $this->last_name,
				'roles'            => [
					'administrator',
				]
			]
		];

		$actual = do_graphql_request( $mutation, 'createUser', $variables );

		return $actual;

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
		$this->assertNotEmpty( $actual['errors'] );

	}

	public function testCreateUserObjectWithProperCapabilities() {

		wp_set_current_user( $this->admin );

		$username = 'rusercreatedbyadmin';
		$email    = 'UserCreatedByAdmin@test.com';

		$actual = $this->createUserMutation( [
			'username' => $username,
			'email'    => $email,
		] );

		$expected = [
			'data' => [
				'createUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'firstName' => $this->first_name,
						'lastName'  => $this->last_name,
						'roles'     => [
							'administrator',
						],
						'email'     => $email,
						'username'  => $username,
					]
				]
			]
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testPreventDuplicateUsernames() {

		wp_set_current_user( $this->admin );

		$username = 'duplicateUsername';

		$this->factory->user->create( [
			'user_login' => $username
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

		$mutation = '
		mutation updateUser($input:UpdateUserInput!) {
		  updateUser(input:$input){
			clientMutationId
			user{
			  firstName
			  lastName
			  roles
			  username
			  email
			  userId
			  id
			}
		  }
		}
		';

		$updated_email     = 'testUserUpdated@test.com';
		$updated_firstname = 'Testupdate';
		$updated_lastname  = 'Updatetest';

		$variables = [
			'input' => [
				'id'               => $guid,
				'clientMutationId' => $this->client_mutation_id,
				'email'            => $updated_email,
				'firstName'        => $updated_firstname,
				'lastName'         => $updated_lastname,
				'roles'            => [
					'administrator',
				]
			]
		];

		$actual = do_graphql_request( $mutation, 'updateUser', $variables );

		$expected = [
			'data' => [
				'updateUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'firstName' => $updated_firstname,
						'lastName'  => $updated_lastname,
						'roles'     => [
							'administrator',
						],
						'username'  => $user_login,
						'email'     => $updated_email,
						'userId'    => $user_id,
						'id'        => $guid,
					]
				]
			]
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testDeleteUserWithCapability() {

		wp_set_current_user( $this->admin );

		$username = 'user_to_delete_with_capability';

		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$mutation = '
		mutation deleteUser($input:DeleteUserInput!) {
		  deleteUser(input:$input){
			clientMutationId
			user{
			  username
			  userId
			  id
			}
		  }
		}
		';

		$variables = [
			'input' => [
				'id'               => $guid,
				'clientMutationId' => $this->client_mutation_id,
			]
		];

		$actual = do_graphql_request( $mutation, 'deleteUser', $variables );

		$expected = [
			'data' => [
				'deleteUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'username' => $username,
						'userId'   => $user_id,
						'id'       => $guid,
					]
				]
			]
		];

		$this->assertEquals( $expected, $actual );

		$user_obj_after_delete = get_user_by( 'id', $user_id );

		/**
		 * Make sure the user actually got deleted.
		 */
		$this->assertEquals( false, $user_obj_after_delete );

	}

	public function testDeleteUserWithoutCapability() {

		$username = 'user_to_delete_without_capability';

		$user_id = $this->factory->user->create( [
			'role'       => 'subscriber',
			'user_login' => $username,
		] );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$mutation = '
		mutation deleteUser($input:DeleteUserInput!) {
		  deleteUser(input:$input){
			clientMutationId
			user{
			  username
			  userId
			  id
			}
		  }
		}
		';

		$variables = [
			'input' => [
				'id'               => $guid,
				'clientMutationId' => $this->client_mutation_id,
			]
		];

		$actual = do_graphql_request( $mutation, 'deleteUser', $variables );

		$this->assertEquals( 'Sorry, you are not allowed to delete users.', $actual['errors'][0]['message'] );

		$user_obj_after_delete = get_user_by( 'id', $user_id );

		/**
		 * Make sure the user didn't actually get deleted.
		 */
		$this->assertNotEquals( false, $user_obj_after_delete );

	}

	public function testCreateUserWithExtraFields() {

		$username    = 'userwithextrafields';
		$email       = 'userWithExtraFields@test.com';
		$nicename    = 'user NiceName';
		$url         = 'http://wpgraphql.com';
		$date        = date( "Y-m-d H:i:s" );
		$displayName = 'User Display Name';
		$nickname    = 'User Nickname';
		$description = 'User Description';
		$locale      = 'en';

		wp_set_current_user( $this->admin );

		$variables = [
			'input' => [
				'firstName'        => $this->first_name,
				'lastName'         => $this->last_name,
				'clientMutationId' => $this->client_mutation_id,
				'username'         => $username,
				'email'            => $email,
				'password'         => 'somePassword',
				'websiteUrl'       => $url,
				'nicename'         => $nicename,
				'displayName'      => $displayName,
				'nickname'         => $nickname,
				'description'      => $description,
				'registered'       => $date,
				'locale'           => $locale,
				'roles'            => [
					'administrator',
				],
			],
		];

		$mutation = '
		mutation createAndGetUser( $input:CreateUserInput! ) {
			createUser( input: $input ) {
				clientMutationId
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

		$actual = do_graphql_request( $mutation, 'createAndGetUser', $variables );

		$expected = [
			'data' => [
				'createUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'firstName'   => $this->first_name,
						'lastName'    => $this->last_name,
						'email'       => $email,
						'username'    => $username,
						'nicename'    => strtolower( str_ireplace( ' ', '-', $nicename ) ),
						'name'        => $displayName,
						'nickname'    => $nickname,
						'description' => $description,
						'locale'      => $locale
					]
				]
			]
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testCreateUserWithoutRoles() {

		$mutation = '
		mutation createUserWithoutRoles( $input:CreateUserInput! ) {
			createUser( input: $input ) {
				clientMutationId
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
				'firstName'        => $this->first_name,
				'lastName'         => $this->last_name,
				'username'         => 'createuserwithoutroles',
				'clientMutationId' => $this->client_mutation_id,
			],
		];

		wp_set_current_user( $this->admin );

		$actual = do_graphql_request( $mutation, 'createUserWithoutRoles', $variables );

		$expected = [
			'data' => [
				'createUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'firstName' => $this->first_name,
						'lastName'  => $this->last_name,
						'username'  => 'createuserwithoutroles',
					],
				],
			],
		];

		$this->assertEquals( $actual, $expected );

	}

	public function testUpdateUserWithInvalidRole() {

		$mutation = '
		mutation updateUserWithInvalidRole( $input:UpdateUserInput! ) {
			updateUser( input: $input )	{
				clientMutationId
				user {
					id
					name
				}
			}
		}
		';

		$variables = [
			'input' => [
				'clientMutationId' => $this->client_mutation_id,
				'id'               => \GraphQLRelay\Relay::toGlobalId( 'user', $this->author ),
				'roles'            => [
					'invalidRole'
				],
			],
		];

		wp_set_current_user( $this->admin );

		$actual = do_graphql_request( $mutation, 'updateUserWithInvalidRole', $variables );

		$this->assertTrue( ( 'Sorry, you are not allowed to give this the following role: invalidRole.' === $actual['errors'][0]['message'] ) || ( 'Internal server error' === $actual['errors'][0]['message'] ) );

	}

	public function registerUserMutation( $args ) {

		$mutation = '
		mutation registerUser($input:RegisterUserInput!) {
		  registerUser(input:$input) {
		    clientMutationId
			user {
			  username
			  email
			  roles
			}
		  }
		}';

		$variables = [
			'input' => [
				'clientMutationId' => $this->client_mutation_id,
				'username'         => $args['username'],
				'email'            => $args['email'],
			]
		];

		$actual = do_graphql_request( $mutation, 'registerUser', $variables );

		return $actual;

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

		$username     = 'userDoesNotExist';
		$email        = 'emailDoesNotExist@test.com';
		$default_role = get_option( 'default_role' );

		/**
		 * Enable new user registration.
		 */
		update_option( 'users_can_register', 1 );

		/**
		 * Run the mutation.
		 */
		$actual = $this->registerUserMutation( [
			'username' => $username,
			'email'    => $email,
		] );

		$expected = [
			'data' => [
				'registerUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'username' => $username,
						'email'    => $email,
						'roles'    => [
							$default_role,
						],
					]
				]
			]
		];

		$this->assertEquals( $expected, $actual );

	}

	public function resetUserPasswordMutation( $args ) {

		$mutation = '
		mutation resetUserPassword($input:ResetUserPasswordInput!) {
			resetUserPassword(input:$input){
				clientMutationId
				user {
					username
					email
					roles
				}
			}
		}';

		$variables = [
			'input' => [
				'clientMutationId' => $this->client_mutation_id,
				'key'              => $args['key'],
				'login'            => $args['login'],
				'password'         => $args['password'],
			]
		];

		return do_graphql_request( $mutation, 'resetUserPassword', $variables );

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

		$actual = $this->resetUserPasswordMutation( $args );

		$expected = [
			'data' => [
				'resetUserPassword' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'username' => $login,
						'email'    => $email,
						'roles'    => $roles,
					],
				],
			],
		];

		$this->assertEquals( $actual, $expected );

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
		$authenticated_user   = wp_authenticate( $login, $new_password );
		$was_reset_successful = ! is_wp_error( $authenticated_user );

		/**
		 * Assert that password was successfully reset.
		 */
		$this->assertTrue( $was_reset_successful );

	}

	public function sendPasswordResetEmailMutation( $username ) {
		$mutation  = '
		mutation sendPasswordResetEmail( $input:SendPasswordResetEmailInput! ) {
			sendPasswordResetEmail( input: $input ) {
				clientMutationId
				user {
					username
					email
					roles
				} 
			}
		}
		';
		$variables = [
			'input' => [
				'clientMutationId' => $this->client_mutation_id,
				'username'         => $username,
			],
		];

		return do_graphql_request( $mutation, 'sendPasswordResetEmail', $variables );
	}

	public function testSendPasswordResetEmailWithInvalidUsername() {
		$username = 'userDoesNotExist';
		// Run the mutation, passing in an invalid username.
		$actual = $this->sendPasswordResetEmailMutation( $username );
		/**
		 * We're asserting that this will properly return an error
		 * because this user does not exist.
		 */
		$this->assertNotEmpty( $actual['errors'] );
	}

	public function testSendPasswordResetEmailResponseWithUsername() {
		$user     = get_userdata( $this->subscriber );
		$username = $user->user_login;
		// Run the mutation, passing in a valid username.
		$actual   = $this->sendPasswordResetEmailMutation( $username );
		$expected = $this->getSendPasswordResetEmailExpected();
		/**
		 * Assert that the expected user data was returned.
		 */
		$this->assertEquals( $expected, $actual );
	}

	public function testSendPasswordResetEmailResponseWithEmail() {
		$user  = get_userdata( $this->subscriber );
		$email = $user->user_email;
		// Run the mutation, passing in a valid email address.
		$actual   = $this->sendPasswordResetEmailMutation( $email );
		$expected = $this->getSendPasswordResetEmailExpected();
		/**
		 * Assert that the expected user data was returned.
		 */
		$this->assertEquals( $expected, $actual );
	}

	public function getSendPasswordResetEmailExpected() {
		$user     = get_userdata( $this->subscriber );
		$username = $user->user_login;
		$email    = $user->user_email;
		$roles    = $user->roles;

		return [
			'data' => [
				'sendPasswordResetEmail' => [
					'clientMutationId' => $this->client_mutation_id,
					'user'             => [
						'username' => $username,
						'email'    => $email,
						'roles'    => $roles,
					]
				]
			]
		];
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
		$update_email_sent = function () use ( &$email_sent ) {
			$email_sent = false;
		};
		add_action( 'wp_mail_failed', $update_email_sent );
		$this->sendPasswordResetEmailMutation( $mutation_arg );
		remove_action( 'wp_mail_failed', $update_email_sent );

		return $email_sent;
	}

}
