<?php
/**
 * Confirms that settings groups, now that they are Nodes backed by a Model and
 * a DataLoader in WPGraphQL core (#2459), are collected by the Query Analyzer
 * and mapped into the Smart Cache node -> query collection like any other node.
 *
 * This is the tagging half of settings caching: it proves a settings query is
 * tagged with the group's node key. Purging that key when the underlying option
 * changes is a separate concern (see SettingsCacheInvalidationTest).
 */

namespace WPGraphQL\SmartCache;

use GraphQLRelay\Relay;
use WPGraphQL\SmartCache\Cache\Collection;

class SettingsCacheKeysTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		\WPGraphQL::clear_schema();

		if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
			define( 'GRAPHQL_DEBUG', true );
		}

		// Enable the node -> query cache maps so save_query_mapping_cb records nodes.
		add_filter( 'wpgraphql_cache_enable_cache_maps', '__return_true' );

		parent::setUp();
	}

	public function tearDown(): void {
		remove_filter( 'wpgraphql_cache_enable_cache_maps', '__return_true' );
		\WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * A query for a settings group should map that query's request key to the
	 * group's node id in the Smart Cache collection.
	 */
	public function testSettingsGroupQueryIsTaggedWithGroupNodeKey() {
		$query = 'query GetGeneralSettings {
			generalSettings {
				id
			}
		}';

		$response = graphql( [ 'query' => $query ] );

		// Sanity check: the group exposes a node id (core #2459 behavior).
		$expected_id = Relay::toGlobalId( 'setting_group', 'general' );
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertSame( $expected_id, $response['data']['generalSettings']['id'] );

		// The collection should have mapped this query to the group's node key.
		$collection    = new Collection();
		$mapped_queries = $collection->get( $expected_id );

		$this->assertNotEmpty(
			$mapped_queries,
			'Expected the settings group node key to be collected for the query.'
		);
	}

	/**
	 * Distinct settings groups should be tagged with distinct node keys, so a
	 * future change to one group can purge only the queries that touched it.
	 */
	public function testDistinctSettingsGroupsProduceDistinctNodeKeys() {
		graphql(
			[
				'query' => 'query GetGeneral {
					generalSettings {
						id
					}
				}',
			]
		);

		graphql(
			[
				'query' => 'query GetReading {
					readingSettings {
						id
					}
				}',
			]
		);

		$collection = new Collection();

		$general_id = Relay::toGlobalId( 'setting_group', 'general' );
		$reading_id = Relay::toGlobalId( 'setting_group', 'reading' );

		$general_queries = $collection->get( $general_id );
		$reading_queries = $collection->get( $reading_id );

		$this->assertNotEmpty( $general_queries );
		$this->assertNotEmpty( $reading_queries );

		// The general-only query should not be tagged under the reading group's key.
		$this->assertEmpty(
			array_intersect( (array) $general_queries, (array) $reading_queries ),
			'A query for one settings group should not be mapped to another group\'s node key.'
		);
	}
}
