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
}
