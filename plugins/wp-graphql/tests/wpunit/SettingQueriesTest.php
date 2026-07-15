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
	 * When a site uses a manual UTC offset instead of a named timezone, WordPress
	 * stores the offset in the `gmt_offset` option and leaves `timezone_string`
	 * empty. The `generalSettings.timezone` field maps to `timezone_string`, so it
	 * should fall back to the resolved offset string instead of returning empty.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/2060
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testGeneralSettingTimezoneFallsBackToUtcOffset() {
		wp_set_current_user( $this->admin );

		// Simulate a site configured with a manual UTC offset (e.g. UTC+2).
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 2 );

		$query = '
			query {
				generalSettings {
					timezone
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['generalSettings']['timezone'] );
		$this->assertEquals( '+02:00', $actual['data']['generalSettings']['timezone'] );
	}

	/**
	 * A setting registered with the `number` type should be cast to a float when
	 * resolved. Core ships no float settings, so this exercises the `number`/`float`
	 * branch of the settings resolver's type switch.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testRegisteredNumberSettingResolvesAsFloat() {
		wp_set_current_user( $this->admin );

		register_setting(
			'floatGroup',
			'ratio',
			[
				'type'            => 'number',
				'description'     => __( 'Test registering a number setting.', 'wp-graphql' ),
				'show_in_graphql' => true,
			]
		);

		update_option( 'ratio', '2.5' );

		$query = '
			query {
				floatGroupSettings {
					ratio
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertIsFloat( $actual['data']['floatGroupSettings']['ratio'] );
		$this->assertEquals( 2.5, $actual['data']['floatGroupSettings']['ratio'] );
	}

	/**
	 * A setting whose registered type is not one of the explicitly-cast scalar types
	 * (integer, string, boolean, number/float) should resolve to its raw stored value,
	 * exercising the `default` branch of the settings resolver's type switch.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testRegisteredSettingWithUnmappedTypeResolvesRawValue() {
		wp_set_current_user( $this->admin );

		// `id` resolves to the ID scalar but is not handled by an explicit `case`,
		// so the resolver falls through to the default branch.
		register_setting(
			'rawGroup',
			'token',
			[
				'type'            => 'id',
				'description'     => __( 'Test registering a setting with an unmapped type.', 'wp-graphql' ),
				'show_in_graphql' => true,
			]
		);

		update_option( 'token', 'abc-123' );

		$query = '
			query {
				rawGroupSettings {
					token
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'abc-123', $actual['data']['rawGroupSettings']['token'] );
	}

	/**
	 * The `graphql_setting_field_value` filter should be able to override a setting's
	 * resolved value, covering the filter seam added alongside the timezone fallback.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function testSettingFieldValueFilterCanOverrideResolvedValue() {
		wp_set_current_user( $this->admin );

		update_option( 'blogname', 'original title' );

		$filter = static function ( $value, $setting_field ) {
			if ( isset( $setting_field['key'] ) && 'blogname' === $setting_field['key'] ) {
				return 'filtered title';
			}

			return $value;
		};

		add_filter( 'graphql_setting_field_value', $filter, 10, 2 );

		$query = '
			query {
				generalSettings {
					title
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		remove_filter( 'graphql_setting_field_value', $filter, 10 );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'filtered title', $actual['data']['generalSettings']['title'] );
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

	/**
	 * A setting registered with a group must surface on both read surfaces:
	 * as a field on its group type and as a group-prefixed field on the
	 * flat Settings type.
	 */
	public function testRegisteredSettingAppearsInFlatAndGroupedTypes() {
		wp_set_current_user( $this->admin );

		register_setting(
			'zool',
			'points',
			[
				'type'            => 'number',
				'description'     => __( 'Test how many points we have in Zool.' ),
				'show_in_graphql' => true,
			]
		);

		$query = '
		query GetTypes {
			grouped: __type(name: "ZoolSettings") {
				fields {
					name
				}
			}
			flat: __type(name: "Settings") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		unregister_setting( 'zool', 'points' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$grouped_fields = wp_list_pluck( $actual['data']['grouped']['fields'], 'name' );
		$flat_fields    = wp_list_pluck( $actual['data']['flat']['fields'], 'name' );
		$this->assertContains( 'points', $grouped_fields );
		$this->assertContains( 'zoolSettingsPoints', $flat_fields );
	}

	/**
	 * A setting registered with `show_in_rest` (and no explicit
	 * `show_in_graphql`) must also surface on both read surfaces.
	 */
	public function testSettingRegisteredForRestAppearsInFlatAndGroupedTypes() {
		wp_set_current_user( $this->admin );

		register_setting(
			'zool',
			'points',
			[
				'type'         => 'number',
				'description'  => __( 'Test how many points we have in Zool.' ),
				'show_in_rest' => true,
			]
		);

		$query = '
		query GetTypes {
			grouped: __type(name: "ZoolSettings") {
				fields {
					name
				}
			}
			flat: __type(name: "Settings") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		unregister_setting( 'zool', 'points' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$grouped_fields = wp_list_pluck( $actual['data']['grouped']['fields'], 'name' );
		$flat_fields    = wp_list_pluck( $actual['data']['flat']['fields'], 'name' );
		$this->assertContains( 'points', $grouped_fields );
		$this->assertContains( 'zoolSettingsPoints', $flat_fields );
	}

	/**
	 * The grouped and flat read surfaces terminate in independent filters:
	 * emptying `graphql_allowed_settings_by_group` removes the per-group
	 * root fields but leaves the flat allSettings surface intact.
	 */
	public function testFilteringGroupedSettingsDoesNotAffectFlatSettings() {
		wp_set_current_user( $this->admin );

		$filter = static function () {
			return [];
		};

		add_filter( 'graphql_allowed_settings_by_group', $filter, 99 );
		$this->clearSchema();

		$query = '
		query GetTypes {
			__type(name: "RootQuery") {
				fields {
					name
				}
			}
			flat: __type(name: "Settings") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		remove_filter( 'graphql_allowed_settings_by_group', $filter, 99 );
		$this->clearSchema();

		$this->assertArrayNotHasKey( 'errors', $actual );
		$root_fields = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		$this->assertNotContains( 'generalSettings', $root_fields, 'Expected per-group root fields to be removed when the grouped settings are filtered out' );
		$this->assertContains( 'allSettings', $root_fields, 'Expected the flat allSettings surface to be unaffected by the grouped settings filter' );
		$flat_fields = wp_list_pluck( $actual['data']['flat']['fields'], 'name' );
		$this->assertContains( 'generalSettingsTitle', $flat_fields );
	}

	/**
	 * An entry seeded into the normalized settings map via the
	 * `graphql_normalized_settings` filter surfaces on both read surfaces
	 * without calling register_setting(), and its `graphql_resolve` callback
	 * normalizes the resolved value.
	 */
	public function testNormalizedSettingsFilterCanAddSetting() {
		wp_set_current_user( $this->admin );

		update_option( 'shim_option', 'raw value' );

		$filter = static function ( $settings ) {
			$settings['shim_option'] = [
				'key'             => 'shim_option',
				'group'           => 'shimmed',
				'type'            => 'string',
				'description'     => 'A setting seeded into the normalized map without register_setting().',
				'graphql_resolve' => static function ( $value ) {
					return strtoupper( (string) $value );
				},
			];
			return $settings;
		};

		add_filter( 'graphql_normalized_settings', $filter );
		$this->clearSchema();

		$query = '
		query {
			shimmedSettings {
				shimOption
			}
			flat: __type(name: "Settings") {
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		remove_filter( 'graphql_normalized_settings', $filter );
		delete_option( 'shim_option' );
		$this->clearSchema();

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'RAW VALUE', $actual['data']['shimmedSettings']['shimOption'] );
		$flat_fields = wp_list_pluck( $actual['data']['flat']['fields'], 'name' );
		$this->assertContains( 'shimmedSettingsShimOption', $flat_fields );
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

	/**
	 * Test that the siteIconUrl field returns null when no site icon is set.
	 *
	 * @return void
	 */
	public function testSiteIconUrlReturnsNullWhenNotSet() {
		// Ensure no site icon is set.
		delete_option( 'site_icon' );

		$query = '
			{
				generalSettings {
					siteIconUrl
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['generalSettings']['siteIconUrl'] );
	}

	/**
	 * Test that the siteIconUrl field returns the URL when a site icon is set.
	 *
	 * @return void
	 */
	public function testSiteIconUrlReturnsUrlWhenSet() {
		// Create a test attachment to use as site icon.
		$filename      = WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png';
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		// Set the site icon.
		update_option( 'site_icon', $attachment_id );

		$query = '
			{
				generalSettings {
					siteIconUrl
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['generalSettings']['siteIconUrl'] );
		$this->assertStringContainsString( 'test', $actual['data']['generalSettings']['siteIconUrl'] );

		// Cleanup.
		delete_option( 'site_icon' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that the siteIconUrl field respects the size argument.
	 *
	 * @return void
	 */
	public function testSiteIconUrlWithSizeArgument() {
		// Create a test attachment to use as site icon.
		$filename      = WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png';
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		// Set the site icon.
		update_option( 'site_icon', $attachment_id );

		$query = '
			{
				generalSettings {
					defaultSize: siteIconUrl
					smallSize: siteIconUrl(size: 32)
					largeSize: siteIconUrl(size: 512)
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['generalSettings']['defaultSize'] );
		$this->assertNotNull( $actual['data']['generalSettings']['smallSize'] );
		$this->assertNotNull( $actual['data']['generalSettings']['largeSize'] );

		// Cleanup.
		delete_option( 'site_icon' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test that the siteIcon connection returns null when no site icon is set.
	 *
	 * @return void
	 */
	public function testSiteIconConnectionReturnsNullWhenNotSet() {
		// Ensure no site icon is set.
		delete_option( 'site_icon' );

		$query = '
			{
				generalSettings {
					siteIcon {
						node {
							id
							databaseId
							sourceUrl
						}
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['generalSettings']['siteIcon'] );
	}

	/**
	 * Test that the siteIcon connection returns the MediaItem when a site icon is set.
	 *
	 * @return void
	 */
	public function testSiteIconConnectionReturnsMediaItemWhenSet() {
		// Create a test attachment to use as site icon.
		$filename      = WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png';
		$attachment_id = $this->factory()->attachment->create_upload_object( $filename );

		// Set the site icon.
		update_option( 'site_icon', $attachment_id );

		$query = '
			{
				generalSettings {
					siteIcon {
						node {
							id
							databaseId
							sourceUrl
						}
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['generalSettings']['siteIcon'] );
		$this->assertNotNull( $actual['data']['generalSettings']['siteIcon']['node'] );
		$this->assertEquals( $attachment_id, $actual['data']['generalSettings']['siteIcon']['node']['databaseId'] );
		$this->assertNotNull( $actual['data']['generalSettings']['siteIcon']['node']['sourceUrl'] );

		// Cleanup.
		delete_option( 'site_icon' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * The `home` (Site Address) option is seeded as an in-memory shim: exposed as
	 * `homeUrl` on GeneralSettings and `generalSettingsHomeUrl` on the flat Settings
	 * type, resolving via get_home_url().
	 *
	 * @return void
	 */
	public function testHomeUrlShimResolvesOnBothSurfaces() {
		wp_set_current_user( $this->admin );

		$query = '
			{
				generalSettings { homeUrl }
				allSettings { generalSettingsHomeUrl }
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( get_home_url(), $actual['data']['generalSettings']['homeUrl'] );
		$this->assertEquals( get_home_url(), $actual['data']['allSettings']['generalSettingsHomeUrl'] );
	}

	/**
	 * The permalink options are seeded as in-memory shims under a new `permalink`
	 * group, exposed on PermalinkSettings and on the flat Settings type.
	 *
	 * @return void
	 */
	public function testPermalinkSettingsShimResolvesOnBothSurfaces() {
		wp_set_current_user( $this->admin );

		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'category_base', 'topics' );
		update_option( 'tag_base', 'labels' );

		$query = '
			{
				permalinkSettings { structure categoryBase tagBase }
				allSettings {
					permalinkSettingsStructure
					permalinkSettingsCategoryBase
					permalinkSettingsTagBase
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		delete_option( 'permalink_structure' );
		delete_option( 'category_base' );
		delete_option( 'tag_base' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( '/%postname%/', $actual['data']['permalinkSettings']['structure'] );
		$this->assertEquals( 'topics', $actual['data']['permalinkSettings']['categoryBase'] );
		$this->assertEquals( 'labels', $actual['data']['permalinkSettings']['tagBase'] );
		$this->assertEquals( '/%postname%/', $actual['data']['allSettings']['permalinkSettingsStructure'] );
		$this->assertEquals( 'topics', $actual['data']['allSettings']['permalinkSettingsCategoryBase'] );
		$this->assertEquals( 'labels', $actual['data']['allSettings']['permalinkSettingsTagBase'] );
	}

	/**
	 * `graphql_field_name` is authoritative: a setting seeded via the
	 * graphql_normalized_settings filter appears under that exact name (no
	 * camelCasing) on both the grouped and flat surfaces.
	 *
	 * @return void
	 */
	public function testGraphqlFieldNameOverrideIsAuthoritative() {
		$filter = static function ( array $settings ) {
			$settings['my_shim_option'] = [
				'group'              => 'general',
				'type'               => 'string',
				'description'        => 'A test shim option.',
				'graphql_field_name' => 'myShimFieldName',
			];
			return $settings;
		};

		add_filter( 'graphql_normalized_settings', $filter );
		WPGraphQL::clear_schema();

		update_option( 'my_shim_option', 'shim-value' );
		wp_set_current_user( $this->admin );

		$query = '
			{
				generalSettings { myShimFieldName }
				allSettings { generalSettingsMyShimFieldName }
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		remove_filter( 'graphql_normalized_settings', $filter );
		delete_option( 'my_shim_option' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'shim-value', $actual['data']['generalSettings']['myShimFieldName'] );
		$this->assertEquals( 'shim-value', $actual['data']['allSettings']['generalSettingsMyShimFieldName'] );
	}

	/**
	 * The `url` field (siteurl) resolves on both the grouped and flat surfaces. On
	 * single-site it comes from the registered `siteurl` setting; on multisite it
	 * comes from the seeded shim (which replaced the former register_field polyfill
	 * and adds the flat `generalSettingsUrl` field multisite previously lacked).
	 *
	 * @return void
	 */
	public function testSiteUrlResolvesOnBothSurfaces() {
		wp_set_current_user( $this->admin );

		$query = '
			{
				generalSettings { url }
				allSettings { generalSettingsUrl }
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['generalSettings']['url'] );
		$this->assertEquals( $actual['data']['generalSettings']['url'], $actual['data']['allSettings']['generalSettingsUrl'] );
	}
}
