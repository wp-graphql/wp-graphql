<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Results;

/**
 * A response that carries errors must not be stored, and must not be advertised as
 * cacheable.
 *
 * These go through `graphql_response_headers_to_send` - the filter the Router actually
 * runs - rather than through MaxAge directly, so they exercise the whole path a request
 * takes. The HTTP header behaviour has its own functional coverage in
 * tests/functional/ErrorResponseCacheControlCest.php.
 */
class ErrorResponseCacheControlTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		\WPGraphQL::clear_schema();
		delete_option( 'graphql_cache_section' );
	}

	public function tearDown(): void {
		remove_all_filters( 'graphql_cache_error_responses' );

		delete_option( 'graphql_cache_section' );
		\WPGraphQL::clear_schema();

		parent::tearDown();
	}

	/**
	 * Turn caching on, with a max-age that a bug would leak onto an error response.
	 *
	 * @param array $settings Extra settings to merge into graphql_cache_section.
	 *
	 * @return void
	 */
	protected function enable_cache( array $settings = [] ) {
		update_option( 'graphql_cache_section', array_merge( [ 'cache_toggle' => 'on' ], $settings ) );
	}

	/**
	 * Build the response headers the Router would send, the way it builds them.
	 *
	 * @return array
	 */
	protected function build_headers() {
		return apply_filters( 'graphql_response_headers_to_send', [ 'Content-Type' => 'application/json' ] );
	}

	public function testErrorResponseIsNotAdvertisedAsCacheable() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		$response = graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );
		$this->assertArrayHasKey( 'errors', $response );

		$headers = $this->build_headers();

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	/**
	 * A partial response - data alongside errors, which is what a resolver failing on
	 * one nullable field produces - is still an error response. The issue calls this
	 * out specifically, and it is the shape most likely to slip through a check that
	 * only looks for "did the whole thing fail".
	 */
	public function testPartialResponseIsNotAdvertisedAsCacheable() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_field(
					'RootQuery',
					'fieldThatAlwaysFails',
					[
						'type'    => 'String',
						'resolve' => static function () {
							throw new \RuntimeException( 'resolver blew up' );
						},
					]
				);
			}
		);

		$response = graphql( [ 'query' => 'query { __typename fieldThatAlwaysFails }' ] );

		$this->assertArrayHasKey( 'errors', $response );
		$this->assertSame( 'RootQuery', $response['data']['__typename'] );
		$this->assertNull( $response['data']['fieldThatAlwaysFails'] );

		$headers = $this->build_headers();

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	public function testCleanResponseStillGetsMaxAge() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		$response = graphql( [ 'query' => 'query { __typename }' ] );
		$this->assertArrayNotHasKey( 'errors', $response );

		$headers = $this->build_headers();

		$this->assertSame( 'max-age=30, s-maxage=30, must-revalidate', $headers['Cache-Control'] );
	}

	public function testBatchWithOneErrorIsNotAdvertisedAsCacheable() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		$response = graphql( [
			[ 'query' => 'query { __typename }' ],
			[ 'query' => 'query { thisFieldDoesNotExist }' ],
		] );

		$this->assertArrayNotHasKey( 'errors', $response[0] );
		$this->assertArrayHasKey( 'errors', $response[1] );

		$headers = $this->build_headers();

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	/**
	 * The batch verdict accumulates, so it cannot depend on which operation happens to
	 * be the last one in the batch.
	 */
	public function testErrorAnywhereInTheBatchWinsRegardlessOfOrder() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		graphql( [
			[ 'query' => 'query { thisFieldDoesNotExist }' ],
			[ 'query' => 'query { __typename }' ],
		] );

		$headers = $this->build_headers();

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	public function testCleanBatchStillGetsMaxAge() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		$response = graphql( [
			[ 'query' => 'query { __typename }' ],
			[ 'query' => 'query { generalSettings { title } }' ],
		] );

		$this->assertArrayNotHasKey( 'errors', $response[0] );
		$this->assertArrayNotHasKey( 'errors', $response[1] );

		$headers = $this->build_headers();

		$this->assertSame( 'max-age=30, s-maxage=30, must-revalidate', $headers['Cache-Control'] );
	}

	public function testErrorResponseWithNoMaxAgeConfiguredIsStillNoStore() {
		$this->enable_cache();

		graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );

		$headers = $this->build_headers();

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	/**
	 * The verdict is per request. A long-lived process - a test run, a queue worker, a
	 * PHP process that serves more than one request - must not carry an error verdict
	 * into a request that succeeded.
	 */
	public function testErrorVerdictDoesNotLeakIntoTheNextRequest() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );
		$this->assertSame( 'no-store', $this->build_headers()['Cache-Control'] );

		graphql( [ 'query' => 'query { __typename }' ] );
		$this->assertSame( 'max-age=30, s-maxage=30, must-revalidate', $this->build_headers()['Cache-Control'] );
	}

	/**
	 * The mirror image: a clean request followed by an errored one. The errored request
	 * must still be caught.
	 */
	public function testCleanVerdictDoesNotMaskTheNextError() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		graphql( [ 'query' => 'query { __typename }' ] );
		$this->assertSame( 'max-age=30, s-maxage=30, must-revalidate', $this->build_headers()['Cache-Control'] );

		graphql( [ 'query' => 'query { anotherMissingField }' ] );
		$this->assertSame( 'no-store', $this->build_headers()['Cache-Control'] );
	}

	/**
	 * The opt-out has to be reachable, or it is dead code.
	 */
	public function testFilterRestoresCacheableErrorResponses() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );
		add_filter( 'graphql_cache_error_responses', '__return_true' );

		graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );

		$headers = $this->build_headers();

		$this->assertSame( 'max-age=30, s-maxage=30, must-revalidate', $headers['Cache-Control'] );
	}

	/**
	 * The opt-out restores the old behaviour, it does not invent a max-age. With nothing
	 * configured there is still nothing to send.
	 */
	public function testFilterOptOutDoesNotInventAMaxAge() {
		$this->enable_cache();
		add_filter( 'graphql_cache_error_responses', '__return_true' );

		graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );

		$headers = $this->build_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $headers );
	}

	/**
	 * A configured max-age of zero already means "do not cache". An error response still
	 * has to say so, and it must not be upgraded to a real TTL.
	 */
	public function testZeroMaxAgeIsUnchanged() {
		$this->enable_cache( [ 'global_max_age' => '0' ] );

		graphql( [ 'query' => 'query { __typename }' ] );

		$headers = $this->build_headers();

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	/**
	 * A Cache-Control that already forbids storage is left alone. The Router sends
	 * `no-store, private` for a preview-context request, and `private` narrows the
	 * audience further, so replacing the value would throw away a stronger directive.
	 */
	public function testExistingNoStoreDirectivesArePreserved() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );

		$headers = apply_filters(
			'graphql_response_headers_to_send',
			[ 'Content-Type' => 'application/json', 'Cache-Control' => 'no-store, private' ]
		);

		$this->assertSame( 'no-store, private', $headers['Cache-Control'] );
	}

	/**
	 * Any other existing value is replaced outright rather than merged: `no-store` and
	 * a `max-age` in one header contradict each other, and a shared cache only gets to
	 * act on the header as a whole.
	 */
	public function testContradictoryExistingDirectivesAreReplaced() {
		$this->enable_cache( [ 'global_max_age' => '30' ] );

		graphql( [ 'query' => 'query { thisFieldDoesNotExist }' ] );

		$headers = apply_filters(
			'graphql_response_headers_to_send',
			[ 'Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=600' ]
		);

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	/**
	 * An error response must not be written to the object cache: it would then be
	 * replayed for the whole TTL, with no cache key to purge it by.
	 */
	public function testErrorResponseIsNotSavedToTheObjectCache() {
		$this->enable_cache();

		$query    = 'query { aFieldThatDoesNotExist }';
		$response = graphql( [ 'query' => $query ] );
		$this->assertArrayHasKey( 'errors', $response );

		$results = new Results();
		$key     = $results->the_results_key( null, $query );

		$this->assertNotFalse( $key );
		$this->assertFalse( $results->get( $key ) );
	}

	/**
	 * The companion guard: a clean response is still cached.
	 */
	public function testCleanResponseIsStillSavedToTheObjectCache() {
		$this->enable_cache();

		$query    = 'query { __typename }';
		$response = graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $response );

		$results = new Results();
		$key     = $results->the_results_key( null, $query );

		$this->assertNotFalse( $key );
		$cached = $results->get( $key );
		$this->assertNotEmpty( $cached );
		$this->assertSame( 'RootQuery', $cached['data']['__typename'] );
	}

	/**
	 * In a batch the object cache decision is per operation, so a clean operation is
	 * still worth storing.
	 */
	public function testOnlyTheErroringOperationIsSkippedInTheObjectCache() {
		$this->enable_cache();

		$clean_query   = 'query { __typename }';
		$erroring_query = 'query { aFieldThatDoesNotExist }';

		graphql( [
			[ 'query' => $clean_query ],
			[ 'query' => $erroring_query ],
		] );

		$results = new Results();

		$this->assertNotEmpty( $results->get( $results->the_results_key( null, $clean_query ) ) );
		$this->assertFalse( $results->get( $results->the_results_key( null, $erroring_query ) ) );
	}

	/**
	 * The error detector backs two decisions that must never disagree, so it is worth
	 * pinning down the shapes it is handed.
	 */
	public function testResponseCarriesErrorsDetectsTheShapesItIsHanded() {
		$this->assertTrue( Results::response_carries_errors( new \GraphQL\Executor\ExecutionResult( null, [ new \GraphQL\Error\Error( 'boom' ) ] ) ) );
		$this->assertFalse( Results::response_carries_errors( new \GraphQL\Executor\ExecutionResult( [ 'ok' => true ] ) ) );

		// A single result reshaped into an array by a graphql_request_results filter.
		$this->assertTrue( Results::response_carries_errors( [ 'data' => null, 'errors' => [ [ 'message' => 'boom' ] ] ] ) );
		$this->assertFalse( Results::response_carries_errors( [ 'data' => [ 'ok' => true ] ] ) );
		$this->assertFalse( Results::response_carries_errors( [ 'data' => [], 'errors' => [] ] ) );

		// A batch.
		$this->assertTrue( Results::response_carries_errors( [
			new \GraphQL\Executor\ExecutionResult( [ 'ok' => true ] ),
			new \GraphQL\Executor\ExecutionResult( null, [ new \GraphQL\Error\Error( 'boom' ) ] ),
		] ) );
		$this->assertFalse( Results::response_carries_errors( [
			new \GraphQL\Executor\ExecutionResult( [ 'ok' => true ] ),
			new \GraphQL\Executor\ExecutionResult( [ 'ok' => true ] ),
		] ) );

		// Nothing to inspect.
		$this->assertFalse( Results::response_carries_errors( [] ) );
		$this->assertFalse( Results::response_carries_errors( '' ) );
		$this->assertFalse( Results::response_carries_errors( null ) );
	}

	/**
	 * A response that exposes `errors` only through a magic accessor still counts as
	 * errored - the check must not quietly miss it. Reporting errors it cannot read as
	 * present costs one extra `no-store`; missing them would re-advertise the failure,
	 * which is the bug this whole change is about.
	 */
	public function testResponseCarriesErrorsReadsErrorsFromAMagicAccessor() {
		$response = new class() {
			private $errors = [ 'boom' ];

			public function __get( $name ) {
				return 'errors' === $name ? $this->errors : null;
			}
		};

		$this->assertTrue( Results::response_carries_errors( $response ) );
	}

	/**
	 * An opaque object with nothing to read is not an error, and must not be fatal:
	 * this runs on every response, so a crash here would take down the whole request.
	 */
	public function testResponseCarriesErrorsToleratesAnOpaqueObject() {
		$response = new class() {
			public $data = [];
		};

		$this->assertFalse( Results::response_carries_errors( $response ) );
	}
}
