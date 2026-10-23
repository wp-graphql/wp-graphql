<?php

/**
 * Regression tests for the global query state a Model sets up while resolving a field.
 *
 * Before each field callback, Model::setup() points the global $wp_query (and the global
 * $post) at the modeled object, so filters that run during resolution see it as the current
 * object. That state has to be put back afterwards. Post never restored it, and Term and User
 * restored only the global $post, so the modeled object's query vars, conditional flags and
 * queried object leaked into everything resolved later in the request.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4572
 */
class ModelGlobalQueryStateTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Clear the cached schema before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
	}

	/**
	 * Clear the cached schema after each test.
	 */
	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Describes what the global main query is currently serving.
	 *
	 * @return array<string,mixed>
	 */
	private function main_query_state(): array {
		global $post;

		return [
			'queried_object_id' => get_queried_object_id(),
			'is_singular'       => is_singular(),
			'is_category'       => is_category(),
			'is_author'         => is_author(),
			'query_vars'        => $GLOBALS['wp_query']->query_vars,
			'post'              => $post instanceof \WP_Post ? $post->ID : null,
		];
	}

	/**
	 * Widest surface: the reproduction from the issue. A field resolved after a Post model's
	 * fields must see the main query as it was, not as the Post model left it.
	 */
	public function testFieldResolvedAfterAPostSeesTheOriginalMainQuery() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_field(
					'RootQuery',
					'modelPost',
					[
						'type'    => 'Post',
						'args'    => [ 'id' => [ 'type' => [ 'non_null' => 'Int' ] ] ],
						'resolve' => static function ( $root, $args ) {
							return new \WPGraphQL\Model\Post( get_post( $args['id'] ) );
						},
					]
				);
				register_graphql_field(
					'RootQuery',
					'mainQueryState',
					[
						'type'    => 'String',
						'resolve' => static function () {
							return sprintf( 'queried_object_id=%d is_singular=%s', get_queried_object_id(), is_singular() ? 'true' : 'false' );
						},
					]
				);
			}
		);

		$expected = sprintf( 'queried_object_id=%d is_singular=%s', get_queried_object_id(), is_singular() ? 'true' : 'false' );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: Int! ) { modelPost( id: $id ) { title } mainQueryState }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['mainQueryState'] );
	}

	public function testPostModelRestoresTheGlobalQueryAfterEachField() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$before  = $this->main_query_state();

		$model = new \WPGraphQL\Model\Post( get_post( $post_id ) );
		$this->assertSame( $post_id, $model->databaseId );

		$this->assertSame( $before, $this->main_query_state() );
	}

	public function testTermModelRestoresTheGlobalQueryAfterEachField() {
		$term_id = self::factory()->category->create();
		$before  = $this->main_query_state();

		$model = new \WPGraphQL\Model\Term( get_term( $term_id, 'category' ) );
		$this->assertNotEmpty( $model->name );

		$this->assertSame( $before, $this->main_query_state() );
	}

	public function testUserModelRestoresTheGlobalQueryAfterEachField() {
		$user_id = self::factory()->user->create( [ 'role' => 'author' ] );

		// Resolve as an administrator so the user is visible regardless of their published posts.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->main_query_state();

		$model = new \WPGraphQL\Model\User( get_user_by( 'id', $user_id ) );
		$this->assertNotEmpty( $model->name );

		$this->assertSame( $before, $this->main_query_state() );
	}

	/**
	 * The modeled post is still the current post while its own fields resolve, which is what
	 * setup() is for.
	 */
	public function testPostIsCurrentWhileItsFieldsResolve() {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$add_field = static function ( $fields, $model_name ) {
			if ( 'PostObject' === $model_name ) {
				$fields['globalStateDuringResolution'] = static function () {
					return [ get_queried_object_id(), is_singular(), get_the_ID() ];
				};
			}
			return $fields;
		};
		add_filter( 'graphql_model_prepare_fields', $add_field, 10, 2 );

		$model = new \WPGraphQL\Model\Post( get_post( $post_id ) );
		$state = $model->globalStateDuringResolution;

		remove_filter( 'graphql_model_prepare_fields', $add_field, 10 );

		$this->assertSame( [ $post_id, true, $post_id ], $state );
	}
}
