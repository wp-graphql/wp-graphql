<?php

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Document;

/**
 * Audit of automatically registered documents: detection, views, and purge.
 */
class DocumentAuditTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		foreach ( get_posts( [ 'post_type' => Document::TYPE_NAME, 'post_status' => 'any', 'numberposts' => -1 ] ) as $post ) {
			wp_delete_post( $post->ID, true );
		}
		delete_transient( Audit::COUNTS_TRANSIENT );
		delete_option( Audit::NOTICE_DISMISSED );
		wp_set_current_user( 0 );
	}

	private function register_automatically( string $query ): int {
		wp_set_current_user( 0 );
		$post_id = ( new Document() )->save( hash( 'sha256', $query ), $query );
		update_post_meta( $post_id, Document::SOURCE_META_KEY, Document::SOURCE_AUTOMATIC );
		return $post_id;
	}

	/**
	 * A document stored by a pre-fix version: no author, no source marker.
	 */
	private function register_legacy_anonymous( string $query, array $aliases = [] ): int {
		$post_id = wp_insert_post( [
			'post_type'    => Document::TYPE_NAME,
			'post_status'  => 'publish',
			'post_author'  => 0,
			'post_title'   => 'Legacy',
			'post_name'    => hash( 'sha256', $query ),
			'post_content' => $query,
		], true );
		wp_add_object_terms( $post_id, array_merge( [ hash( 'sha256', $query ) ], $aliases ), Document::ALIAS_TAXONOMY_NAME );
		return $post_id;
	}

	private function create_as_admin( string $query, array $aliases = [] ): int {
		$admin_id = static::factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id  = wp_insert_post( [
			'post_type'    => Document::TYPE_NAME,
			'post_status'  => 'publish',
			'post_author'  => $admin_id,
			'post_title'   => 'Curated by admin',
			'post_name'    => hash( 'sha256', $query ),
			'post_content' => $query,
		], true );
		wp_add_object_terms( $post_id, array_merge( [ hash( 'sha256', $query ) ], $aliases ), Document::ALIAS_TAXONOMY_NAME );
		return $post_id;
	}

	public function testHashAliasDetection(): void {
		$this->assertTrue( Audit::is_hash_alias( hash( 'sha256', 'x' ) ) );
		$this->assertFalse( Audit::is_hash_alias( 'homepage-posts' ) );
		$this->assertFalse( Audit::is_hash_alias( strtoupper( hash( 'sha256', 'x' ) ) ) );
		$this->assertFalse( Audit::is_hash_alias( substr( hash( 'sha256', 'x' ), 1 ) ) );
	}

	public function testAutomaticDocumentsAreDetectedByMarkerOrMissingAuthor(): void {
		$marked   = $this->register_automatically( '{ __typename }' );
		$legacy   = $this->register_legacy_anonymous( '{ posts { nodes { id } } }' );
		$authored = $this->create_as_admin( '{ pages { nodes { id } } }', [ 'all-pages' ] );

		$this->assertTrue( Audit::is_automatic( $marked ) );
		$this->assertTrue( Audit::is_automatic( $legacy ) );
		$this->assertFalse( Audit::is_automatic( $authored ) );

		$ids = Audit::get_automatic_document_ids();
		sort( $ids );
		$this->assertSame( [ $marked, $legacy ], $ids );
	}

	public function testUnverifiedAliasesAreOnlyFlaggedOnAutomaticDocuments(): void {
		$legacy_squatted = $this->register_legacy_anonymous( '{ posts { nodes { id } } }', [ 'homepage-posts' ] );
		$legacy_clean    = $this->register_legacy_anonymous( '{ __typename }' );
		$authored        = $this->create_as_admin( '{ pages { nodes { id } } }', [ 'all-pages' ] );

		$this->assertSame( [ 'homepage-posts' ], Audit::get_unverified_aliases( $legacy_squatted ) );
		$this->assertSame( [], Audit::get_unverified_aliases( $legacy_clean ) );
		$this->assertSame( [ 'all-pages' ], Audit::get_unverified_aliases( $authored ) );

		$this->assertSame( [ $legacy_squatted ], Audit::get_automatic_document_ids( true ) );

		$counts = Audit::get_counts();
		$this->assertSame( 2, $counts['automatic'] );
		$this->assertSame( 1, $counts['unverified'] );
	}

	public function testPurgeDeletesAutomaticDocumentsAndKeepsAuthoredAndCurated(): void {
		$squatted = $this->register_legacy_anonymous( '{ posts { nodes { id } } }', [ 'homepage-posts' ] );
		$plain    = $this->register_automatically( '{ __typename }' );
		$curated  = $this->register_legacy_anonymous( '{ tags { nodes { id } } }' );
		( new Grant() )->save( $curated, Grant::ALLOW );
		$authored = $this->create_as_admin( '{ pages { nodes { id } } }', [ 'all-pages' ] );

		$result = Audit::purge();

		$this->assertSame( 2, $result['deleted'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( 0, $result['remaining'] );

		$this->assertNull( get_post( $squatted ) );
		$this->assertNull( get_post( $plain ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $curated ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $authored ) );

		// The squatted alias is gone with its document, so it can be claimed legitimately again.
		$this->assertEmpty( term_exists( 'homepage-posts', Document::ALIAS_TAXONOMY_NAME ) );
		$this->assertNotEmpty( term_exists( 'all-pages', Document::ALIAS_TAXONOMY_NAME ) );

		$counts = Audit::get_counts();
		$this->assertSame( 1, $counts['automatic'] );
		$this->assertSame( 0, $counts['unverified'] );
	}

	public function testPurgeCanIncludeCuratedDocuments(): void {
		$curated = $this->register_legacy_anonymous( '{ tags { nodes { id } } }' );
		wp_set_post_terms( $curated, [ 'my-group' ], Group::TAXONOMY_NAME );

		$this->assertSame( 0, Audit::purge()['deleted'] );
		$this->assertSame( 1, Audit::purge( true )['deleted'] );
		$this->assertNull( get_post( $curated ) );
	}

	public function testRequestPathRegistrationCarriesTheSourceMarker(): void {
		$query   = '{ __typename }';
		$request = [ 'query' => $query, 'queryId' => hash( 'sha256', $query ) ];

		$document = new Document();
		$filtered = $document->graphql_query_contains_query_id_cb( $request, [] );
		$this->assertArrayNotHasKey( 'queryId', $filtered, 'The verified document is re-inserted as the query for this request' );
		$this->assertSame( "{\n  __typename\n}\n", $filtered['query'] );
		$this->assertSame( 0, count( Audit::get_automatic_document_ids() ), 'Nothing is written before execution' );

		$document->persist_pending_document_cb( [ 'data' => [ '__typename' => 'RootQuery' ] ], null, null, $filtered['query'], null, null, null );

		$ids = Audit::get_automatic_document_ids();
		$this->assertCount( 1, $ids );
		$this->assertSame( Document::SOURCE_AUTOMATIC, get_post_meta( $ids[0], Document::SOURCE_META_KEY, true ) );
	}

	public function testRequestPathDoesNotPersistWhenValidationFailed(): void {
		$query   = '{ notARealField }';
		$request = [ 'query' => $query, 'queryId' => hash( 'sha256', $query ) ];

		$document = new Document();
		$filtered = $document->graphql_query_contains_query_id_cb( $request, [] );
		$document->persist_pending_document_cb( [ 'errors' => [ [ 'message' => 'Cannot query field' ] ] ], null, null, $filtered['query'], null, null, null );

		$this->assertSame( [], Audit::get_automatic_document_ids() );
		$this->assertNull( ( new Document() )->get( hash( 'sha256', $query ) ) );
	}

	/**
	 * A failed save must leave the executed data intact and report the failure only when debug is enabled.
	 *
	 * @dataProvider saveFailureDebugProvider
	 * @since x-release-please-version
	 */
	public function testRequestReportsSaveFailureInDebugLog( bool $debug_enabled, bool $object_response, bool $redact_debug ): void {
		$query    = sprintf( 'query SaveFailure%d%d%d { __typename }', (int) $debug_enabled, (int) $object_response, (int) $redact_debug );
		$query_id = hash( 'sha256', $query );
		$debug    = $debug_enabled ? '__return_true' : '__return_false';
		$attempts = 0;
		$fail     = static function ( $data ) use ( &$attempts ) {
			if ( Document::TYPE_NAME === $data['post_type'] ) {
				++$attempts;
				throw new \RuntimeException( 'Simulated document write failure' );
			}

			return $data;
		};
		$redact   = static function ( $response ) {
			if ( is_array( $response ) ) {
				unset( $response['extensions']['debug'] );
			} else {
				unset( $response->extensions['debug'] );
			}
			return $response;
		};
		$previous_get    = $_GET;
		$previous_method = $_SERVER['REQUEST_METHOD'] ?? null;

		add_filter( 'graphql_debug_enabled', $debug );
		add_filter( 'wp_insert_post_data', $fail );
		if ( $redact_debug ) {
			add_filter( 'graphql_request_results', $redact, 5 );
		}
		try {
			if ( $object_response ) {
				$_SERVER['REQUEST_METHOD'] = 'GET';
				$_GET                     = [ 'query' => $query, 'queryId' => $query_id ];
				$response                 = ( new \WPGraphQL\Request() )->execute_http();
				$this->assertInstanceOf( \GraphQL\Executor\ExecutionResult::class, $response );
				$response = $response->toArray();
			} else {
				$response = graphql( [ 'query' => $query, 'queryId' => $query_id ] );
			}
		} finally {
			$_GET = $previous_get;
			if ( null === $previous_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $previous_method;
			}
			remove_filter( 'graphql_request_results', $redact, 5 );
			remove_filter( 'wp_insert_post_data', $fail );
			remove_filter( 'graphql_debug_enabled', $debug );
		}

		$this->assertSame( 1, $attempts );
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertSame( [ '__typename' => 'RootQuery' ], $response['data'] );
		$this->assertSame( [], Audit::get_automatic_document_ids() );
		$this->assertNull( ( new Document() )->get( $query_id ) );

		if ( $redact_debug ) {
			$this->assertArrayNotHasKey( 'debug', $response['extensions'] ?? [] );
		} elseif ( $debug_enabled ) {
			$logs = $response['extensions']['debug'];
			$failures = array_values( array_filter( $logs, static function ( $entry ) use ( $query_id ) {
				return $query_id === ( $entry['queryId'] ?? null );
			} ) );
			$this->assertCount( 1, $failures );
			$this->assertStringContainsString( 'Persisted query document could not be saved', $failures[0]['message'] );
		} else {
			$this->assertSame( [ 'DEBUG_LOGS_INACTIVE' ], wp_list_pluck( $response['extensions']['debug'], 'type' ) );
		}
	}

	/**
	 * @since x-release-please-version
	 * @return array<string,array{bool,bool,bool}>
	 */
	public function saveFailureDebugProvider(): array {
		return [
			'debug enabled, array response'   => [ true, false, false ],
			'debug disabled, array response'  => [ false, false, false ],
			'debug enabled, HTTP response'    => [ true, true, false ],
			'debug disabled, HTTP response'   => [ false, true, false ],
			'debug redacted, array response'  => [ true, false, true ],
			'debug redacted, HTTP response'   => [ true, true, true ],
		];
	}

	/**
	 * @since x-release-please-version
	 */
	public function testSuccessfulRequestPersistsDocument(): void {
		$query    = 'query SuccessfulSave { __typename }';
		$query_id = hash( 'sha256', $query );
		$response = graphql( [ 'query' => $query, 'queryId' => $query_id ] );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertSame( [ '__typename' => 'RootQuery' ], $response['data'] );
		$ids = Audit::get_automatic_document_ids();
		$this->assertCount( 1, $ids );
		$this->assertSame( Document::SOURCE_AUTOMATIC, get_post_meta( $ids[0], Document::SOURCE_META_KEY, true ) );
		$this->assertSame( "query SuccessfulSave {\n  __typename\n}\n", ( new Document() )->get( $query_id ) );
	}
}
