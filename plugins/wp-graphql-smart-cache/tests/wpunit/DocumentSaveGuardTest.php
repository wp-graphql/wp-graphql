<?php

namespace WPGraphQL\SmartCache;

use GraphQL\Server\RequestError;

/**
 * Document::save() is the sink the automatic persisted query request path
 * reaches. It must only bind a queryId to a document when the id is the
 * SHA-256 hash of that document (raw string or normalized form).
 */
class DocumentSaveGuardTest extends \Codeception\TestCase\WPTestCase {

	const RAW_QUERY = '{ posts { nodes { title } } }';

	public function _before() {
		$posts = get_posts( [ 'post_type' => Document::TYPE_NAME, 'post_status' => 'any', 'numberposts' => -1 ] );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
		wp_set_current_user( 0 );
	}

	private function normalized_query(): string {
		return \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( self::RAW_QUERY ) );
	}

	private function count_documents(): int {
		return count( get_posts( [ 'post_type' => Document::TYPE_NAME, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) );
	}

	public function testSaveRejectsIdThatIsNotTheQueryHash(): void {
		$document = new Document();

		try {
			$document->save( 'homepage-posts', self::RAW_QUERY );
			$this->fail( 'Expected a RequestError for a non-hash queryId' );
		} catch ( RequestError $e ) {
			$this->assertStringContainsString( 'does not match the query hash', $e->getMessage() );
		}

		$this->assertSame( 0, $this->count_documents() );
		$this->assertFalse( Utils::getPostByTermName( 'homepage-posts', Document::TYPE_NAME, Document::ALIAS_TAXONOMY_NAME ) );
	}

	public function testSaveRejectsHashOfADifferentQuery(): void {
		$document = new Document();

		try {
			$document->save( hash( 'sha256', '{ __typename }' ), self::RAW_QUERY );
			$this->fail( 'Expected a RequestError for the hash of a different query' );
		} catch ( RequestError $e ) {
			$this->assertStringContainsString( 'does not match the query hash', $e->getMessage() );
		}

		$this->assertSame( 0, $this->count_documents() );
	}

	public function testSaveAcceptsRawStringHash(): void {
		$raw_hash        = hash( 'sha256', self::RAW_QUERY );
		$normalized_hash = hash( 'sha256', $this->normalized_query() );

		$post_id = ( new Document() )->save( $raw_hash, self::RAW_QUERY );

		$this->assertSame( $normalized_hash, get_post( $post_id )->post_name );
		$this->assertSame( $this->normalized_query(), ( new Document() )->get( $raw_hash ) );
		$this->assertSame( $this->normalized_query(), ( new Document() )->get( $normalized_hash ) );
	}

	public function testSaveAcceptsNormalizedHash(): void {
		$normalized_hash = hash( 'sha256', $this->normalized_query() );

		$post_id = ( new Document() )->save( $normalized_hash, self::RAW_QUERY );

		$this->assertSame( $normalized_hash, get_post( $post_id )->post_name );
		$this->assertSame( $this->normalized_query(), ( new Document() )->get( $normalized_hash ) );
	}

	public function testSaveReturnsExistingDocumentForAnAliasAnAuthorizedUserAssigned(): void {
		$document = new Document();
		$post_id  = $document->save( hash( 'sha256', self::RAW_QUERY ), self::RAW_QUERY );
		wp_add_object_terms( $post_id, 'homepage-posts', Document::ALIAS_TAXONOMY_NAME );

		$this->assertSame( $post_id, $document->save( 'homepage-posts', self::RAW_QUERY ) );
		$this->assertSame( 1, $this->count_documents() );

		try {
			$document->save( 'homepage-posts', '{ __typename }' );
			$this->fail( 'Expected a RequestError when a different query is sent with an existing alias' );
		} catch ( RequestError $e ) {
			$this->assertStringContainsString( 'already been associated with another query', $e->getMessage() );
		}
		$this->assertSame( 1, $this->count_documents() );
		$this->assertSame( $this->normalized_query(), get_post( $post_id )->post_content );
	}

	/**
	 * The scenario from the advisory: an anonymous caller stores a mutation under
	 * a predictable alias, and an administrator later executes by that alias.
	 */
	public function testAnonymousCannotPoisonAnAliasForLaterAdminExecution(): void {
		$query_id  = 'public-frontend-homepage-query';
		$malicious = 'mutation HomepageQuery { createUser(input: { username: "persisted_query_backdoor", email: "backdoor@example.test", password: "Poisoned-Query-Pass-9384!", roles: ["administrator"] }) { user { databaseId } } }';

		wp_set_current_user( 0 );
		try {
			( new Document() )->save( $query_id, $malicious );
			$this->fail( 'Expected a RequestError for a non-hash queryId' );
		} catch ( RequestError $e ) {
			$this->assertStringContainsString( 'does not match the query hash', $e->getMessage() );
		}

		$admin_id = static::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Nothing was stored, so nothing resolves for the alias. The programmatic
		// graphql() path surfaces a missing document as an exception rather than a
		// PersistedQueryNotFound error (the HTTP path is covered functionally);
		// either way the stored mutation must not run.
		$executed = false;
		try {
			$response = graphql( [ 'queryId' => $query_id ] );
			$executed = ! isset( $response['errors'] );
		} catch ( \Throwable $e ) {
			$executed = false;
		}

		$this->assertFalse( $executed, 'The alias must not resolve to an executable document' );
		$this->assertFalse( get_user_by( 'login', 'persisted_query_backdoor' ) );
		$this->assertNull( ( new Document() )->get( $query_id ) );
	}
}
