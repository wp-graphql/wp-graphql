<?php

/**
 * Tests for the query depth validation rule, the introspection exemption and the
 * `graphql_query_depth_max` filter.
 */
class QueryDepthRuleTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * A content query that is 6 levels deep.
	 */
	const DEEP_QUERY = '
	{
		posts {
			nodes {
				author {
					node {
						posts {
							nodes {
								id
							}
						}
					}
				}
			}
		}
	}
	';

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $this->factory()->user->create( [ 'role' => 'author' ] ),
			]
		);

		$this->set_depth_settings( 'on', 2 );
	}

	public function tearDown(): void {
		delete_option( 'graphql_general_settings' );
		remove_all_filters( 'graphql_query_depth_max' );
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Stores the query depth settings.
	 *
	 * @param string $enabled 'on' or 'off'.
	 * @param int    $max     The max depth.
	 */
	private function set_depth_settings( string $enabled, int $max ): void {
		$settings                        = get_option( 'graphql_general_settings', [] );
		$settings                        = is_array( $settings ) ? $settings : [];
		$settings['query_depth_enabled'] = $enabled;
		$settings['query_depth_max']     = $max;
		update_option( 'graphql_general_settings', $settings );
	}

	/**
	 * Whether the response contains the query depth error.
	 *
	 * @param array<string,mixed> $response The GraphQL response.
	 */
	private function has_depth_error( array $response ): bool {
		foreach ( $response['errors'] ?? [] as $error ) {
			if ( false !== strpos( $error['message'], 'max query depth' ) ) {
				return true;
			}
		}

		return false;
	}

	public function testDeepQueryIsRejectedWhenEnabled(): void {
		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );

		$this->assertTrue( $this->has_depth_error( $response ) );
		$this->assertArrayNotHasKey( 'data', $response );
	}

	public function testDeepQueryExecutesWhenDisabled(): void {
		$this->set_depth_settings( 'off', 2 );

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );

		$this->assertFalse( $this->has_depth_error( $response ) );
		$this->assertArrayNotHasKey( 'errors', $response );
	}

	public function testIntrospectionQueryIsNotLimited(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $this->graphql( [ 'query' => \GraphQL\Type\Introspection::getIntrospectionQuery() ] );

		$this->assertFalse( $this->has_depth_error( $response ) );
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertNotEmpty( $response['data']['__schema']['types'] );
	}

	public function testIntrospectionSpreadAtTheRootIsNotLimited(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$query = '
		query {
			__typename
			...SchemaTypes
			... on RootQuery {
				__type(name: "Post") {
					fields {
						type {
							ofType {
								ofType {
									name
								}
							}
						}
					}
				}
			}
		}
		fragment SchemaTypes on RootQuery {
			__schema {
				types {
					fields {
						type {
							ofType {
								name
							}
						}
					}
				}
			}
		}
		';

		$response = $this->graphql( [ 'query' => $query ] );

		$this->assertFalse( $this->has_depth_error( $response ) );
		$this->assertArrayNotHasKey( 'errors', $response );
	}

	public function testIntrospectionNextToDeepContentQueryIsLimited(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$query = '
		{
			__type(name: "Post") {
				name
			}
			posts {
				nodes {
					author {
						node {
							posts {
								nodes {
									id
								}
							}
						}
					}
				}
			}
		}
		';

		$response = $this->graphql( [ 'query' => $query ] );

		$this->assertTrue( $this->has_depth_error( $response ) );
	}

	public function testIntrospectionFragmentWithDeepContentQueryIsLimited(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$query = '
		query {
			...Mixed
		}
		fragment Mixed on RootQuery {
			__schema {
				queryType {
					name
				}
			}
			posts {
				nodes {
					author {
						node {
							posts {
								nodes {
									id
								}
							}
						}
					}
				}
			}
		}
		';

		$response = $this->graphql( [ 'query' => $query ] );

		$this->assertTrue( $this->has_depth_error( $response ) );
	}

	public function testFilterReceivesTheConfiguredMaxDepth(): void {
		$received = [];

		add_filter(
			'graphql_query_depth_max',
			static function ( $max_depth ) use ( &$received ) {
				$received[] = $max_depth;
				return $max_depth;
			}
		);

		$this->graphql( [ 'query' => '{ posts { nodes { id } } }' ] );
		$this->assertContains( 2, $received );

		$received = [];
		$this->set_depth_settings( 'off', 2 );
		$this->graphql( [ 'query' => '{ posts { nodes { id } } }' ] );
		$this->assertContains( 0, $received );
	}

	public function testFilterCanRemoveTheLimit(): void {
		add_filter( 'graphql_query_depth_max', '__return_zero' );

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );

		$this->assertFalse( $this->has_depth_error( $response ) );
		$this->assertArrayNotHasKey( 'errors', $response );
	}

	public function testFilterReturningANegativeNumberRemovesTheLimit(): void {
		add_filter(
			'graphql_query_depth_max',
			static function () {
				return -1;
			}
		);

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );

		$this->assertFalse( $this->has_depth_error( $response ) );
	}

	public function testFilterReturningANonNumericValueKeepsTheConfiguredLimit(): void {
		add_filter( 'graphql_query_depth_max', '__return_null' );

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );

		$this->assertTrue( $this->has_depth_error( $response ) );
	}

	public function testFilterCanRaiseTheLimitForTrustedUsers(): void {
		add_filter(
			'graphql_query_depth_max',
			static function ( $max_depth ) {
				return current_user_can( 'manage_options' ) ? 20 : $max_depth;
			}
		);

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );
		$this->assertTrue( $this->has_depth_error( $response ) );

		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );
		$this->assertFalse( $this->has_depth_error( $response ) );
	}

	public function testFilterCanEnableTheLimitWhenTheSettingIsOff(): void {
		$this->set_depth_settings( 'off', 10 );

		add_filter(
			'graphql_query_depth_max',
			static function () {
				return 2;
			}
		);

		$response = $this->graphql( [ 'query' => self::DEEP_QUERY ] );

		$this->assertTrue( $this->has_depth_error( $response ) );
	}

	public function testFreshInstallTurnsOnQueryDepthLimiting(): void {
		delete_option( 'graphql_general_settings' );
		delete_option( 'wp_graphql_version' );

		graphql_init()->upgrade();

		$this->assertSame( 'on', get_graphql_setting( 'query_depth_enabled', 'off' ) );
		$this->assertSame( 15, get_graphql_setting( 'query_depth_max', 10 ) );
		$this->assertSame( WPGRAPHQL_VERSION, get_option( 'wp_graphql_version' ) );
	}

	public function testFreshInstallKeepsSettingsThatAreAlreadyStored(): void {
		update_option(
			'graphql_general_settings',
			[
				'query_depth_enabled'          => 'off',
				'public_introspection_enabled' => 'on',
			]
		);
		delete_option( 'wp_graphql_version' );

		graphql_init()->upgrade();

		$this->assertSame( 'off', get_graphql_setting( 'query_depth_enabled', 'off' ) );
		$this->assertSame( 'on', get_graphql_setting( 'public_introspection_enabled', 'off' ) );
		$this->assertSame( 15, get_graphql_setting( 'query_depth_max', 10 ) );
	}

	public function testExistingInstallDoesNotChangeQueryDepthSettings(): void {
		delete_option( 'graphql_general_settings' );
		update_option( 'wp_graphql_version', '2.0.0' );

		graphql_init()->upgrade();

		$settings = get_option( 'graphql_general_settings', [] );
		$this->assertArrayNotHasKey( 'query_depth_enabled', (array) $settings );
		$this->assertSame( 'off', get_graphql_setting( 'query_depth_enabled', 'off' ) );
	}
}
