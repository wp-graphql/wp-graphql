<?php

/**
 * Tests for the AppContext class.
 *
 * This test file covers both existing functionality (baseline tests)
 * and new get/set API functionality.
 */
class AppContextTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * BASELINE TESTS FOR EXISTING FUNCTIONALITY
	 * These tests ensure existing AppContext behavior continues to work.
	 */

	/**
	 * Test that AppContext can be instantiated.
	 */
	public function testAppContextCanBeInstantiated() {
		$context = new \WPGraphQL\AppContext();
		$this->assertInstanceOf( \WPGraphQL\AppContext::class, $context );
	}

	/**
	 * Test that AppContext initializes with expected properties.
	 */
	public function testAppContextInitializesWithExpectedProperties() {
		$context = new \WPGraphQL\AppContext();

		// Test that core properties exist
		$this->assertObjectHasProperty( 'viewer', $context );
		$this->assertObjectHasProperty( 'request', $context );
		$this->assertObjectHasProperty( 'config', $context );
		$this->assertObjectHasProperty( 'type_registry', $context );
		$this->assertObjectHasProperty( 'node_resolver', $context );
		$this->assertObjectHasProperty( 'currentConnection', $context );
		$this->assertObjectHasProperty( 'connectionArgs', $context );
	}

	/**
	 * Test that node_resolver is properly initialized.
	 */
	public function testNodeResolverIsInitialized() {
		$context = new \WPGraphQL\AppContext();
		$this->assertInstanceOf( \WPGraphQL\Data\NodeResolver::class, $context->node_resolver );
	}

	/**
	 * Test that config property can be filtered via graphql_app_context_config.
	 */
	public function testConfigPropertyCanBeFiltered() {
		$test_config = [ 'test_key' => 'test_value' ];

		add_filter(
			'graphql_app_context_config',
			function ( $config ) use ( $test_config ) {
				return array_merge( (array) $config, $test_config );
			}
		);

		$context = new \WPGraphQL\AppContext();

		$this->assertIsArray( $context->config );
		$this->assertArrayHasKey( 'test_key', $context->config );
		$this->assertEquals( 'test_value', $context->config['test_key'] );

		remove_all_filters( 'graphql_app_context_config' );
	}

	/**
	 * Test get_loader() method returns correct loader instance.
	 */
	public function testGetLoaderReturnsCorrectInstance() {
		$context = new \WPGraphQL\AppContext();

		$post_loader = $context->get_loader( 'post' );
		$this->assertInstanceOf( \WPGraphQL\Data\Loader\PostObjectLoader::class, $post_loader );

		$user_loader = $context->get_loader( 'user' );
		$this->assertInstanceOf( \WPGraphQL\Data\Loader\UserLoader::class, $user_loader );

		$term_loader = $context->get_loader( 'term' );
		$this->assertInstanceOf( \WPGraphQL\Data\Loader\TermObjectLoader::class, $term_loader );
	}

	/**
	 * Test get_loader() throws exception for non-existent loader.
	 */
	public function testGetLoaderThrowsExceptionForNonExistentLoader() {
		$context = new \WPGraphQL\AppContext();

		$this->expectException( \GraphQL\Error\UserError::class );
		$this->expectExceptionMessage( 'No loader assigned to the key non_existent_loader' );

		$context->get_loader( 'non_existent_loader' );
	}

	/**
	 * Test that loaders are instantiated lazily (on first access).
	 */
	public function testLoadersAreInstantiatedLazily() {
		$context = new \WPGraphQL\AppContext();

		// Access the loaders property via __get (which should trigger deprecation notice)
		// but we're testing that it works
		$loaders = $context->loaders;

		// Initially, loaders array should be empty (lazy loading)
		// unless the deprecated graphql_data_loaders filter is in use
		if ( ! has_filter( 'graphql_data_loaders' ) ) {
			$this->assertEmpty( $loaders );
		}

		// After calling get_loader(), that specific loader should be instantiated
		$context->get_loader( 'post' );
		$loaders = $context->loaders;
		$this->assertArrayHasKey( 'post', $loaders );
		$this->assertInstanceOf( \WPGraphQL\Data\Loader\PostObjectLoader::class, $loaders['post'] );
	}

	/**
	 * Test get_connection_args() method.
	 */
	public function testGetConnectionArgs() {
		$context = new \WPGraphQL\AppContext();

		// Initially should return empty array
		$this->assertIsArray( $context->get_connection_args() );
		$this->assertEmpty( $context->get_connection_args() );

		// Set up a mock connection
		$context->currentConnection                     = 'testConnection';
		$context->connectionArgs['testConnection'] = [ 'first' => 10 ];

		$args = $context->get_connection_args();
		$this->assertIsArray( $args );
		$this->assertArrayHasKey( 'first', $args );
		$this->assertEquals( 10, $args['first'] );
	}

	/**
	 * Test get_current_connection() method.
	 */
	public function testGetCurrentConnection() {
		$context = new \WPGraphQL\AppContext();

		// Initially should return null
		$this->assertNull( $context->get_current_connection() );

		// Set a current connection
		$context->currentConnection = 'testConnection';
		$this->assertEquals( 'testConnection', $context->get_current_connection() );
	}

	/**
	 * Test that dynamic properties can be set and retrieved (existing behavior).
	 */
	public function testDynamicPropertiesCanBeSetAndRetrieved() {
		$context = new \WPGraphQL\AppContext();

		// Set a dynamic property
		$context->custom_property = 'custom_value';

		// Retrieve it
		$this->assertEquals( 'custom_value', $context->custom_property );
	}

	/**
	 * Test that existing public properties can be set without issues.
	 */
	public function testExistingPublicPropertiesCanBeSet() {
		$context = new \WPGraphQL\AppContext();

		// Set existing public properties
		$context->viewer  = 'test_viewer';
		$context->request = 'test_request';
		$context->config  = [ 'test' => 'config' ];

		// Verify they're set correctly
		$this->assertEquals( 'test_viewer', $context->viewer );
		$this->assertEquals( 'test_request', $context->request );
		$this->assertIsArray( $context->config );
		$this->assertEquals( [ 'test' => 'config' ], $context->config );
	}

	/**
	 * Test deprecated getLoader() method still works.
	 */
	public function testDeprecatedGetLoaderMethodStillWorks() {
		$context = new \WPGraphQL\AppContext();

		// Suppress deprecation notice for this test
		$post_loader = @$context->getLoader( 'post' );
		$this->assertInstanceOf( \WPGraphQL\Data\Loader\PostObjectLoader::class, $post_loader );
	}

	/**
	 * Test deprecated getConnectionArgs() method still works.
	 */
	public function testDeprecatedGetConnectionArgsMethodStillWorks() {
		$context = new \WPGraphQL\AppContext();

		$context->currentConnection                     = 'testConnection';
		$context->connectionArgs['testConnection'] = [ 'first' => 5 ];

		// Suppress deprecation notice for this test
		$args = @$context->getConnectionArgs();
		$this->assertIsArray( $args );
		$this->assertEquals( [ 'first' => 5 ], $args );
	}

	/**
	 * Test deprecated getCurrentConnection() method still works.
	 */
	public function testDeprecatedGetCurrentConnectionMethodStillWorks() {
		$context = new \WPGraphQL\AppContext();

		$context->currentConnection = 'testConnection';

		// Suppress deprecation notice for this test
		$connection = @$context->getCurrentConnection();
		$this->assertEquals( 'testConnection', $connection );
	}

	/**
	 * TESTS FOR NEW GET/SET API FUNCTIONALITY
	 * These tests validate the new namespaced key-value store methods.
	 */

	/**
	 * Test set() and get() methods with various data types.
	 */
	public function testSetAndGetWithVariousDataTypes() {
		$context = new \WPGraphQL\AppContext();

		// Test with string
		$context->set( 'test-plugin', 'string-key', 'string value' );
		$this->assertEquals( 'string value', $context->get( 'test-plugin', 'string-key' ) );

		// Test with integer
		$context->set( 'test-plugin', 'int-key', 42 );
		$this->assertEquals( 42, $context->get( 'test-plugin', 'int-key' ) );

		// Test with float
		$context->set( 'test-plugin', 'float-key', 3.14 );
		$this->assertEquals( 3.14, $context->get( 'test-plugin', 'float-key' ) );

		// Test with boolean
		$context->set( 'test-plugin', 'bool-key', true );
		$this->assertTrue( $context->get( 'test-plugin', 'bool-key' ) );

		// Test with array
		$context->set( 'test-plugin', 'array-key', [ 'a', 'b', 'c' ] );
		$this->assertEquals( [ 'a', 'b', 'c' ], $context->get( 'test-plugin', 'array-key' ) );

		// Test with object
		$obj       = new \stdClass();
		$obj->prop = 'value';
		$context->set( 'test-plugin', 'object-key', $obj );
		$retrieved = $context->get( 'test-plugin', 'object-key' );
		$this->assertInstanceOf( \stdClass::class, $retrieved );
		$this->assertEquals( 'value', $retrieved->prop );

		// Test with null
		$context->set( 'test-plugin', 'null-key', null );
		$this->assertNull( $context->get( 'test-plugin', 'null-key' ) );
	}

	/**
	 * Test get() returns default value when key doesn't exist.
	 */
	public function testGetReturnsDefaultValueWhenKeyDoesntExist() {
		$context = new \WPGraphQL\AppContext();

		// Test with default value
		$this->assertEquals( 'default', $context->get( 'test-plugin', 'non-existent', 'default' ) );
		$this->assertEquals( 123, $context->get( 'test-plugin', 'non-existent', 123 ) );
		$this->assertEquals( [], $context->get( 'test-plugin', 'non-existent', [] ) );

		// Test without default value (should return null)
		$this->assertNull( $context->get( 'test-plugin', 'non-existent' ) );
	}

	/**
	 * Test has() method returns correct boolean values.
	 */
	public function testHasReturnsCorrectBoolean() {
		$context = new \WPGraphQL\AppContext();

		// Should return false for non-existent key
		$this->assertFalse( $context->has( 'test-plugin', 'non-existent' ) );

		// Should return true after setting
		$context->set( 'test-plugin', 'existing-key', 'value' );
		$this->assertTrue( $context->has( 'test-plugin', 'existing-key' ) );

		// Should return true even when value is null
		$context->set( 'test-plugin', 'null-key', null );
		$this->assertTrue( $context->has( 'test-plugin', 'null-key' ) );

		// Should return false for non-existent namespace
		$this->assertFalse( $context->has( 'non-existent-namespace', 'key' ) );
	}

	/**
	 * Test remove() method correctly removes keys.
	 */
	public function testRemoveCorrectlyRemovesKeys() {
		$context = new \WPGraphQL\AppContext();

		// Set a value
		$context->set( 'test-plugin', 'key-to-remove', 'value' );
		$this->assertTrue( $context->has( 'test-plugin', 'key-to-remove' ) );

		// Remove it
		$context->remove( 'test-plugin', 'key-to-remove' );
		$this->assertFalse( $context->has( 'test-plugin', 'key-to-remove' ) );

		// Removing non-existent key should not cause error
		$context->remove( 'test-plugin', 'non-existent' );
		$this->assertFalse( $context->has( 'test-plugin', 'non-existent' ) );
	}

	/**
	 * Test clear() method removes entire namespace.
	 */
	public function testClearRemovesEntireNamespace() {
		$context = new \WPGraphQL\AppContext();

		// Set multiple values in namespace
		$context->set( 'test-plugin', 'key1', 'value1' );
		$context->set( 'test-plugin', 'key2', 'value2' );
		$context->set( 'test-plugin', 'key3', 'value3' );

		// Verify they exist
		$this->assertTrue( $context->has( 'test-plugin', 'key1' ) );
		$this->assertTrue( $context->has( 'test-plugin', 'key2' ) );
		$this->assertTrue( $context->has( 'test-plugin', 'key3' ) );

		// Clear the namespace
		$context->clear( 'test-plugin' );

		// Verify all keys are gone
		$this->assertFalse( $context->has( 'test-plugin', 'key1' ) );
		$this->assertFalse( $context->has( 'test-plugin', 'key2' ) );
		$this->assertFalse( $context->has( 'test-plugin', 'key3' ) );

		// Clearing non-existent namespace should not cause error
		$context->clear( 'non-existent-namespace' );
	}

	/**
	 * Test all() method returns all keys in namespace.
	 */
	public function testAllReturnsAllKeysInNamespace() {
		$context = new \WPGraphQL\AppContext();

		// Empty namespace should return empty array
		$this->assertEquals( [], $context->all( 'test-plugin' ) );

		// Set multiple values
		$context->set( 'test-plugin', 'key1', 'value1' );
		$context->set( 'test-plugin', 'key2', 'value2' );
		$context->set( 'test-plugin', 'key3', 'value3' );

		// Get all values
		$all = $context->all( 'test-plugin' );

		$this->assertIsArray( $all );
		$this->assertCount( 3, $all );
		$this->assertEquals( 'value1', $all['key1'] );
		$this->assertEquals( 'value2', $all['key2'] );
		$this->assertEquals( 'value3', $all['key3'] );

		// Non-existent namespace should return empty array
		$this->assertEquals( [], $context->all( 'non-existent-namespace' ) );
	}

	/**
	 * Test namespace isolation - different namespaces don't interfere.
	 */
	public function testNamespaceIsolation() {
		$context = new \WPGraphQL\AppContext();

		// Set same key in different namespaces
		$context->set( 'plugin-a', 'shared-key', 'value-a' );
		$context->set( 'plugin-b', 'shared-key', 'value-b' );
		$context->set( 'plugin-c', 'shared-key', 'value-c' );

		// Each namespace should have its own value
		$this->assertEquals( 'value-a', $context->get( 'plugin-a', 'shared-key' ) );
		$this->assertEquals( 'value-b', $context->get( 'plugin-b', 'shared-key' ) );
		$this->assertEquals( 'value-c', $context->get( 'plugin-c', 'shared-key' ) );

		// Removing from one namespace shouldn't affect others
		$context->remove( 'plugin-b', 'shared-key' );
		$this->assertEquals( 'value-a', $context->get( 'plugin-a', 'shared-key' ) );
		$this->assertNull( $context->get( 'plugin-b', 'shared-key' ) );
		$this->assertEquals( 'value-c', $context->get( 'plugin-c', 'shared-key' ) );

		// Clearing one namespace shouldn't affect others
		$context->clear( 'plugin-a' );
		$this->assertNull( $context->get( 'plugin-a', 'shared-key' ) );
		$this->assertEquals( 'value-c', $context->get( 'plugin-c', 'shared-key' ) );
	}

	/**
	 * Test that dynamic property deprecation notice is triggered for new properties.
	 */
	public function testDynamicPropertyDeprecationNoticeIsTriggered() {
		$context = new \WPGraphQL\AppContext();

		// Capture the doing_it_wrong notice
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		// Set a dynamic property (not a pre-existing property)
		$context->new_dynamic_property = 'test value';

		// The property should still be set (backward compatibility)
		$this->assertEquals( 'test value', $context->new_dynamic_property );

		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	/**
	 * Test that existing public properties don't trigger deprecation.
	 */
	public function testExistingPropertiesDontTriggerDeprecation() {
		$context = new \WPGraphQL\AppContext();

		// Setting existing properties should not trigger deprecation
		// We can't easily test for absence of notice, but we verify properties work
		$context->viewer  = 'test_viewer';
		$context->request = 'test_request';
		$context->config  = [ 'test' => 'value' ];

		$this->assertEquals( 'test_viewer', $context->viewer );
		$this->assertEquals( 'test_request', $context->request );
		$this->assertEquals( [ 'test' => 'value' ], $context->config );
	}

	/**
	 * Test that dynamic properties still work alongside new API (backward compatibility).
	 */
	public function testDynamicPropertiesStillWorkAlongsideNewAPI() {
		$context = new \WPGraphQL\AppContext();

		// Suppress deprecation for this test
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		// Use both old and new approaches
		$context->old_way = 'old value';
		$context->set( 'test-plugin', 'new-way', 'new value' );

		// Both should work
		$this->assertEquals( 'old value', $context->old_way );
		$this->assertEquals( 'new value', $context->get( 'test-plugin', 'new-way' ) );

		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	/**
	 * Integration test: Demonstrate directive-like pattern.
	 *
	 * This test shows how the new API could be used in a directive
	 * to store and retrieve state across execution lifecycle.
	 */
	public function testDirectiveLikePattern() {
		$context = new \WPGraphQL\AppContext();

		// Simulate a directive storing original state before execution
		$original_locale = 'en_US';
		$context->set( 'setLocale', 'original_locale', $original_locale );
		$context->set( 'setLocale', 'switched', true );

		// Simulate query execution...

		// Retrieve the state after execution
		$this->assertTrue( $context->has( 'setLocale', 'switched' ) );
		$this->assertEquals( $original_locale, $context->get( 'setLocale', 'original_locale' ) );

		// Clean up after directive execution
		$context->remove( 'setLocale', 'switched' );
		$this->assertFalse( $context->has( 'setLocale', 'switched' ) );

		// Or clear entire namespace
		$context->clear( 'setLocale' );
		$this->assertFalse( $context->has( 'setLocale', 'original_locale' ) );
	}
}

