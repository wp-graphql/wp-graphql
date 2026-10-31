<?php
/**
 * Asserts that meta-change purges pass the changed meta key to `graphql_purge`
 * listeners as structured context, while keeping the event label stable.
 */

namespace WPGraphQL\SmartCache;

use GraphQLRelay\Relay;

class PurgeContextTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Purges captured from `graphql_purge`, as [ key, event, context ] tuples.
	 *
	 * @var array<int,array{0:string,1:string,2:mixed}>
	 */
	public $purges = [];

	public function setUp(): void {
		parent::setUp();
		$this->purges = [];
		add_action( 'graphql_purge', [ $this, 'capture_purge' ], 10, 4 );
	}

	public function tearDown(): void {
		remove_action( 'graphql_purge', [ $this, 'capture_purge' ], 10 );
		parent::tearDown();
	}

	public function capture_purge( $key, $event, $hostname, $context = null ) {
		$this->purges[] = [ $key, $event, $context ];
	}

	/**
	 * Returns the event and context of the purges fired for the given key.
	 */
	public function purges_for( $key ) {
		$matches = [];
		foreach ( $this->purges as $purge ) {
			if ( $key === $purge[0] ) {
				$matches[] = [ $purge[1], $purge[2] ];
			}
		}
		return $matches;
	}

	public function testUserMetaChangePassesMetaKeyAsContext() {
		$user_id = self::factory()->user->create();
		$this->purges = [];

		update_user_meta( $user_id, 'foo_data', 'bar' );

		$this->assertSame(
			[ [ 'user_meta_updated', [ 'meta_key' => 'foo_data' ] ] ],
			$this->purges_for( Relay::toGlobalId( 'user', $user_id ) )
		);
	}

	public function testTermMetaChangePassesMetaKeyAsContext() {
		$term_id = self::factory()->category->create();
		$this->purges = [];

		update_term_meta( $term_id, 'foo_data', 'bar' );

		$this->assertSame(
			[ [ 'term_updated', [ 'meta_key' => 'foo_data' ] ] ],
			$this->purges_for( Relay::toGlobalId( 'term', $term_id ) )
		);
	}

	public function testPostMetaChangePassesMetaKeyAsContextAndKeepsEventLabel() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->purges = [];

		update_post_meta( $post_id, 'foo_data', 'bar' );

		$this->assertSame(
			[ [ 'postmeta_changed (meta_key foo_data)', [ 'meta_key' => 'foo_data' ] ] ],
			$this->purges_for( Relay::toGlobalId( 'post', $post_id ) )
		);
	}

	public function testPurgeWithoutContextPassesEmptyArray() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$this->purges = [];

		wp_publish_post( $post_id );

		$this->assertNotEmpty( $this->purges );
		foreach ( $this->purges as $purge ) {
			$this->assertSame( [], $purge[2] );
		}
	}
}
