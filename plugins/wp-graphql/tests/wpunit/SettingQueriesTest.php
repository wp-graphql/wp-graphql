<?php

class SettingQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $editor;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->admin = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->editor = $this->factory->user->create(
			[
				'role' => 'editor',
			]
		);

		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	/**
	 * Method for testing whether a user can query settings
	 * if they don't have the 'manage_options' capability
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testSettingQueryAsEditor() {
		/**
		 * Set the editor user
		 * Set the query
		 * Make the request
		 * Validate the request has errors
		 */
		wp_set_current_user( $this->editor );

		$query = '
			query {
				generalSettings {
						email
					}
				}
			';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Method for testing the generalSettings
	 *
	 * @return void
	 */
	public function testGeneralSettingQuery() {
		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );

		$mock_options = [
			'date_format'     => 'test date format',
			'blogdescription' => 'test description',
			'admin_email'     => 'test@test.com',
			'language'        => 'test language',
			'start_of_week'   => 0,
			'time_format'     => 'test_time_format',
			'timezone_string' => 'UTC',
			'blogname'        => 'test_title',
			'siteurl'         => 'http://test.com',
		];

		foreach ( $mock_options as $mock_option_key => $mock_value ) {
			update_option( $mock_option_key, $mock_value );
		}

		if ( is_multisite() ) {
			update_network_option( 1, 'admin_email', 'test email' );
		}

		if ( true === is_multisite() ) {
			$query = '
				query {
					generalSettings {
							dateFormat
							description
							language
							startOfWeek
							timeFormat
							timezone
							title
					}
				}
			';
		} else {
			$query = '
				query {
					generalSettings {
							dateFormat
							description
							email
							language
							startOfWeek
							timeFormat
							timezone
							title
							url
					}
				}
			';
		}

		$actual = $this->graphql( compact( 'query' ) );

		$generalSettings = $actual['data']['generalSettings'];

		$this->assertNotEmpty( $generalSettings );
		$this->assertEquals( $mock_options['date_format'], $generalSettings['dateFormat'] );
		$this->assertEquals( $mock_options['blogdescription'], $generalSettings['description'] );
		if ( ! is_multisite() ) {
			$this->assertEquals( $mock_options['admin_email'], $generalSettings['email'] );
		}
		$this->assertEquals( $mock_options['start_of_week'], $generalSettings['startOfWeek'] );
		$this->assertEquals( $mock_options['time_format'], $generalSettings['timeFormat'] );
		$this->assertEquals( $mock_options['timezone_string'], $generalSettings['timezone'] );
		$this->assertEquals( $mock_options['blogname'], $generalSettings['title'] );
		if ( ! is_multisite() ) {
			$this->assertEquals( $mock_options['siteurl'], $generalSettings['url'] );
		}
	}

	/**
	 * Method for testing the writingSettings
	 *
	 * @return void
	 */
	public function testWritingSettingQuery() {
		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );
		$query = '
			query {
				writingSettings {
					defaultCategory
					defaultPostFormat
					useSmilies
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$writingSettings = $actual['data']['writingSettings'];

		$this->assertNotEmpty( $writingSettings );
		$this->assertTrue( is_int( $writingSettings['defaultCategory'] ) );
		$this->assertTrue( is_string( $writingSettings['defaultPostFormat'] ) );
		$this->assertTrue( is_bool( $writingSettings['useSmilies'] ) );
	}

	/**
	 * Method for testing the readingSettings
	 *
	 * @return array $actual
	 */
	public function testReadingSettingQuery() {
		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );

		update_option( 'posts_per_page', 12 );

		$query = '
			query {
				readingSettings {
					postsPerPage
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$readingSettings = $actual['data']['readingSettings'];

		$this->assertNotEmpty( $readingSettings );
		$this->assertEquals( 12, $readingSettings['postsPerPage'] );
	}

	/**
	 * Method for testing the discussionSettings
	 *
	 * @return array $actual
	 */
	public function testDiscussionSettingQuery() {
		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );

		update_option( 'default_comment_status', 'test_value' );
		update_option( 'default_ping_status', 'test_value' );

		$query = '
			query {
				discussionSettings {
					defaultCommentStatus
					defaultPingStatus
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$discussionSettings = $actual['data']['discussionSettings'];

		$this->assertNotEmpty( $discussionSettings );
		$this->assertEquals( 'test_value', $discussionSettings['defaultCommentStatus'] );
		$this->assertEquals( 'test_value', $discussionSettings['defaultPingStatus'] );
	}

	/**
	 * Method for testing the testGetAllowedSettingsByGroup
	 * and then checking that zoolSettings gets added and removed
	 *
	 * @return void
	 */
	public function testGetAllowedSettingsByGroup() {

		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Manually Register a setting for testing
		 *
		 * This registers a setting as a number to see if it gets the correct type
		 * associated with it and returned through WPGraphQL
		 */
		register_setting(
			'zool',
			'points',
			[
				'type'            => 'number',
				'description'     => __( 'Test how many points we have in Zool.' ),
				'show_in_graphql' => true,
				'default'         => 4.5,
			]
		);

		$query = '
		query GetType( $typeName: String! ){
			__type(name: $typeName) {
				name
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'ZoolSettings',
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ZoolSettings', $actual['data']['__type']['name'] );
	}

	/**
	 * Method for testing the testGetAllowedSettings
	 * and then checking that zoolSettings gets added and removed
	 *
	 * @return void
	 */
	public function testGetAllowedSettings() {

		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Manually Register a setting for testing
		 *
		 * This registers a setting as a number to see if it gets the correct type
		 * associated with it and returned through WPGraphQL
		 */
		register_setting(
			'zool',
			'points',
			[
				'type'            => 'number',
				'description'     => __( 'Test how many points we have in Zool.' ),
				'show_in_graphql' => true,
				'default'         => 4.5,
			]
		);

		$query = '
		query getType( $typeName: String! ){
			__type(name: $typeName) {
				name
				fields {
					name
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'ZoolSettings',
				],
			]
		);

		codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'ZoolSettings', $actual['data']['__type']['name'] );
		$this->assertNotEmpty( $actual['data']['__type']['fields'] );

		$names = [];
		foreach ( $actual['data']['__type']['fields'] as $field ) {
			$names[ $field['name'] ] = $field['name'];
		}

		codecept_debug( $names );

		$this->assertArrayHasKey( 'points', $names );

		unregister_setting( 'zool', 'points' );
	}

	public function testUnregisteringSettingPreventsItFromBeingInTheSchema() {

		register_setting(
			'zool',
			'test',
			[
				'show_in_rest' => true,
				'type'         => 'string',
			]
		);

		unregister_setting( 'zool', 'test' );

		$query = '
		query getType( $typeName: String! ){
			__type(name: $typeName) {
				name
				fields {
					name
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'typeName' => 'ZoolSettings',
				],
			]
		);

		codecept_debug( $actual );

		// There should be no type found
		$this->assertNull( $actual['data']['__type'] );
	}

	/**
	 * Method for testing custom Settings
	 *
	 * @return void
	 */
	public function testRegisteredSettingInCamelcaseQuery() {
		wp_set_current_user( $this->admin );

		register_setting(
			'fooBar',
			'biz',
			[
				'type'            => 'string',
				'description'     => __( 'Test register setting in camelcase.' ),
				'show_in_graphql' => true,
				'default'         => 1.1,
			]
		);

		$query  = '
			{
				fooBarSettings {
					biz
				}
			}
		';
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'fooBarSettings', $actual['data'] );
		$this->assertEquals( '1.1', $actual['data']['fooBarSettings']['biz'] );
	}

	/**
	 * Method for testing custom Settings containing underscores
	 *
	 * @return void
	 */
	public function testRegisteredSettingWithUnderscoresQuery() {
		wp_set_current_user( $this->admin );

		register_setting(
			'zoo_bar',
			'biz',
			[
				'type'            => 'string',
				'description'     => __( 'Test register setting with underscore.' ),
				'show_in_graphql' => true,
				'default'         => 2.2,
			]
		);

		$query  = '
			{
				zooBarSettings {
					biz
				}
			}
		';
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'zooBarSettings', $actual['data'] );
		$this->assertEquals( '2.2', $actual['data']['zooBarSettings']['biz'] );
	}

	public function testRegisterFieldToSettingGroupTypeSuccessfullyAddsFieldToTheType() {

		$expected = 'my custom field value';

		$this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		add_action(
			'graphql_register_types',
			static function () use ( $expected ) {
				register_graphql_field(
					'GeneralSettings',
					'myCustomField',
					[
						'type'    => 'String',
						'resolve' => static function () use ( $expected ) {
							return $expected;
						},
					]
				);
				register_graphql_field(
					'RootQuery',
					'rootCustomField',
					[
						'type'    => 'String',
						'resolve' => static function () use ( $expected ) {
							return $expected;
						},
					]
				);
				register_graphql_field(
					'Post',
					'customPostField',
					[
						'type'    => 'String',
						'resolve' => static function () use ( $expected ) {
							return $expected;
						},
					]
				);
			}
		);

		$query = '
		{
			posts {
				nodes {
					id
					customPostField
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['posts']['nodes'][0]['customPostField'] );

		$query = '
		{
			rootCustomField
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['rootCustomField'] );

		$query = '
		{
			generalSettings {
				myCustomField
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['generalSettings']['myCustomField'] );
	}
}
