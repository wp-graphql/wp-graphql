<?php

class TracingTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testQueryWithTracingEnabledForAnyUser() {

		// enable tracing for any user
		$settings                      = get_option( 'graphql_general_settings', [] );
		$settings['tracing_enabled']   = 'on';
		$settings['tracing_user_role'] = 'any';
		update_option( 'graphql_general_settings', $settings );

		$query = '
		{
			posts {
				nodes {
					id
				}
			}
		}
		';

		$response = $this->graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $response );

		$this->assertResponseIsValid( $response );
		$this->assertNotEmpty( $response['extensions']['tracing'] );
	}

	public function testQueryByGraphqlIdWorksWithTracingEnabled() {

		// enable tracing for any user
		$settings                      = get_option( 'graphql_general_settings', [] );
		$settings['tracing_enabled']   = 'on';
		$settings['tracing_user_role'] = 'any';
		update_option( 'graphql_general_settings', $settings );

		$query = '
		{
			posts {
				nodes {
					id
				}
			}
		}
		';

		$response = $this->graphql(
			[
				'queryId' => $query,
			]
		);

		codecept_debug( $response );

		$this->assertResponseIsValid( $response );
		$this->assertNotEmpty( $response['extensions']['tracing'] );
	}
}
