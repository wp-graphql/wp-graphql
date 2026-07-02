<?php
/**
 * Behavior when an imported document's normalized content collides
 * with an existing `graphql_document` under Smart Cache.
 *
 * Smart Cache treats the sha256 of every normalized GraphQL document
 * as content-addressed identity (the `graphql_query_alias` taxonomy
 * term that `save_document_cb` stamps onto every save, draft or
 * publish). When the IDE's importer tried to insert a draft whose
 * content already existed on another doc, the old code path went
 * straight to `wp_insert_post` — Smart Cache's hook then threw a
 * `RequestError` mid-`save_post`, leaving an orphaned post row and
 * crashing the import loop.
 *
 * The fix in `ImportExport::upsert()` normalizes + hashes every
 * import (not just publishes) and looks the hash up via the alias
 * taxonomy before inserting, so a collision returns `'skipped'`
 * cleanly and the existing post gets attached to the new collection.
 * These tests assert the post-fix behavior across the four
 * combinations of (existing-status × incoming-status).
 *
 * Smart Cache must be active for the alias term to exist; the
 * `wpunit-integration` suite handles that.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE\Integration\ImportExport;

class SmartCacheCollisionTest extends \Codeception\TestCase\WPTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Insert a `graphql_document` post with a given status. Smart Cache's
	 * `save_document_cb` will fire and stamp the content's sha256 onto
	 * the `graphql_query_alias` taxonomy term for us.
	 */
	private function seed_doc( string $query, string $status, string $title ): int {
		return $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => $status,
			'post_author'  => $this->admin,
			'post_title'   => $title,
			'post_content' => $query,
		] );
	}

	private function payload( string $tag, string $query, string $status = 'publish' ): array {
		$doc = [ 'title' => 'Imported ' . $tag, 'query' => $query ];
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

	private function count_docs_with_content( string $content ): int {
		$posts = get_posts( [
			'post_type'      => 'graphql_document',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			's'              => $content,
			'fields'         => 'ids',
		] );
		return count( $posts );
	}

	// ---------------------------------------------------------------
	// Each combination of (existing-status × incoming-status). All four
	// resolve to `skipped` + existing post reused.
	// ---------------------------------------------------------------

	public function test_importing_draft_with_same_content_as_existing_draft_is_skipped() {
		$query    = 'query QexistingDraft { posts { nodes { id } } }';
		$existing = $this->seed_doc( $query, 'draft', 'Existing draft' );

		$result = \WPGraphQLIDE\ImportExport::import(
			$this->payload( 'd2d', $query, 'draft' ),
			$this->admin
		);

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( 1, $this->count_docs_with_content( 'query QexistingDraft' ) );
		$this->assertContains(
			$existing,
			array_map( 'intval', get_objects_in_term( $result['collections'][0], 'graphql_document_group' ) ),
			'Existing draft should be attached to the imported collection.'
		);
	}

	public function test_importing_draft_with_same_content_as_existing_publish_is_skipped() {
		$query    = 'query QexistingPub { posts { nodes { id } } }';
		$existing = $this->seed_doc( $query, 'publish', 'Existing publish' );

		$result = \WPGraphQLIDE\ImportExport::import(
			$this->payload( 'd2p', $query, 'draft' ),
			$this->admin
		);

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['skipped'] );
		// The pre-fix code path attempted wp_insert_post here and left an
		// orphaned draft when Smart Cache threw. Post-fix: no insert
		// happens at all.
		$this->assertSame( 1, $this->count_docs_with_content( 'query QexistingPub' ) );
		$this->assertContains(
			$existing,
			array_map( 'intval', get_objects_in_term( $result['collections'][0], 'graphql_document_group' ) )
		);
	}

	public function test_importing_publish_with_same_content_as_existing_draft_is_skipped() {
		$query    = 'query QdraftToPub { posts { nodes { id } } }';
		$existing = $this->seed_doc( $query, 'draft', 'Existing draft' );

		$result = \WPGraphQLIDE\ImportExport::import(
			$this->payload( 'p2d', $query, 'publish' ),
			$this->admin
		);

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( 1, $this->count_docs_with_content( 'query QdraftToPub' ) );
		// The existing doc keeps its draft status — the importer's job
		// is to attach metadata, not to promote.
		$this->assertSame( 'draft', get_post_status( $existing ) );
		$this->assertContains(
			$existing,
			array_map( 'intval', get_objects_in_term( $result['collections'][0], 'graphql_document_group' ) )
		);
	}

	public function test_importing_publish_with_same_content_as_existing_publish_is_skipped() {
		$query    = 'query QpubToPub { posts { nodes { id } } }';
		$existing = $this->seed_doc( $query, 'publish', 'Existing publish' );

		$result = \WPGraphQLIDE\ImportExport::import(
			$this->payload( 'p2p', $query, 'publish' ),
			$this->admin
		);

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertSame( 1, $this->count_docs_with_content( 'query QpubToPub' ) );
		$this->assertContains(
			$existing,
			array_map( 'intval', get_objects_in_term( $result['collections'][0], 'graphql_document_group' ) )
		);
	}

	// ---------------------------------------------------------------
	// Negative: distinct content still creates fresh posts.
	// ---------------------------------------------------------------

	public function test_importing_distinct_drafts_still_creates_them() {
		// Sanity check that the dedup-by-hash path doesn't over-match.
		$this->seed_doc(
			'query QseedDistinct { posts { nodes { id } } }',
			'draft',
			'Seed'
		);

		$result = \WPGraphQLIDE\ImportExport::import(
			$this->payload( 'distinct', 'query QimportedDistinct { posts { nodes { id title } } }', 'draft' ),
			$this->admin
		);

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( 1, $this->count_docs_with_content( 'QimportedDistinct' ) );
	}
}
