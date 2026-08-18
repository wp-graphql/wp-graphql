<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Admin\Editor;

/**
 * Regression coverage for #3837: admin-authored graphql_document posts must get
 * the content-hash post_name (slug) like programmatically-saved documents, so
 * graphqlDocument(idType: SLUG) resolves them by hash.
 */
class AdminDocumentSlugTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();

		\WPGraphQL::clear_schema();

		$this->admin = self::factory()->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown(): void {
		\WPGraphQL::clear_schema();

		parent::tearDown();
	}

	/**
	 * Simulate authoring a document in wp-admin: the editor registers
	 * Editor::validate_and_pre_save_cb on wp_insert_post_data during admin_init,
	 * then WordPress inserts the post with a human-readable title.
	 *
	 * @return int The created post ID.
	 */
	protected function author_document_in_admin( string $title, string $content ): int {
		$editor = new Editor();
		add_filter( 'wp_insert_post_data', [ $editor, 'validate_and_pre_save_cb' ], 10, 2 );

		$post_id = self::factory()->post->create( [
			'post_type'    => Document::TYPE_NAME,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		] );

		remove_filter( 'wp_insert_post_data', [ $editor, 'validate_and_pre_save_cb' ], 10 );

		return (int) $post_id;
	}

	/**
	 * Admin form submissions arrive slashed (wp_magic_quotes() slashes $_POST,
	 * and edit_post() passes it slashed into wp_update_post()), and WordPress
	 * unslashes the filtered data after wp_insert_post_data returns. A query
	 * whose normalized form contains literal backslashes (escaped quotes or
	 * backslashes inside a GraphQL string argument) must survive that round
	 * trip: the stored content must be the normalized print of what was
	 * authored, and the slug must be the hash of the *stored* content so the
	 * alias term, re-saves, and SLUG lookups all agree.
	 */
	public function testAdminAuthoredDocumentWithEscapedStringsKeepsContentAndSlugInSync() {
		wp_set_current_user( $this->admin );

		// Raw text as typed by the author. The GraphQL string argument contains
		// an escaped quote (\") and an escaped backslash (\\), so the printed
		// normalized document contains literal backslashes.
		$raw = 'query EscapedStrings { contentNodes( where: { search: "say \"hi\" to \\\\ backslash" } ) { nodes { __typename } } }';

		$editor = new Editor();
		add_filter( 'wp_insert_post_data', [ $editor, 'validate_and_pre_save_cb' ], 10, 2 );

		// Slash the postarr exactly as an admin form submission would be.
		$post_id = wp_insert_post(
			wp_slash( [
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_title'   => 'Escaped Strings Demo',
				'post_content' => $raw,
			] ),
			true
		);

		remove_filter( 'wp_insert_post_data', [ $editor, 'validate_and_pre_save_cb' ], 10 );

		$this->assertNotWPError( $post_id );

		$stored              = get_post( $post_id )->post_content;
		$expected_normalized = \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( $raw ) );

		$this->assertSame(
			$expected_normalized,
			$stored,
			'The stored document should be the normalized print of the authored query, with escape sequences intact.'
		);

		$this->assertSame(
			Utils::generateHash( $stored ),
			get_post( $post_id )->post_name,
			'The slug should be the hash of the content as stored, so slug, alias term, and re-saves all agree.'
		);
	}

	/**
	 * The runtime persisted-query path (Document::save(), used by the
	 * persisted query loader) must round-trip escaped strings the same way:
	 * wp_insert_post() expects slashed data, so save() must slash what it
	 * inserts or stored documents lose backslashes.
	 */
	public function testProgrammaticallySavedDocumentWithEscapedStringsRoundTrips() {
		$raw = 'query EscapedStrings { contentNodes( where: { search: "say \"hi\" to \\\\ backslash" } ) { nodes { __typename } } }';

		$expected_normalized = \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( $raw ) );
		$expected_hash       = Utils::getHashFromFormattedString( $expected_normalized );

		$document = new Document();
		$post_id  = $document->save( $expected_hash, $raw );

		$stored = get_post( $post_id )->post_content;

		$this->assertSame(
			$expected_normalized,
			$stored,
			'The stored document should be the normalized print of the saved query, with escape sequences intact.'
		);

		$this->assertSame(
			$expected_hash,
			get_post( $post_id )->post_name,
			'The slug should be the hash of the content as stored.'
		);

		// The persisted-query loader must return the document by its hash.
		$this->assertSame( $expected_normalized, $document->get( $expected_hash ) );
	}

	public function testAdminAuthoredDocumentGetsContentHashSlug() {
		wp_set_current_user( $this->admin );

		$content = 'query QueryDemo { __typename }';
		$post_id = $this->author_document_in_admin( 'Query Demo', $content );

		$expected_hash = Utils::generateHash( $content );

		// The slug should be the content hash, not the title-derived "query-demo".
		$this->assertSame(
			$expected_hash,
			get_post( $post_id )->post_name,
			'Admin-authored document should be saved with the content-hash slug.'
		);
	}

	public function testAdminAuthoredDocumentResolvesByContentHashSlug() {
		wp_set_current_user( $this->admin );

		$content       = 'query QueryDemo { __typename }';
		$post_id       = $this->author_document_in_admin( 'Query Demo', $content );
		$expected_hash = Utils::generateHash( $content );

		$query = 'query GetDoc( $id: ID! ) {
			graphqlDocument( id: $id, idType: SLUG ) {
				databaseId
				slug
			}
		}';

		$actual = graphql( [
			'query'     => $query,
			'variables' => [ 'id' => $expected_hash ],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $post_id, $actual['data']['graphqlDocument']['databaseId'] );
		$this->assertSame( $expected_hash, $actual['data']['graphqlDocument']['slug'] );
	}
}
