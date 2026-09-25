<?php
/**
 * Automatic persisted query registration over HTTP.
 *
 * A request that carries both `query` and `queryId` may only register the
 * document when the queryId is the SHA-256 hash of that query (either the raw
 * string as sent, or the normalized document). Custom alias names are managed
 * by authorized users through the admin editor or the graphqlDocument
 * mutations, never claimed by an anonymous request. Documents are persisted
 * only after they pass validation.
 */
class PersistedQueryRegistrationCest {

	const MISMATCH_MESSAGE = 'The provided queryId does not match the query hash. Alias names for saved GraphQL Documents can only be assigned by an authorized user.';

	const RAW_QUERY = '{ posts { nodes { title } } }';

	// graphql-php's printer output for RAW_QUERY (definitions joined, trailing newline).
	const NORMALIZED_QUERY = "{\n  posts {\n    nodes {\n      title\n    }\n  }\n}\n";

	public function _before( FunctionalTester $I ) {
		$I->dontHavePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontHaveTermInDatabase( [ 'taxonomy' => 'graphql_query_alias' ] );
		$I->dontHaveOptionInDatabase( 'graphql_persisted_queries_section' );
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
	}

	public function anonymousPostWithCustomAliasIsRejectedTest( FunctionalTester $I ) {
		$I->wantTo( 'Reject an anonymous POST that tries to claim a custom alias for a query' );

		$I->sendPost( 'graphql', json_encode( [
			'query'   => self::RAW_QUERY,
			'queryId' => 'homepage-posts',
		] ) );

		$I->seeResponseContainsJson( [
			'errors' => [
				[ 'message' => self::MISMATCH_MESSAGE ],
			],
		] );
		$I->dontSeeResponseContainsJson( [ 'data' => [ 'posts' => [] ] ] );
		$I->dontSeePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontSeeTermInDatabase( [ 'name' => 'homepage-posts' ] );
	}

	public function anonymousGetWithCustomAliasIsRejectedTest( FunctionalTester $I ) {
		$I->wantTo( 'Reject an anonymous GET that tries to claim a custom alias for a query' );

		$I->sendGet( 'graphql', [
			'query'   => self::RAW_QUERY,
			'queryId' => 'homepage-posts',
		] );

		$I->seeResponseContainsJson( [
			'errors' => [
				[ 'message' => self::MISMATCH_MESSAGE ],
			],
		] );
		$I->dontSeePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontSeeTermInDatabase( [ 'name' => 'homepage-posts' ] );
	}

	public function rawStringHashRegistersDocumentTest( FunctionalTester $I ) {
		$I->wantTo( 'Register a document when the queryId is the hash of the raw query string, as Apollo clients send it' );

		$raw_hash        = hash( 'sha256', self::RAW_QUERY );
		$normalized_hash = hash( 'sha256', self::NORMALIZED_QUERY );

		$I->sendPost( 'graphql', json_encode( [
			'query'   => self::RAW_QUERY,
			'queryId' => $raw_hash,
		] ) );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );

