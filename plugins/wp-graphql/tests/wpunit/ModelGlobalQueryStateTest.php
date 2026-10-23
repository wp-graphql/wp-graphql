<?php

/**
 * Regression tests for the global state a node's model sets up while its part of a query resolves.
 *
 * Post, Term and User models point the global main query (and globals such as $post) at the
 * modeled object, so template tags and filters that run during resolution see it as the current
 * object. That context belongs to the node's part of the query: every field selected under the
 * node sees it, including fields other plugins register and fields of nested values that are not
 * models, and nothing outside the node does. Before, the state was set up whenever a model field
 * resolved and never restored, so it leaked into everything resolved later in the request.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4572
 */
class ModelGlobalQueryStateTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var int
	 */
	private $author_id;

	/**
	 * Clear the cached schema before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->author_id = self::factory()->user->create( [ 'role' => 'author' ] );

		// Fields that report what WordPress considers the current object while they resolve.
		add_action(
			'graphql_register_types',
			function () {
				$current_state = [
					'type'    => 'String',
					'resolve' => static function () {
						return self::current_state();
					},
				];

				register_graphql_field( 'RootQuery', 'currentState', $current_state );
				register_graphql_field( 'Post', 'currentState', $current_state );
				register_graphql_field( 'Category', 'currentState', $current_state );
				register_graphql_field( 'User', 'currentState', $current_state );

				// A nested value that is not a model, like the list of blocks some plugins expose.
				register_graphql_object_type(
					'ModelGlobalQueryStateTestPart',
					[
						'fields' => [
							'currentState' => $current_state,
						],
					]
				);
				register_graphql_field(
					'Post',
					'parts',
					[
						'type'    => [ 'list_of' => 'ModelGlobalQueryStateTestPart' ],
						'resolve' => static function () {
							return [ [ 'part' => 1 ], [ 'part' => 2 ] ];
						},
					]
				);

				// A root field that builds a Post model synchronously, as in the issue's reproduction
				// (the built-in post field resolves through a deferred loader, which hides the ordering).
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

				// A field that runs a whole GraphQL request of its own, then reports the state.
				register_graphql_field(
					'Post',
					'stateAfterNestedRequest',
					[
						'type'    => 'String',
						'args'    => [ 'otherId' => [ 'type' => [ 'non_null' => 'Int' ] ] ],
						'resolve' => static function ( $post, $args ) {
							graphql(
								[
									'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { title currentState } }',
									'variables' => [ 'id' => $args['otherId'] ],
								]
							);

							return self::current_state();
						},
					]
				);
			}
		);
	}

	/**
	 * Clear the cached schema after each test.
	 */
	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Describes what WordPress considers the current object.
	 */
	public static function current_state(): string {
		global $post, $authordata;

		return sprintf(
			'post=%s queried=%d singular=%s category=%s author=%s authordata=%s',
			$post instanceof \WP_Post ? $post->ID : 'none',
			get_queried_object_id(),
			is_singular() ? 'yes' : 'no',
			is_category() ? 'yes' : 'no',
			is_author() ? 'yes' : 'no',
			$authordata instanceof \WP_User ? $authordata->ID : 'none'
		);
	}

	/**
	 * The state a post node's fields should see.
	 *
	 * @param int $post_id The post's ID.
	 */
	private function post_state( int $post_id ): string {
		return sprintf( 'post=%1$d queried=%1$d singular=yes category=no author=no authordata=%2$d', $post_id, $this->author_id );
	}

	/**
	 * Describes the global state in more detail than current_state(), for direct model access.
	 *
	 * @return array<string,mixed>
	 */
	private function global_state(): array {
		global $post, $authordata;

		return [
			'queried_object_id' => get_queried_object_id(),
			'is_singular'       => is_singular(),
			'is_category'       => is_category(),
			'is_author'         => is_author(),
			'query_vars'        => $GLOBALS['wp_query']->query_vars,
			'post'              => $post instanceof \WP_Post ? $post->ID : null,
			'authordata'        => $authordata instanceof \WP_User ? $authordata->ID : null,
		];
	}

	/**
	 * Creates a published post by the test author.
	 */
	private function create_post(): int {
		return self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			]
		);
	}

	/**
	 * The reproduction from the issue: a field resolved after a post's part of the query must see
	 * the state the request started with, not the post.
	 */
	public function testFieldResolvedAfterAPostSeesTheOriginalState() {
		$post_id  = $this->create_post();
		$expected = self::current_state();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: Int! ) { modelPost( id: $id ) { title } currentState }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['currentState'] );
	}

	/**
	 * A field another plugin registers on Post sees the post as the current object, whether or not
	 * a model field was selected before it.
	 */
	public function testFieldRegisteredOnPostSeesThePost() {
		$post_id = $this->create_post();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { currentState title } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['currentState'] );
	}

	/**
	 * Fields of nested values that are not models (like blocks) see the post they belong to.
	 */
	public function testFieldsNestedUnderAPostSeeThePost() {
		$post_id = $this->create_post();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { parts { currentState } } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame(
			[
				[ 'currentState' => $this->post_state( $post_id ) ],
				[ 'currentState' => $this->post_state( $post_id ) ],
			],
			$actual['data']['post']['parts']
		);
	}

	/**
	 * In a list, each node's fields see that node, not the one resolved before it.
	 */
	public function testEachNodeInAListSeesItself() {
		$post_ids = [ $this->create_post(), $this->create_post(), $this->create_post() ];

		$actual = $this->graphql(
			[
				'query'     => 'query ( $in: [ID] ) { posts( where: { in: $in, orderby: { field: DATE, order: ASC } } ) { nodes { databaseId currentState parts { currentState } } } }',
				'variables' => [ 'in' => $post_ids ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 3, $actual['data']['posts']['nodes'] );

		foreach ( $actual['data']['posts']['nodes'] as $node ) {
			$expected = $this->post_state( $node['databaseId'] );

			$this->assertSame( $expected, $node['currentState'] );
			$this->assertSame( $expected, $node['parts'][0]['currentState'] );
		}
	}

	/**
	 * A node nested under another sees itself, and the outer node's later fields see the outer
	 * node again.
	 */
	public function testNestedNodeSeesItselfAndTheOuterNodeIsRestoredAfter() {
		$post_id = $this->create_post();

		// Resolve as an administrator so the author is visible.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { author { node { currentState } } after: currentState } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$author_state = $actual['data']['post']['author']['node']['currentState'];
		$this->assertStringContainsString( sprintf( 'queried=%d', $this->author_id ), $author_state );
		$this->assertStringContainsString( 'author=yes', $author_state );
		$this->assertStringContainsString( sprintf( 'authordata=%d', $this->author_id ), $author_state );

		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['after'] );
	}

	/**
	 * Term nodes are set up as the current object for their part of the query too.
	 */
	public function testFieldRegisteredOnACategorySeesTheCategory() {
		$term_id = self::factory()->category->create();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { category( id: $id, idType: DATABASE_ID ) { currentState } currentState }',
				'variables' => [ 'id' => $term_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertStringContainsString( sprintf( 'queried=%d', $term_id ), $actual['data']['category']['currentState'] );
		$this->assertStringContainsString( 'category=yes', $actual['data']['category']['currentState'] );
		$this->assertStringContainsString( 'category=no', $actual['data']['currentState'] );
	}

	/**
	 * A GraphQL request run from inside a resolver leaves the outer node's context in place.
	 */
	public function testNestedRequestLeavesTheOuterNodeInPlace() {
		$post_id  = $this->create_post();
		$other_id = $this->create_post();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID!, $otherId: Int! ) { post( id: $id, idType: DATABASE_ID ) { stateAfterNestedRequest( otherId: $otherId ) } }',
				'variables' => [
					'id'      => $post_id,
					'otherId' => $other_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['stateAfterNestedRequest'] );
	}

	/**
	 * The global state after a request is what it was before it.
	 */
	public function testRequestLeavesTheGlobalStateAsItFoundIt() {
		$post_id = $this->create_post();
		$before  = $this->global_state();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: Int! ) { modelPost( id: $id ) { title currentState parts { currentState } } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $before, $this->global_state() );
	}

	/**
	 * Reading a Post model's fields from PHP restores the state afterwards, including fields whose
	 * resolvers read other fields of the same model (id reads databaseId).
	 */
	public function testPostModelRestoresTheGlobalStateAfterAField() {
		$post_id = $this->create_post();
		$before  = $this->global_state();

		$model = new \WPGraphQL\Model\Post( get_post( $post_id ) );
		$this->assertNotEmpty( $model->id );
		$this->assertSame( $post_id, $model->databaseId );

		$this->assertSame( $before, $this->global_state() );
	}

	public function testTermModelRestoresTheGlobalStateAfterAField() {
		$term_id = self::factory()->category->create();
		$before  = $this->global_state();

		$model = new \WPGraphQL\Model\Term( get_term( $term_id, 'category' ) );
		$this->assertNotEmpty( $model->name );

		$this->assertSame( $before, $this->global_state() );
	}

	public function testUserModelRestoresTheGlobalStateAfterAField() {
		// Resolve as an administrator so the user is visible regardless of their published posts.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before = $this->global_state();

		$model = new \WPGraphQL\Model\User( get_user_by( 'id', $this->author_id ) );
		$this->assertNotEmpty( $model->name );

		$this->assertSame( $before, $this->global_state() );
	}

	/**
	 * The modeled post is the current post while its own fields resolve from PHP, which is what
	 * setup() is for.
	 */
	public function testPostIsCurrentWhileItsFieldsResolveFromPhp() {
		$post_id = $this->create_post();

		$add_field = static function ( $fields, $model_name ) {
			if ( 'PostObject' === $model_name ) {
				$fields['currentState'] = static function () {
					return self::current_state();
				};
			}
			return $fields;
		};
		add_filter( 'graphql_model_prepare_fields', $add_field, 10, 2 );

		$model = new \WPGraphQL\Model\Post( get_post( $post_id ) );
		$state = $model->currentState;

		remove_filter( 'graphql_model_prepare_fields', $add_field, 10 );

		$this->assertSame( $this->post_state( $post_id ), $state );
	}
}
