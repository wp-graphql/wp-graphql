<?php
/**
 * Example WPUnit test
 *
 * @package WPGraphQL\PQU\Tests\WPUnit
 * @since 0.1.0-beta.1
 */

/**
 * Class ExampleTest
 *
 * @package WPGraphQL\PQU\Tests\WPUnit
 */
class ExampleTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test that the plugin is loaded
	 *
	 * @return void
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'WPGRAPHQL_PQU_VERSION' ) );
		$this->assertEquals( '0.1.0-beta.1', WPGRAPHQL_PQU_VERSION );
	}
}
