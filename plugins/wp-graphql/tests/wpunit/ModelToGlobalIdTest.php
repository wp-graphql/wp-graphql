<?php

/**
 * Tests the static `to_global_id()` helper on the Model layer.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/842
 */
class ModelToGlobalIdTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Each model encodes the type its `id` field uses.
	 */
	public function testToGlobalIdEncodesTheModelType() {
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'post', '1' ), \WPGraphQL\Model\Post::to_global_id( 1 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'term', '2' ), \WPGraphQL\Model\Term::to_global_id( 2 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'term', '3' ), \WPGraphQL\Model\Menu::to_global_id( 3 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'post', '4' ), \WPGraphQL\Model\MenuItem::to_global_id( 4 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'user', '5' ), \WPGraphQL\Model\User::to_global_id( 5 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'comment', '6' ), \WPGraphQL\Model\Comment::to_global_id( 6 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'post_type', 'page' ), \WPGraphQL\Model\PostType::to_global_id( 'page' ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'taxonomy', 'category' ), \WPGraphQL\Model\Taxonomy::to_global_id( 'category' ) );
	}

	/**
	 * The helper returns the same ID the model resolves for its `id` field.
	 *
	 * @throws \Exception
	 */
	public function testToGlobalIdMatchesTheResolvedIdField() {
		$post_id = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = $this->factory()->term->create( [ 'taxonomy' => 'category' ] );
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->assertSame( ( new \WPGraphQL\Model\Post( get_post( $post_id ) ) )->id, \WPGraphQL\Model\Post::to_global_id( $post_id ) );
		$this->assertSame( ( new \WPGraphQL\Model\Term( get_term( $term_id ) ) )->id, \WPGraphQL\Model\Term::to_global_id( $term_id ) );
		$this->assertSame( ( new \WPGraphQL\Model\User( get_user_by( 'id', $user_id ) ) )->id, \WPGraphQL\Model\User::to_global_id( $user_id ) );
	}

	/**
	 * Models without a global ID refuse to build one.
	 */
	public function testToGlobalIdThrowsForModelsWithoutAGlobalId() {
		$this->expectException( \LogicException::class );
		\WPGraphQL\Model\Avatar::to_global_id( 1 );
	}
}
