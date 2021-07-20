<?php

class ConnectionRegistrationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		// before
		$this->clearSchema();
		parent::setUp();
		// your set up methods here
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		parent::tearDown();
	}

	public function testRegisteringConnectionsFromTypeRegistrationAddsConnectionsToSchema() {

		register_graphql_object_type( 'TestTypeWithOneToOneConnection', [
			'fields' => [
				'id' => [
					'type' => 'ID'
				],
			],
			'connections' => [
				'connectedPosts' => [
					'toType' => 'Post',
				],
				'connectedPost' => [
					'toType' => 'Post',
					'oneToOne' => true,
				]
			]
		]);

		register_graphql_connection( [
			'fromType' => 'RootQuery',
			'toType' => 'TestTypeWithOneToOneConnection',
			'fromFieldName' => 'testTypeConnection',
		]);

		$query = '
		{
		  testTypeConnection {
		    nodes {
		      id
		      connectedPosts {
		        nodes {
		          id
		        }
		      }
		      connectedPost {
		        node {
		          id
		        }
		      }
		    }
		  }
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		// Assert that the query above is successful given the registered type and connections
		// But since there's no data for the connection, we can safely assert the response
		// should be null, but with no errors
		$this->assertQuerySuccessful( $actual, [
			$this->expectedField( 'testTypeConnection', self::IS_NULL )
		] );

	}

}
