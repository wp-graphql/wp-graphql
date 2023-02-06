<?php
class QueryAnalyzerTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $post_id;

	public function _setUp():void {
		parent::_setUp();

		$this->post_id = self::factory()->post->create([
			'post_status' => 'publish',
			'post_title' => 'test post'
		]);

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

		codecept_debug( $list_types );

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

		$request = graphql([
			'query' => $query,
		], true);


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

}
