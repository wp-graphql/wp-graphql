<?php

/**
 * Who can read a setting, on both read surfaces (the grouped `<group>Settings`
 * types and the flat `allSettings` type).
 *
 * - WordPress core's own settings stay public (the administrator email stays
 *   restricted to users who can manage options).
 * - A setting exposed only because it is registered with `show_in_rest` (no
 *   `show_in_graphql`) requires `manage_options` by default.
 * - A setting registered with `show_in_graphql => true` is public unless it
 *   declares a `graphql_capability`.
 * - Connector API keys always require `manage_options` and are masked.
 */
class SettingsVisibilityTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * A connector ID registered for the connector tests.
	 */
	private const CONNECTOR_ID = 'wpgraphql_test_connector';

	/**
	 * The option name the test connector stores its API key under.
	 */
	private const CONNECTOR_SETTING = 'wpgraphql_test_connector_api_key';

	/**
	 * @var int
	 */
	public $admin;

	/**
	 * @var int
	 */
	public $subscriber;

	/**
	 * Settings registered by the current test, as [ group, option ] pairs, so
	 * tearDown can unregister them.
	 *
	 * @var array<int,array{0:string,1:string}>
	 */
	private $registered = [];

	/**
	 * Whether the current test registered the test connector.
	 *
	 * @var bool
	 */
	private $registered_connector = false;

	public function setUp(): void {
		parent::setUp();

		$this->admin      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}

		$this->clearSchema();
	}

	public function tearDown(): void {
		foreach ( $this->registered as $setting ) {
			unregister_setting( $setting[0], $setting[1] );
			delete_option( $setting[1] );
		}
		$this->registered = [];

		if ( $this->registered_connector && class_exists( 'WP_Connector_Registry' ) ) {
			$registry = WP_Connector_Registry::get_instance();
			if ( null !== $registry && $registry->is_registered( self::CONNECTOR_ID ) ) {
				$registry->unregister( self::CONNECTOR_ID );
			}
		}
		$this->registered_connector = false;

		wp_set_current_user( 0 );
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Registers a setting for the current test and remembers it for tearDown.
	 *
	 * @param string              $group  The settings group.
	 * @param string              $option The option name.
	 * @param array<string,mixed> $args   The register_setting() args.
	 */
	private function register_test_setting( string $group, string $option, array $args ): void {
		register_setting( $group, $option, $args );
		$this->registered[] = [ $group, $option ];
		$this->clearSchema();
	}

	/**
	 * Registers the test connector (an API-key connector) and its setting,
	 * skipping the test when the running WordPress has no connectors API.
	 *
	 * @param array<string,mixed> $setting_args Extra register_setting() args.
	 */
	private function register_test_connector( array $setting_args = [] ): void {
		if ( ! function_exists( 'wp_get_connectors' ) || ! class_exists( 'WP_Connector_Registry' ) || null === WP_Connector_Registry::get_instance() ) {
			$this->markTestSkipped( 'The connectors API requires WordPress 7.0 or later.' );
		}

		$registered = WP_Connector_Registry::get_instance()->register(
			self::CONNECTOR_ID,
			[
				'name'           => 'WPGraphQL Test Connector',
				'description'    => 'A connector registered by the WPGraphQL test suite.',
				'type'           => 'spam_filtering',
				'authentication' => [
					'method'       => 'api_key',
					'setting_name' => self::CONNECTOR_SETTING,
				],
			]
		);

		$this->assertNotNull( $registered, 'The test connector should register.' );
		$this->registered_connector = true;

		// Mirrors how WordPress core registers a connector's API key setting.
		$this->register_test_setting(
			'connectors',
			self::CONNECTOR_SETTING,
			array_merge(
				[
					'type'         => 'string',
					'default'      => '',
					'show_in_rest' => true,
				],
				$setting_args
			)
		);
	}

	/**
	 * Runs a query as the given user.
	 *
	 * @param int                 $user_id   The user to run the query as, 0 for an anonymous request.
	 * @param string              $query     The query.
	 * @param array<string,mixed> $variables The query variables.
	 *
	 * @return array<string,mixed>
	 */
	private function query_as( int $user_id, string $query, array $variables = [] ): array {
		wp_set_current_user( $user_id );

		return $this->graphql( compact( 'query', 'variables' ) );
	}

	/**
	 * Queries one setting on both read surfaces as the given user and returns
	 * the two values, asserting the request had no errors.
	 *
	 * @param int    $user_id    The user to run the query as, 0 for an anonymous request.
	 * @param string $group_type The grouped root field, e.g. `acmePluginSettings`.
	 * @param string $field      The grouped field name, e.g. `secret`.
	 * @param string $flat_field The flat field name, e.g. `acmePluginSettingsSecret`.
	 *
	 * @return array{grouped:mixed,flat:mixed}
	 */
	private function read_setting_as( int $user_id, string $group_type, string $field, string $flat_field ): array {
		$query = sprintf(
			'query { %1$s { %2$s } allSettings { %3$s } }',
			$group_type,
			$field,
			$flat_field
		);

		$actual = $this->query_as( $user_id, $query );

		$this->assertArrayNotHasKey( 'errors', $actual, isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' );

		return [
			'grouped' => $actual['data'][ $group_type ][ $field ],
			'flat'    => $actual['data']['allSettings'][ $flat_field ],
		];
	}

	/**
	 * The users every visibility case is checked for, keyed by a label used in
	 * assertion messages.
	 *
	 * @return array<string,int>
	 */
	private function users(): array {
		return [
			'anonymous'     => 0,
			'subscriber'    => $this->subscriber,
			'administrator' => $this->admin,
		];
	}

	/**
	 * Asserts a setting reads as null for anonymous users and subscribers and
	 * as the expected value for administrators, on both read surfaces.
	 *
	 * @param mixed  $expected   The value administrators should read.
	 * @param string $group_type The grouped root field.
	 * @param string $field      The grouped field name.
	 * @param string $flat_field The flat field name.
	 */
	private function assertReadableOnlyByAdmins( $expected, string $group_type, string $field, string $flat_field ): void {
		foreach ( $this->users() as $label => $user_id ) {
			$values = $this->read_setting_as( $user_id, $group_type, $field, $flat_field );
			$want   = 'administrator' === $label ? $expected : null;

			$this->assertSame( $want, $values['grouped'], sprintf( '%s.%s as %s', $group_type, $field, $label ) );
			$this->assertSame( $want, $values['flat'], sprintf( 'allSettings.%s as %s', $flat_field, $label ) );
		}
	}

	/**
	 * Asserts a setting reads as the expected value for every user, on both
	 * read surfaces.
	 *
	 * @param mixed  $expected   The value every user should read.
	 * @param string $group_type The grouped root field.
	 * @param string $field      The grouped field name.
	 * @param string $flat_field The flat field name.
	 */
	private function assertReadableByEveryone( $expected, string $group_type, string $field, string $flat_field ): void {
		foreach ( $this->users() as $label => $user_id ) {
			$values = $this->read_setting_as( $user_id, $group_type, $field, $flat_field );

			$this->assertSame( $expected, $values['grouped'], sprintf( '%s.%s as %s', $group_type, $field, $label ) );
			$this->assertSame( $expected, $values['flat'], sprintf( 'allSettings.%s as %s', $flat_field, $label ) );
		}
	}

	/**
	 * Asserts the test connector's key reads as null for anonymous users and
	 * subscribers and as the expected value for administrators, on both read
	 * surfaces.
	 *
	 * @param mixed $expected The value administrators should read.
	 */
	private function assertConnectorKeyReadableOnlyByAdmins( $expected ): void {
		$this->assertReadableOnlyByAdmins( $expected, 'connectorsSettings', 'wpgraphqlTestConnectorApiKey', 'connectorsSettingsWpgraphqlTestConnectorApiKey' );
	}

	/**
	 * A plugin setting exposed only because it is registered with `show_in_rest`
	 * is readable only by users who can manage options.
	 */
	public function testRestOnlyPluginSettingRequiresManageOptions(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);
		update_option( 'acme_plugin_secret', 'plugin-secret-value' );

		$this->assertReadableOnlyByAdmins( 'plugin-secret-value', 'acmePluginSettings', 'acmePluginSecret', 'acmePluginSettingsAcmePluginSecret' );
	}

	/**
	 * Core's own settings are identified by option name, not group: a plugin
	 * setting registered into the `general` group with only `show_in_rest` is
	 * restricted like any other plugin setting.
	 */
	public function testRestOnlyPluginSettingInGeneralGroupRequiresManageOptions(): void {
		$this->register_test_setting(
			'general',
			'acme_general_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);
		update_option( 'acme_general_secret', 'general-group-secret' );

		$this->assertReadableOnlyByAdmins( 'general-group-secret', 'generalSettings', 'acmeGeneralSecret', 'generalSettingsAcmeGeneralSecret' );
	}

	/**
	 * A restricted setting stays in the Schema and doesn't fail the request: a
	 * user without the capability gets null and a debug message naming the
	 * field, on both surfaces.
	 */
	public function testRestOnlyPluginSettingReportsRestrictedFieldInDebug(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);
		update_option( 'acme_plugin_secret', 'plugin-secret-value' );

		add_filter( 'graphql_debug_enabled', '__return_true' );
		$actual = $this->query_as( 0, 'query { acmePluginSettings { acmePluginSecret } allSettings { acmePluginSettingsAcmePluginSecret } }' );
		remove_filter( 'graphql_debug_enabled', '__return_true' );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$restricted = wp_list_pluck( $actual['extensions']['debug'], 'required_capability', 'field' );
		$this->assertSame( 'manage_options', $restricted['AcmePluginSettings.acmePluginSecret'] ?? null );
		$this->assertSame( 'manage_options', $restricted['Settings.acmePluginSettingsAcmePluginSecret'] ?? null );
	}

	/**
	 * WordPress core's own settings stay public for every user, on both
	 * surfaces.
	 */
	public function testCoreSettingsStayPublic(): void {
		update_option( 'blogname', 'Visibility Site Title' );
		update_option( 'blogdescription', 'Visibility tagline' );
		update_option( 'timezone_string', 'America/Denver' );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'start_of_week', 3 );
		update_option( 'posts_per_page', 7 );
		update_option( 'show_on_front', 'posts' );
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		update_option( 'use_smilies', 1 );

		$query = '
			query {
				generalSettings { title description timezone dateFormat timeFormat startOfWeek language homeUrl }
				readingSettings { postsPerPage showOnFront pageOnFront pageForPosts }
				discussionSettings { defaultCommentStatus defaultPingStatus }
				writingSettings { useSmilies defaultCategory defaultPostFormat }
				permalinkSettings { structure }
				allSettings {
					generalSettingsTitle
					generalSettingsDescription
					generalSettingsTimezone
					generalSettingsDateFormat
					generalSettingsTimeFormat
					generalSettingsStartOfWeek
					generalSettingsHomeUrl
					readingSettingsPostsPerPage
					readingSettingsShowOnFront
					discussionSettingsDefaultCommentStatus
					discussionSettingsDefaultPingStatus
					writingSettingsUseSmilies
					writingSettingsDefaultCategory
				}
			}
		';

		foreach ( $this->users() as $label => $user_id ) {
			$actual = $this->query_as( $user_id, $query );

			$this->assertArrayNotHasKey( 'errors', $actual, $label . ': ' . ( isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' ) );

			$general = $actual['data']['generalSettings'];
			$this->assertSame( 'Visibility Site Title', $general['title'], $label );
			$this->assertSame( 'Visibility tagline', $general['description'], $label );
			$this->assertSame( 'America/Denver', $general['timezone'], $label );
			$this->assertSame( 'Y-m-d', $general['dateFormat'], $label );
			$this->assertSame( 'H:i', $general['timeFormat'], $label );
			$this->assertSame( 3, $general['startOfWeek'], $label );
			$this->assertNotNull( $general['language'], $label );
			$this->assertSame( get_home_url(), $general['homeUrl'], $label );

			$reading = $actual['data']['readingSettings'];
			$this->assertSame( 7, $reading['postsPerPage'], $label );
			$this->assertSame( 'posts', $reading['showOnFront'], $label );
			$this->assertNotNull( $reading['pageOnFront'], $label );
			$this->assertNotNull( $reading['pageForPosts'], $label );

			$this->assertSame( 'closed', $actual['data']['discussionSettings']['defaultCommentStatus'], $label );
			$this->assertSame( 'closed', $actual['data']['discussionSettings']['defaultPingStatus'], $label );
			$this->assertTrue( $actual['data']['writingSettings']['useSmilies'], $label );
			$this->assertNotNull( $actual['data']['writingSettings']['defaultCategory'], $label );
			$this->assertNotNull( $actual['data']['writingSettings']['defaultPostFormat'], $label );
			$this->assertNotNull( $actual['data']['permalinkSettings']['structure'], $label );

			$all = $actual['data']['allSettings'];
			$this->assertSame( 'Visibility Site Title', $all['generalSettingsTitle'], $label );
			$this->assertSame( 'Visibility tagline', $all['generalSettingsDescription'], $label );
			$this->assertSame( 'America/Denver', $all['generalSettingsTimezone'], $label );
			$this->assertSame( 'Y-m-d', $all['generalSettingsDateFormat'], $label );
			$this->assertSame( 'H:i', $all['generalSettingsTimeFormat'], $label );
			$this->assertSame( 3, $all['generalSettingsStartOfWeek'], $label );
			$this->assertSame( get_home_url(), $all['generalSettingsHomeUrl'], $label );
			$this->assertSame( 7, $all['readingSettingsPostsPerPage'], $label );
			$this->assertSame( 'posts', $all['readingSettingsShowOnFront'], $label );
			$this->assertSame( 'closed', $all['discussionSettingsDefaultCommentStatus'], $label );
			$this->assertSame( 'closed', $all['discussionSettingsDefaultPingStatus'], $label );
			$this->assertTrue( $all['writingSettingsUseSmilies'], $label );
			$this->assertNotNull( $all['writingSettingsDefaultCategory'], $label );
		}
	}

	/**
	 * The site URL stays public, and the administrator email stays restricted
	 * to users who can manage options. Both are only registered on single-site.
	 */
	public function testSiteUrlStaysPublicAndAdminEmailStaysRestricted(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'The siteurl and admin_email settings are not registered on multisite.' );
		}

		update_option( 'admin_email', 'owner@example.test' );

		$this->assertReadableByEveryone( get_option( 'siteurl' ), 'generalSettings', 'url', 'generalSettingsUrl' );
		$this->assertReadableOnlyByAdmins( 'owner@example.test', 'generalSettings', 'email', 'generalSettingsEmail' );
	}

	/**
	 * A setting registered with `show_in_graphql => true` is public, as
	 * documented.
	 */
	public function testShowInGraphqlSettingStaysPublic(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_public',
			[
				'type'            => 'string',
				'show_in_graphql' => true,
			]
		);
		update_option( 'acme_plugin_public', 'public-value' );

		$this->assertReadableByEveryone( 'public-value', 'acmePluginSettings', 'acmePluginPublic', 'acmePluginSettingsAcmePluginPublic' );
	}

	/**
	 * A setting registered with both `show_in_graphql => true` and
	 * `show_in_rest` is public: the explicit GraphQL opt-in wins.
	 */
	public function testShowInGraphqlAndShowInRestSettingStaysPublic(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_both',
			[
				'type'            => 'string',
				'show_in_graphql' => true,
				'show_in_rest'    => true,
			]
		);
		update_option( 'acme_plugin_both', 'both-value' );

		$this->assertReadableByEveryone( 'both-value', 'acmePluginSettings', 'acmePluginBoth', 'acmePluginSettingsAcmePluginBoth' );
	}

	/**
	 * A setting registered with `show_in_graphql => true` that declares a
	 * `graphql_capability` is restricted to users with that capability.
	 */
	public function testShowInGraphqlSettingWithCapabilityIsRestricted(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_gated',
			[
				'type'               => 'string',
				'show_in_graphql'    => true,
				'graphql_capability' => 'manage_options',
			]
		);
		update_option( 'acme_plugin_gated', 'gated-value' );

		$this->assertReadableOnlyByAdmins( 'gated-value', 'acmePluginSettings', 'acmePluginGated', 'acmePluginSettingsAcmePluginGated' );
	}

	/**
	 * A `show_in_rest`-only setting that declares its own `graphql_capability`
	 * keeps it instead of the default.
	 */
	public function testRestOnlySettingKeepsDeclaredCapability(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_readers',
			[
				'type'               => 'string',
				'show_in_rest'       => true,
				'graphql_capability' => 'read',
			]
		);
		update_option( 'acme_plugin_readers', 'readers-value' );

		foreach ( $this->users() as $label => $user_id ) {
			$values = $this->read_setting_as( $user_id, 'acmePluginSettings', 'acmePluginReaders', 'acmePluginSettingsAcmePluginReaders' );
			$want   = 'anonymous' === $label ? null : 'readers-value';

			$this->assertSame( $want, $values['grouped'], $label );
			$this->assertSame( $want, $values['flat'], $label );
		}
	}

	/**
	 * The `graphql_normalized_settings` filter runs after the defaults are
	 * applied, so a site can make a `show_in_rest`-only setting public again.
	 */
	public function testNormalizedSettingsFilterCanMakeRestOnlySettingPublic(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);
		update_option( 'acme_plugin_secret', 'opted-in-value' );

		$filter = static function ( array $settings ) {
			if ( isset( $settings['acme_plugin_secret'] ) ) {
				unset( $settings['acme_plugin_secret']['graphql_capability'] );
			}

			return $settings;
		};
		add_filter( 'graphql_normalized_settings', $filter );
		$this->clearSchema();

		$this->assertReadableByEveryone( 'opted-in-value', 'acmePluginSettings', 'acmePluginSecret', 'acmePluginSettingsAcmePluginSecret' );

		remove_filter( 'graphql_normalized_settings', $filter );
	}

	/**
	 * An empty `graphql_capability` (null, an empty string or false) at
	 * registration is the same as not declaring one, so a `show_in_rest`-only
	 * setting still gets the `manage_options` default.
	 */
	public function testEmptyDeclaredCapabilityDoesNotMakeRestOnlySettingPublic(): void {
		$capabilities = [ null, '', false ];

		foreach ( $capabilities as $index => $capability ) {
			$option = 'acme_plugin_empty_cap_' . $index;

			$this->register_test_setting(
				'acme_plugin',
				$option,
				[
					'type'               => 'string',
					'show_in_rest'       => true,
					'graphql_capability' => $capability,
				]
			);
			update_option( $option, 'empty-cap-value' );
		}

		foreach ( array_keys( $capabilities ) as $index ) {
			$this->assertReadableOnlyByAdmins(
				'empty-cap-value',
				'acmePluginSettings',
				'acmePluginEmptyCap' . $index,
				'acmePluginSettingsAcmePluginEmptyCap' . $index
			);
		}
	}

	/**
	 * Setting an entry's `graphql_capability` to null in the
	 * `graphql_normalized_settings` filter works the same as removing it.
	 */
	public function testNormalizedSettingsFilterNullCapabilityMakesRestOnlySettingPublic(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);
		update_option( 'acme_plugin_secret', 'nulled-value' );

		$filter = static function ( array $settings ) {
			if ( isset( $settings['acme_plugin_secret'] ) ) {
				$settings['acme_plugin_secret']['graphql_capability'] = null;
			}

			return $settings;
		};
		add_filter( 'graphql_normalized_settings', $filter );
		$this->clearSchema();

		$this->assertReadableByEveryone( 'nulled-value', 'acmePluginSettings', 'acmePluginSecret', 'acmePluginSettingsAcmePluginSecret' );

		remove_filter( 'graphql_normalized_settings', $filter );
	}

	/**
	 * Restricting a setting doesn't change the Schema: restricted fields stay
	 * on both types, so existing queries keep validating.
	 */
	public function testRestrictedSettingsStayInTheSchema(): void {
		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);

		$actual = $this->query_as(
			0,
			'query {
				grouped: __type(name: "AcmePluginSettings") { fields { name } }
				flat: __type(name: "Settings") { fields { name } }
				input: __type(name: "UpdateSettingsInput") { inputFields { name } }
			}'
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertContains( 'acmePluginSecret', wp_list_pluck( $actual['data']['grouped']['fields'], 'name' ) );
		$this->assertContains( 'acmePluginSettingsAcmePluginSecret', wp_list_pluck( $actual['data']['flat']['fields'], 'name' ) );
		$this->assertContains( 'acmePluginSettingsAcmePluginSecret', wp_list_pluck( $actual['data']['input']['inputFields'], 'name' ) );
	}

	/**
	 * A connector API key is readable only by users who can manage options,
	 * and is masked for them in WordPress core's format: one bullet per
	 * character except the last four, capped at 16 bullets, then the last four
	 * characters.
	 */
	public function testConnectorApiKeyIsRestrictedAndMasked(): void {
		$this->register_test_connector();

		// 28 characters: masked to 16 bullets (the cap) plus the last four.
		update_option( self::CONNECTOR_SETTING, 'sk-live-abcdefghijklmnop1234' );

		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 16 ) . '1234' );

		// 10 characters: six bullets plus the last four.
		update_option( self::CONNECTOR_SETTING, 'abcdef9876' );

		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 6 ) . '9876' );
	}

	/**
	 * A key of four characters or fewer is fully masked rather than returned
	 * as is, and an empty key stays empty.
	 */
	public function testShortAndEmptyConnectorApiKeys(): void {
		$this->register_test_connector();

		update_option( self::CONNECTOR_SETTING, 'abcd' );
		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 4 ) );

		update_option( self::CONNECTOR_SETTING, 'x' );
		$this->assertConnectorKeyReadableOnlyByAdmins( "\u{2022}" );

		update_option( self::CONNECTOR_SETTING, '' );
		$this->assertConnectorKeyReadableOnlyByAdmins( '' );
	}

	/**
	 * A connector API key stored as a number is cast to a string before it's
	 * masked, so it's masked like any other key.
	 */
	public function testNumericConnectorApiKeyIsMasked(): void {
		$this->register_test_connector();

		$filter = static function () {
			return 1234567890;
		};
		add_filter( 'pre_option_' . self::CONNECTOR_SETTING, $filter );

		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 6 ) . '7890' );

		remove_filter( 'pre_option_' . self::CONNECTOR_SETTING, $filter );
	}

	/**
	 * A value filter that returns something other than a string for a
	 * connector API key can't have it masked, so the field resolves to null
	 * for everyone, administrators included, rather than returning the value
	 * as is.
	 */
	public function testNonStringFilteredConnectorApiKeyResolvesToNull(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-live-abcdefghijklmnop1234' );

		$returned = 1234567890;
		$filter   = static function ( $value, array $setting_field ) use ( &$returned ) {
			return self::CONNECTOR_SETTING === ( $setting_field['key'] ?? null ) ? $returned : $value;
		};
		add_filter( 'graphql_setting_field_value', $filter, 10, 2 );

		// An integer would otherwise serialize as the full number.
		$this->assertConnectorKeyReadableOnlyByAdmins( null );

		$returned = [ 'key' => 'sk-live-abcdefghijklmnop1234' ];
		$this->assertConnectorKeyReadableOnlyByAdmins( null );

		remove_filter( 'graphql_setting_field_value', $filter, 10 );
	}

	/**
	 * A connector API key stays restricted and masked even when its setting
	 * opts into GraphQL with `show_in_graphql => true`.
	 */
	public function testConnectorApiKeyOptedIntoGraphqlIsStillRestrictedAndMasked(): void {
		$this->register_test_connector( [ 'show_in_graphql' => true ] );
		update_option( self::CONNECTOR_SETTING, 'opted-in-key-5678' );

		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 13 ) . '5678' );
	}

	/**
	 * Removing a connector key's capability with the `graphql_normalized_settings`
	 * filter doesn't make it public, and a weaker declared capability doesn't
	 * lower the `manage_options` requirement.
	 */
	public function testConnectorApiKeyRestrictionCannotBeLowered(): void {
		$this->register_test_connector( [ 'graphql_capability' => 'read' ] );
		update_option( self::CONNECTOR_SETTING, 'lowered-key-4321' );

		$filter = static function ( array $settings ) {
			foreach ( $settings as $key => $setting ) {
				unset( $settings[ $key ]['graphql_capability'] );
			}

			return $settings;
		};
		add_filter( 'graphql_normalized_settings', $filter );
		$this->clearSchema();

		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 12 ) . '4321' );

		remove_filter( 'graphql_normalized_settings', $filter );
	}

	/**
	 * A connector API key is masked even if a `graphql_setting_field_value`
	 * filter returns the stored value.
	 */
	public function testConnectorApiKeyMaskAppliesAfterValueFilter(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'filtered-key-2468' );

		$filter = static function ( $value, $setting_field ) {
			if ( self::CONNECTOR_SETTING === ( $setting_field['key'] ?? '' ) ) {
				return get_option( self::CONNECTOR_SETTING );
			}

			return $value;
		};
		add_filter( 'graphql_setting_field_value', $filter, 10, 2 );

		$values = $this->read_setting_as( $this->admin, 'connectorsSettings', 'wpgraphqlTestConnectorApiKey', 'connectorsSettingsWpgraphqlTestConnectorApiKey' );

		remove_filter( 'graphql_setting_field_value', $filter, 10 );

		$this->assertSame( str_repeat( "\u{2022}", 13 ) . '2468', $values['grouped'] );
		$this->assertSame( str_repeat( "\u{2022}", 13 ) . '2468', $values['flat'] );
	}

	/**
	 * Writing a connector key's masked value back through updateSettings (as a
	 * client that reads settings and saves them back would) leaves the stored
	 * key unchanged, while a new key is stored.
	 */
	public function testUpdateSettingsDoesNotOverwriteConnectorKeyWithItsMask(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-original-key-9999' );

		$mutation = '
			mutation ( $key: String ) {
				updateSettings( input: { connectorsSettingsWpgraphqlTestConnectorApiKey: $key } ) {
					connectorsSettings { wpgraphqlTestConnectorApiKey }
					allSettings { connectorsSettingsWpgraphqlTestConnectorApiKey }
				}
			}
		';

		wp_set_current_user( $this->admin );

		$masked = str_repeat( "\u{2022}", 16 ) . '9999';
		$actual = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [ 'key' => $masked ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual, isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' );
		$this->assertSame( 'sk-original-key-9999', get_option( self::CONNECTOR_SETTING ) );
		$this->assertSame( $masked, $actual['data']['updateSettings']['connectorsSettings']['wpgraphqlTestConnectorApiKey'] );
		$this->assertSame( $masked, $actual['data']['updateSettings']['allSettings']['connectorsSettingsWpgraphqlTestConnectorApiKey'] );

		$actual = $this->graphql(
			[
				'query'     => $mutation,
				'variables' => [ 'key' => 'sk-replacement-key-1111' ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual, isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' );
		$this->assertSame( 'sk-replacement-key-1111', get_option( self::CONNECTOR_SETTING ) );
		$this->assertSame( str_repeat( "\u{2022}", 16 ) . '1111', $actual['data']['updateSettings']['connectorsSettings']['wpgraphqlTestConnectorApiKey'] );
	}

	/**
	 * Runs the connector key update mutation as an administrator.
	 *
	 * @param string $key The value to send.
	 *
	 * @return array<string,mixed>
	 */
	private function update_connector_key_as_admin( string $key ): array {
		$mutation = '
			mutation ( $key: String ) {
				updateSettings( input: { connectorsSettingsWpgraphqlTestConnectorApiKey: $key } ) {
					connectorsSettings { wpgraphqlTestConnectorApiKey }
				}
			}
		';

		$actual = $this->query_as( $this->admin, $mutation, [ 'key' => $key ] );

		$this->assertArrayNotHasKey( 'errors', $actual, isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' );

		return $actual;
	}

	/**
	 * When a value filter returns a different key than the stored one (for
	 * example one defined in a constant), administrators read the mask of that
	 * key. Sending that mask back must still leave the stored key unchanged.
	 */
	public function testUpdateSettingsDoesNotOverwriteConnectorKeyWithTheMaskOfAFilteredValue(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-stored-key-9999' );

		$filter = static function ( $value, array $setting_field ) {
			return self::CONNECTOR_SETTING === ( $setting_field['key'] ?? null ) ? 'sk-key-from-a-constant-4321' : $value;
		};
		add_filter( 'graphql_setting_field_value', $filter, 10, 2 );

		$masked = str_repeat( "\u{2022}", 16 ) . '4321';
		$this->assertConnectorKeyReadableOnlyByAdmins( $masked );

		$this->update_connector_key_as_admin( $masked );

		remove_filter( 'graphql_setting_field_value', $filter, 10 );

		$this->assertSame( 'sk-stored-key-9999', get_option( self::CONNECTOR_SETTING ) );
	}

	/**
	 * When the key is read as a number (here through a `pre_option_` filter),
	 * administrators read its mask, and sending that mask back must leave the
	 * stored key unchanged.
	 */
	public function testUpdateSettingsDoesNotOverwriteNumericConnectorKeyWithItsMask(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-stored-key-9999' );

		$filter = static function () {
			return 1234567890;
		};
		add_filter( 'pre_option_' . self::CONNECTOR_SETTING, $filter );

		$masked = str_repeat( "\u{2022}", 6 ) . '7890';
		$this->assertConnectorKeyReadableOnlyByAdmins( $masked );

		$this->update_connector_key_as_admin( $masked );

		remove_filter( 'pre_option_' . self::CONNECTOR_SETTING, $filter );

		$this->assertSame( 'sk-stored-key-9999', get_option( self::CONNECTOR_SETTING ) );
	}

	/**
	 * Sending an empty string through updateSettings clears a connector key.
	 */
	public function testUpdateSettingsClearsConnectorKeyWithAnEmptyString(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-stored-key-9999' );

		$actual = $this->update_connector_key_as_admin( '' );

		$this->assertSame( '', get_option( self::CONNECTOR_SETTING ) );
		$this->assertSame( '', $actual['data']['updateSettings']['connectorsSettings']['wpgraphqlTestConnectorApiKey'] );
	}

	/**
	 * Lowering a connector key's capability through
	 * `graphql_model_field_capability` doesn't make it readable by users who
	 * can't manage options.
	 */
	public function testConnectorApiKeyRestrictionCannotBeLoweredByModelFieldCapability(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-live-abcdefghijklmnop1234' );

		$filter = static function () {
			return '';
		};
		add_filter( 'graphql_model_field_capability', $filter );

		$this->assertConnectorKeyReadableOnlyByAdmins( str_repeat( "\u{2022}", 16 ) . '1234' );

		remove_filter( 'graphql_model_field_capability', $filter );
	}

	/**
	 * Refetching a settings group with `node( id: )` applies the same
	 * restrictions as the settings fields.
	 */
	public function testSettingGroupNodeRefetchAppliesRestrictions(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-live-abcdefghijklmnop1234' );

		$this->register_test_setting(
			'acme_plugin',
			'acme_plugin_secret',
			[
				'type'         => 'string',
				'show_in_rest' => true,
			]
		);
		update_option( 'acme_plugin_secret', 'plugin-secret-value' );

		$query = '
			query ( $connectors: ID!, $acme: ID! ) {
				connectors: node( id: $connectors ) { ... on ConnectorsSettings { wpgraphqlTestConnectorApiKey } }
				acme: node( id: $acme ) { ... on AcmePluginSettings { acmePluginSecret } }
			}
		';

		$variables = [
			'connectors' => \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'connectors' ),
			'acme'       => \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'acmePlugin' ),
		];

		foreach ( $this->users() as $label => $user_id ) {
			$actual = $this->query_as( $user_id, $query, $variables );

			$this->assertArrayNotHasKey( 'errors', $actual, $label . ': ' . ( isset( $actual['errors'] ) ? wp_json_encode( $actual['errors'] ) : '' ) );

			$is_admin = 'administrator' === $label;
			$this->assertSame( $is_admin ? str_repeat( "\u{2022}", 16 ) . '1234' : null, $actual['data']['connectors']['wpgraphqlTestConnectorApiKey'] ?? null, $label );
			$this->assertSame( $is_admin ? 'plugin-secret-value' : null, $actual['data']['acme']['acmePluginSecret'] ?? null, $label );
		}
	}

	/**
	 * A site administrator who isn't a network super admin reads a connector
	 * key masked, like any user who can manage options. On a single site this
	 * is the same as any administrator.
	 */
	public function testSiteAdministratorReadsConnectorKeyMasked(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-live-abcdefghijklmnop1234' );

		$site_admin = $this->factory->user->create( [ 'role' => 'administrator' ] );

		$values = $this->read_setting_as( $site_admin, 'connectorsSettings', 'wpgraphqlTestConnectorApiKey', 'connectorsSettingsWpgraphqlTestConnectorApiKey' );

		$this->assertSame( str_repeat( "\u{2022}", 16 ) . '1234', $values['grouped'] );
		$this->assertSame( str_repeat( "\u{2022}", 16 ) . '1234', $values['flat'] );
	}

	/**
	 * Every setting WordPress registers in register_initial_settings() is on
	 * the list of core settings that stay public, so a core setting is never
	 * restricted by accident. This runs on every WordPress version in the test
	 * matrix, so it fails when a release adds a setting.
	 */
	public function testCoreSettingNamesCoverRegisterInitialSettings(): void {
		global $wp_registered_settings;

		$backup                 = $wp_registered_settings;
		$wp_registered_settings = [];

		register_initial_settings();
		$registered = array_keys( $wp_registered_settings );

		$wp_registered_settings = $backup;

		$method = new \ReflectionMethod( \WPGraphQL\Data\DataSource::class, 'get_core_setting_names' );
		$method->setAccessible( true );
		$core_names = $method->invoke( null );

		$this->assertNotEmpty( $registered );
		$this->assertSame( [], array_values( array_diff( $registered, $core_names ) ) );
	}

	/**
	 * Users who can't manage options still can't write a connector key.
	 */
	public function testUpdateSettingsRejectsConnectorKeyWriteFromNonAdmins(): void {
		$this->register_test_connector();
		update_option( self::CONNECTOR_SETTING, 'sk-original-key-9999' );

		$mutation = 'mutation { updateSettings( input: { connectorsSettingsWpgraphqlTestConnectorApiKey: "replacement-key" } ) { connectorsSettings { wpgraphqlTestConnectorApiKey } } }';

		foreach ( [ 'anonymous' => 0, 'subscriber' => $this->subscriber ] as $label => $user_id ) {
			$actual = $this->query_as( $user_id, $mutation );

			$this->assertArrayHasKey( 'errors', $actual, $label );
			$this->assertSame( 'sk-original-key-9999', get_option( self::CONNECTOR_SETTING ), $label );
		}
	}
}
