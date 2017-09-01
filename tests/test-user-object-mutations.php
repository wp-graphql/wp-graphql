<?php

class WP_GraphQL_Test_User_Object_Mutations extends WP_UnitTestCase {

	public $first_name;
	public $last_name;
	public $client_mutation_id;

	public $author;
	public $admin;
	public $subscriber;

	public function setUp() {

		$this->client_mutation_id = 'someUniqueId';
		$this->first_name = 'Test';
		$this->last_name = 'User';

		$this->author = $this->factory->user->create( [
			'role' => 'author',
		] );

		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$this->subscriber = $this->factory->user->create( [
			'role' => 'subscriber',
		] );

		parent::setUp();

	}

	/**
	 * Runs after each method.
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function createUserMutation( $args ) {

		$mutation = '
		mutation createUser($input:createUserInput!) {
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
				'username' => $args['username'],
				'email' => $args['email'],
				'firstName' => $this->first_name,
				'lastName' => $this->last_name,
				'roles' => [
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
			'email' => 'emailDoesNotExist@test.com',
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

		$username = 'userCreatedByAdmin';
		$email = 'userCreatedByAdmin@test.com';

		$actual = $this->createUserMutation( [
			'username' => $username,
			'email' => $email,
		]);

		$expected = [
			'data' => [
				'createUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user' => [
						'firstName' => $this->first_name,
						'lastName' => $this->last_name,
						'roles' => [
							'administrator',
						],
						'email' => $email,
						'username' => $username,
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
			'email' => 'secondUsername@test.com',
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
			'email' => $email,
		] );

		$this->assertEquals( $second_user['errors'][0]['message'], 'Sorry, that email address is already used!' );

	}

	public function testInvalidEmailAddress() {

		wp_set_current_user( $this->admin );

		$user = $this->createUserMutation( [
			'username' => 'testInvalidEmail',
			'email' => 'notanemail',
		] );

		$this->assertEquals( $user['errors'][0]['message'], 'The email address you are trying to use is invalid' );

	}

	public function testUpdateUser() {

		wp_set_current_user( $this->admin );

		$user_login = 'testUserUpdate';
		$user_email = 'testUserUpdate@test.com';
		$user_role = 'editor';
		$first_name = 'Test';
		$last_name = 'User';

		$args = [
			'user_pass' => null,
			'user_login' => $user_login,
			'user_email' => $user_email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'role' => $user_role,
		];

		$user_id = $this->factory->user->create( $args );
		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$user_object = get_user_by( 'ID', $user_id );

		$this->assertEquals( $user_object->user_login, $user_login );
		$this->assertEquals( $user_object->user_email, $user_email );
		$this->assertEquals( $user_object->roles[0], $user_role );
		$this->assertEquals( $user_object->first_name, $first_name );
		$this->assertEquals( $user_object->last_name, $last_name );

		$mutation = '
		mutation updateUser($input:updateUserInput!) {
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

		$updated_email = 'testUserUpdated@test.com';
		$updated_firstname = 'Testupdate';
		$updated_lastname = 'Updatetest';

		$variables = [
			'input' => [
				'id' => $guid,
				'clientMutationId' => $this->client_mutation_id,
				'email' => $updated_email,
				'firstName' => $updated_firstname,
				'lastName' => $updated_lastname,
				'roles' => [
					'administrator',
				]
			]
		];

		$actual = do_graphql_request( $mutation, 'updateUser', $variables );

		$expected = [
			'data' => [
				'updateUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user' => [
						'firstName' => $updated_firstname,
						'lastName' => $updated_lastname,
						'roles' => [
							'administrator',
						],
						'username' => $user_login,
						'email' => $updated_email,
						'userId' => $user_id,
						'id' => $guid,
					]
				]
			]
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testDeleteUserWithCapability() {

		wp_set_current_user( $this->admin );

		$username = 'userToDelete';

		$user_id = $this->factory->user->create( [
			'role' => 'administrator',
			'user_login' => $username,
		] );

		$guid =  \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$mutation = '
		mutation deleteUser($input:deleteUserInput!) {
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
				'id' => $guid,
				'clientMutationId' => $this->client_mutation_id,
			]
		];

		$actual = do_graphql_request( $mutation, 'deleteUser', $variables );

		$expected = [
			'data' => [
				'deleteUser' => [
					'clientMutationId' => $this->client_mutation_id,
					'user' => [
						'username' => $username,
						'userId' => $user_id,
						'id' => $guid,
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

		$username = 'userToDelete';

		$user_id = $this->factory->user->create( [
			'role' => 'subscriber',
			'user_login' => $username,
		] );

		$guid = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		$mutation = '
		mutation deleteUser($input:deleteUserInput!) {
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
				'id' => $guid,
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

}
