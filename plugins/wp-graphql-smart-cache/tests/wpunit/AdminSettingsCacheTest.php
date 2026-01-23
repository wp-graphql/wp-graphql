<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Admin\Settings;

/**
 * Test the wp-graphql request to cached query is faster
 */

class AdminSettingsCacheTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_cache_section' );
	}

	public function _after() {
		delete_option( 'graphql_cache_section' );
	}

	public function testCacheSettingsOff() {
		delete_option( 'graphql_cache_section' );
		$this->assertFalse( Settings::caching_enabled() );

		update_option( 'graphql_cache_section', [] );
		$this->assertFalse( Settings::caching_enabled() );

		update_option( 'graphql_cache_section', [ 'cache_toggle' => 'off' ] );
		$this->assertFalse( Settings::caching_enabled() );

		update_option( 'graphql_cache_section', [ 'cache_toggle' => false ] );
		$this->assertFalse( Settings::caching_enabled() );
	}

	public function testCacheSettingsOn() {
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );
		$this->assertTrue( Settings::caching_enabled() );
	}

	public function testQueryAnalyzerSettingIsForcedOn() {

		// disable debug mode
		add_filter( 'graphql_debug_enabled', '__return_false' );

		// assert that debug mode is off
		$this->assertFalse( \WPGraphQL::debug() );

		// update the setting to disable query analyzer
		update_option( 'graphql_general_settings', [ 'query_analyzer_enabled', 'off' ] );

		// assert that the query analyzer is still enabled, even though the setting is turned off
		$this->assertTrue( \WPGraphQL\Utils\QueryAnalyzer::is_enabled() );
	}
}
