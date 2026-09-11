<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document\MaxAge;

/**
 * A saved document's max-age can be set to 0, meaning "do not cache".
 *
 * Regression tests for the zero value being dropped: WordPress treats a scalar
 * "0" passed to wp_set_post_terms() as empty and removes the term, and the
 * header logic used truthy checks that ignored a stored "0".
 */
class MaxAgeZeroTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public $created_post_ids = [];

	public function setUp(): void {
		parent::setUp();

		\WPGraphQL::clear_schema();
		delete_option( 'graphql_cache_section' );

		$this->admin = self::factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
	}

	public function tearDown(): void {
		foreach ( $this->created_post_ids as $post_id ) {
			if ( $post_id > 0 ) {
				wp_delete_post( $post_id, true );
			}
		}
		delete_option( 'graphql_cache_section' );
		\WPGraphQL::clear_schema();

		parent::tearDown();
	}

	/**
	 * Save a persisted query and return its post id.
	 *
	 * @param string $alias The alias / query id to save the document under.
	 *
	 * @return int
	 */
	private function save_document( string $alias ): int {
		$document = new Document();
		$post_id  = $document->save( $alias, sprintf( 'query %s { posts { nodes { id title } } }', $alias ) );

		$this->created_post_ids[] = $post_id;

		return $post_id;
	}

	public function testZeroMaxAgeIsPersisted() {
		$post_id = $this->save_document( uniqid( 'query_posts_', false ) );

		$max_age = new MaxAge();
		$max_age->save( $post_id, '0' );

		$this->assertSame( '0', $max_age->get( $post_id ) );
		$this->assertSame( [ '0' ], wp_list_pluck( get_the_terms( $post_id, MaxAge::TAXONOMY_NAME ), 'name' ) );
	}

	public function testZeroMaxAgeSendsNoStoreHeader() {
		$alias   = uniqid( 'query_posts_', false );
		$post_id = $this->save_document( $alias );

		$max_age = new MaxAge();
		$max_age->save( $post_id, '0' );

		$request = [ 'params' => [ 'queryId' => $alias ] ];
		$max_age->peek_at_executing_query_cb( '', json_decode( json_encode( $request ) ) );

		$headers = $max_age->http_headers_cb( [] );

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	public function testZeroWinsAsTheMinimumInABatch() {
		$alias_zero = uniqid( 'query_posts_', false );
		$alias_ten  = uniqid( 'query_posts_', false );

		$max_age = new MaxAge();
		$max_age->save( $this->save_document( $alias_ten ), '10' );
		$max_age->save( $this->save_document( $alias_zero ), '0' );

		$request = [
			'params' => [
				[ 'queryId' => $alias_ten ],
				[ 'queryId' => $alias_zero ],
			],
		];
		$max_age->peek_at_executing_query_cb( '', json_decode( json_encode( $request ) ) );

		$headers = $max_age->http_headers_cb( [] );

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	public function testZeroGlobalMaxAgeSettingSendsNoStoreHeader() {
		// Settings are stored as strings; a global max-age of "0" must also mean no-store.
		update_option( 'graphql_cache_section', [ 'global_max_age' => '0' ] );

		$max_age = new MaxAge();
		$headers = $max_age->http_headers_cb( [] );

		$this->assertSame( 'no-store', $headers['Cache-Control'] );
	}

	public function testNonZeroMaxAgeStillSendsMaxAgeHeader() {
		$alias   = uniqid( 'query_posts_', false );
		$post_id = $this->save_document( $alias );

		$max_age = new MaxAge();
		$max_age->save( $post_id, '600' );

		$request = [ 'params' => [ 'queryId' => $alias ] ];
		$max_age->peek_at_executing_query_cb( '', json_decode( json_encode( $request ) ) );

		$headers = $max_age->http_headers_cb( [] );

		$this->assertSame( 'max-age=600, s-maxage=600, must-revalidate', $headers['Cache-Control'] );
	}

	public function testCreateDocumentMutationWithZeroMaxAgeHeader() {
		wp_set_current_user( $this->admin );

		$mutation = 'mutation CreateDocument($input: CreateGraphqlDocumentInput!) {
			createGraphqlDocument(input: $input) {
				graphqlDocument {
					databaseId
					maxAgeHeader
				}
			}
		}';

		$variables = [
			'input' => [
				'content'      => 'query max_age_zero { __typename }',
				'status'       => 'PUBLISH',
				'maxAgeHeader' => 0,
			],
		];

		$actual = do_graphql_request( $mutation, 'CreateDocument', $variables );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->created_post_ids[] = (int) $actual['data']['createGraphqlDocument']['graphqlDocument']['databaseId'];

		$this->assertSame( 0, $actual['data']['createGraphqlDocument']['graphqlDocument']['maxAgeHeader'] );
	}
}
