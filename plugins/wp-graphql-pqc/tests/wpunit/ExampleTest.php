<?php
/**
 * Example WPUnit test
 *
 * @package WPGraphQL\PQC\Tests\WPUnit
 * @since 0.1.0-beta.1
 */

/**
 * Class ExampleTest
 *
 * @package WPGraphQL\PQC\Tests\WPUnit
 */
class ExampleTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test that the plugin is loaded
	 *
	 * @return void
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'WPGRAPHQL_PQC_VERSION' ) );
		$this->assertEquals( '0.1.0-beta.1', WPGRAPHQL_PQC_VERSION );
	}
}
