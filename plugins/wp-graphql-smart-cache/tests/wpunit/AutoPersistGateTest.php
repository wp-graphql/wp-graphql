<?php
/**
 * Automatic Persisted Queries: who may save a new document, and the bound on how
 * many anonymous ones are kept.
 *
 * The write path is reachable by anyone who can reach the endpoint, which is how
 * the protocol is meant to work. What was missing is a bound: garbage collection
 * is opt-in, defaults to off and prunes by age only, so anonymous traffic grew
 * `wp_posts` indefinitely.
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document;
use WPGraphQL\SmartCache\Document\Group;

class AutoPersistGateTest extends \Codeception\TestCase\WPTestCase {

	public function _before() {
		delete_option( 'graphql_persisted_queries_section' );
	}

	public function _after() {
		delete_option( 'graphql_persisted_queries_section' );
		wp_set_current_user( 0 );
		remove_all_filters( 'graphql_smart_cache_can_auto_persist_document' );
		remove_all_filters( 'graphql_smart_cache_automatic_document_limit' );
	}

	/**
	 * Run a query by id, the shape that triggers auto-persist.
	 *
	 * @param string $query The query string.
	 * @return \WP_Post|null The saved document, if one was saved.
	 */
	private function persist( string $query ) {
		$hash = Utils::generateHash( $query );
		graphql(
			[
				'queryId' => $hash,
				'query'   => $query,
			]
		);
		return get_page_by_path( $hash, OBJECT, Document::TYPE_NAME );
	}

	/**
	 * How many anonymous, ungrouped documents exist.
	 */
	private function anonymousCount(): int {
		$q = new \WP_Query(
			[
				'post_type'      => Document::TYPE_NAME,
				'post_status'    => 'publish',
				'author'         => 0,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);
		return count( $q->posts );
	}

	public function testAnyoneCanAutoPersistByDefault() {
		// The default must not change behavior for applications relying on it.
		wp_set_current_user( 0 );

		$this->assertNotNull( $this->persist( 'query defaultModeAnon { __typename }' ) );
	}

	public function testAuthenticatedModeBlocksLoggedOutVisitors() {
		add_option(
			'graphql_persisted_queries_section',
			[ 'apq_auto_persist_mode' => Document::AUTO_PERSIST_AUTHENTICATED ]
		);
		wp_set_current_user( 0 );

		$this->assertNull( $this->persist( 'query authedModeAnon { __typename }' ) );
	}

	public function testAuthenticatedModeAllowsLoggedInUsers() {
		add_option(
			'graphql_persisted_queries_section',
			[ 'apq_auto_persist_mode' => Document::AUTO_PERSIST_AUTHENTICATED ]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertNotNull( $this->persist( 'query authedModeUser { __typename }' ) );
	}

	public function testOffModeBlocksEveryone() {
		add_option(
			'graphql_persisted_queries_section',
			[ 'apq_auto_persist_mode' => Document::AUTO_PERSIST_OFF ]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertNull( $this->persist( 'query offModeAdmin { __typename }' ) );
	}

	/**
	 * Reading an already saved document must keep working whatever the mode is.
	 * Only saving a new one is gated.
	 */
	public function testOffModeStillExecutesAlreadySavedDocuments() {
		$query = 'query offModeExisting { __typename }';
		$hash  = Utils::generateHash( $query );

		// Saved while the default mode allows it.
		$this->assertNotNull( $this->persist( $query ) );

		add_option(
			'graphql_persisted_queries_section',
			[ 'apq_auto_persist_mode' => Document::AUTO_PERSIST_OFF ]
		);

		$result = graphql( [ 'queryId' => $hash ] );

		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertSame( 'RootQuery', $result['data']['__typename'] );
	}

	/**
	 * The filter is the seam for anything the setting cannot express, such as an
	 * API key belonging to a known first-party client.
	 */
	public function testFilterCanAllowWhatTheSettingBlocks() {
		add_option(
			'graphql_persisted_queries_section',
			[ 'apq_auto_persist_mode' => Document::AUTO_PERSIST_OFF ]
		);
		add_filter( 'graphql_smart_cache_can_auto_persist_document', '__return_true' );
		wp_set_current_user( 0 );

		$this->assertNotNull( $this->persist( 'query filterAllows { __typename }' ) );
	}

	public function testFilterCanBlockWhatTheSettingAllows() {
		add_filter( 'graphql_smart_cache_can_auto_persist_document', '__return_false' );
		wp_set_current_user( 0 );

		$this->assertNull( $this->persist( 'query filterBlocks { __typename }' ) );
	}

	/**
	 * The bound. Anonymous documents past the limit are removed oldest first, so
	 * the table cannot grow without end on a default install, where garbage
	 * collection is off.
	 */
	public function testAutomaticDocumentsAreBoundedByTheLimit() {
		add_filter(
			'graphql_smart_cache_automatic_document_limit',
			static function () {
				return 3;
			}
		);
		wp_set_current_user( 0 );

		$saved = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$query = sprintf( 'query boundedQuery%d { __typename }', $i );
			$this->persist( $query );
			$saved[ $i ] = Utils::generateHash( $query );
		}

		$this->assertLessThanOrEqual( 3, $this->anonymousCount() );

		// The newest survives, the oldest does not.
		$this->assertNotNull( get_page_by_path( $saved[6], OBJECT, Document::TYPE_NAME ) );
		$this->assertNull( get_page_by_path( $saved[1], OBJECT, Document::TYPE_NAME ) );
	}

	/**
	 * A document somebody filed in a Group is curated, so the limit must leave it
	 * alone even though it was saved anonymously.
	 */
	public function testGroupedDocumentsAreNeverRemovedByTheLimit() {
		wp_set_current_user( 0 );

		$keeper = $this->persist( 'query groupedKeeper { __typename }' );
		$this->assertNotNull( $keeper );
		wp_add_object_terms( $keeper->ID, 'keep-me', Group::TAXONOMY_NAME );

		add_filter(
			'graphql_smart_cache_automatic_document_limit',
			static function () {
				return 1;
			}
		);

		for ( $i = 1; $i <= 4; $i++ ) {
			$this->persist( sprintf( 'query groupedFiller%d { __typename }', $i ) );
		}

		$this->assertNotNull( get_post( $keeper->ID ) );
	}

	/**
	 * A document an administrator granted is curated, so the limit must leave it
	 * alone even though it arrived through the automatic path.
	 */
	public function testCuratedDocumentsSurviveTheLimit() {
		wp_set_current_user( 0 );

		$keeper = $this->persist( 'query grantedKeeper { __typename }' );
		$this->assertNotNull( $keeper );
		wp_add_object_terms( $keeper->ID, \WPGraphQL\SmartCache\Document\Grant::ALLOW, \WPGraphQL\SmartCache\Document\Grant::TAXONOMY_NAME );

		add_filter(
			'graphql_smart_cache_automatic_document_limit',
			static function () {
				return 1;
			}
		);

		for ( $i = 1; $i <= 4; $i++ ) {
			$this->persist( sprintf( 'query grantedFiller%d { __typename }', $i ) );
		}

		$this->assertNotNull( get_post( $keeper->ID ) );
	}
}
