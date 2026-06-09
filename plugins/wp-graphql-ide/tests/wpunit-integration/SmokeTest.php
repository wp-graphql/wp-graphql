<?php
/**
 * Sanity check that the wpunit-integration suite boots WordPress with
 * Smart Cache active. Keeps the harness honest — if the plugin list
 * ever drifts, this fires before any real integration test does.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE\Integration;

class SmokeTest extends \Codeception\TestCase\WPTestCase {

	public function test_smart_cache_classes_are_loaded() {
		$this->assertTrue(
			class_exists( '\WPGraphQL\SmartCache\Document' ),
			'Smart Cache must be active for the wpunit-integration suite.'
		);
	}

	public function test_smart_cache_post_type_is_registered() {
		$this->assertTrue(
			post_type_exists( 'graphql_document' ),
			'`graphql_document` post type must be registered (Smart Cache owns it).'
		);
		$this->assertTrue(
			taxonomy_exists( 'graphql_query_alias' ),
			'`graphql_query_alias` taxonomy must be registered (Smart Cache uses it for the content-addressed hash).'
		);
	}
}
