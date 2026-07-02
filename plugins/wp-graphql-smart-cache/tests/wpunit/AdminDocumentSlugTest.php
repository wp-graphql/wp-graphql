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
