<?php
class QueryAnalyzerTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $post_id;

	public function _setUp():void {
		parent::_setUp();

		$this->post_id = self::factory()->post->create([
			'post_status' => 'publish',
			'post_title' => 'test post'
		]);

		WPGraphQL::clear_schema();

	}

	public function _tearDown():void {
		wp_delete_post( $this->post_id, true );
		parent::_tearDown();
	}

	public function testListTypes() {

		$query = '{ posts { nodes { id, title } } }';

		$request = graphql([
			'query' => $query,
		], true);

		// before execution, this should be null
		$this->assertEmpty( $request->get_query_analyzer()->get_list_types() );


		// execute the query
		$request->execute();

		// get the list types that were generated during execution
		$list_types = $request->get_query_analyzer()->get_list_types();

		// Assert the expected list types are returned
		$this->assertEqualSets( [ 'list:post' ], $list_types );
	}

	public function testListModels() {
		$query = '{ posts { nodes { id, title } } }';

		$request = graphql([
			'query' => $query,
		], true);

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

		$request = graphql([
			'query' => $query,
		], true);


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

		add_action( 'graphql_register_types', function() {
			register_graphql_interface_type( 'TestInterface', [
				'eagerlyLoadType' => true,
				'fields'      => [
					'test' => [
						'type' => 'String',
					],
				],
				'resolveType' => function () {
					return 'TestType';
				},
			] );

			register_graphql_object_type( 'TestType', [
				'eagerlyLoadType' => true,
				'interfaces' => [ 'TestInterface' ],
				'fields'     => [
					'test' => [
						'type' => 'String',
					],
				],
			] );

			register_graphql_field( 'Post', 'testField', [
				'type'    => [ 'list_of' => 'TestInterface' ],
				'resolve' => function () {
					return [
						[
							'test' => 'value',
						],
						[
							'test' => 'value',
						],
					];
				},
			] );
		} );


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

		$request = graphql([
			'query' => $query
		], true );

		$request->execute();

		$list_types = $request->get_query_analyzer()->get_list_types();

		codecept_debug( $list_types );
		$keys_array = $list_types;
		codecept_debug( $list_types );

		$this->assertNotContains( 'list:testinterface', $list_types );
		$this->assertNotContains( 'list:testtype', $list_types );
		$this->assertContains( 'list:post', $list_types );

	}

}
