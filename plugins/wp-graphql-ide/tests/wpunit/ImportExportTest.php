<?php
/**
 * Tests for `WPGraphQLIDE\ImportExport` — the JSON wire format used by
 * the activation seeder, the REST /import / /export handlers, and the
 * canonical example dataset.
 *
 * Three things make this surface easy to break and worth covering:
 *   - Round-trip: an export taken now must re-import cleanly later
 *     (drift between the two stops the seeder, the UI, and any user
 *     who's exported / re-imported a backup).
 *   - Author scoping: imports must land as the importer, exports must
 *     emit only the requested user — the IDE relies on this for the
 *     per-user "my documents" view.
 *   - Version handling: a future schema bump must reject older
 *     payloads gracefully rather than silently mangle them.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE;

class ImportExportTest extends \Codeception\TestCase\WPTestCase {

	// Seeds manage_graphql_ide in setUp — WPLoader doesn't. See the trait.
	use \Helper\GrantsIdeCapability;

	private $admin_a;
	private $admin_b;

	public function setUp(): void {
		parent::setUp();

		// WPLoader doesn't fire the activation that grants manage_graphql_ide,
		// so seed it here — otherwise import/export runs as an admin without
		// the cap and creates nothing. See GrantsIdeCapability.
		$this->grantIdeCapability();

		$this->admin_a = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_b = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	/**
	 * Build a single-collection payload pointed at the given author.
	 * Distinct query strings are appended with `$tag` so the per-test
	 * fixture doesn't collide with Smart Cache's content-addressed
	 * alias taxonomy (which rejects two docs with the same content).
	 */
	private function payload_with( string $tag, string $status = 'publish', array $extra = [] ): array {
		$doc = array_merge(
			[
				'title' => 'Doc ' . $tag,
				'query' => sprintf( 'query Q%s { posts { nodes { id } } }', $tag ),
			],
			$extra
		);
		if ( 'publish' !== $status ) {
			$doc['status'] = $status;
		}
		return [
			'version'     => \WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			'collections' => [
				[ 'name' => 'Coll-' . $tag, 'documents' => [ $doc ] ],
			],
		];
	}

	// ---------------------------------------------------------------
	// Version handling
	// ---------------------------------------------------------------

	public function test_import_rejects_unknown_schema_version() {
		$payload = $this->payload_with( 'v99' );
		$payload['version'] = 99;

		$result = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( '99', $result['error'] );
	}

	public function test_import_accepts_missing_version_as_current() {
		$payload = $this->payload_with( 'nover' );
		unset( $payload['version'] );

		$result = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );

		$this->assertSame( 1, $result['created'] );
		$this->assertArrayNotHasKey( 'error', $result );
	}

	// ---------------------------------------------------------------
	// Author scoping
	// ---------------------------------------------------------------

	public function test_imported_documents_land_as_the_importer() {
		$payload = $this->payload_with( 'authorimport' );

		\WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );

		$posts = get_posts( [
			'post_type'      => 'graphql_document',
			'post_status'    => 'publish',
			'author'         => $this->admin_a,
			'posts_per_page' => -1,
		] );
		$this->assertCount( 1, $posts );
		$this->assertSame( $this->admin_a, (int) $posts[0]->post_author );
	}

	public function test_export_only_emits_the_requested_users_docs() {
		// One doc per admin, in separate collections so each is
		// exportable on its own terms.
		\WPGraphQLIDE\ImportExport::import(
			$this->payload_with( 'mine' ),
			$this->admin_a
		);
		\WPGraphQLIDE\ImportExport::import(
			$this->payload_with( 'theirs' ),
			$this->admin_b
		);

		$exported = \WPGraphQLIDE\ImportExport::export( $this->admin_a );

		$names = array_column( $exported['collections'], 'name' );
		$this->assertContains( 'Coll-mine', $names );
		$this->assertNotContains( 'Coll-theirs', $names );
	}

	// ---------------------------------------------------------------
	// Published-doc dedup
	// ---------------------------------------------------------------

	public function test_importing_the_same_published_query_twice_creates_then_skips() {
		// Same `$tag` produces an identical normalized query — the
		// importer's SHA-256 lookup should reuse the first post on the
		// second pass and report it as skipped (no second wp_insert_post,
		// which would otherwise collide on Smart Cache's alias term).
		$payload = $this->payload_with( 'dedup' );

		$first  = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );
		$second = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );

		$this->assertSame( 1, $first['created'] );
		$this->assertSame( 0, $first['skipped'] );

		$this->assertSame( 0, $second['created'] );
		$this->assertSame( 1, $second['skipped'] );

		// Exactly one post on disk for that content.
		$posts = get_posts( [
			'post_type'      => 'graphql_document',
			'post_status'    => 'publish',
			'author'         => $this->admin_a,
			'posts_per_page' => -1,
			's'              => 'query Qdedup',
		] );
		$this->assertCount( 1, $posts );
	}

	// ---------------------------------------------------------------
	// Round-trip (export → import on a clean fixture set)
	// ---------------------------------------------------------------

	public function test_round_trip_preserves_title_query_variables_headers_and_status() {
		$payload = $this->payload_with( 'rt', 'draft', [
			'variables' => '{"first": 5}',
			'headers'   => '{"X-Test": "yes"}',
		] );

		$imported = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );
		$this->assertSame( 1, $imported['created'] );

		$exported = \WPGraphQLIDE\ImportExport::export( $this->admin_a );

		// Locate the exported doc by collection name.
		$collection = null;
		foreach ( $exported['collections'] as $c ) {
			if ( 'Coll-rt' === $c['name'] ) {
				$collection = $c;
				break;
			}
		}
		$this->assertNotNull( $collection );
		$this->assertCount( 1, $collection['documents'] );

		$doc = $collection['documents'][0];
		$this->assertSame( 'Doc rt', $doc['title'] );
		$this->assertStringContainsString( 'posts', $doc['query'] );
		$this->assertSame( '{"first": 5}', $doc['variables'] );
		$this->assertSame( '{"X-Test": "yes"}', $doc['headers'] );
		// Drafts must round-trip as drafts — `status: publish` is the
		// default and intentionally omitted from the export, so any
		// payload with `status` is meaningful and must equal the input.
		$this->assertSame( 'draft', $doc['status'] );
	}

	public function test_export_omits_status_for_published_docs() {
		\WPGraphQLIDE\ImportExport::import(
			$this->payload_with( 'omitstatus' ),
			$this->admin_a
		);

		$exported = \WPGraphQLIDE\ImportExport::export( $this->admin_a );

		foreach ( $exported['collections'] as $collection ) {
			if ( 'Coll-omitstatus' !== $collection['name'] ) {
				continue;
			}
			foreach ( $collection['documents'] as $doc ) {
				$this->assertArrayNotHasKey(
					'status',
					$doc,
					'Published docs omit `status` so the wire payload stays minimal.'
				);
			}
		}
	}

	// ---------------------------------------------------------------
	// Edge cases
	// ---------------------------------------------------------------

	public function test_empty_query_is_treated_as_an_error_and_no_post_is_created() {
		$payload = [
			'version'     => \WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			'collections' => [
				[
					'name'      => 'Coll-empty',
					'documents' => [ [ 'title' => 'Empty', 'query' => '' ] ],
				],
			],
		];

		$result = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );
		// The collection itself is still created (terms are cheap and
		// the user may want to drop a doc into it later); the empty
		// document just doesn't materialize.
		$this->assertCount( 1, $result['collections'] );

		$posts = get_posts( [
			'post_type'      => 'graphql_document',
			'post_status'    => [ 'publish', 'draft' ],
			'author'         => $this->admin_a,
			'posts_per_page' => -1,
			'title'          => 'Empty',
		] );
		$this->assertCount( 0, $posts );
	}

	public function test_export_drops_documents_not_attached_to_a_collection() {
		// Manually create a doc without a collection term — the export
		// should ignore it so a future re-import doesn't try to drop it
		// into a phantom collection.
		$orphan = $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $this->admin_a,
			'post_content' => 'query Qorphan { posts { nodes { id } } }',
			'post_title'   => 'Orphan',
		] );

		$exported = \WPGraphQLIDE\ImportExport::export( $this->admin_a );

		$titles = [];
		foreach ( $exported['collections'] as $collection ) {
			foreach ( $collection['documents'] as $doc ) {
				$titles[] = $doc['title'];
			}
		}
		$this->assertNotContains( 'Orphan', $titles );
	}

	public function test_malformed_payload_returns_zero_counts() {
		$result = \WPGraphQLIDE\ImportExport::import(
			[ 'version' => \WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION, 'collections' => 'not-an-array' ],
			$this->admin_a
		);
		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( [], $result['collections'] );
	}

	public function test_two_collections_with_the_same_name_in_one_payload_are_merged_into_one_term() {
		// `term_exists` short-circuits the second `wp_insert_term` call,
		// so the two collection blocks resolve to the SAME term — their
		// documents land in one collection rather than spawning two
		// homonymous terms (which would surface as "Coll-merge (2)" in
		// the UI thanks to `wp_unique_term_slug`).
		$payload = [
			'version'     => \WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			'collections' => [
				[
					'name'      => 'Coll-merge',
					'documents' => [
						[
							'title' => 'A',
							'query' => 'query Qmerge1 { posts { nodes { id } } }',
						],
					],
				],
				[
					'name'      => 'Coll-merge',
					'documents' => [
						[
							'title' => 'B',
							'query' => 'query Qmerge2 { posts { nodes { id } } }',
						],
					],
				],
			],
		];

		$result = \WPGraphQLIDE\ImportExport::import( $payload, $this->admin_a );

		// Both documents created, both attached to the same term.
		$this->assertSame( 2, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );

		// `result['collections']` collects the term IDs returned per
		// collection block; both entries here should be the same ID.
		$this->assertCount( 2, $result['collections'] );
		$this->assertSame(
			$result['collections'][0],
			$result['collections'][1],
			'Same-named collection blocks must resolve to the same term ID.'
		);

		// Exactly one matching taxonomy term exists.
		$terms = get_terms( [
			'taxonomy'   => 'graphql_document_group',
			'name'       => 'Coll-merge',
			'hide_empty' => false,
		] );
		$this->assertCount( 1, $terms );
	}
}
