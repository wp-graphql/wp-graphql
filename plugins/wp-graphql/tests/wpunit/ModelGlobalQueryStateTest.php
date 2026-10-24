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
	 * Registers the fields the tests query.
	 *
	 * @var callable
	 */
	private $register_fields;

	/**
	 * How many times the custom model's setup() ran.
	 *
	 * @var int
	 */
	public static $custom_setups = 0;

	/**
	 * How many times the opted-in model's setup() ran.
	 *
	 * @var int
	 */
	public static $opted_setups = 0;

	/**
	 * The global a plugin sets when a post is set up, like WooCommerce's $product.
	 */
	public const ITEM_GLOBAL = 'model_global_query_state_test_item';

	/**
	 * Clear the cached schema before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->author_id = self::factory()->user->create( [ 'role' => 'author' ] );

		// Fields that report what WordPress considers the current object while they resolve.
		$this->register_fields = static function () {
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

			// Reports a global a plugin sets, like WooCommerce's $product.
			$current_item = [
				'type'    => 'String',
				'resolve' => static function () {
					return isset( $GLOBALS[ self::ITEM_GLOBAL ] ) ? (string) $GLOBALS[ self::ITEM_GLOBAL ] : 'none';
				},
			];
			register_graphql_field( 'RootQuery', 'currentItem', $current_item );
			register_graphql_field( 'Post', 'currentItem', $current_item );

			// A nested value that is not a model, like the list of blocks some plugins expose.
			register_graphql_object_type(
				'ModelGlobalQueryStateTestPart',
				[
					'fields' => [
						'currentState' => $current_state,
						'currentItem'  => $current_item,
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

			// Fields whose value is worked out in a Deferred callback, which runs after other
			// fields (here, the other posts in a list) have resolved: unbound and bound.
			register_graphql_field(
				'Post',
				'deferredState',
				[
					'type'    => 'String',
					'resolve' => static function () {
						return new \GraphQL\Deferred(
							static function () {
								return self::current_state();
							}
						);
					},
				]
			);
			register_graphql_field(
				'Post',
				'boundDeferredState',
				[
					'type'    => 'String',
					'resolve' => static function () {
						return new \GraphQL\Deferred(
							graphql_bind_node_context(
								static function () {
									return self::current_state();
								}
							)
						);
					},
				]
			);

			// A nested object resolved from the post itself, the way some plugins expose an "seo"
			// object whose source is the post.
			register_graphql_field(
				'Post',
				'self',
				[
					'type'    => 'Post',
					'resolve' => static function ( $post ) {
						return $post;
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

			// A field that loops over another post and leaves the globals pointing at it, the way
			// rendering a query loop block does (in a GraphQL request the main query has no post,
			// so wp_reset_postdata() doesn't put the node back).
			register_graphql_field(
				'Post',
				'loopOver',
				[
					'type'    => 'Int',
					'args'    => [ 'otherId' => [ 'type' => [ 'non_null' => 'Int' ] ] ],
					'resolve' => static function ( $post, $args ) {
						$query = new \WP_Query( [ 'p' => $args['otherId'] ] );
						while ( $query->have_posts() ) {
							$query->the_post();
						}
						wp_reset_postdata();

						return get_the_ID();
					},
				]
			);

			// A nested object backed by a model with a setup() of its own, like the SEO models
			// some plugins add.
			register_graphql_object_type(
				'ModelGlobalQueryStateTestCustom',
				[
					'fields' => [
						'first'  => [ 'type' => 'String' ],
						'second' => [ 'type' => 'String' ],
					],
				]
			);
			register_graphql_field(
				'Post',
				'custom',
				[
					'type'    => 'ModelGlobalQueryStateTestCustom',
					'resolve' => static function () {
						return new class() extends \WPGraphQL\Model\Model {
							/**
							 * Models some fixed data.
							 */
							public function __construct() {
								$this->data = [ 'source' => 'test' ];
								parent::__construct();
							}

							/**
							 * Counts each setup, instead of changing any globals.
							 */
							public function setup() {
								++ModelGlobalQueryStateTest::$custom_setups;
							}

							/**
							 * Two fields that report the current object.
							 */
							protected function init() {
								$this->fields = [
									'first'  => static function () {
										return ModelGlobalQueryStateTest::current_state();
									},
									'second' => static function () {
										return ModelGlobalQueryStateTest::current_state();
									},
								];
							}
						};
					},
				]
			);

			// A nested object backed by a model that opts in to node scope, the way a plugin's own
			// node type could. Its setup() sets the plugin global.
			register_graphql_object_type(
				'ModelGlobalQueryStateTestOpted',
				[
					'fields' => [
						'first'  => [ 'type' => 'String' ],
						'second' => [ 'type' => 'String' ],
						'parts'  => [
							'type'    => [ 'list_of' => 'ModelGlobalQueryStateTestPart' ],
							'resolve' => static function () {
								return [ [ 'part' => 1 ] ];
							},
						],
					],
				]
			);
			register_graphql_field(
				'Post',
				'opted',
				[
					'type'    => 'ModelGlobalQueryStateTestOpted',
					'resolve' => static function () {
						return new class() extends \WPGraphQL\Model\Model {
							/**
							 * Models some fixed data.
							 */
							public function __construct() {
								$this->data = [ 'source' => 'test' ];
								parent::__construct();
							}

							/**
							 * Opts in to node scope.
							 */
							public function scopes_setup_to_node(): bool {
								return true;
							}

							/**
							 * Points the plugin global at this object.
							 */
							public function setup() {
								++ModelGlobalQueryStateTest::$opted_setups;
								$GLOBALS[ ModelGlobalQueryStateTest::ITEM_GLOBAL ] = 'opted';
							}

							/**
							 * Two fields that report the plugin global.
							 */
							protected function init() {
								$this->fields = [
									'first'  => static function () {
										return $GLOBALS[ ModelGlobalQueryStateTest::ITEM_GLOBAL ] ?? 'none';
									},
									'second' => static function () {
										return $GLOBALS[ ModelGlobalQueryStateTest::ITEM_GLOBAL ] ?? 'none';
									},
								];
							}
						};
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
		};
		add_action( 'graphql_register_types', $this->register_fields );
	}

	/**
	 * Clear the cached schema after each test.
	 */
	public function tearDown(): void {
		remove_action( 'graphql_register_types', $this->register_fields );
		unset( $GLOBALS[ self::ITEM_GLOBAL ] );
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
	 * A field another plugin registers, selected before any of the node's own fields in a list,
	 * sees its own node: the shape reported in #1398, where it saw a different post.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/1398
	 */
	public function testFieldSelectedFirstInAListSeesItsOwnNode() {
		$post_ids = [ $this->create_post(), $this->create_post(), $this->create_post() ];

		$actual = $this->graphql(
			[
				'query'     => 'query ( $in: [ID] ) { posts( where: { in: $in } ) { nodes { currentState databaseId } } }',
				'variables' => [ 'in' => $post_ids ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 3, $actual['data']['posts']['nodes'] );

		foreach ( $actual['data']['posts']['nodes'] as $node ) {
			$this->assertSame( $this->post_state( $node['databaseId'] ), $node['currentState'] );
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
	 * A node is set up once for its part of the query, not around each of its fields, and not
	 * again for a nested object that resolves to the same model.
	 */
	public function testNodeIsSetUpOncePerPartOfTheQuery() {
		$post_id = $this->create_post();

		$main_query_parses = 0;
		$count_parses      = static function ( $query ) use ( &$main_query_parses ) {
			if ( $query === $GLOBALS['wp_query'] ) {
				++$main_query_parses;
			}
		};
		add_action( 'parse_query', $count_parses );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: Int! ) { modelPost( id: $id ) { title self { currentState self { currentState } } databaseId slug } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		remove_action( 'parse_query', $count_parses );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['modelPost']['self']['self']['currentState'] );
		$this->assertSame( 1, $main_query_parses );
	}

	/**
	 * When a field leaves the globals pointing at another post, the node's later fields, and the
	 * fields nested under them, still see the node.
	 */
	public function testNodeIsSetUpAgainAfterAFieldPointsTheGlobalsElsewhere() {
		$post_id  = $this->create_post();
		$other_id = $this->create_post();

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID!, $otherId: Int! ) { post( id: $id, idType: DATABASE_ID ) { loopOver( otherId: $otherId ) after: currentState parts { currentState } } }',
				'variables' => [
					'id'      => $post_id,
					'otherId' => $other_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Inside the looping field, the globals point at the other post...
		$this->assertSame( $other_id, $actual['data']['post']['loopOver'] );

		// ...and the fields after it see the node again.
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['after'] );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['parts'][0]['currentState'] );
	}

	/**
	 * A model with a setup() of its own is set up around each of its fields, as before, rather
	 * than once for its part of the query, and the node above it is in place around it.
	 */
	public function testModelWithItsOwnSetupIsSetUpAroundEachField() {
		$post_id             = $this->create_post();
		self::$custom_setups = 0;

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { custom { first second } after: currentState } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 2, self::$custom_setups );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['custom']['first'] );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['custom']['second'] );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['after'] );
	}

	/**
	 * A global a plugin sets when a post is set up (as WooCommerce sets $product on the_post) is
	 * restored when resolution leaves the post, once it's added to the restored globals.
	 */
	public function testFilteredGlobalIsRestoredWhenLeavingANode() {
		$post_id = $this->create_post();
		$query   = 'query ( $id: Int! ) { modelPost( id: $id ) { currentItem parts { currentItem } } currentItem }';

		$set_item = static function ( $post ) {
			$GLOBALS[ self::ITEM_GLOBAL ] = $post->ID;
		};
		add_action( 'the_post', $set_item );

		// Without the filter, the plugin global leaks out of the post's part of the query.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [ 'id' => $post_id ],
			]
		);
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( (string) $post_id, $actual['data']['currentItem'] );

		unset( $GLOBALS[ self::ITEM_GLOBAL ] );
		$add_global = static function ( $globals ) {
			$globals[] = self::ITEM_GLOBAL;
			return $globals;
		};
		add_filter( 'graphql_model_node_context_globals', $add_global );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [ 'id' => $post_id ],
			]
		);

		remove_filter( 'graphql_model_node_context_globals', $add_global );
		remove_action( 'the_post', $set_item );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Inside the post's part of the query the plugin global is the post...
		$this->assertSame( (string) $post_id, $actual['data']['modelPost']['currentItem'] );
		$this->assertSame( (string) $post_id, $actual['data']['modelPost']['parts'][0]['currentItem'] );

		// ...and outside it, it's back to what it was (unset), including after the request.
		$this->assertSame( 'none', $actual['data']['currentItem'] );
		$this->assertArrayNotHasKey( self::ITEM_GLOBAL, $GLOBALS );
	}

	/**
	 * A model that opts in to node scope is set up once for its part of the query, everything under
	 * it sees its context, and the node above gets its own context back afterwards.
	 */
	public function testModelThatOptsInIsSetUpOnceForItsPartOfTheQuery() {
		$post_id            = $this->create_post();
		self::$opted_setups = 0;

		$add_global = static function ( $globals ) {
			$globals[] = self::ITEM_GLOBAL;
			return $globals;
		};
		add_filter( 'graphql_model_node_context_globals', $add_global );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { opted { first parts { currentItem } second } after: currentItem currentState } }',
				'variables' => [ 'id' => $post_id ],
			]
		);

		remove_filter( 'graphql_model_node_context_globals', $add_global );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 1, self::$opted_setups );
		$this->assertSame( 'opted', $actual['data']['post']['opted']['first'] );
		$this->assertSame( 'opted', $actual['data']['post']['opted']['parts'][0]['currentItem'] );
		$this->assertSame( 'opted', $actual['data']['post']['opted']['second'] );
		$this->assertSame( 'none', $actual['data']['post']['after'] );
		$this->assertSame( $this->post_state( $post_id ), $actual['data']['post']['currentState'] );
	}

	/**
	 * A Deferred callback bound with graphql_bind_node_context() sees the node its field is
	 * selected under, though it runs after the other nodes in the list have resolved.
	 */
	public function testBoundDeferredCallbackSeesItsOwnNode() {
		$post_ids = [ $this->create_post(), $this->create_post(), $this->create_post() ];

		$actual = $this->graphql(
			[
				'query'     => 'query ( $in: [ID] ) { posts( where: { in: $in } ) { nodes { databaseId deferredState boundDeferredState } } }',
				'variables' => [ 'in' => $post_ids ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 3, $actual['data']['posts']['nodes'] );

		$unbound = [];
		foreach ( $actual['data']['posts']['nodes'] as $node ) {
			$this->assertSame( $this->post_state( $node['databaseId'] ), $node['boundDeferredState'] );
			$unbound[] = $node['deferredState'];
		}

		// Unbound, every callback runs with whichever node was set up last, so they can't all be
		// right. This guards that the test still exercises the deferred timing it's about.
		$this->assertCount( 1, array_unique( $unbound ) );
	}

	/**
	 * The filters a connection runs in its Deferred callback see the node the connection belongs
	 * to, not whichever node was set up last.
	 */
	public function testConnectionCallbacksSeeTheNodeTheyBelongTo() {
		$expected = [];
		foreach ( [ 'A', 'B', 'C' ] as $name ) {
			$category_id = self::factory()->category->create( [ 'name' => 'Category ' . $name ] );
			$post_id     = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_author'   => $this->author_id,
					'post_category' => [ $category_id ],
				]
			);

			$expected[ $post_id ] = 'Category ' . $name;
		}

		$seen         = [];
		$record_nodes = static function ( $nodes, $resolver ) use ( &$seen ) {
			if ( $resolver instanceof \WPGraphQL\Data\Connection\TermObjectConnectionResolver ) {
				$seen[ get_the_ID() ] = implode( ',', wp_list_pluck( array_map( static fn ( $node ) => get_term( $node->databaseId ), $nodes ), 'name' ) );
			}
			return $nodes;
		};
		add_filter( 'graphql_connection_nodes', $record_nodes, 10, 2 );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $in: [ID] ) { posts( where: { in: $in } ) { nodes { databaseId categories { nodes { name } } } } }',
				'variables' => [ 'in' => array_keys( $expected ) ],
			]
		);

		remove_filter( 'graphql_connection_nodes', $record_nodes, 10 );

		$this->assertArrayNotHasKey( 'errors', $actual );

		ksort( $expected );
		ksort( $seen );
		$this->assertSame( $expected, $seen );
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
	 * A GraphQL request made from a theme template, inside the loop of a page showing a post, leaves
	 * the loop as it was: the main query, its current post, and the global post data.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/1420
	 */
	public function testRequestInsideATemplateLoopLeavesTheLoopAsItWas() {
		$page_post_id = $this->create_post();
		$other_ids    = [ $this->create_post(), $this->create_post() ];

		// Render the post's page and enter the loop, the way a single post template does.
		$this->go_to( get_permalink( $page_post_id ) );
		$this->assertTrue( have_posts() );
		the_post();

		$loop_state = static function (): array {
			global $wp_query;

			return [
				'post'         => get_the_ID(),
				'queried'      => get_queried_object_id(),
				'singular'     => is_singular(),
				'in_the_loop'  => in_the_loop(),
				'current_post' => $wp_query->current_post,
				'query_post'   => $wp_query->post instanceof \WP_Post ? $wp_query->post->ID : null,
				'query_vars'   => $wp_query->query_vars,
				'title'        => get_the_title(),
			];
		};
		$before     = $loop_state();

		$actual = graphql(
			[
				'query'     => 'query ( $in: [ID] ) { posts( where: { in: $in } ) { nodes { databaseId title currentState parts { currentState } categories { nodes { name } } author { node { currentState } } } } }',
				'variables' => [ 'in' => $other_ids ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 2, $actual['data']['posts']['nodes'] );

		// The request saw its own nodes...
		foreach ( $actual['data']['posts']['nodes'] as $node ) {
			$this->assertSame( $this->post_state( $node['databaseId'] ), $node['currentState'] );
		}

		// ...and the template's loop is exactly where it was.
		$this->assertSame( $before, $loop_state() );
		$this->assertSame( $page_post_id, $before['post'] );
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

	/**
	 * Reading a Term model's fields from PHP restores the state afterwards.
	 */
	public function testTermModelRestoresTheGlobalStateAfterAField() {
		$term_id = self::factory()->category->create();
		$before  = $this->global_state();

		$model = new \WPGraphQL\Model\Term( get_term( $term_id, 'category' ) );
		$this->assertNotEmpty( $model->name );

		$this->assertSame( $before, $this->global_state() );
	}

	/**
	 * Reading a User model's fields from PHP restores the state afterwards.
	 */
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
