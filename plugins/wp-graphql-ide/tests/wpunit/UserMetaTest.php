<?php
/**
 * Coverage for `UserMeta` — per-user IDE preferences and personal
 * collections.
 *
 * The biggest risk surfaces here are silent ones:
 *   - A sanitization callback drifts and the IDE silently writes
 *     malformed prefs (or rejects valid ones). Users see "my settings
 *     keep resetting" with no error.
 *   - `sanitize_personal_collections` is the authorization boundary
 *     for which document IDs and which sharing targets are accepted.
 *     If the boundary regresses, a user could persist a reference to
 *     another user's document or share with users who can't use the
 *     IDE at all.
 *   - `purge_document_from_personal_collections` sweeps deleted docs.
 *     Without it, personal collections accumulate stale IDs that
 *     resolve to nothing on read.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE;

class UserMetaTest extends \Codeception\TestCase\WPTestCase {

	private int $admin_a;
	private int $admin_b;
	private int $subscriber;

	public function setUp(): void {
		parent::setUp();
		$this->admin_a    = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_b    = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Create a saved-query post for a given user. Each call uses a unique
	 * query body so Smart Cache's content-addressed alias doesn't reject
	 * the insert.
	 */
	private function create_doc( int $author ): int {
		static $n = 0;
		++$n;
		return $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_content' => sprintf( 'query Quser%d { posts { nodes { id } } }', $n ),
		] );
	}

	// ---------------------------------------------------------------
	// Theme sanitizer
	// ---------------------------------------------------------------

	/**
	 * @dataProvider provide_theme_round_trip_cases
	 */
	public function test_theme_sanitizer_round_trips_only_valid_values( $input, string $expected ) {
		// `register_meta`'s sanitize_callback runs on every update. Bad
		// values must not land in storage — empty string is the agreed
		// "default" sentinel that means "use the system theme."
		update_user_meta( $this->admin_a, 'wpgraphql_ide_theme', $input );
		$this->assertSame(
			$expected,
			get_user_meta( $this->admin_a, 'wpgraphql_ide_theme', true )
		);
	}

	public function provide_theme_round_trip_cases(): array {
		return [
			'empty stays empty'         => [ '', '' ],
			'light is allowed'          => [ 'light', 'light' ],
			'dark is allowed'           => [ 'dark', 'dark' ],
			'unknown drops to empty'    => [ 'midnight', '' ],
			'mixed case is rejected'    => [ 'Dark', '' ],
			'whitespace is not trimmed' => [ ' light', '' ],
		];
	}

	// ---------------------------------------------------------------
	// sanitize_personal_collections
	// ---------------------------------------------------------------

	public function test_personal_collections_filters_each_entry_to_a_known_shape() {
		wp_set_current_user( $this->admin_a );
		$doc = $this->create_doc( $this->admin_a );

		$result = \WPGraphQLIDE\UserMeta::sanitize_personal_collections( [
			[
				'id'           => 'pc_valid',
				'name'         => 'My collection',
				'document_ids' => [ $doc ],
				'shared_with'  => [],
				'unknown_key'  => 'ignored',
			],
		] );

		$this->assertCount( 1, $result );
		$this->assertSame( 'pc_valid', $result[0]['id'] );
		$this->assertSame( 'My collection', $result[0]['name'] );
		$this->assertSame( [ $doc ], $result[0]['document_ids'] );
		$this->assertSame( [], $result[0]['shared_with'] );
		$this->assertArrayNotHasKey(
			'unknown_key',
			$result[0],
			'Sanitizer must reject keys outside the known schema.'
		);
	}

	public function test_personal_collections_drops_entries_with_invalid_id_or_missing_name() {
		wp_set_current_user( $this->admin_a );

		$result = \WPGraphQLIDE\UserMeta::sanitize_personal_collections( [
			[ 'id' => '', 'name' => 'empty id' ],
			[ 'id' => 'has space', 'name' => 'bad chars' ],
			[ 'id' => str_repeat( 'a', 65 ), 'name' => 'too long' ],
			[ 'id' => 'pc_no_name', 'name' => '' ],
		] );

		$this->assertSame( [], $result );
	}

	public function test_personal_collections_caps_name_at_200_chars() {
		wp_set_current_user( $this->admin_a );

		$result = \WPGraphQLIDE\UserMeta::sanitize_personal_collections( [
			[
				'id'   => 'pc_longname',
				'name' => str_repeat( 'x', 500 ),
			],
		] );

		$this->assertCount( 1, $result );
		$this->assertSame( 200, strlen( $result[0]['name'] ) );
	}

	public function test_personal_collections_drops_document_ids_the_user_does_not_own() {
		// Authorization boundary: even if the client sends another
		// user's document id, the sanitizer must strip it. Without
		// this, a malicious client could pin references to docs they
		// can't read.
		wp_set_current_user( $this->admin_a );
		$mine   = $this->create_doc( $this->admin_a );
		$theirs = $this->create_doc( $this->admin_b );

		$result = \WPGraphQLIDE\UserMeta::sanitize_personal_collections( [
			[
				'id'           => 'pc_secboundary',
				'name'         => 'Mixed bag',
				'document_ids' => [ $mine, $theirs, 0, -1 ],
			],
		] );

		$this->assertSame( [ $mine ], $result[0]['document_ids'] );
	}

	public function test_personal_collections_drops_shared_with_targets_who_cannot_use_the_ide() {
		// Sharing with a subscriber is meaningless — they can't open
		// the IDE. Drop those entries instead of silently persisting
		// dead references.
		wp_set_current_user( $this->admin_a );

		$result = \WPGraphQLIDE\UserMeta::sanitize_personal_collections( [
			[
				'id'          => 'pc_share',
				'name'        => 'Shared',
				'shared_with' => [
					$this->admin_b,
					$this->subscriber,
					0,
					$this->admin_a, // owner can't share with themselves
				],
			],
		] );

		$this->assertSame( [ $this->admin_b ], $result[0]['shared_with'] );
	}

	public function test_personal_collections_dedupes_document_ids_and_shared_with_entries() {
		wp_set_current_user( $this->admin_a );
		$mine = $this->create_doc( $this->admin_a );

		$result = \WPGraphQLIDE\UserMeta::sanitize_personal_collections( [
			[
				'id'           => 'pc_dedup',
				'name'         => 'Dedup',
				'document_ids' => [ $mine, $mine ],
				'shared_with'  => [ $this->admin_b, $this->admin_b ],
			],
		] );

		$this->assertSame( [ $mine ], $result[0]['document_ids'] );
		$this->assertSame( [ $this->admin_b ], $result[0]['shared_with'] );
	}

	public function test_personal_collections_rejects_non_array_payloads() {
		$this->assertSame(
			[],
			\WPGraphQLIDE\UserMeta::sanitize_personal_collections( 'not-an-array' )
		);
		$this->assertSame(
			[],
			\WPGraphQLIDE\UserMeta::sanitize_personal_collections( null )
		);
	}

	// ---------------------------------------------------------------
	// aggregate_shared_collections
	// ---------------------------------------------------------------

	public function test_aggregate_shared_collections_surfaces_only_collections_shared_with_me() {
		// admin_a shares one collection with admin_b. admin_b also has
		// their own collection (not shared). When admin_b reads the
		// aggregate, they should see admin_a's shared entry only.
		$mine_a = $this->create_doc( $this->admin_a );

		wp_set_current_user( $this->admin_a );
		update_user_meta( $this->admin_a, 'wpgraphql_ide_personal_collections', [
			[
				'id'           => 'pc_shared_a',
				'name'         => 'A shares with B',
				'document_ids' => [ $mine_a ],
				'shared_with'  => [ $this->admin_b ],
			],
			[
				'id'           => 'pc_private_a',
				'name'         => 'A keeps private',
				'document_ids' => [],
				'shared_with'  => [],
			],
		] );

		wp_set_current_user( $this->admin_b );
		$shared = \WPGraphQLIDE\UserMeta::aggregate_shared_collections();

		$this->assertCount( 1, $shared );
		$this->assertSame( 'pc_shared_a', $shared[0]['id'] );
		$this->assertSame( $this->admin_a, $shared[0]['owner']['id'] );
		// Recipients never see the original ACL.
		$this->assertArrayNotHasKey( 'shared_with', $shared[0] );
	}

	public function test_aggregate_shared_collections_returns_empty_for_anonymous() {
		wp_set_current_user( 0 );
		$this->assertSame( [], \WPGraphQLIDE\UserMeta::aggregate_shared_collections() );
	}

	// ---------------------------------------------------------------
	// purge_document_from_personal_collections
	// ---------------------------------------------------------------

	/**
	 * Seed the owner's personal_collections meta via direct DB write so
	 * the registered `sanitize_callback` doesn't reshape (and possibly
	 * drop) our fixture. The "purge" tests below exercise the post-write
	 * sweep, not the sanitizer — and need a payload with arbitrary IDs
	 * (including stale ones) in storage to make their assertion possible.
	 */
	private function seed_personal_collections( int $owner, array $collections ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
			$owner,
			'wpgraphql_ide_personal_collections'
		) );
		$wpdb->insert( $wpdb->usermeta, [
			'user_id'    => $owner,
			'meta_key'   => 'wpgraphql_ide_personal_collections',
			'meta_value' => maybe_serialize( $collections ),
		] );
		wp_cache_delete( $owner, 'user_meta' );
	}

	public function test_purge_strips_deleted_document_id_from_owner_collections() {
		$mine  = $this->create_doc( $this->admin_a );
		$other = $this->create_doc( $this->admin_a );

		$this->seed_personal_collections( $this->admin_a, [
			[
				'id'           => 'pc_purge',
				'name'         => 'Purge me',
				'document_ids' => [ $mine, $other ],
				'shared_with'  => [],
			],
		] );

		$post = get_post( $mine );
		\WPGraphQLIDE\UserMeta::purge_document_from_personal_collections( $mine, $post );

		$after = get_user_meta( $this->admin_a, 'wpgraphql_ide_personal_collections', true );
		$this->assertSame( [ $other ], $after[0]['document_ids'] );
	}

	public function test_purge_is_a_noop_when_no_collection_references_the_id() {
		$mine = $this->create_doc( $this->admin_a );
		$this->seed_personal_collections( $this->admin_a, [
			[
				'id'           => 'pc_keep',
				'name'         => 'No match',
				'document_ids' => [ 9999 ],
				'shared_with'  => [],
			],
		] );

		$post = get_post( $mine );
		\WPGraphQLIDE\UserMeta::purge_document_from_personal_collections( $mine, $post );

		$after = get_user_meta( $this->admin_a, 'wpgraphql_ide_personal_collections', true );
		$this->assertSame( [ 9999 ], $after[0]['document_ids'] );
	}

	public function test_purge_ignores_non_graphql_document_post_types() {
		// `purge_document_from_personal_collections` runs from the
		// generic `before_delete_post` hook, which fires for every
		// post type. The guard against non-graphql_document types is
		// what stops it from scanning user_meta on every blog-post
		// delete.
		$regular_post_id = $this->factory()->post->create( [ 'post_type' => 'post' ] );
		$mine            = $this->create_doc( $this->admin_a );

		$this->seed_personal_collections( $this->admin_a, [
			[
				'id'           => 'pc_unrelated',
				'name'         => 'Should not be touched',
				'document_ids' => [ $mine ],
				'shared_with'  => [],
			],
		] );

		\WPGraphQLIDE\UserMeta::purge_document_from_personal_collections(
			$regular_post_id,
			get_post( $regular_post_id )
		);

		$after = get_user_meta( $this->admin_a, 'wpgraphql_ide_personal_collections', true );
		$this->assertSame( [ $mine ], $after[0]['document_ids'] );
	}
}
