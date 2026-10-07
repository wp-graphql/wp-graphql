<?php

namespace WPGraphQL\SmartCache;

/**
 * Every saved document is stored under the hash of the query it holds, whichever
 * way it was written. A name a caller asks for is kept as an alias, which is what
 * names a document, and it can never take a name another document already holds.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3837
 * @see https://github.com/wp-graphql/wp-graphql/issues/4355
 */
class DocumentSlugTest extends \Codeception\TestCase\WPTestCase {

	const RAW_QUERY   = '{ posts { nodes { title } } }';
	const OTHER_QUERY = '{ pages { nodes { title } } }';

	public $admin;

	public function setUp(): void {
		parent::setUp();
		\WPGraphQL::clear_schema();
		wp_cache_flush();

		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		\WPGraphQL::clear_schema();
		parent::tearDown();
	}

	private function hash_of( string $query ): string {
		return hash( 'sha256', \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( $query ) ) );
	}

	private function aliases_of( int $post_id ): array {
		$terms = wp_get_object_terms( $post_id, Document::ALIAS_TAXONOMY_NAME, [ 'fields' => 'names' ] );

		return is_array( $terms ) ? $terms : [];
	}

	private function create_document( array $input ): array {
		wp_set_current_user( $this->admin );

		$mutation = 'mutation Create($input: CreateGraphqlDocumentInput!) {
			createGraphqlDocument(input: $input) { graphqlDocument { databaseId slug alias } }
		}';

		return do_graphql_request( $mutation, 'Create', [ 'input' => $input ] );
	}

	public function testMutationStoresDocumentUnderItsContentHash(): void {
		$actual = $this->create_document( [ 'title' => 'Homepage Posts', 'content' => self::RAW_QUERY, 'status' => 'PUBLISH' ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$document = $actual['data']['createGraphqlDocument']['graphqlDocument'];

		$this->assertSame( $this->hash_of( self::RAW_QUERY ), get_post( $document['databaseId'] )->post_name );

		// The slug the API reports is the slug the API accepts back, whether or not a
		// name was asked for when the document was stored.
		$this->assertSame( $this->hash_of( self::RAW_QUERY ), $document['slug'] );

		$named = $this->create_document( [ 'title' => 'Pages', 'content' => self::OTHER_QUERY, 'slug' => 'all-pages', 'status' => 'PUBLISH' ] );
		$this->assertArrayNotHasKey( 'errors', $named );
		$this->assertSame( $this->hash_of( self::OTHER_QUERY ), $named['data']['createGraphqlDocument']['graphqlDocument']['slug'] );

		$fetch = do_graphql_request(
			'query Fetch($id: ID!) { graphqlDocument( id: $id, idType: SLUG ) { databaseId } }',
			'Fetch',
			[ 'id' => $named['data']['createGraphqlDocument']['graphqlDocument']['slug'] ]
		);
		$this->assertSame( $named['data']['createGraphqlDocument']['graphqlDocument']['databaseId'], $fetch['data']['graphqlDocument']['databaseId'] );
	}

	public function testMutationKeepsARequestedSlugAsAnAlias(): void {
		$actual = $this->create_document( [
			'title'   => 'Homepage Posts',
			'content' => self::RAW_QUERY,
			'slug'    => 'homepage-posts',
			'status'  => 'PUBLISH',
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$post_id = $actual['data']['createGraphqlDocument']['graphqlDocument']['databaseId'];

		$this->assertSame( $this->hash_of( self::RAW_QUERY ), get_post( $post_id )->post_name );
		$this->assertContains( 'homepage-posts', $this->aliases_of( $post_id ) );
		$this->assertContains( $this->hash_of( self::RAW_QUERY ), $this->aliases_of( $post_id ) );

		// The name works as a queryId, which is the point of keeping it.
		$this->assertSame( ( new Document() )->get( $this->hash_of( self::RAW_QUERY ) ), ( new Document() )->get( 'homepage-posts' ) );
	}

	/**
	 * The point of the content-hash slug: the hash a client sends as its queryId also
	 * fetches the document through the schema.
	 */
	public function testDocumentResolvesByItsContentHashSlug(): void {
		$created = $this->create_document( [ 'title' => 'Homepage Posts', 'content' => self::RAW_QUERY, 'slug' => 'homepage-posts', 'status' => 'PUBLISH' ] );
		$post_id = $created['data']['createGraphqlDocument']['graphqlDocument']['databaseId'];

		$query  = 'query Fetch($id: ID!) { graphqlDocument( id: $id, idType: SLUG ) { databaseId } }';
		$actual = do_graphql_request( $query, 'Fetch', [ 'id' => $this->hash_of( self::RAW_QUERY ) ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $post_id, $actual['data']['graphqlDocument']['databaseId'] );
	}

	/**
	 * Clearing a document's names must not take the content hash with them, or the
	 * document stops resolving by the queryId clients send.
	 */
	public function testClearingNamesKeepsTheContentHash(): void {
		$created = $this->create_document( [ 'title' => 'Homepage Posts', 'content' => self::RAW_QUERY, 'slug' => 'homepage-posts', 'status' => 'PUBLISH' ] );
		$post_id = $created['data']['createGraphqlDocument']['graphqlDocument']['databaseId'];

		wp_set_current_user( $this->admin );
		$mutation = 'mutation Update($input: UpdateGraphqlDocumentInput!) {
			updateGraphqlDocument(input: $input) { graphqlDocument { databaseId alias } }
		}';
		$actual   = do_graphql_request( $mutation, 'Update', [
			'input' => [
				'id'    => \GraphQLRelay\Relay::toGlobalId( 'graphql_document', (string) $post_id ),
				'alias' => [],
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [ $this->hash_of( self::RAW_QUERY ) ], $this->aliases_of( $post_id ) );
		$this->assertSame( \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( self::RAW_QUERY ) ), ( new Document() )->get( $this->hash_of( self::RAW_QUERY ) ) );
	}

	public function testMutationCannotTakeANameAnotherDocumentHolds(): void {
		$first   = $this->create_document( [ 'title' => 'Homepage Posts', 'content' => self::RAW_QUERY, 'slug' => 'homepage-posts', 'status' => 'PUBLISH' ] );
		$first_id = $first['data']['createGraphqlDocument']['graphqlDocument']['databaseId'];

		$second = $this->create_document( [ 'title' => 'Pages', 'content' => self::OTHER_QUERY, 'slug' => 'homepage-posts', 'status' => 'PUBLISH' ] );

		$this->assertArrayHasKey( 'errors', $second );
		$this->assertStringContainsString( 'already in use by another query', $second['errors'][0]['message'] );

		// The name still belongs to the document that had it.
		$this->assertContains( 'homepage-posts', $this->aliases_of( $first_id ) );
		$this->assertSame( \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( self::RAW_QUERY ) ), ( new Document() )->get( 'homepage-posts' ) );
	}

	public function testUpdateCanSendTheDocumentsOwnNameBack(): void {
		$created = $this->create_document( [ 'title' => 'Homepage Posts', 'content' => self::RAW_QUERY, 'slug' => 'homepage-posts', 'status' => 'PUBLISH' ] );
		$post_id = $created['data']['createGraphqlDocument']['graphqlDocument']['databaseId'];

		wp_set_current_user( $this->admin );
		$mutation = 'mutation Update($input: UpdateGraphqlDocumentInput!) {
			updateGraphqlDocument(input: $input) { graphqlDocument { databaseId alias } }
		}';
		$actual   = do_graphql_request( $mutation, 'Update', [
			'input' => [
				'id'    => \GraphQLRelay\Relay::toGlobalId( 'graphql_document', (string) $post_id ),
				'slug'  => 'homepage-posts',
				'title' => 'Homepage Posts Renamed',
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertContains( 'homepage-posts', $this->aliases_of( $post_id ) );
		$this->assertSame( $this->hash_of( self::RAW_QUERY ), get_post( $post_id )->post_name );
	}

	public function testProgrammaticInsertIsStoredUnderItsContentHash(): void {
		$post_id = wp_insert_post(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_title'   => 'My Query',
				'post_name'    => 'my-query',
				'post_content' => self::RAW_QUERY,
			],
			true
		);

		$this->assertIsInt( $post_id );
		$this->assertSame( $this->hash_of( self::RAW_QUERY ), get_post( $post_id )->post_name );
		$this->assertContains( 'my-query', $this->aliases_of( $post_id ) );
	}

	/**
	 * A document stored before documents were content-addressed keeps its name: the
	 * slug moves to the content hash and the name it was stored under becomes an
	 * alias, so nothing that resolved before stops resolving.
	 */
	public function testEditingALegacyDocumentKeepsItsNameAsAnAlias(): void {
		global $wpdb;

		$post_id = wp_insert_post(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_title'   => 'Legacy',
				'post_content' => self::RAW_QUERY,
			],
			true
		);
		$this->assertIsInt( $post_id );

		// Put the row back the way an older version stored it.
		$wpdb->update( $wpdb->posts, [ 'post_name' => 'legacy' ], [ 'ID' => $post_id ] );
		clean_post_cache( $post_id );
		wp_cache_flush();

		wp_update_post( [ 'ID' => $post_id, 'post_title' => 'Legacy Renamed' ] );

		$this->assertSame( $this->hash_of( self::RAW_QUERY ), get_post( $post_id )->post_name );
		$this->assertContains( 'legacy', $this->aliases_of( $post_id ) );
		$this->assertSame( \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( self::RAW_QUERY ) ), ( new Document() )->get( 'legacy' ) );
	}

	/**
	 * The automatic persisted query path never claims a name: a document it stores
	 * is addressed by its hash and nothing else.
	 */
	public function testAutomaticRegistrationClaimsNoName(): void {
		wp_set_current_user( 0 );

		$hash    = $this->hash_of( self::RAW_QUERY );
		$post_id = ( new Document() )->save( $hash, self::RAW_QUERY );

		$this->assertSame( $hash, get_post( $post_id )->post_name );
		$this->assertSame( [ $hash ], array_values( array_unique( $this->aliases_of( $post_id ) ) ) );
	}
}
