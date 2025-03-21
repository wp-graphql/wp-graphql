<?php
class ModelUserTest extends \Codeception\TestCase\WPTestCase {

	public $administrator;
	public $author;
	public $contributor;
	public $editor;
	public $subscriber;
	public $userIds;

	public function setUp(): void {
		// before
		parent::setUp();

		WPGraphQL::clear_schema();
		$users = [
			[
				'role'      => 'administrator',
				'user_data' => [
					'user_nicename' => 'Admin Nicename',
					'user_login'    => 'administrator',
					'user_pass'     => 'admin',
					'user_url'      => 'http://admin.com',
					'user_email'    => 'admin@admin.com',
					'display_name'  => 'Admin Display',
					'nickname'      => 'Admin Nickname',
					'first_name'    => 'Admin First',
					'last_name'     => 'Admin Last',
					'description'   => 'Admin desc',
					'rich_editing'  => false,
				],
			],
			[
				'role'      => 'author',
				'user_data' => [
					'user_nicename' => 'Author Nicename',
					'user_login'    => 'author',
					'user_pass'     => 'author',
					'user_url'      => 'http://author.com',
					'user_email'    => 'author@author.com',
					'display_name'  => 'Author Display',
					'nickname'      => 'Author Nickname',
					'first_name'    => 'Author First',
					'last_name'     => 'Author Last',
					'description'   => 'Author desc',
					'rich_editing'  => false,
				],
			],
			[
				'role'      => 'contributor',
				'user_data' => [
					'user_nicename' => 'Contributor Nicename',
					'user_login'    => 'contributor',
					'user_pass'     => 'contributor',
					'user_url'      => 'http://contributor.com',
					'user_email'    => 'contributor@contributor.com',
					'display_name'  => 'Contributor Display',
					'nickname'      => 'Contributor Nickname',
					'first_name'    => 'Contributor First',
					'last_name'     => 'Contributor Last',
					'description'   => 'Contributor desc',
					'rich_editing'  => false,
				],
			],
			[
				'role'      => 'editor',
				'user_data' => [
					'user_nicename' => 'Editor Nicename',
					'user_login'    => 'editor',
					'user_pass'     => 'editor',
					'user_url'      => 'http://editor.com',
					'user_email'    => 'editor@editor.com',
					'display_name'  => 'Editor Display',
					'nickname'      => 'Editor Nickname',
					'first_name'    => 'Editor First',
					'last_name'     => 'Editor Last',
					'description'   => 'Editor desc',
					'rich_editing'  => false,
				],
			],
			[
				'role'      => 'subscriber',
				'user_data' => [
					'user_nicename' => 'Subscriber Nicename',
					'user_login'    => 'subscriber',
					'user_pass'     => 'subscriber',
					'user_url'      => 'http://subscriber.com',
					'user_email'    => 'subscriber@subscriber.com',
					'display_name'  => 'Subscriber Display',
					'nickname'      => 'Subscriber Nickname',
					'first_name'    => 'Subscriber First',
					'last_name'     => 'Subscriber Last',
					'description'   => 'Subscriber desc',
					'rich_editing'  => false,
				],
			],
		];

		/**
		 * Loop through and create the users and update their user data.
		 */
		foreach ( $users as $user ) {
			$this->{ $user['role'] } = $id = (int) $this->factory->user->create(
				[
					'role' => $user['role'],
				]
			);

			/**
			 * Create a post published by the user
			 */
			$this->factory()->post->create(
				[
					'post_title'  => 'Post by ' . $user['role'],
					'post_status' => 'publish',
					'post_author' => $id,
				]
			);

			$this->userIds[]         = $id;
			$user['user_data']['ID'] = $id;

			wp_update_user( $user['user_data'] );
		}
	}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * Query a list of users with some core fields
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function queryUsers() {

		$request = '
		query GET_USERS( $ids: [Int] ) {
			users( where: { include: $ids } ) {
				nodes {
					id
					userId
					username
					firstName
					lastName
					email
					roles {
						nodes {
							name
						}
					}
				}
			}
		}
		';

		$variables = [
			'ids' => $this->userIds,
		];

		/**
		 * Return the results of the query
		 */
		return do_graphql_request( $request, 'GET_USERS', $variables );
	}

