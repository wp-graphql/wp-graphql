<?php

/**
 * Tests the `graphql_purge_all` per-entry config key: core flags its
 * broadly-impactful settings (the permalink options) with it, it does not leak
 * onto unrelated settings, and extensions can set it through the normalized
 * settings map. The key itself has no core behavior; it is consumed by
 * cache-invalidation layers (e.g. WPGraphQL Smart Cache).
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/2459
 */
class SettingsPurgeAllConfigTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
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
	 * Returns the normalized settings, grouped, after booting the schema.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	protected function get_settings_by_group(): array {
		// A request boots the schema so the normalized settings map is built.
		$this->graphql( [ 'query' => '{ __typename }' ] );

		return \WPGraphQL\Data\DataSource::get_allowed_settings_by_group( \WPGraphQL::get_type_registry() );
	}

	/**
	 * The permalink options should carry graphql_purge_all: they drive the `uri`
	 * field across the schema, so a change has schema-wide impact.
	 */
	public function testPermalinkSettingsCarryPurgeAllFlag() {
		$by_group = $this->get_settings_by_group();

		$this->assertNotEmpty( $by_group['permalink']['permalink_structure']['graphql_purge_all'] ?? null );
		$this->assertNotEmpty( $by_group['permalink']['category_base']['graphql_purge_all'] ?? null );
		$this->assertNotEmpty( $by_group['permalink']['tag_base']['graphql_purge_all'] ?? null );
	}

	/**
	 * The flag should be scoped to the settings that declare it. No setting
	 * outside the permalink group should carry graphql_purge_all in the first
	 * cut, so a change to (e.g.) the site title stays a per-group invalidation.
	 */
	public function testPurgeAllFlagDoesNotLeakToOtherSettings() {
		$by_group = $this->get_settings_by_group();

		foreach ( $by_group as $group => $settings ) {
			if ( 'permalink' === $group ) {
				continue;
			}

			foreach ( $settings as $key => $setting ) {
				$this->assertArrayNotHasKey(
					'graphql_purge_all',
					$setting,
					sprintf( 'Setting "%s" in group "%s" should not be flagged graphql_purge_all.', $key, $group )
				);
			}
		}
	}

	/**
	 * An extension registering a broadly-impactful setting through the
	 * normalized settings map can flag it graphql_purge_all, and the flag
	 * survives into the grouped map.
	 */
	public function testExtensionCanFlagPurgeAllViaNormalizedFilter() {
		$filter = static function ( $settings ) {
			$settings['my_broad_option'] = [
				'key'               => 'my_broad_option',
				'group'             => 'general',
				'type'              => 'string',
				'description'       => 'A broadly-impactful setting seeded for testing.',
				'graphql_purge_all' => true,
			];
			return $settings;
		};

		add_filter( 'graphql_normalized_settings', $filter );

		$by_group = $this->get_settings_by_group();

		remove_filter( 'graphql_normalized_settings', $filter );

		$this->assertNotEmpty( $by_group['general']['my_broad_option']['graphql_purge_all'] ?? null );
	}

	/**
	 * The grouped settings map is resolvable without a built schema. Cache
	 * invalidation reads it on `updated_option` outside a GraphQL request, where
	 * the type registry is not initialized, so `get_allowed_settings_by_group()`
	 * must work when called with no TypeRegistry.
	 *
	 * This test deliberately does NOT boot the schema (no graphql() call) before
	 * reading the map.
	 */
	public function testGroupedMapResolvesWithoutBuiltSchema() {
		// No registry passed, and the schema is never booted in this test.
		$by_group = \WPGraphQL\Data\DataSource::get_allowed_settings_by_group();

		// A registered core setting still maps to its group.
		$this->assertArrayHasKey( 'general', $by_group );
		$this->assertArrayHasKey( 'blogname', $by_group['general'] );

		// The in-memory permalink shims (and their broad-impact flag) are present
		// too, so a permalink change escalates correctly with no schema built.
		$this->assertNotEmpty( $by_group['permalink']['permalink_structure']['graphql_purge_all'] ?? null );
		$this->assertNotEmpty( $by_group['permalink']['tag_base']['graphql_purge_all'] ?? null );
	}

	/**
	 * The type gate is conditional on a TypeRegistry being provided. A setting
	 * whose declared type has no corresponding GraphQL type is excluded from the
	 * map when a registry is passed (the schema-build path, where it couldn't
	 * become a field), but included when the map is resolved without one (where
	 * the map only identifies settings).
	 */
	public function testTypeGateAppliesOnlyWhenRegistryProvided() {
		register_setting(
			'general',
			'wpgraphql_test_unknown_type_option',
			[
				'type'         => 'wpgraphql_nonexistent_type',
				'show_in_rest' => true,
			]
		);

		// Boot the schema so the type registry is built, then resolve the flat map
		// WITH the registry: the unresolved-type setting is gated out.
		$this->graphql( [ 'query' => '{ __typename }' ] );
		$with_registry = \WPGraphQL\Data\DataSource::get_allowed_settings( \WPGraphQL::get_type_registry() );
		$this->assertArrayNotHasKey( 'wpgraphql_test_unknown_type_option', $with_registry );

		// Resolve the same map WITHOUT a registry: the gate is skipped, so it's kept.
		$without_registry = \WPGraphQL\Data\DataSource::get_allowed_settings();
		$this->assertArrayHasKey( 'wpgraphql_test_unknown_type_option', $without_registry );

		unregister_setting( 'general', 'wpgraphql_test_unknown_type_option' );
	}
}
