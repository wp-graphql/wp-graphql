<?php

use WPGraphQL\Utils\QueryAnalyzer;

class QueryAnalyzerTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $post_id;

	public function _setUp(): void {
		parent::_setUp();

		$this->post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'test post',
			]
		);

		WPGraphQL::clear_schema();
	}

	public function _tearDown(): void {
		wp_delete_post( $this->post_id, true );

		parent::_tearDown();
	}

	/**
	 * Sets the query analyzer setting. If null is passed, the setting is unset.
	 */
	protected function toggle_query_analyzer( ?bool $toggle ): void {
		$settings = get_option( 'graphql_general_settings', [] );

		// Unset the setting if null is passed
		if ( null === $toggle ) {
			unset( $settings['query_analyzer_enabled'] );
			update_option( 'graphql_general_settings', $settings );
			return;
		}

		$settings['query_analyzer_enabled'] = $toggle === true ? 'on' : 'off';

		update_option( 'graphql_general_settings', $settings );
	}

	public function testEnableQueryAnalyzer(): void {
		// Unset GraphQL Debugging.
		add_filter( 'graphql_debug_enabled', '__return_false' );

		$query = '{ posts { nodes { id, title } } }';

		/** Test with Query Analyzer toggled on */
		$this->toggle_query_analyzer( true );
		$actual = QueryAnalyzer::is_enabled();
		$this->assertTrue( $actual, 'Query Analyzer should be enabled when turned "on"' );

		$request         = graphql( [ 'query' => $query ], true );
		$actual_response = $request->execute();
		$actual_analyzer = $request->get_query_analyzer();

		$this->assertTrue( $actual_analyzer->is_enabled(), 'Query Analyzer should be enabled when turned "on"' );
		$this->assertTrue( $actual_analyzer->is_enabled_for_query(), 'Query Analyzer should be enabled for query when turned "on"' );
		$this->assertArrayNotHasKey( 'queryAnalyzer', $actual_response['extensions'], 'There should be no extension output if GraphQL debugging is disabled ' );

		/** Test with Query Analyzer toggled off */
		$this->toggle_query_analyzer( false );
		$actual = QueryAnalyzer::is_enabled();
		$this->assertFalse( $actual, 'Query Analyzer should be disabled when turned "off".' );

		$request         = graphql( [ 'query' => $query ], true );
		$actual_response = $request->execute();
		$actual_analyzer = $request->get_query_analyzer();

		$this->assertFalse( $actual_analyzer->is_enabled(), 'Query Analyzer should be disabled when turned "off"' );
		$this->assertFalse( $actual_analyzer->is_enabled_for_query(), 'Query Analyzer should be disabled for query when turned "off"' );
		$this->assertArrayNotHasKey( 'queryAnalyzer', $actual_response['extensions'], 'There should be no extension output if GraphQL debugging is disabled ' );

		/** Test with GraphQL Debugging Enabled */
		add_filter( 'graphql_debug_enabled', '__return_true' );
		$actual = QueryAnalyzer::is_enabled();
		$this->assertTrue( $actual, 'Query Analyzer should be enabled when the "graphql_debug_enabled" filter is set to true.' );

		$request         = graphql( [ 'query' => $query ], true );
		$actual_response = $request->execute();
		$actual_analyzer = $request->get_query_analyzer();

		$this->assertTrue( $actual_analyzer->is_enabled(), 'Query Analyzer should be enabled when GraphQL Debugging is on' );
		$this->assertTrue( $actual_analyzer->is_enabled_for_query(), 'Query Analyzer should be enabled for query when GraphQL Debugging is on' );
		$this->assertNotEmpty( $actual_response['extensions']['queryAnalyzer'], 'There should be extension output if GraphQL debugging and Query Analyzer are enabled.' );

		// Clean up
		$this->toggle_query_analyzer( null );
		remove_filter( 'graphql_debug_enabled', '__return_true' );
		remove_filter( 'graphql_debug_enabled', '__return_false' );
	}

	public function testListTypes() {

		$query = '{ posts { nodes { id, title } } }';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		// before execution, this should be null
		$this->assertEmpty( $request->get_query_analyzer()->get_list_types() );

		// execute the query
		$request->execute();

		// get the list types that were generated during execution
		$list_types = $request->get_query_analyzer()->get_list_types();

		codecept_debug( $list_types );

		// Assert the expected list types are returned
		$this->assertEqualSets( [ 'list:post' ], $list_types );
	}

	public function testListModels() {
		$query = '{ posts { nodes { id, title } } }';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		// before execution, this should be null (no nodes have loaded)
		$this->assertEmpty( $request->get_query_analyzer()->get_runtime_nodes() );

		// execute the request
		$request->execute();

		$nodes = $request->get_query_analyzer()->get_runtime_nodes();

		$node_id = \GraphQLRelay\Relay::toGlobalId( 'post', $this->post_id );

		$this->assertEqualSets( [ $node_id ], $nodes );
	}

	public function testQueryTypes() {

		$query = '{ posts { nodes { id, title } } }';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		// before execution, this should be null
		$this->assertEmpty( $request->get_query_analyzer()->get_query_types() );

		// Execute the request
		$request->execute();

		$types = $request->get_query_analyzer()->get_query_types();
		$this->assertEqualSets( [ 'rootquery', 'rootquerytopostconnection', 'post' ], $types );
	}

	public function testQueryForListOfNonNodeInterfaceTypesDoesntAddKeys() {

		// types that do not implement the "Node" interface shouldn't be tracked as keys
		// in the Query Analyzer

		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_interface_type(
					'TestInterface',
					[
						'eagerlyLoadType' => true,
						'fields'          => [
							'test' => [
								'type' => 'String',
							],
						],
						'resolveType'     => static function () {
							return 'TestType';
						},
					]
				);

				register_graphql_object_type(
					'TestType',
					[
						'eagerlyLoadType' => true,
						'interfaces'      => [ 'TestInterface' ],
						'fields'          => [
							'test' => [
								'type' => 'String',
							],
						],
					]
				);

				register_graphql_field(
					'Post',
					'testField',
					[
						'type'    => [ 'list_of' => 'TestInterface' ],
						'resolve' => static function () {
							return [
								[
									'test' => 'value',
								],
								[
									'test' => 'value',
								],
							];
						},
					]
				);
			}
		);

		$query = '
		{
			posts {
				nodes {
					testField {
						test
					}
				}
			}
		}
		';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		$request->execute();

		$list_types = $request->get_query_analyzer()->get_list_types();

		codecept_debug( $list_types );
		$keys_array = $list_types;
		codecept_debug( $list_types );

		$this->assertNotContains( 'list:testinterface', $list_types );
		$this->assertNotContains( 'list:testtype', $list_types );
		$this->assertContains( 'list:post', $list_types );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2711
	 * @return void
	 */
	public function testQueryOneToOneConnectionNodesNotShownInListTypes() {

		$query = '
		{
			tags {
				edges {
					node {
						id
					}
				}
			}
			posts {
				nodes {
					id
					title
					author {
						node {
							name
						}
					}
					featuredImage {
						node {
							sourceUrl
						}
					}
				}
			}
		}
		';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		// before execution, this should be null
		$this->assertEmpty( $request->get_query_analyzer()->get_list_types() );

		// Execute the request
		$request->execute();

		$types = $request->get_query_analyzer()->get_list_types();
		codecept_debug( $types );
		$this->assertContains( 'list:post', $types );
		$this->assertContains( 'list:tag', $types );

		// the author and media item were queried as part of one-to-one
		// connections and should not be output as lists
		$this->assertNotContains( 'list:mediaitem', $types );
		$this->assertNotContains( 'list:user', $types );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2711
	 * @return void
	 */
	public function testQueryContentNodesReturnsListOfDifferentTypes() {

		$query = '
		{
			contentNodes {
				nodes {
					id
					title
					author {
						node {
							name
						}
					}
				}
			}
		}
		';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		// before execution, this should be null
		$this->assertEmpty( $request->get_query_analyzer()->get_list_types() );

		// Execute the request
		$request->execute();

		$types = $request->get_query_analyzer()->get_list_types();
		codecept_debug( $types );

		// querying for a list of content nodes means that
		// any Type that can be a content node needs to be tagged
		// as it could impact the cache invalidation if a new type
		// of any of the possible types is published
		$this->assertContains( 'list:post', $types );
		$this->assertContains( 'list:page', $types );
		$this->assertContains( 'list:mediaitem', $types );

		// the author was queried as part of one-to-one
		// connection and should not be output as list:user
		$this->assertNotContains( 'list:user', $types );
	}

	public function testNestedConnectionDoesNotShowInListTypes() {

		$query = '
		query getCategory( $id: ID! $postId: ID! ) {
			category( id: $id idType: DATABASE_ID ) {
				name
				posts {
					nodes {
						 id
						 title
					}
				}
			}
			post (id: $postId idType: DATABASE_ID ) {
				title
				categories {
					nodes {
						id
						name
					}
				}
			}
			pages {
				nodes {
					id
					title
				}
			}
		}
		';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		// before execution, this should be null
		$this->assertEmpty( $request->get_query_analyzer()->get_list_types() );

		// Execute the request
		$request->execute();

		$types = $request->get_query_analyzer()->get_list_types();
		codecept_debug( $types );

		// this query queried for a list of posts and categories as nested connections
		// so they shouldn't be tracked, only the root list (for list:page) should be tracked
		$this->assertNotContains( 'list:post', $types );
		$this->assertNotContains( 'list:category', $types );
		$this->assertContains( 'list:page', $types );

		$this->assertSame( [ 'list:page' ], $types );
	}

	public function testNonNullListOfNonNullPostMapsToListOfPosts() {

		register_graphql_field(
			'RootQuery',
			'listOfThing',
			[
				'type' => [
					'non_null' => [
						'list_of' => [
							'non_null' => 'Post',
						],
					],
				],
			]
		);

		$query = '
		{
			listOfThing {
				__typename
			}
		}
		';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		$request->execute();

		$types = $request->get_query_analyzer()->get_list_types();

		$this->assertContains( 'list:post', $types );
	}

	public function testListOfNonNullPostMapsToListOfPosts() {

		register_graphql_field(
			'RootQuery',
			'listOfThing',
			[
				'type' => [
					'list_of' => [
						'non_null' => 'Post',
					],
				],
			]
		);

		$query = '
		{
			listOfThing {
				__typename
			}
		}
		';

		$request = graphql(
			[
				'query' => $query,
			],
			true
		);

		$request->execute();

		$types = $request->get_query_analyzer()->get_list_types();

		$this->assertContains( 'list:post', $types );
	}

	public function testNodeIdsAreInQueryAnalyzerWhenLoadManyIsUsed() {

		$post_id_1 = $this->factory()->post->create();
		$post_id_2 = $this->factory()->post->create();
		register_graphql_field(
			'RootQuery',
			'testLoadmany',
			[
				'type'    => [ 'list_of' => 'Post' ],
				'resolve' => static function ( $source, $args, $context, $info ) use ( $post_id_1, $post_id_2 ) {
					$post_ids = [ $post_id_1, $post_id_2 ];
					return $context->get_loader( 'post' )->load_many( $post_ids );
				},
			]
		);

		$query = '
		query TestLoadMany {
		  testLoadmany {
		    databaseId
		  }
		}
		';

		$request = $this->graphql( [ 'query' => $query ], true );
		$actual  = $request->execute();

		codecept_debug(
			[
				'actual' => $actual,
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedObject(
					'testLoadmany',
					[
						'databaseId' => $post_id_1,
					]
				),
				$this->expectedObject(
					'testLoadmany',
					[
						'databaseId' => $post_id_2,
					]
				),
			]
		);

		$node_ids = $request->get_query_analyzer()->get_runtime_nodes();

		codecept_debug( $node_ids );

		$this->assertNotEmpty( $node_ids );
		$this->assertContains( \GraphQLRelay\Relay::toGlobalId( 'post', $post_id_1 ), $node_ids );
		$this->assertContains( \GraphQLRelay\Relay::toGlobalId( 'post', $post_id_2 ), $node_ids );
	}
}
