<?php
/**
 * Automatic persisted queries must pass dynamic argument values as variables.
 *
 * A client that interpolates values into the query string (an id from the URL,
 * a slug) registers a new document for every value it sends, which once grew a
 * site to 72,000 stored queries. Such a query is refused with an error that
 * says why, instead of being stored.
 */

namespace WPGraphQL\SmartCache;

use GraphQL\Server\RequestError;
use WPGraphQL\SmartCache\Document;

class DocumentLiteralArgumentsTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
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

	public function literalArgumentProvider(): array {
		return [
			'interpolated id'      => [ '{ post(id: 123, idType: DATABASE_ID) { id title } }' ],
			'interpolated slug'    => [ '{ post(id: "hello-world", idType: SLUG) { id title } }' ],
			'nested in input'      => [ 'query ($status: PostStatusEnum) { posts(where: { authorName: "admin", status: $status }) { nodes { id } } }' ],
			'inside a list'        => [ '{ posts(where: { in: [ 1, 2 ] }) { nodes { id } } }' ],
		];
	}

	/**
	 * @dataProvider literalArgumentProvider
	 */
	public function testQueryWithLiteralArgumentsIsNotPersisted( string $query ) {
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
			$this->assertSame( Document::literalArgumentsMessage(), $e->getMessage() );
		}

		$this->assertNull( get_page_by_path( $hash, OBJECT, Document::TYPE_NAME ) );
	}

	public function argumentsAsVariablesProvider(): array {
		return [
			'variables'                 => [ 'query ($id: ID!) { post(id: $id, idType: DATABASE_ID) { id title } }', [ 'id' => 123 ] ],
			'enum and boolean literals' => [ '{ posts(where: { status: PUBLISH, hasPassword: false }) { nodes { id } } }' ],
			'variable default value'    => [ 'query ($first: Int = 10) { posts(first: $first) { nodes { id } } }' ],
			'no arguments'              => [ '{ posts { nodes { id } } }' ],
		];
	}

	/**
	 * @dataProvider argumentsAsVariablesProvider
	 */
	public function testQueryWithoutLiteralArgumentsIsPersisted( string $query, array $variables = [] ) {
		[ $response, $document ] = $this->persist( $query, $variables );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertNotNull( $document );
	}

	public function testOffModeStillExecutesQueryWithLiteralArguments() {
		// Nothing is stored when automatic persisting is off, so there is nothing to refuse.
		add_option(
			'graphql_persisted_queries_section',
			[ 'apq_auto_persist_mode' => Document::AUTO_PERSIST_OFF ]
		);

		[ $response, $document ] = $this->persist( '{ post(id: 123, idType: DATABASE_ID) { id } }' );

		$this->assertNull( $document );
		$this->assertArrayNotHasKey( 'errors', $response );
	}

	public function testAlreadyStoredQueryWithLiteralArgumentsStillExecutes() {
		// A document an authorized user stored deliberately keeps working by its hash.
		$query   = '{ post(id: 123, idType: DATABASE_ID) { id } }';
		$post_id = self::factory()->post->create(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_content' => $query,
			]
		);
		$this->assertNotEmpty( get_post( $post_id )->post_content );

		[ $response ] = $this->persist( $query );

		$this->assertArrayNotHasKey( 'errors', $response );
	}
}