	/**
	 * Admin should have access to all fields on users
	 */
	public function testQueryUserByAdmin() {

		wp_set_current_user( $this->administrator );

		$actual = $this->queryUsers();

		$this->assertNotEmpty( $actual['data']['users']['nodes'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['id'] );
		$this->assertTrue( in_array( $actual['data']['users']['nodes'][0]['userId'], $this->userIds, true ) );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['username'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['firstName'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['lastName'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['email'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['roles'] );
	}

	/**
	 * Author shouldn't have access to email or roles
	 */
	public function testQueryUserByAuthor() {

		wp_set_current_user( $this->author );

		$actual = $this->queryUsers();

		codecept_debug( $actual );

		$this->assertNotEmpty( $actual['data']['users']['nodes'] );

		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['id'] );
		$this->assertTrue( in_array( $actual['data']['users']['nodes'][0]['userId'], $this->userIds, true ) );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['firstName'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['lastName'] );
		$this->assertNull( $actual['data']['users']['nodes'][0]['email'] );
		$this->assertEmpty( $actual['data']['users']['nodes'][0]['roles'] );
		$this->assertNull( $actual['data']['users']['nodes'][0]['username'] );

		// They should still have access to their own email & role
		$this->assertNotEmpty( $actual['data']['users']['nodes'][1]['email'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][1]['roles']['nodes'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][1]['roles']['nodes'] );
	}

	/**
	 * Contributors shouldn't have access to email or roles
	 */
	public function testQueryUserByContributor() {

		wp_set_current_user( $this->contributor );

		$actual = $this->queryUsers();

		$this->assertNotEmpty( $actual['data']['users']['nodes'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['id'] );
		$this->assertTrue( in_array( $actual['data']['users']['nodes'][0]['userId'], $this->userIds, true ) );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['firstName'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['lastName'] );
		$this->assertNull( $actual['data']['users']['nodes'][0]['email'] );
		$this->assertEmpty( $actual['data']['users']['nodes'][0]['roles'] );
		$this->assertNull( $actual['data']['users']['nodes'][0]['username'] );

		// They should still have access to their own email & role
		$this->assertNotEmpty( $actual['data']['users']['nodes'][2]['email'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][2]['roles']['nodes'] );
	}

	/**
	 * Editors shouldn't have access to email or roles
	 */
	public function testQueryUserByEditor() {

		wp_set_current_user( $this->editor );

		$actual = $this->queryUsers();

		$this->assertNotEmpty( $actual['data']['users']['nodes'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['id'] );
		$this->assertTrue( in_array( $actual['data']['users']['nodes'][0]['userId'], $this->userIds, true ) );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['firstName'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['lastName'] );
		$this->assertNull( $actual['data']['users']['nodes'][0]['email'] );
		$this->assertEmpty( $actual['data']['users']['nodes'][0]['roles'] );
		$this->assertNull( $actual['data']['users']['nodes'][0]['username'] );

		// They should still have access to their own email & role
		$this->assertNotEmpty( $actual['data']['users']['nodes'][3]['email'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][3]['roles']['nodes'] );
	}

	/**
	 * Subscribers shouldn't have access to email or roles
	 */
	public function testQueryUserBySubscriber() {

		wp_set_current_user( $this->subscriber );

		$actual = $this->queryUsers();

		$this->assertNotEmpty( $actual['data']['users']['nodes'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['id'] );
		$this->assertTrue( in_array( $actual['data']['users']['nodes'][0]['userId'], $this->userIds, true ) );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['firstName'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][0]['lastName'] );
		$this->assertNull( $actual['data']['users']['nodes'][1]['email'] );
		$this->assertEmpty( $actual['data']['users']['nodes'][1]['roles'] );
		$this->assertNull( $actual['data']['users']['nodes'][1]['username'] );

		// They should still have access to their own email & role
		$this->assertNotEmpty( $actual['data']['users']['nodes'][4]['email'] );
		$this->assertNotEmpty( $actual['data']['users']['nodes'][4]['roles']['nodes'] );
	}
}
