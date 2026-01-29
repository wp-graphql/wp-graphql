<?php
namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Collection;

class SettingsCacheInvalidationTest extends \Codeception\TestCase\WPTestCase {

	protected $collection;

	public function setUp(): void {
		\WPGraphQL::clear_schema();

		if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
			define( 'GRAPHQL_DEBUG', true );
		}

		$this->collection = new Collection();

		// enable caching for the whole test suite
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		parent::setUp();
	}

	public function tearDown(): void {
		\WPGraphQL::clear_schema();
		// disable caching
		delete_option( 'graphql_cache_section' );
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// @todo I think many updates to settings might require a purge_all.
	// for example, if I change something like the timezone or the site language,
	// that will likely have wide impact on how resolvers execute and should probably
	// trigger wide reaching purge event.
	// I think there's probably going to need to be some
	// case-by-case decisions made here for how settings should
	// actually impact cache.
	//
	// like the "general" settings group seems pretty far reaching
	// but the "writing" settings doesn't seem like
	// it would have _as much_ of an impact.
	// if I were to change some setting like the "default post category" in the writing settings,
	// that wouldn't necessitate a purge_all()
	//
	// the reading settings probably have wider reach though too.
	// if I change the homepage display option, that will impact several things.
	//
	// I'm thinking we need some way to either purge queries for a settings group,
	// purge_all or do nothing.
	//
	//
	// for example,
	// a change to general_settings group should probably purge_all
	// a change to writing settings should probably only purge queries like { writingSettings { ... } }








	// update untracked options (no purge)
	// update tracked option (purge group?)
	// set transient doesn't purge (don't want weird infinite loops)



	// new post types detected?
	// post type removed?
	// new taxonomy added?
	// taxonomy removed?
	// schema breaking change detected?
	// update permalinks (purge all?)




}
