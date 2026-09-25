<?php

namespace WPGraphQL\SmartCache;

use GraphQL\Server\RequestError;

/**
 * Registering a persisted query is a read-then-insert, so two requests carrying
 * the same brand-new query can both miss the lookup and both write a row.
 *
 * The invariant these tests hold to: a document is identified by the hash of the
 * query it holds. Concurrent registrations of an identical query converge on one
 * document, and a duplicate row that an earlier version already left behind must
 * never stop that query's id from resolving.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4355
 */
class DocumentConcurrentSaveTest extends \Codeception\TestCase\WPTestCase {

	const RAW_QUERY = '{ posts { nodes { title } } }';

	public function _before() {
		// Each test runs in a rolled-back transaction while the in-process object
		// cache survives, so flush it before querying documents.
		wp_cache_flush();

		$posts = get_posts( [ 'post_type' => Document::TYPE_NAME, 'post_status' => 'any', 'numberposts' => -1 ] );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
		wp_set_current_user( 0 );
	}

	private function normalized_query(): string {
		return \GraphQL\Language\Printer::doPrint( \GraphQL\Language\Parser::parse( self::RAW_QUERY ) );
	}

	private function normalized_hash(): string {
		return hash( 'sha256', $this->normalized_query() );
	}

	private function count_documents(): int {
		return count( get_posts( [ 'post_type' => Document::TYPE_NAME, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) );
	}

	/**
	 * Write a document row the way a concurrent request would have written it:
	 * straight to the table, so the plugin's own save hooks don't run. Terms are
	 * attached by the caller, mirroring the order Document::save() writes them in.
	 *
	 * @param string $slug           The slug the row is stored with.
	 * @param int    $seconds_newer  Seconds to add to the post date, to control which row the alias lookup returns first.
	 */
	private function insert_row_directly( string $slug, int $seconds_newer = 0 ): int {
		global $wpdb;

		$date = gmdate( 'Y-m-d H:i:s', time() + $seconds_newer );

		$wpdb->insert(
			$wpdb->posts,
			[
				'post_author'       => 0,
				'post_date'         => $date,
				'post_date_gmt'     => $date,
				'post_modified'     => $date,
				'post_modified_gmt' => $date,
				'post_content'      => $this->normalized_query(),
				'post_title'        => 'RaceProbe',
				'post_status'       => 'publish',
				'post_name'         => $slug,
				'post_type'         => Document::TYPE_NAME,
				'comment_status'    => 'closed',
				'ping_status'       => 'closed',
				'to_ping'           => '',
				'pinged'            => '',
				'post_excerpt'      => '',
				'post_content_filtered' => '',
			]
		);

		$post_id = (int) $wpdb->insert_id;
		clean_post_cache( $post_id );
		wp_cache_flush();

		return $post_id;
	}

	/**
	 * The breaking outcome from the report: the losing request's row was stored
	 * with the uniquified `<hash>-2` slug and still got the hash alias attached.
	 * Once the alias lookup returns that row, every later registration of the
	 * same query was refused, because the slug was used as the document's identity.
	 */
	public function testQueryIdResolvesWhenADuplicateRowHoldsTheAlias(): void {
		$hash     = $this->normalized_hash();
		$document = new Document();

		$first_id = $document->save( $hash, self::RAW_QUERY );

		// The row a racing request left behind: same query, uniquified slug, aliased, newer.
		$duplicate_id = $this->insert_row_directly( $hash . '-2', 1 );
		wp_add_object_terms( $duplicate_id, $hash, Document::ALIAS_TAXONOMY_NAME );

		// The alias lookup returns the newest match, which is the duplicate.
		$found = Utils::getPostByTermName( $hash, Document::TYPE_NAME, Document::ALIAS_TAXONOMY_NAME );
		$this->assertSame( $duplicate_id, $found->ID, 'Expected the duplicate row to be the row the alias lookup returns' );

		// Registering the same query again must still resolve, not throw.
		$resolved_id = $document->save( $hash, self::RAW_QUERY );
		$this->assertContains( $resolved_id, [ $first_id, $duplicate_id ] );
		$this->assertSame( $this->normalized_query(), $document->get( $hash ) );
	}

	/**
	 * A racing request has written its row but not yet attached the alias terms,
	 * so the alias lookup misses it. The second registration must end up on that
	 * one document instead of adding a second row.
	 */
	public function testConcurrentRegistrationConvergesOnOneDocument(): void {
		$hash     = $this->normalized_hash();
		$document = new Document();

		$other_request_id = $this->insert_row_directly( $hash );

		$post_id = $document->save( $hash, self::RAW_QUERY );

		$this->assertSame( 1, $this->count_documents(), 'Expected one document for one query' );
		$this->assertSame( $other_request_id, $post_id );
		$this->assertSame( $hash, get_post( $post_id )->post_name );
		$this->assertSame( $this->normalized_query(), $document->get( $hash ) );
	}

	/**
	 * Alias terms are shared between rows, so deleting a duplicate document must
	 * not take the surviving document's alias with it. This is the cleanup path a
	 * site owner takes to get rid of a duplicate by hand.
	 */
	public function testDeletingADuplicateKeepsTheAliasOnTheSurvivingDocument(): void {
		$hash     = $this->normalized_hash();
		$document = new Document();

		$first_id     = $document->save( $hash, self::RAW_QUERY );
		$duplicate_id = $this->insert_row_directly( $hash . '-2', 1 );
		wp_add_object_terms( $duplicate_id, $hash, Document::ALIAS_TAXONOMY_NAME );

		wp_delete_post( $duplicate_id, true );

		$this->assertSame( $this->normalized_query(), $document->get( $hash ), 'The surviving document must still resolve by its hash' );
		$this->assertSame( $first_id, $document->save( $hash, self::RAW_QUERY ) );
		$this->assertSame( 1, $this->count_documents() );
	}

	/**
	 * A document does not have to be stored under the hash as its slug to be the
	 * document for that hash. Documents stored by earlier versions carry a slug of
	 * their own with the hash as an alias, and a client registering that same query
	 * by its hash has to reach them.
	 */
	public function testQueryIdResolvesForADocumentStoredUnderItsOwnSlug(): void {
		global $wpdb;

		$hash     = $this->normalized_hash();
		$document = new Document();

		$post_id = wp_insert_post(
			[
				'post_type'    => Document::TYPE_NAME,
				'post_status'  => 'publish',
				'post_title'   => 'Homepage Posts',
				'post_content' => self::RAW_QUERY,
			],
			true
		);
		$this->assertIsInt( $post_id );

		// Put the row back the way a version that named documents by title stored it.
		$wpdb->update( $wpdb->posts, [ 'post_name' => 'homepage-posts' ], [ 'ID' => $post_id ] );
		clean_post_cache( $post_id );
		wp_cache_flush();

		$this->assertSame( 'homepage-posts', get_post( $post_id )->post_name );
		$this->assertSame( $this->normalized_query(), $document->get( $hash ), 'The document is expected to be aliased by its content hash' );

		$this->assertSame( $post_id, $document->save( $hash, self::RAW_QUERY ) );
		$this->assertSame( 1, $this->count_documents() );
	}

	/**
	 * The guard from the advisory stays in place: a queryId bound to a document
	 * holding a different query is still refused.
	 */
	public function testAliasBoundToADifferentQueryIsStillRefused(): void {
		$document = new Document();
		$post_id  = $document->save( $this->normalized_hash(), self::RAW_QUERY );
		wp_add_object_terms( $post_id, 'homepage-posts', Document::ALIAS_TAXONOMY_NAME );

		try {
			$document->save( 'homepage-posts', '{ __typename }' );
			$this->fail( 'Expected a RequestError when a different query is sent with an existing alias' );
		} catch ( RequestError $e ) {
			$this->assertStringContainsString( 'already been associated with another query', $e->getMessage() );
		}

		$this->assertSame( 1, $this->count_documents() );
	}
}
