<?php
/**
 * Activation seeder coverage.
 *
 * `ImportExport::seed()` runs on first activation (and after a bumped
 * `SEED_VERSION`). It reads `seeds/example-documents.json` and feeds it
 * to `ImportExport::import()` as the current user, then stamps the
 * `wpgraphql_ide_seed_version` option so subsequent boots no-op.
 *
 * Two ways this silently breaks:
 *   - The seed file goes missing or stops parsing — new installs land
 *     with an empty Saved-Queries panel and the user has no example
 *     queries to copy from.
 *   - The seed file's schema version drifts from `IMPORT_SCHEMA_VERSION`
 *     — the importer returns `{ error: ... }` and creates nothing,
 *     same outcome but louder in logs.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE\Integration\ImportExport;

class SeederTest extends \Codeception\TestCase\WPTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		// Reset the option so each test starts unsensed.
		delete_option( 'wpgraphql_ide_seed_version' );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		delete_option( 'wpgraphql_ide_seed_version' );
		parent::tearDown();
	}

	public function test_seed_file_exists_parses_and_is_at_the_current_schema_version() {
		// File existence + JSON-decode + version-match are the three
		// preconditions for `seed()` to succeed at all. Verifying them
		// directly here gives a clearer failure than a downstream zero-
		// docs-imported assertion if any of them slip.
		$path = WPGRAPHQL_IDE_PLUGIN_DIR_PATH . 'seeds/example-documents.json';

		$this->assertFileExists( $path, 'Seed file must ship in the plugin.' );

		$contents = file_get_contents( $path );
		$this->assertNotFalse( $contents, 'Seed file must be readable.' );

		$data = json_decode( $contents, true );
		$this->assertIsArray( $data, 'Seed file must parse as JSON.' );

		$this->assertArrayHasKey( 'version', $data );
		$this->assertSame(
			\WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			(int) $data['version'],
			'Seed file schema version must match `IMPORT_SCHEMA_VERSION`.'
		);
		$this->assertArrayHasKey( 'collections', $data );
		$this->assertNotEmpty( $data['collections'] );
	}

	public function test_seed_creates_collections_and_documents_for_the_active_user() {
		wp_set_current_user( $this->admin );

		\WPGraphQLIDE\ImportExport::seed();

		$terms = get_terms( [
			'taxonomy'   => 'graphql_document_group',
			'hide_empty' => false,
		] );
		$this->assertGreaterThan(
			0,
			count( $terms ),
			'Seed must create at least one collection term.'
		);

		$posts = get_posts( [
			'post_type'      => 'graphql_document',
			'post_status'    => [ 'publish', 'draft' ],
			'author'         => $this->admin,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		$this->assertGreaterThan(
			0,
			count( $posts ),
			'Seed must create example documents under the active user.'
		);
	}

	public function test_seed_stamps_the_version_option_after_a_successful_run() {
		wp_set_current_user( $this->admin );

		\WPGraphQLIDE\ImportExport::seed();

		$this->assertSame(
			\WPGraphQLIDE\ImportExport::SEED_VERSION,
			get_option( 'wpgraphql_ide_seed_version' ),
			'Seed must persist the version option so it no-ops on subsequent boots.'
		);
	}

	public function test_seed_is_idempotent_when_the_version_matches() {
		wp_set_current_user( $this->admin );

		\WPGraphQLIDE\ImportExport::seed();
		$first_post_count = wp_count_posts( 'graphql_document' )->publish;

		// Second pass with the option already set — must not import again.
		\WPGraphQLIDE\ImportExport::seed();
		$second_post_count = wp_count_posts( 'graphql_document' )->publish;

		$this->assertSame(
			$first_post_count,
			$second_post_count,
			'Repeated `seed()` calls with the same SEED_VERSION must not create duplicates.'
		);
	}

	public function test_seed_is_a_noop_when_there_is_no_current_user() {
		// Activation context occasionally fires without a current user
		// (CLI, certain plugin loaders). `seed()` exits early; this
		// guards against accidentally creating orphan posts.
		wp_set_current_user( 0 );

		\WPGraphQLIDE\ImportExport::seed();

		$this->assertFalse(
			get_option( 'wpgraphql_ide_seed_version' ),
			'Seed must NOT stamp the version when no user is available — it never imported.'
		);
		$posts = get_posts( [
			'post_type'      => 'graphql_document',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		$this->assertCount( 0, $posts );
	}
}
