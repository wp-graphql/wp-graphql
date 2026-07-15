<?php

class WP_GraphQL_Test_Settings_Queries extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
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

		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Restricted settings return null when queried without manage_options.
	 *
	 * @return void
	 */
	public function testAllSettingsQueryAsEditor() {
		wp_set_current_user( $this->editor );
		$query  = '
			query {
				allSettings {
					generalSettingsEmail
				}
			}
		';
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['allSettings']['generalSettingsEmail'] );
	}

	/**
	 * Method for testing the generalSettings
	 *
	 * @return void
	 */
	public function testAllSettingsQuery() {

		/**
		 * Set the admin user
		 * Set the query
		 * Make the request
		 * Validate the request
		 */
		wp_set_current_user( $this->admin );

		$mock_options = [
			'default_comment_status' => 'closed',
			'default_ping_status'    => 'closed',
			'date_format'            => 'test date format',
			'blogdescription'        => 'test description',
			'admin_email'            => 'test@test.com',
			'start_of_week'          => 0,
			'time_format'            => 'test_time_format',
			'timezone_string'        => 'UTC',
			'blogname'               => 'test_title',
			'siteurl'                => 'http://test.com',
			'posts_per_page'         => 20,
			'default_category'       => 2,
			'default_post_format'    => 'quote',
			'use_smilies'            => 0,
			'points'                 => 5.5,
		];

		foreach ( $mock_options as $mock_option_key => $mock_value ) {
			update_option( $mock_option_key, $mock_value );
		}

		if ( true === is_multisite() ) {
			$query = '
				query {
					allSettings {
						discussionSettingsDefaultCommentStatus
						discussionSettingsDefaultPingStatus
						generalSettingsDateFormat
						generalSettingsDescription
						generalSettingsLanguage
						generalSettingsStartOfWeek
						generalSettingsTimeFormat
						generalSettingsTimezone
						generalSettingsTitle
						readingSettingsPostsPerPage
						writingSettingsDefaultCategory
						writingSettingsDefaultPostFormat
						writingSettingsUseSmilies
					}
				}
			';
		} else {
			$query = '
				query {
					allSettings {
						discussionSettingsDefaultCommentStatus
						discussionSettingsDefaultPingStatus
						generalSettingsDateFormat
						generalSettingsDescription
						generalSettingsEmail
						generalSettingsLanguage
						generalSettingsStartOfWeek
						generalSettingsTimeFormat
						generalSettingsTimezone
						generalSettingsTitle
						generalSettingsUrl
						readingSettingsPostsPerPage
						writingSettingsDefaultCategory
						writingSettingsDefaultPostFormat
						writingSettingsUseSmilies
					}
				}
			';
		}

		$actual = $this->graphql( compact( 'query' ) );

		$allSettings = $actual['data']['allSettings'];

		$this->assertNotEmpty( $allSettings );
		$this->assertEquals( $mock_options['default_comment_status'], $allSettings['discussionSettingsDefaultCommentStatus'] );
		$this->assertEquals( $mock_options['default_ping_status'], $allSettings['discussionSettingsDefaultPingStatus'] );
		$this->assertEquals( $mock_options['date_format'], $allSettings['generalSettingsDateFormat'] );
		$this->assertEquals( $mock_options['blogdescription'], $allSettings['generalSettingsDescription'] );
		if ( ! is_multisite() ) {
			$this->assertEquals( $mock_options['admin_email'], $allSettings['generalSettingsEmail'] );
		}
		$this->assertEquals( 'en_US', $allSettings['generalSettingsLanguage'] );
		$this->assertEquals( $mock_options['start_of_week'], $allSettings['generalSettingsStartOfWeek'] );
		$this->assertEquals( $mock_options['time_format'], $allSettings['generalSettingsTimeFormat'] );
		$this->assertEquals( $mock_options['timezone_string'], $allSettings['generalSettingsTimezone'] );
		$this->assertEquals( $mock_options['blogname'], $allSettings['generalSettingsTitle'] );
		if ( ! is_multisite() ) {
			$this->assertEquals( $mock_options['siteurl'], $allSettings['generalSettingsUrl'] );
		}
		$this->assertEquals( $mock_options['posts_per_page'], $allSettings['readingSettingsPostsPerPage'] );
		$this->assertEquals( $mock_options['default_category'], $allSettings['writingSettingsDefaultCategory'] );
		$this->assertEquals( $mock_options['default_post_format'], $allSettings['writingSettingsDefaultPostFormat'] );
		$this->assertEquals( $mock_options['use_smilies'], $allSettings['writingSettingsUseSmilies'] );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/pull/2276
	 * @throws \Exception
	 */
	public function testGeneralSettingsUrlDoesntThrowErrorOnMultisite() {

		$query = '
		{
			generalSettings {
				dateFormat
				url
			}
		}
		';

		$actual = graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['generalSettings']['url'] );
	}

	/**
	 * The `home` option ("Site Address") should be queryable via generalSettings.homeUrl.
	 *
	 * WordPress core registers `siteurl` ("WordPress Address") for the REST API, which is
	 * exposed as `generalSettings.url`, but core does not register `home`. WPGraphQL
	 * registers `homeUrl` for GraphQL so headless sites can read the canonical front-end URL,
	 * which can differ from the WordPress Address.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/2520
	 * @throws \Exception
	 */
	public function testGeneralSettingsHomeUrlIsExposed() {
		wp_set_current_user( $this->admin );

		// Set the Site Address (home) to a value distinct from the WordPress Address (siteurl).
		update_option( 'home', 'https://frontend.example.com' );
		update_option( 'siteurl', 'https://wp.example.com' );

		$query = '
		{
			generalSettings {
				url
				homeUrl
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// The `homeUrl` field should exist and resolve on every install. Without the
		// registration the field would not exist on the GeneralSettings type and the
		// query above would error, so this guards the registration itself.
		$this->assertArrayHasKey( 'homeUrl', $actual['data']['generalSettings'] );
		$this->assertNotEmpty( $actual['data']['generalSettings']['homeUrl'] );

		// On multisite, update_option() for siteurl/home does not round-trip through
		// GraphQL to the set value (mirrors the guarded assertion in testAllSettingsQuery),
		// so only assert the exact values on single-site installs.
		if ( ! is_multisite() ) {
			$this->assertEquals( 'https://wp.example.com', $actual['data']['generalSettings']['url'] );
			$this->assertEquals( 'https://frontend.example.com', $actual['data']['generalSettings']['homeUrl'] );
		}

		// The field is registered directly on the type rather than through the settings
		// registry, so it must not be writable: the updateSettings mutation input should
		// have no corresponding field.
		$introspection = graphql(
			[
				'query' => '
				{
					__type(name: "UpdateSettingsInput") {
						inputFields {
							name
						}
					}
				}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $introspection );
		$input_field_names = wp_list_pluck( $introspection['data']['__type']['inputFields'], 'name' );
		$this->assertNotContains( 'generalSettingsHomeUrl', $input_field_names );
		$this->assertNotContains( 'generalSettingsHome', $input_field_names );
	}

	/**
	 * Ensure RootQuery does not expose allSettings when no settings are available.
	 *
	 * @return void
	 */
	/**
	 * The timezone fallback (deriving the value from `gmt_offset` when
	 * `timezone_string` is empty) must apply on the flat allSettings surface,
	 * not just the grouped generalSettings surface.
	 */
	public function testFlatTimezoneSettingFallsBackToUtcOffset() {
		wp_set_current_user( $this->admin );

		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', '2' );

		$query = '
		query {
			allSettings {
				generalSettingsTimezone
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( '+02:00', $actual['data']['allSettings']['generalSettingsTimezone'] );
	}

	public function testAllSettingsFieldIsHiddenWhenNoSettingsAreExposed() {
		$filter = static function () {
			return [];
		};

		add_filter( 'graphql_allowed_setting_groups', $filter, 99 );
		$this->clearSchema();

		$query  = '
			query {
				__type(name: "RootQuery") {
					fields {
						name
					}
				}
			}
		';
		$actual = $this->graphql( compact( 'query' ) );

		remove_filter( 'graphql_allowed_setting_groups', $filter, 99 );
		$this->clearSchema();

		$this->assertArrayNotHasKey( 'errors', $actual );
		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );
		$this->assertNotContains( 'allSettings', $field_names, 'Expected RootQuery.allSettings to be hidden when no settings are exposed' );
	}
}
