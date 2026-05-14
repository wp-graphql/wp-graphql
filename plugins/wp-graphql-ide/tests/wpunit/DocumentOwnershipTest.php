<?php
/**
 * Tests for the wpgraphql_ide_user_owns_document() access function.
 *
 * The helper is post-type agnostic by design — callers filter by post
 * type before asking. These tests therefore use the IDE's own
 * `graphql_ide_query` CPT but the behaviour is the same for any post.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE;

class DocumentOwnershipTest extends \Codeception\TestCase\WPTestCase {

	private $author_a;
	private $author_b;
	private $post_by_a;

	public function setUp(): void {
		parent::setUp();

		$this->author_a = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->author_b = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		$this->post_by_a = $this->factory()->post->create(
			[
				'post_type'    => 'graphql_ide_query',
				'post_status'  => 'draft',
				'post_author'  => $this->author_a,
				'post_content' => '{ posts { nodes { id } } }',
			]
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Positive cases
	// ---------------------------------------------------------------

	public function test_returns_true_when_current_user_is_author() {
		wp_set_current_user( $this->author_a );
		$this->assertTrue( wpgraphql_ide_user_owns_document( $this->post_by_a ) );
	}

	public function test_accepts_a_wp_post_instance() {
		wp_set_current_user( $this->author_a );
		$post = get_post( $this->post_by_a );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertTrue( wpgraphql_ide_user_owns_document( $post ) );
	}

	public function test_accepts_a_post_id() {
		wp_set_current_user( $this->author_a );
		$this->assertTrue( wpgraphql_ide_user_owns_document( $this->post_by_a ) );
	}

	// ---------------------------------------------------------------
	// Negative cases
	// ---------------------------------------------------------------

	public function test_returns_false_when_current_user_is_a_different_author() {
		wp_set_current_user( $this->author_b );
		$this->assertFalse( wpgraphql_ide_user_owns_document( $this->post_by_a ) );
	}

	public function test_returns_false_for_anonymous_visitors() {
		wp_set_current_user( 0 );
		$this->assertFalse( wpgraphql_ide_user_owns_document( $this->post_by_a ) );
	}

	public function test_returns_false_for_an_invalid_post_id() {
		wp_set_current_user( $this->author_a );
		$this->assertFalse(
			wpgraphql_ide_user_owns_document( 999999 ),
			'Non-existent post id should not be considered owned.'
		);
	}

	public function test_returns_false_for_null_input() {
		wp_set_current_user( $this->author_a );
		$this->assertFalse( wpgraphql_ide_user_owns_document( null ) );
	}

	// ---------------------------------------------------------------
	// Edge case the helper specifically guards
	// ---------------------------------------------------------------

	public function test_anonymous_user_does_not_own_a_zero_author_post() {
		// A post with post_author = 0 (system-authored / orphaned) should
		// NOT read as "owned by everyone" just because anonymous = 0.
		// This is exactly the guard `current_user_id > 0` in the helper.
		$orphan = $this->factory()->post->create(
			[
				'post_type'   => 'graphql_ide_query',
				'post_status' => 'draft',
				'post_author' => 0,
			]
		);

		wp_set_current_user( 0 );
		$this->assertFalse( wpgraphql_ide_user_owns_document( $orphan ) );
	}
}
