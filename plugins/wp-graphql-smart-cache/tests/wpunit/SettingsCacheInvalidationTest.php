<?php
/**
 * Proves the purge half of settings caching: when a mapped setting's option
 * changes, Smart Cache purges the owning settings-group node key (or purges all
 * for broad-impact settings), while unmapped options and transient writes are
 * no-ops (the loop guard).
 *
 * The tagging half — a settings query being tagged with its group's node key —
 * is covered by SettingsCacheKeysTest. This test keys off that same node id on
 * the invalidation side.
 */

use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;
use WPGraphQL\SmartCache\Cache\Invalidation;

class SettingsCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	/**
	 * Merge settings-group queries into the seeded/populated cache fixture so
	 * getEvictedCaches() tracks them alongside the post/term/user queries.
	 *
	 * `expectedCacheKeys` asserts (at populate time, in setUp) that each query is
	 * tagged with its group's node id, so any failure here isolates to the
	 * invalidation listener rather than the tagging.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function getQueries() {
		$queries = parent::getQueries();

		$queries['generalSettings'] = [
			'name'              => 'generalSettings',
			'query'             => 'query GetGeneralSettings { generalSettings { id title } }',
			'variables'         => null,
			'assertions'        => [],
			'expectedCacheKeys' => [
				$this->toRelayId( 'setting_group', 'general' ),
			],
		];

		$queries['readingSettings'] = [
			'name'              => 'readingSettings',
			'query'             => 'query GetReadingSettings { readingSettings { id } }',
			'variables'         => null,
			'assertions'        => [],
			'expectedCacheKeys' => [
				$this->toRelayId( 'setting_group', 'reading' ),
			],
		];

		return $queries;
	}

	/**
	 * A change to a mapped setting purges only the owning group's node key.
	 * blogname is the `title` field of the general group.
	 */
	public function testMappedSettingUpdatePurgesOwningGroup() {

		// all fixture queries are cached to start
		$this->assertEmpty( $this->getEvictedCaches() );

		update_option( 'blogname', 'Changed Title' );

		$evicted = $this->getEvictedCaches();

		// the general group query is purged
		$this->assertContains( 'generalSettings', $evicted );

		// per-group granularity: a different group's query is untouched
		$this->assertNotContains( 'readingSettings', $evicted );

		// and it does not spill over to unrelated (post/term) queries
		$this->assertNotContains( 'listPost', $evicted );
		$this->assertNotContains( 'singlePost', $evicted );
	}

	/**
	 * Updating an option that is not a mapped setting is a no-op. This is the
	 * primary gate that also covers Smart Cache's own option storage.
	 */
	public function testUnmappedOptionUpdateDoesNotPurge() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add then update so `updated_option` (not `added_option`) fires
		add_option( 'some_unmapped_test_option', 'first' );
		update_option( 'some_unmapped_test_option', 'second' );

		$this->assertEmpty( $this->getEvictedCaches() );
	}

	/**
	 * Transient writes (stored as options) must never purge, or Smart Cache's own
	 * transient churn would evict caches on unrelated activity / loop.
	 */
	public function testTransientUpdateDoesNotPurge() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set twice so the second write fires `updated_option` for the
		// `_transient_*` option key, exercising the short-circuit
		set_transient( 'my_test_transient', 'a', HOUR_IN_SECONDS );
		set_transient( 'my_test_transient', 'b', HOUR_IN_SECONDS );

		// site transients travel the same path (`_site_transient_*`)
		set_site_transient( 'my_test_site_transient', 'a', HOUR_IN_SECONDS );
		set_site_transient( 'my_test_site_transient', 'b', HOUR_IN_SECONDS );

		$this->assertEmpty( $this->getEvictedCaches() );
	}

	/**
	 * A broad-impact setting (carrying `graphql_purge_all` in core's normalized
	 * map — the permalink shims) escalates to a full purge, evicting queries in
	 * every group and unrelated content queries too.
	 */
	public function testBroadImpactSettingEscalatesToPurgeAll() {

		$this->assertEmpty( $this->getEvictedCaches() );

		update_option( 'permalink_structure', '/%postname%/' );

		// purge_all: nothing survives, across every group and content type
		$this->assertEmpty( $this->getNonEvictedCaches() );
	}

	/**
	 * The `graphql_cache_purge_all_option_keys` filter lets a site escalate a raw
	 * option key that is not itself a mapped setting (e.g. `gmt_offset`, which
	 * backs the timezone field but is not registered as a setting).
	 */
	public function testPurgeAllOptionKeysFilterEscalatesRawKey() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// Without the filter, gmt_offset is unmapped -> no-op
		update_option( 'gmt_offset', 3 );
		$this->assertEmpty( $this->getEvictedCaches() );

		// With the filter, the raw key escalates to purge_all
		$callback = static function ( $keys ) {
			$keys[] = 'gmt_offset';
			return $keys;
		};
		add_filter( 'graphql_cache_purge_all_option_keys', $callback );

		update_option( 'gmt_offset', 5 );

		$this->assertEmpty( $this->getNonEvictedCaches() );

		remove_filter( 'graphql_cache_purge_all_option_keys', $callback );
	}

	/**
	 * The updateSettings mutation purges through the same `updated_option`
	 * listener (it calls update_option under the hood) — no separate mutation
	 * hook, no double purge.
	 */
	public function testUpdateSettingsMutationPurgesGroup() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_set_current_user( $this->admin->ID );

		$mutation = 'mutation Update( $input: UpdateSettingsInput! ) {
			updateSettings( input: $input ) {
				generalSettings { title }
			}
		}';

		$response = graphql(
			[
				'query'     => $mutation,
				'variables' => [
					'input' => [ 'generalSettingsTitle' => 'Mutated Title' ],
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $response );

		$evicted = $this->getEvictedCaches();

		$this->assertContains( 'generalSettings', $evicted );
		$this->assertNotContains( 'readingSettings', $evicted );
	}

	/**
	 * The primary production path: an option write outside a GraphQL request (an
	 * admin Settings save, REST, WP-CLI) runs with no schema built, so the GraphQL
	 * type registry is not initialized. The option => group map must still resolve
	 * from WordPress's raw settings registry rather than needing a schema build.
	 *
	 * Invoked on a fresh Invalidation instance (bypassing the process-lifetime
	 * memoized map) with the schema cleared, so the raw-registry path is exercised.
	 */
	public function testMappedSettingPurgesWithUninitializedRegistry() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// Simulate a non-GraphQL request: no schema, uninitialized type registry.
		\WPGraphQL::clear_schema();

		$invalidation = new Invalidation( $this->collection );
		$invalidation->on_updated_option_cb( 'blogname', 'Old Title', 'New Title' );

		$evicted = $this->getEvictedCaches();

		$this->assertContains( 'generalSettings', $evicted );
		$this->assertNotContains( 'readingSettings', $evicted );
	}

	/**
	 * The broad-impact escalation must also work with no schema built: the
	 * permalink shims (which WordPress does not register via register_setting())
	 * resolve from Smart Cache's shim fallback and escalate to purge_all.
	 */
	public function testPermalinkPurgesAllWithUninitializedRegistry() {

		$this->assertEmpty( $this->getEvictedCaches() );

		\WPGraphQL::clear_schema();

		$invalidation = new Invalidation( $this->collection );
		$invalidation->on_updated_option_cb( 'permalink_structure', '', '/%postname%/' );

		$this->assertEmpty( $this->getNonEvictedCaches() );
	}
}
