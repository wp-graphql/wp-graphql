<?php

class ConnectionRegistrationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		// before
		parent::setUp();
		// your set up methods here
		$this->clearSchema();
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		parent::tearDown();
	}

	public function testRegisteringConnectionsFromTypeRegistrationAddsConnectionsToSchema() {

		register_graphql_object_type( 'TestTypeWithOneToOneConnection', [
			'fields'      => [
				'id' => [
					'type' => 'ID',
				],
			],
			'connections' => [
				'connectedPosts' => [
					'toType' => 'Post',
				],
				'connectedPost'  => [
					'toType'   => 'Post',
					'oneToOne' => true,
				],
			],
		]);

		register_graphql_connection( [
			'fromType'      => 'RootQuery',
			'toType'        => 'TestTypeWithOneToOneConnection',
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
			$this->expectedField( 'testTypeConnection', self::IS_NULL ),
		] );

	}

	/**
	 * See: https://github.com/wp-graphql/wp-graphql/issues/2054
	 */
	public function testRegisteringConnectionWithArgsAllowsArgsToBeUsedInQuery() {

		register_graphql_object_type( 'Test', [
			'fields'      => [
				'test' => [
					'type' => 'String',
				],
			],
			'connections' => [
				'testPostConnection' => [
					'toType'         => 'Post',
					'connectionArgs' => [
						'testInput' => [
							'type' => 'String',
						],
					],
				],
			],
		]);

		register_graphql_field( 'RootQuery', 'test', [
			'type' => 'Test',
		]);

		$query = '
		query Test($where: TestToPostConnectionWhereArgs) {
			test {
				testPostConnection(where: $where) {
					nodes {
						id
					}
				}
			}
		}
		';

		$variables = [
			'testInput' => 'test',
		];

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => $variables,
		]);

		$this->assertQuerySuccessful( $actual, [
			$this->expectedField( 'test', self::IS_NULL ),
		]);

	}

}
