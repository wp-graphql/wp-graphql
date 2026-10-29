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
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'comment_author', '7' ), \WPGraphQL\Model\CommentAuthor::to_global_id( 7 ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'plugin', 'hello.php' ), \WPGraphQL\Model\Plugin::to_global_id( 'hello.php' ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'general' ), \WPGraphQL\Model\SettingGroup::to_global_id( 'general' ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'theme', 'twentytwentyone' ), \WPGraphQL\Model\Theme::to_global_id( 'twentytwentyone' ) );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'user_role', 'editor' ), \WPGraphQL\Model\UserRole::to_global_id( 'editor' ) );
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
	 * A subclass that sets its own type gets it from the helper and from the `id` field it inherits.
	 *
	 * @throws \Exception
	 */
	public function testSubclassWithItsOwnTypeUsesIt() {
		$post_id = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$model   = new class( get_post( $post_id ) ) extends \WPGraphQL\Model\Post {
			/**
			 * {@inheritDoc}
			 *
			 * @var string
			 */
			protected static $global_id_type = 'custom_post';
		};

		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'custom_post', (string) $post_id ), $model::to_global_id( $post_id ) );
		$this->assertSame( $model::to_global_id( $post_id ), $model->id );
		$this->assertSame( \GraphQLRelay\Relay::toGlobalId( 'post', (string) $post_id ), \WPGraphQL\Model\Post::to_global_id( $post_id ) );
	}

	/**
	 * Models without a global ID refuse to build one.
	 */
	public function testToGlobalIdThrowsForModelsWithoutAGlobalId() {
		$this->expectException( \LogicException::class );
		\WPGraphQL\Model\Avatar::to_global_id( 1 );
	}
}
