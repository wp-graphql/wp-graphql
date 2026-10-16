<?php

namespace WPGraphQL\SmartCache;

/**
 * A saved document someone else has not published is visible to a user who can edit
 * documents, but only in a restricted form: the node resolves, and its fields are limited
 * to the ones a restricted node exposes. The settings Smart Cache adds to the document
 * (its aliases, access grant, max-age and description) are not among them.
 */
class DocumentRestrictedFieldsTest extends \Codeception\TestCase\WPTestCase {

	const QUERY = 'query Fetch($id: ID!) {
		graphqlDocument( id: $id, idType: DATABASE_ID ) {
			databaseId
			alias
			grant
			maxAgeHeader
			description
		}
	}';

	public $admin;
	public $author;
	public $document;

	public function setUp(): void {
		parent::setUp();
		\WPGraphQL::clear_schema();
		wp_cache_flush();

		$this->admin  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->author = self::factory()->user->create( [ 'role' => 'author' ] );

		$this->document = self::factory()->post->create(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'draft',
				'post_author'  => $this->admin,
				'post_title'   => 'Unpublished document',
				'post_content' => '{ __typename }',
				'post_excerpt' => 'Unpublished description',
			]
		);

		wp_set_object_terms( $this->document, [ 'unpublished-alias' ], Document::ALIAS_TAXONOMY_NAME );
		wp_set_object_terms( $this->document, [ Document\Grant::DENY ], Document\Grant::TAXONOMY_NAME );
		wp_set_object_terms( $this->document, [ '42' ], Document\MaxAge::TAXONOMY_NAME );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		\WPGraphQL::clear_schema();
		parent::tearDown();
	}

	private function fetch_document_as( int $user_id ): array {
		wp_set_current_user( $user_id );

		return do_graphql_request( self::QUERY, 'Fetch', [ 'id' => $this->document ] );
	}

	public function testRestrictedDocumentDoesNotExposeItsSettings(): void {
		$actual = $this->fetch_document_as( $this->author );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// The node resolves in its restricted form, so this is the restricted view under
		// test and not a document hidden altogether.
		$document = $actual['data']['graphqlDocument'];
		$this->assertSame( $this->document, $document['databaseId'] );

		$this->assertNull( $document['alias'] );
		$this->assertNull( $document['grant'] );
		$this->assertNull( $document['maxAgeHeader'] );
		$this->assertNull( $document['description'] );
	}

	public function testDocumentOwnerSeesItsSettings(): void {
		$actual = $this->fetch_document_as( $this->admin );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$document = $actual['data']['graphqlDocument'];
		$this->assertSame( [ 'unpublished-alias' ], $document['alias'] );
		$this->assertSame( Document\Grant::DENY, $document['grant'] );
		$this->assertSame( 42, $document['maxAgeHeader'] );
		$this->assertSame( 'Unpublished description', $document['description'] );
	}
}
