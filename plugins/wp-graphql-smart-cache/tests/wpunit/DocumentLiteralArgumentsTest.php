<?php
/**
 * Automatically saved queries with values written into their arguments.
 *
 * A client that interpolates values into the query string (an id from the URL,
 * a slug) registers a new document for every value it sends, which once grew a
 * site to 72,000 stored queries. The literal arguments setting decides what
 * happens to such a query: Off stores it as always, Warn stores it and names the
 * arguments in a debug message, Reject refuses it with an error that says why.
 */

namespace WPGraphQL\SmartCache;

use GraphQL\Server\RequestError;
use WPGraphQL\SmartCache\Document;

class DocumentLiteralArgumentsTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_persisted_queries_section' );
		add_filter( 'graphql_debug_enabled', '__return_true' );
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
		remove_filter( 'graphql_debug_enabled', '__return_true' );
	}

	/**
	 * Set the literal arguments mode, and optionally who may save queries automatically.
	 *
	 * @param string      $mode         One of the Document::LITERAL_ARGUMENTS_* modes.
	 * @param string|null $persist_mode One of the Document::AUTO_PERSIST_* modes, or null for the default.
	 */
	private function set_mode( string $mode, ?string $persist_mode = null ): void {
		$settings = [ 'apq_literal_arguments_mode' => $mode ];
		if ( null !== $persist_mode ) {
			$settings['apq_auto_persist_mode'] = $persist_mode;
		}
		update_option( 'graphql_persisted_queries_section', $settings );
	}

	/**
	 * Send a query with the hash of it as its queryId, the shape that registers it.
	 *
	 * @param string               $query     The query string.
	 * @param array<string,mixed>  $variables The variables to send with it.
	 *
	 * @return array{0: array<string,mixed>, 1: \WP_Post|null} The response, and the stored document if one was stored.
	 */
	private function persist( string $query, array $variables = [] ): array {
		$hash     = Utils::generateHash( $query );
		$response = graphql(
			[
				'queryId'   => $hash,
				'query'     => $query,
				'variables' => $variables,
			]
		);

		return [ $response, get_page_by_path( $hash, OBJECT, Document::TYPE_NAME ) ];
	}

	/**
	 * The literal arguments debug entries in a response.
	 *
	 * @param array<string,mixed> $response The GraphQL response.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function literal_warnings( array $response ): array {
		$debug = $response['extensions']['debug'] ?? [];

		return array_values(
			array_filter(
				$debug,
				static function ( $entry ) {
					return 'LITERAL_ARGUMENTS' === ( $entry['type'] ?? null );
				}
			)
		);
	}

	/**
	 * Queries with literal argument values, and the arguments that should be named.
	 */
	public function literalArgumentProvider(): array {
		return [
			'interpolated id'   => [ '{ post(id: 123, idType: DATABASE_ID) { id title } }', [ 'post.id' ] ],
			'interpolated slug' => [ '{ post(id: "hello-world", idType: SLUG) { id title } }', [ 'post.id' ] ],
			'nested in input'   => [ 'query ($status: PostStatusEnum) { posts(where: { authorName: "admin", status: $status }) { nodes { id } } }', [ 'posts.where' ] ],
			'inside a list'     => [ '{ posts(where: { in: [ 1, 2 ] }) { nodes { id } } }', [ 'posts.where' ] ],
			'constant limit'    => [ '{ posts(first: 10) { nodes { id } } }', [ 'posts.first' ] ],
			'several, deduped'  => [ '{ a: post(id: 1, idType: DATABASE_ID) { id } b: post(id: 2, idType: DATABASE_ID) { id } posts(first: 5) { nodes { id } } }', [ 'post.id', 'posts.first' ] ],
			'nested field'      => [ '{ posts { nodes { categories(first: 3) { nodes { id } } } } }', [ 'categories.first' ] ],
			'in a fragment'     => [ '{ posts { nodes { ...P } } } fragment P on Post { tags(first: 2) { nodes { id } } }', [ 'tags.first' ] ],
		];
	}

	/**
	 * Queries without string or number literals in their arguments.
	 */
	public function argumentsAsVariablesProvider(): array {
		return [
			'variables'                 => [ 'query ($id: ID!) { post(id: $id, idType: DATABASE_ID) { id title } }', [ 'id' => 123 ] ],
			'enum and boolean literals' => [ '{ posts(where: { status: PUBLISH, hasPassword: false }) { nodes { id } } }' ],
			'null literal'              => [ '{ posts(after: null) { nodes { id } } }' ],
			'variable default value'    => [ 'query ($first: Int = 10) { posts(first: $first) { nodes { id } } }' ],
			'no arguments'              => [ '{ posts { nodes { id } } }' ],
		];
	}

	/**
	 * @dataProvider literalArgumentProvider
	 */
	public function testOffByDefaultStoresQueryWithLiteralArguments( string $query ) {
		[ $response, $document ] = $this->persist( $query );

		$this->assertNotNull( $document, 'With no setting saved, a query with literal arguments is stored as before.' );
		$this->assertSame( [], $this->literal_warnings( $response ) );
	}

	/**
	 * @dataProvider literalArgumentProvider
	 */
	public function testWarnStoresQueryAndNamesTheArguments( string $query, array $arguments ) {
		$this->set_mode( Document::LITERAL_ARGUMENTS_WARN );

		[ $response, $document ] = $this->persist( $query );

		$this->assertNotNull( $document, 'Warn still stores the query.' );

		$warnings = $this->literal_warnings( $response );
		$this->assertCount( 1, $warnings );
		$this->assertSame( Document::literalArgumentsWarningMessage( $arguments ), $warnings[0]['message'] );
		$this->assertSame( $arguments, $warnings[0]['arguments'] );
		$this->assertSame( Utils::generateHash( $query ), $warnings[0]['queryId'] );
	}

	/**
	 * @dataProvider literalArgumentProvider
	 */
	public function testRejectRefusesQueryAndNamesTheArguments( string $query, array $arguments ) {
		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT );
		$hash = Utils::generateHash( $query );

		try {
			graphql(
				[
					'queryId' => $hash,
					'query'   => $query,
				]
			);
			$this->fail( 'Expected a RequestError for a query with literal argument values' );
		} catch ( RequestError $e ) {
			$this->assertSame( Document::literalArgumentsMessage( $arguments ), $e->getMessage() );
		}

		$this->assertNull( get_page_by_path( $hash, OBJECT, Document::TYPE_NAME ) );
	}

	/**
	 * @dataProvider argumentsAsVariablesProvider
	 */
	public function testRejectStoresQueryWithoutLiteralArguments( string $query, array $variables = [] ) {
		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT );

		[ $response, $document ] = $this->persist( $query, $variables );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertNotNull( $document );
	}

	/**
	 * @dataProvider argumentsAsVariablesProvider
	 */
	public function testWarnIsSilentForQueryWithoutLiteralArguments( string $query, array $variables = [] ) {
		$this->set_mode( Document::LITERAL_ARGUMENTS_WARN );

		[ $response, $document ] = $this->persist( $query, $variables );

		$this->assertNotNull( $document );
		$this->assertSame( [], $this->literal_warnings( $response ) );
	}

	public function testRejectNamesDirectiveArguments() {
		// No built-in directive takes a string or number, but the check runs before
		// validation, so a parseable query still shows how directive arguments are named.
		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT );

		try {
			$this->persist( '{ posts @skip(if: 1) { nodes { id } } }' );
			$this->fail( 'Expected a RequestError for a directive with a literal argument value' );
		} catch ( RequestError $e ) {
			$this->assertSame( Document::literalArgumentsMessage( [ '@skip.if' ] ), $e->getMessage() );
		}
	}

	public function testWarnDoesNotStoreQueryThatFailsValidation() {
		// The warning goes out before execution, and a query that fails validation is
		// never stored, which is why the message says "unless it fails validation".
		$this->set_mode( Document::LITERAL_ARGUMENTS_WARN );

		[ $response, $document ] = $this->persist( '{ post(id: 123, idType: DATABASE_ID) { notAField } }' );

		$this->assertArrayHasKey( 'errors', $response );
		$this->assertNull( $document );
		$this->assertCount( 1, $this->literal_warnings( $response ) );
	}

	public function testRejectDoesNothingWhenAutomaticSavingIsOff() {
		// Nothing is stored when automatic saving is off, so there is nothing to refuse.
		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT, Document::AUTO_PERSIST_OFF );

		[ $response, $document ] = $this->persist( '{ post(id: 123, idType: DATABASE_ID) { id } }' );

		$this->assertNull( $document );
		$this->assertArrayNotHasKey( 'errors', $response );
	}

	public function testWarnIsSilentWhenAutomaticSavingIsNotAllowedForTheRequest() {
		// Logged out, with saving limited to logged in users, the query is served but not stored.
		$this->set_mode( Document::LITERAL_ARGUMENTS_WARN, Document::AUTO_PERSIST_AUTHENTICATED );
		wp_set_current_user( 0 );

		[ $response, $document ] = $this->persist( '{ post(id: 123, idType: DATABASE_ID) { id } }' );

		$this->assertNull( $document );
		$this->assertSame( [], $this->literal_warnings( $response ) );
	}

	public function testRejectKeepsAlreadyStoredQueryWorking() {
		// A document stored before Reject was turned on keeps working by its hash.
		$query   = '{ post(id: 123, idType: DATABASE_ID) { id } }';
		$post_id = self::factory()->post->create(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_content' => $query,
			]
		);
		$this->assertNotEmpty( get_post( $post_id )->post_content );

		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT );

		[ $response ] = $this->persist( $query );

		$this->assertArrayNotHasKey( 'errors', $response );
	}

	/**
	 * Apollo-style clients hash the query text exactly as they wrote it. When that
	 * differs from the stored document only in formatting, the queryId misses the
	 * stored document's aliases, but the query is still the stored one, so saving it
	 * only adds the queryId as another alias.
	 *
	 * @dataProvider modesThatActProvider
	 */
	public function testStoredQueryIsNotCheckedWhenTheQueryIdIsAHashOfItsRawText( string $mode ) {
		$normalized = '{ post(id: 123, idType: DATABASE_ID) { id } }';
		$post_id    = self::factory()->post->create(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_content' => $normalized,
			]
		);

		$this->set_mode( $mode );

		$raw      = "{\n  post(id: 123, idType: DATABASE_ID) {\n    id\n  }\n}";
		$raw_hash = Utils::getHashFromFormattedString( $raw );
		$this->assertNotSame( Utils::generateHash( $raw ), $raw_hash, 'The raw text hash must differ from the stored hash for this to test anything.' );

		$response = graphql(
			[
				'queryId' => $raw_hash,
				'query'   => $raw,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertSame( [], $this->literal_warnings( $response ) );

		// No second document: the raw text hash became an alias of the stored one.
		$documents = get_posts(
			[
				'post_type'   => Document::TYPE_NAME,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);
		$this->assertSame( [ $post_id ], $documents );
		$this->assertTrue( has_term( $raw_hash, Document::ALIAS_TAXONOMY_NAME, $post_id ) );
	}

	public function modesThatActProvider(): array {
		return [
			'warn'   => [ Document::LITERAL_ARGUMENTS_WARN ],
			'reject' => [ Document::LITERAL_ARGUMENTS_REJECT ],
		];
	}

	/**
	 * A request can become allowed to save while it runs, for example a login
	 * mutation that logs the user in. It skipped the check up front, so under Reject
	 * the save afterwards must still be refused.
	 */
	public function testRejectDoesNotSaveWhenTheRequestBecomesAllowedWhileItRuns() {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		add_action(
			'graphql_register_types',
			static function () use ( $admin_id ) {
				register_graphql_field(
					'RootQuery',
					'testLogInDuringRequest',
					[
						'type'    => 'Boolean',
						'args'    => [
							'username' => [ 'type' => 'String' ],
						],
						'resolve' => static function () use ( $admin_id ) {
							wp_set_current_user( $admin_id );
							return true;
						},
					]
				);
			}
		);
		\WPGraphQL::clear_schema();

		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT, Document::AUTO_PERSIST_AUTHENTICATED );
		wp_set_current_user( 0 );

		[ $response, $document ] = $this->persist( '{ testLogInDuringRequest(username: "admin") }' );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertTrue( is_user_logged_in(), 'The resolver should have logged the user in, or this tests nothing.' );
		$this->assertNull( $document );

		\WPGraphQL::clear_schema();
	}

	public function testRejectChecksLoggedInUsersWhenOnlyTheyCanSave() {
		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT, Document::AUTO_PERSIST_AUTHENTICATED );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->expectException( RequestError::class );
		$this->persist( '{ post(id: 123, idType: DATABASE_ID) { id } }' );
	}

	/**
	 * Requests that only send a query, or only a queryId, aren't saving a query, so
	 * Reject doesn't apply to them.
	 */
	public function testRejectDoesNotAffectRequestsThatAreNotSavingAQuery() {
		$query = '{ post(id: 123, idType: DATABASE_ID) { id } }';
		$hash  = Utils::generateHash( $query );
		self::factory()->post->create(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_content' => $query,
			]
		);

		$this->set_mode( Document::LITERAL_ARGUMENTS_REJECT );

		$query_only = graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $query_only );

		$query_id_only = graphql( [ 'queryId' => $hash ] );
		$this->assertArrayNotHasKey( 'errors', $query_id_only );
		$this->assertArrayHasKey( 'post', $query_id_only['data'] );
	}

	public function testUnknownModeFallsBackToOff() {
		$this->set_mode( 'not-a-mode' );

		$this->assertSame( Document::LITERAL_ARGUMENTS_OFF, Document::literal_arguments_mode() );

		[ , $document ] = $this->persist( '{ post(id: 123, idType: DATABASE_ID) { id } }' );
		$this->assertNotNull( $document );
	}
}
