<?php

/**
 * Tests the Model layer for setting groups: per-setting `graphql_capability`
 * restrictions enforced through the SettingGroup Model, and node-level
 * privacy via the `graphql_data_is_private` filter.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/2459
 */
class SettingGroupModelTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var int
	 */
	public $admin;

	/**
	 * @var int
	 */
	public $editor;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->editor = $this->factory()->user->create(
			[
				'role' => 'editor',
			]
		);

		WPGraphQL::clear_schema();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	/**
	 * A setting carrying `graphql_capability` should resolve to null (with a
	 * debug message) for users lacking the capability, on both the grouped
	 * and flat read surfaces, and resolve normally for users who have it.
	 *
	 * @throws \Exception
	 */
	public function testGraphqlCapabilityRestrictsSettingOnBothSurfaces() {
		update_option( 'secret_shim', 'top secret' );

		$filter = static function ( $settings ) {
			$settings['secret_shim'] = [
				'key'                => 'secret_shim',
				'group'              => 'general',
				'type'               => 'string',
				'description'        => 'A capability-restricted setting seeded for testing.',
				'graphql_capability' => 'manage_options',
			];
			return $settings;
		};

		add_filter( 'graphql_normalized_settings', $filter );
		add_filter( 'graphql_debug_enabled', '__return_true' );
		$this->clearSchema();

		$query = '
			query {
				generalSettings {
					secretShim
				}
				allSettings {
					generalSettingsSecretShim
				}
			}
		';

		// A user without manage_options gets null on both surfaces.
		wp_set_current_user( $this->editor );
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['generalSettings']['secretShim'] );
		$this->assertNull( $actual['data']['allSettings']['generalSettingsSecretShim'] );

		$restricted_fields = array_column( $actual['extensions']['debug'], 'field' );
		$this->assertContains( 'GeneralSettings.secretShim', $restricted_fields );
		$this->assertContains( 'Settings.generalSettingsSecretShim', $restricted_fields );
		$this->assertContains(
			'manage_options',
			array_column( $actual['extensions']['debug'], 'required_capability' )
		);

		// A user with manage_options gets the value on both surfaces.
		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'top secret', $actual['data']['generalSettings']['secretShim'] );
		$this->assertSame( 'top secret', $actual['data']['allSettings']['generalSettingsSecretShim'] );

		remove_filter( 'graphql_normalized_settings', $filter );
		remove_filter( 'graphql_debug_enabled', '__return_true' );
	}

	/**
	 * A settings group made private via the `graphql_data_is_private` filter
	 * should resolve to null as a whole, without affecting other groups.
	 *
	 * @throws \Exception
	 */
	public function testGroupPrivatizedViaDataIsPrivateFilterResolvesNull() {
		update_option( 'permalink_structure', '/%postname%/' );

		$filter = static function ( $is_private, $model_name ) {
			if ( 'GeneralSettings' === $model_name ) {
				return true;
			}
			return $is_private;
		};

		add_filter( 'graphql_data_is_private', $filter, 10, 2 );

		$query = '
			query {
				generalSettings {
					title
				}
				permalinkSettings {
					structure
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		remove_filter( 'graphql_data_is_private', $filter, 10 );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['generalSettings'] );
		$this->assertSame( '/%postname%/', $actual['data']['permalinkSettings']['structure'] );
	}

	/**
	 * The node(id:) field should also return null for a privatized group.
	 *
	 * @throws \Exception
	 */
	public function testNodeQueryForPrivatizedGroupReturnsNull() {
		$filter = static function ( $is_private, $model_name ) {
			if ( 'GeneralSettings' === $model_name ) {
				return true;
			}
			return $is_private;
		};

		add_filter( 'graphql_data_is_private', $filter, 10, 2 );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'general' );

		$query = '
			query GetSettingGroupNode( $id: ID! ) {
				node( id: $id ) {
					__typename
					id
				}
			}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $global_id,
				],
			]
		);

		remove_filter( 'graphql_data_is_private', $filter, 10 );

		$this->assertNull( $actual['data']['node'] );
	}
}
