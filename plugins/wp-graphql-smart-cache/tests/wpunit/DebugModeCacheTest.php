<?php

namespace WPGraphQL\SmartCache;

/**
 * @see https://github.com/wp-graphql/wp-graphql/issues/4303
 */
class DebugModeCacheTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The storage in use before the test, restored in tearDown.
	 *
	 * @var mixed
	 */
	protected $original_storage;

	public function setUp(): void {
		parent::setUp();

		// Enable the local cache transient cache for these tests
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		// Without a persistent object cache, transients are stored in the options table, which
		// serializes them. That is what triggers the bug; a non-persistent object cache would not.
		$this->original_storage = Cache\Query::$storage;
		Cache\Query::$storage   = new Storage\Transient( Cache\Query::GROUP_NAME );

		add_filter( 'graphql_debug_enabled', '__return_true' );

		self::factory()->post->create( [
			'post_status' => 'publish',
		] );
	}

	public function tearDown(): void {
		Cache\Query::$storage = $this->original_storage;
		delete_option( 'graphql_cache_section' );

		parent::tearDown();
	}

	/**
	 * Log a debug entry with the given context on every GraphQL execution.
	 *
	 * @param string $message The debug message.
	 * @param array  $context The debug context.
	 */
	protected function log_debug_entry( string $message, array $context ): void {
		add_action(
			'graphql_execute',
			static function () use ( $message, $context ) {
				graphql_debug( $message, $context );
			}
		);
	}

	/**
	 * Assert a response was served from the object cache and the stored entry has no debug log.
	 *
	 * @param array $response The response of the request served from cache.
	 */
	protected function assertServedFromCacheWithoutStoredDebug( array $response ): void {
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertNotEmpty( $response['data']['posts']['nodes'] );
		$this->assertNotEmpty( $response['extensions']['graphqlSmartCache']['graphqlObjectCache'] );

		$cached = Cache\Query::$storage->get( $response['extensions']['graphqlSmartCache']['graphqlObjectCache']['cacheKey'] );
		$this->assertIsArray( $cached );
		$this->assertSame( $cached['data'], $response['data'] );
		$this->assertArrayNotHasKey( 'debug', $cached['extensions'] ?? [] );
	}

	public function testDebugEntryWithClosureDoesNotBreakCachedQuery() {
		$this->log_debug_entry( 'closure context', [ 'callback' => static function () {} ] );

		$query = '{ posts { nodes { id } } }';

		$response = do_graphql_request( $query );
		codecept_debug( $response );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertNotEmpty( $response['data']['posts']['nodes'] );
		$this->assertEmpty( $response['extensions']['graphqlSmartCache']['graphqlObjectCache'] );

		// The client still receives the debug log of its own request.
		$this->assertContains( 'closure context', wp_list_pluck( $response['extensions']['debug'], 'message' ) );

		// The second request is served from the object cache.
		$response = do_graphql_request( $query );
		codecept_debug( $response );

		$this->assertServedFromCacheWithoutStoredDebug( $response );
	}

	public function testDebugEntryWithoutClosureIsCachedWithoutDebug() {
		$this->log_debug_entry( 'scalar context', [ 'value' => 'foo' ] );

		// A query not used by the other tests, so no earlier cache hit is reported for it.
		$query = '{ posts { nodes { databaseId } } }';

		$response = do_graphql_request( $query );
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEmpty( $response['extensions']['graphqlSmartCache']['graphqlObjectCache'] );

		$response = do_graphql_request( $query );

		$this->assertServedFromCacheWithoutStoredDebug( $response );
	}

	public function testMultipleOperationsWithClosureDebugEntryAreCached() {
		$this->log_debug_entry( 'closure context', [ 'callback' => static function () {} ] );

		// graphql() executes each operation of a list as a separate request.
		// Queries not used by the other tests, so no earlier cache hit is reported for them.
		$batch = [
			[ 'query' => '{ posts { nodes { id uri } } }' ],
			[ 'query' => '{ posts { nodes { databaseId uri } } }' ],
		];

		$response = graphql( $batch );
		codecept_debug( $response );

		foreach ( $response as $operation_response ) {
			$this->assertArrayNotHasKey( 'errors', $operation_response );
			$this->assertEmpty( $operation_response['extensions']['graphqlSmartCache']['graphqlObjectCache'] );
		}

		// Every operation of the second batch is served from the object cache.
		$response = graphql( $batch );
		codecept_debug( $response );

		$this->assertCount( 2, $response );
		foreach ( $response as $operation_response ) {
			$this->assertServedFromCacheWithoutStoredDebug( $operation_response );
		}
	}

	public function testStripDebugExtensionsConvertsResultObjects() {
		$object             = new \GraphQL\Executor\ExecutionResult( [ 'posts' => [] ] );
		$object->extensions = [
			'debug' => [ [ 'callback' => static function () {} ] ],
			'other' => 'kept',
		];

		// A result object is stored the way the storage adapters store it, as an array.
		$stripped = Cache\Results::strip_debug_extensions( $object );
		$this->assertSame( $object->toArray()['data'], $stripped['data'] );
		$this->assertSame( [ 'other' => 'kept' ], $stripped['extensions'] );
		$this->assertIsString( serialize( $stripped ) );

		// The response returned to the client is left untouched.
		$this->assertArrayHasKey( 'debug', $object->extensions );
	}
}