		$I->seePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_name'    => $normalized_hash,
			'post_content' => self::NORMALIZED_QUERY,
		] );
		$I->seeTermInDatabase( [ 'name' => $raw_hash ] );
		$I->seeTermInDatabase( [ 'name' => $normalized_hash ] );

		// Either hash executes the stored document afterwards.
		$I->sendGet( 'graphql', [ 'queryId' => $raw_hash ] );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );

		$I->sendGet( 'graphql', [ 'queryId' => $normalized_hash ] );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );
	}

	public function normalizedHashRegistersDocumentTest( FunctionalTester $I ) {
		$I->wantTo( 'Register a document when the queryId is the hash of the normalized document' );

		$normalized_hash = hash( 'sha256', self::NORMALIZED_QUERY );

		$I->sendPost( 'graphql', json_encode( [
			'query'   => self::RAW_QUERY,
			'queryId' => $normalized_hash,
		] ) );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );
		$I->seePostInDatabase( [ 'post_type' => 'graphql_document', 'post_name' => $normalized_hash ] );
		$I->seeTermInDatabase( [ 'name' => $normalized_hash ] );
	}

	public function apolloExtensionsHashRegistersDocumentTest( FunctionalTester $I ) {
		$I->wantTo( 'Register a document through the Apollo persistedQuery extensions shape' );

		$raw_hash = hash( 'sha256', self::RAW_QUERY );

		$I->sendPost( 'graphql', json_encode( [
			'query'      => self::RAW_QUERY,
			'extensions' => [
				'persistedQuery' => [
					'version'    => 1,
					'sha256Hash' => $raw_hash,
				],
			],
		] ) );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );
		$I->seeTermInDatabase( [ 'name' => $raw_hash ] );

		// And the same shape refuses a non-hash id.
		$I->sendPost( 'graphql', json_encode( [
			'query'      => self::RAW_QUERY,
			'extensions' => [
				'persistedQuery' => [
					'version'    => 1,
					'sha256Hash' => 'homepage-posts',
				],
			],
		] ) );
		$I->seeResponseContainsJson( [ 'errors' => [ [ 'message' => self::MISMATCH_MESSAGE ] ] ] );
		$I->dontSeeTermInDatabase( [ 'name' => 'homepage-posts' ] );
	}

	public function schemaInvalidDocumentIsNotPersistedTest( FunctionalTester $I ) {
		$I->wantTo( 'Skip persisting a document that fails schema validation, even with a matching hash' );

		$query    = '{ notARealField }';
		$raw_hash = hash( 'sha256', $query );

		$I->sendPost( 'graphql', json_encode( [
			'query'   => $query,
			'queryId' => $raw_hash,
		] ) );
		$I->seeResponseContainsJson( [
			'errors' => [
				[ 'message' => 'Cannot query field "notARealField" on type "RootQuery".' ],
			],
		] );
		$I->dontSeePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontSeeTermInDatabase( [ 'name' => $raw_hash ] );
	}

	public function allowOnlyModeDoesNotPersistTest( FunctionalTester $I ) {
		$I->wantTo( 'Skip persisting a document the "Allow only specific queries" mode refuses to execute' );

		$I->haveOptionInDatabase( 'graphql_persisted_queries_section', [ 'grant_mode' => 'only_allowed' ] );

		$raw_hash = hash( 'sha256', self::RAW_QUERY );

		$I->sendPost( 'graphql', json_encode( [
			'query'   => self::RAW_QUERY,
			'queryId' => $raw_hash,
		] ) );
		$I->seeResponseContainsJson( [
			'errors' => [
				[ 'message' => 'Not Found. Only pre-defined queries are allowed.' ],
			],
		] );
		$I->dontSeePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontSeeTermInDatabase( [ 'name' => $raw_hash ] );
	}

	public function preRegisteredAliasWithMatchingQueryExecutesTest( FunctionalTester $I ) {
		$I->wantTo( 'Execute by a custom alias an authorized user registered, when the query sent alongside matches, and refuse a different query' );

		$normalized_hash = hash( 'sha256', self::NORMALIZED_QUERY );
		$alias           = 'homepage-posts';

		$I->haveTermInDatabase( $normalized_hash, 'graphql_query_alias' );
		$I->haveTermInDatabase( $alias, 'graphql_query_alias' );
		$post_id = $I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_title'   => 'Homepage Posts',
			'post_name'    => $normalized_hash,
			'post_content' => self::NORMALIZED_QUERY,
			'tax_input'    => [
				'graphql_query_alias' => [ $normalized_hash, $alias ],
			],
		] );

		// Same document, sent with its alias: executes, nothing new is written.
		$I->sendPost( 'graphql', json_encode( [
			'query'   => self::RAW_QUERY,
			'queryId' => $alias,
		] ) );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );
		$I->assertEquals( 1, $I->grabNumRecords( $I->grabPostsTableName(), [ 'post_type' => 'graphql_document' ] ) );

		// A different document sent with someone else's alias is refused and nothing changes.
		$I->sendPost( 'graphql', json_encode( [
			'query'   => '{ __typename }',
			'queryId' => $alias,
		] ) );
		$I->seeResponseContainsJson( [
			'errors' => [
				[ 'message' => 'This queryId has already been associated with another query "Homepage Posts"' ],
			],
		] );
		$I->assertEquals( 1, $I->grabNumRecords( $I->grabPostsTableName(), [ 'post_type' => 'graphql_document' ] ) );
		$I->seePostInDatabase( [ 'ID' => $post_id, 'post_content' => self::NORMALIZED_QUERY ] );
	}

	/**
	 * Two requests registering the same brand-new query at the same moment could
	 * both write a document, the second under the slug WordPress made unique. Once
	 * that row was the newest holder of the hash, every later registration of the
	 * query was refused, because the slug was taken to be the document's identity.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/4355
	 */
	public function registrationSucceedsWhenADuplicateDocumentHoldsTheHashTest( FunctionalTester $I ) {
		$I->wantTo( 'Register a query whose hash is held by a duplicate document a concurrent registration left behind' );

		$normalized_hash = hash( 'sha256', self::NORMALIZED_QUERY );

		// The state the race left behind: two documents holding the same query, the
		// newer one stored under the uniquified slug, both aliased by the hash.
		[ , $term_taxonomy_id ] = $I->haveTermInDatabase( $normalized_hash, 'graphql_query_alias' );

		$first_id = $I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_title'   => 'RaceProbe',
			'post_name'    => $normalized_hash,
			'post_content' => self::NORMALIZED_QUERY,
			'post_date'    => '2026-01-01 00:00:00',
		] );
		$duplicate_id = $I->havePostInDatabase( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_title'   => 'RaceProbe',
			'post_name'    => $normalized_hash . '-2',
			'post_content' => self::NORMALIZED_QUERY,
			'post_date'    => '2026-01-01 00:00:01',
		] );

		$I->haveTermRelationshipInDatabase( $first_id, $term_taxonomy_id );
		$I->haveTermRelationshipInDatabase( $duplicate_id, $term_taxonomy_id );

		// Registering the same query again resolves to the stored document instead of
		// being refused, and adds no further document.
		$I->sendPost( 'graphql', json_encode( [
			'query'      => self::RAW_QUERY,
			'extensions' => [
				'persistedQuery' => [
					'version'    => 1,
					'sha256Hash' => $normalized_hash,
				],
			],
		] ) );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );
		$I->dontSeeResponseContainsJson( [ 'errors' => [ [ 'message' => 'This queryId has already been associated with another query "RaceProbe"' ] ] ] );
		$I->assertEquals( 2, $I->grabNumRecords( $I->grabPostsTableName(), [ 'post_type' => 'graphql_document' ] ) );

		// And the hash still executes the stored document.
		$I->sendGet( 'graphql', [ 'queryId' => $normalized_hash ] );
		$I->seeResponseContainsJson( [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ] );
	}

	public function poisonedAliasCannotBeExecutedLaterTest( FunctionalTester $I ) {
		$I->wantTo( 'Prevent an anonymous request from storing a mutation under an alias a later request would execute' );

		$mutation = 'mutation KnownFrontendQuery { createUser(input: { username: "poison", email: "poison@example.test", password: "Poisoned-Pass-9384!", roles: ["administrator"] }) { user { databaseId } } }';

		$I->sendPost( 'graphql', json_encode( [
			'query'   => $mutation,
			'queryId' => 'known-frontend-query',
		] ) );
		$I->seeResponseContainsJson( [ 'errors' => [ [ 'message' => self::MISMATCH_MESSAGE ] ] ] );
		$I->dontSeePostInDatabase( [ 'post_type' => 'graphql_document' ] );
		$I->dontSeeTermInDatabase( [ 'name' => 'known-frontend-query' ] );

		$I->sendPost( 'graphql', json_encode( [ 'queryId' => 'known-frontend-query' ] ) );
		$I->seeResponseContainsJson( [ 'errors' => [ [ 'message' => 'PersistedQueryNotFound' ] ] ] );
		$I->dontSeeUserInDatabase( [ 'user_login' => 'poison' ] );
	}
}
