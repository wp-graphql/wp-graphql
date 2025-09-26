<?php

class RouterTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// before
		parent::setUp();

		// your set up methods here
	}

	public function tearDown(): void {
		// your tear down methods here

		// then
		parent::tearDown();
	}

	public function testRouteEndpoint() {
		/**
		 * Test that the default route is set to "graphql"
		 */
		$this->assertEquals( 'graphql', apply_filters( 'graphql_endpoint', \WPGraphQL\Router::$route ) );
	}

	/**
	 * Test to make sure that the rewrite rules properly include the graphql route
	 */
	public function testGraphQLRewriteRule() {
		global $wp_rewrite;
		$route = apply_filters( 'graphql_endpoint', \WPGraphQL\Router::$route );
		$this->assertArrayHasKey( $route . '/?$', $wp_rewrite->extra_rules_top );
	}

	public function testAddQueryVar() {
		$query_vars = [];
		$actual     = \WPGraphQL\Router::add_query_var( $query_vars );
		$this->assertEquals( $actual, [ apply_filters( 'graphql_endpoint', \WPGraphQL\Router::$route ) ] );
	}

	public function testGetRawData() {
		$router = new \WPGraphQL\Router();
		global $HTTP_RAW_POST_DATA;
		$actual = $router->get_raw_data();
		$this->assertEquals( $actual, $HTTP_RAW_POST_DATA );
	}

	public function testGetRawDataEmptyGlobal() {
		$router = new \WPGraphQL\Router();
		global $HTTP_RAW_POST_DATA;
		$HTTP_RAW_POST_DATA = null;
		$actual             = $router->get_raw_data();
		$this->assertEquals( $actual, $HTTP_RAW_POST_DATA );
	}

	/**
	 * Test to make sure the router is setting the request variable
	 *
	 * @see: https://github.com/wp-graphql/wp-graphql/pull/452
	 */
	public function testRequestVariableIsSet() {
		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		{
			posts {
				edges {
					node {
						id
					}
				}
			}
		}';

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Filter the request data
		 */
		add_filter(
			'graphql_request_results',
			function ( $result, $schema, $operation, $request, $variables ) {
				$this->assertEquals( $query, $request );

				return true;
			}
		);
	}

	/**
	 * Test the "send_header" method in the Router class
	 *
	 * @see: https://github.com/sebastianbergmann/phpunit/issues/720
	 * @runInSeparateProcess
	 */
	// public function testSendHeader() {
	// $router = new \WPGraphQL\Router();
	// $router::send_header( 'some_key', 'some_value' );
	// if ( function_exists( 'xdebug_get_headers' ) ) {
	// $this->assertContains( 'some_key: some_value', xdebug_get_headers() );
	// }
	// }
	//
	// public function testAddRewriteRule() {
	//
	// global $wp_rewrite;
	// \WPGraphQL\Router::add_rewrite_rule();
	// flush_rewrite_rules();
	//
	// $this->assertContains( 'index.php?' . \WPGraphQL\Router::$route . '=true', $wp_rewrite->extra_rules_top );
	//
	// }

	/**
	 * @runInSeparateProcess
	 */
	// public function testSetHeadersNoCache() {
	//
	// $router = new \WPGraphQL\Router();
	// $router::set_headers( '200' );
	//
	// $headers = xdebug_get_headers();
	//
	// $this->assertContains( 'Access-Control-Allow-Origin: *', $headers );
	// $this->assertContains( 'Content-Type: application/json ; charset=' . get_option( 'blog_charset' ), $headers );
	// $this->assertContains( 'X-Robots-Tag: noindex', $headers );
	// $this->assertContains( 'X-Content-Type-Options: nosniff', $headers );
	// $this->assertContains( 'Access-Control-Allow-Headers: Authorization, Content-Type', $headers );
	// $this->assertContains( 'X-hacker: If you\'re reading this, you should visit github.com/wp-graphql and contribute!', $headers );
	//
	// }

	/**
	 * @runInSeparateProcess
	 */
	// public function testSetHeadersWithCache() {
	//
	// add_filter( 'graphql_send_nocache_headers', function() {
	// return true;
	// } );
	//
	// $router = new \WPGraphQL\Router();
	// $router::set_headers( '200' );
	// $headers = xdebug_get_headers();
	// $this->assertContains( 'Cache-Control: no-cache, must-revalidate, max-age=0', $headers );
	//
	// }

	/**
	 * Test that content rendering still works correctly with Router-level output buffering
	 */
	public function testContentRenderingWorksWithRouterOutputBuffering() {
		// Add a filter that modifies content (this should still work)
		add_filter( 'the_content', function( $content ) {
			return $content . '<p>This content was filtered and should appear in the response.</p>';
		});

		// Create a test post
		$post_id = $this->factory->post->create([
			'post_title' => 'Test Post for Content Rendering',
			'post_content' => 'Original content',
			'post_status' => 'publish'
		]);

		$query = '
		query postById( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				databaseId
				content
			}
		}
		';

		$actual = graphql([
			'query'     => $query,
			'variables' => [
				'id' => $post_id,
			],
		]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'post', $actual['data'] );

		// Verify content filtering still works (content should contain our filtered text)
		$content = $actual['data']['post']['content'];
		$this->assertStringContainsString( 'This content was filtered and should appear in the response.', $content );
	}

	/**
	 * Test that plugins outputting HTML during wp_head action don't break GraphQL responses
	 * Some plugins hook into wp_head even during AJAX/API requests
	 */
	public function testWpHeadOutputDoesNotBreakGraphQLResponse() {
		add_action( 'wp_head', function() {
			echo '<meta name="test-plugin" content="should-not-break-graphql">';
			echo '<script>window.testPlugin = true;</script>';
		});

		// Trigger wp_head during GraphQL execution (some plugins do this)
		add_action( 'graphql_execute', function() {
			ob_start();
			do_action( 'wp_head' );
			$output = ob_get_clean();
			// Normally this would break the response, but Router-level buffering should handle it
			if ( $output ) {
				echo $output; // This would normally corrupt JSON
			}
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test that plugins outputting HTML during wp_footer action don't break GraphQL responses
	 */
	public function testWpFooterOutputDoesNotBreakGraphQLResponse() {
		add_action( 'wp_footer', function() {
			echo '<script>console.log("Footer script that should not break GraphQL");</script>';
			echo '<!-- Footer comment -->';
		});

		// Some plugins trigger wp_footer during API requests
		add_action( 'graphql_execute', function() {
			ob_start();
			do_action( 'wp_footer' );
			$output = ob_get_clean();
			if ( $output ) {
				echo $output; // This would normally corrupt JSON
			}
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test that plugins outputting HTML during admin_notices don't break GraphQL responses
	 * Some plugins show admin notices even during AJAX/API requests
	 */
	public function testAdminNoticesOutputDoesNotBreakGraphQLResponse() {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>This admin notice should not break GraphQL!</p></div>';
		});

		// Some plugins trigger admin_notices during API requests
		add_action( 'graphql_execute', function() {
			ob_start();
			do_action( 'admin_notices' );
			$output = ob_get_clean();
			if ( $output ) {
				echo $output; // This would normally corrupt JSON
			}
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test that plugins outputting during WordPress shutdown don't break GraphQL responses
	 */
	public function testShutdownOutputDoesNotBreakGraphQLResponse() {
		add_action( 'shutdown', function() {
			echo '<!-- Shutdown output that should not break GraphQL -->';
			echo '<script>console.log("Shutdown script");</script>';
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test multiple simultaneous sources of HTML output don't break GraphQL responses
	 */
	public function testMultipleOutputSourcesDoNotBreakGraphQLResponse() {
		// Multiple sources of unwanted output
		add_action( 'wp_enqueue_scripts', function() {
			wp_print_inline_script_tag( 'window.plugin1 = true;' );
			echo '<script>window.plugin2 = true;</script>';
			echo '<!-- Plugin comment -->';
		});

		add_action( 'graphql_execute', function() {
			echo '<div>Debug output from plugin</div>';
			wp_print_inline_script_tag( 'console.log("Debug script");' );
		});

		add_filter( 'wp_die_handler', function() {
			return function( $message ) {
				echo '<div class="wp-die-message">' . $message . '</div>';
			};
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during WPGraphQL-specific lifecycle hooks
	 * These are the "hidden" cases where plugins think they're only affecting theme layer
	 */

	/**
	 * Test plugins outputting HTML during graphql_init action
	 */
	public function testGraphqlInitOutputDoesNotBreakResponse() {
		add_action( 'graphql_init', function() {
			echo '<script>console.log("Plugin initializing during GraphQL init");</script>';
			echo '<!-- Debug: GraphQL plugin loaded -->';
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during init_graphql_request action
	 */
	public function testInitGraphqlRequestOutputDoesNotBreakResponse() {
		add_action( 'init_graphql_request', function() {
			echo '<div class="graphql-debug">Initializing GraphQL request</div>';
			wp_print_inline_script_tag( 'window.graphqlRequestInit = true;' );
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during graphql_before_resolve_field action
	 */
	public function testGraphqlBeforeResolveFieldOutputDoesNotBreakResponse() {
		add_action( 'graphql_before_resolve_field', function( $source, $args, $context, $info ) {
			// Plugin trying to debug field resolution
			if ( 'title' === $info->fieldName ) {
				echo '<span class="field-debug">Resolving title field</span>';
				echo '<!-- Field: ' . $info->fieldName . ' -->';
			}
		}, 10, 4);

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during graphql_after_resolve_field action
	 */
	public function testGraphqlAfterResolveFieldOutputDoesNotBreakResponse() {
		add_action( 'graphql_after_resolve_field', function( $source, $args, $context, $info, $field_resolver, $type_name, $field_key, $field, $result ) {
			// Plugin trying to log field resolution results
			if ( 'title' === $field_key ) {
				echo '<div class="field-result">Field resolved: ' . esc_html($result) . '</div>';
				wp_print_inline_script_tag( 'console.log("Field resolved: ' . $field_key . '");' );
			}
		}, 10, 9);

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during graphql_execute action
	 */
	public function testGraphqlExecuteOutputDoesNotBreakResponse() {
		add_action( 'graphql_execute', function( $response, $schema, $operation, $query, $variables, $request ) {
			// Plugin trying to log GraphQL execution
			echo '<div class="graphql-execution-log">';
			echo 'Operation: ' . esc_html($operation) . '<br>';
			echo 'Query: ' . esc_html(substr($query, 0, 50)) . '...<br>';
			echo '</div>';
			wp_print_inline_script_tag( 'console.log("GraphQL executed: ' . $operation . '");' );
		}, 10, 6);

		$query = '
		query TestQuery {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'operationName' => 'TestQuery'
		]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during graphql_process_http_request action
	 */
	public function testGraphqlProcessHttpRequestOutputDoesNotBreakResponse() {
		add_action( 'graphql_process_http_request', function() {
			// Plugin trying to add analytics or tracking during HTTP requests
			echo '<script async src="https://www.googletagmanager.com/gtag/js?id=GA_TRACKING_ID"></script>';
			echo '<script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag("js", new Date()); gtag("config", "GA_TRACKING_ID");</script>';
			echo '<!-- GraphQL HTTP request processed -->';
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during graphql_response_set_headers action
	 */
	public function testGraphqlResponseSetHeadersOutputDoesNotBreakResponse() {
		add_action( 'graphql_response_set_headers', function( $headers ) {
			// Plugin trying to add debugging info when headers are set
			echo '<div class="headers-debug">Setting GraphQL response headers</div>';
			echo '<pre>' . print_r($headers, true) . '</pre>';
		});

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML using graphql_schema filter
	 */
	public function testGraphqlSchemaFilterOutputDoesNotBreakResponse() {
		add_filter( 'graphql_schema', function( $schema, $app_context ) {
			// Plugin trying to debug schema during filtering
			echo '<div class="schema-debug">Schema filtered for GraphQL</div>';
			echo '<!-- Schema types: ' . count($schema->getTypeMap()) . ' -->';
			return $schema;
		}, 10, 2);

		$query = '
		query {
			posts(first: 1) {
				nodes {
					databaseId
					title
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during mutation-specific hooks
	 */
	public function testGraphqlMutationHooksOutputDoesNotBreakResponse() {
		// Set up user with proper capabilities
		wp_set_current_user( $this->factory()->user->create( [
			'role' => 'administrator'
		] ) );

		// Hook into post mutation hooks that plugins might use for debugging
		add_action( 'graphql_post_object_mutation_update_additional_data', function( $post_id, $input, $post_type_object, $mutation_name ) {
			echo '<div class="mutation-debug">Post mutation: ' . $mutation_name . ' for post ' . $post_id . '</div>';
			wp_print_inline_script_tag( 'console.log("Post mutated: ' . $post_id . '");' );
		}, 10, 4);

		// Create a simple post to test mutation
		$mutation = '
		mutation CreatePost($input: CreatePostInput!) {
			createPost(input: $input) {
				post {
					databaseId
					title
				}
			}
		}
		';

		$variables = [
			'input' => [
				'title' => 'Test Post for Mutation Output',
				'content' => 'Test content',
				'status' => 'PUBLISH',
				'clientMutationId' => 'test123'
			]
		];

		$actual = graphql([
			'query' => $mutation,
			'variables' => $variables
		]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'createPost', $actual['data'] );
	}

	/**
	 * Test plugins outputting HTML during complex nested scenarios
	 * This simulates real-world scenarios where multiple plugins might be outputting
	 */
	public function testComplexNestedWPGraphQLHooksOutputDoesNotBreakResponse() {
		// Create some test posts to ensure we have data to query
		$this->factory()->post->create_many( 3, [
			'post_status' => 'publish'
		] );

		// Multiple plugins hooking into various WPGraphQL lifecycle events
		add_action( 'init_graphql_request', function() {
			echo '<script>window.debugPlugin1 = "init_request";</script>';
		});

		add_action( 'graphql_before_resolve_field', function( $source, $args, $context, $info ) {
			if ( 'posts' === $info->fieldName ) {
				echo '<div class="query-debug">Resolving posts connection</div>';
			}
		}, 10, 4);

		add_filter( 'graphql_pre_return_field_from_model', function( $result, $field_name, $model_name ) {
			if ( 'title' === $field_name ) {
				echo '<span class="field-filter">Filtering ' . $field_name . ' from ' . $model_name . '</span>';
			}
			return $result;
		}, 10, 3);

		add_action( 'graphql_after_resolve_field', function( $source, $args, $context, $info ) {
			if ( 'title' === $info->fieldName ) {
				echo '<!-- Title field resolved -->';
				wp_print_inline_script_tag( 'console.log("Title resolved");' );
			}
		}, 10, 4);

		add_action( 'graphql_execute', function() {
			echo '<div class="execution-complete">GraphQL execution completed</div>';
		});

		$query = '
		query {
			posts(first: 2) {
				nodes {
					databaseId
					title
					content
				}
			}
		}
		';

		$actual = graphql(['query' => $query]);

		$this->assertIsArray( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'posts', $actual['data'] );

		// Verify we got the expected data structure despite all the HTML output
		$this->assertIsArray( $actual['data']['posts']['nodes'] );
		$this->assertGreaterThan( 0, count($actual['data']['posts']['nodes']) );
	}

	/**
	 * This tests the WPGraphQL Router resolving HTTP requests.
	 */
	// public function testResolveRequest() {
	//
	// **
	// * Create a test a query
	// */
	// $this->factory->post->create( [
	// 'post_title'  => 'test',
	// 'post_status' => 'publish',
	// ] );
	//
	// **
	// * Filter the request data
	// */
	// add_filter( 'graphql_request_data', function( $data ) {
	// $data['query']         = 'query getPosts($first:Int){ posts(first:$first){ edges{ node{ id } } } }';
	// $data['variables']     = [ 'first' => 1 ];
	// $data['operationName'] = 'getPosts';
	//
	// return $data;
	// } );
	//
	// **
	// * Set the query var to "graphql" so we can mock like we're visiting the endpoint via
	// */
	// set_query_var( 'graphql', true );
	// $GLOBALS['wp']->query_vars['graphql'] = true;
	//
	// **
	// * Instantiate the router
	// */
	// $router = new \WPGraphQL\Router();
	//
	// **
	// * Process the request using our filtered data
	// */
	// $router::resolve_http_request();
	//
	// **
	// * Make sure the constant gets defined when it's a GraphQL Request
	// */
	// $this->assertTrue( defined( 'GRAPHQL_HTTP_REQUEST' ) );
	// $this->assertEquals( true, GRAPHQL_HTTP_REQUEST );
	//
	// **
	// * Make sure the actions we expect to be firing are firing
	// */
	// $this->assertNotFalse( did_action( 'graphql_process_http_request' ) );
	// $this->assertNotFalse( did_action( 'graphql_process_http_request_response' ) );
	//
	// }
	//
	// public function testResolveHttpRequestWithJsonVariables() {
	//
	// **
	// * Create a test a query
	// */
	// $this->factory->post->create( [
	// 'post_title'  => 'test',
	// 'post_status' => 'publish',
	// ] );
	//
	// **
	// * Filter the request data
	// */
	// add_filter( 'graphql_request_data', function( $data ) {
	// $data['query']         = 'query getPosts($first:Int){ posts(first:$first){ edges{ node{ id } } } }';
	// $data['variables']     = wp_json_encode( [ 'first' => 1 ] );
	// $data['operationName'] = 'getPosts';
	//
	// return $data;
	// } );
	//
	// **
	// * Set the query var to "graphql" so we can mock like we're visiting the endpoint via
	// */
	// set_query_var( 'graphql', true );
	// $GLOBALS['wp']->query_vars['graphql'] = true;
	//
	// **
	// * Instantiate the router
	// */
	// $router = new \WPGraphQL\Router();
	//
	// **
	// * Process the request using our filtered data
	// */
	// $router::resolve_http_request();
	//
	// **
	// * Make sure the constant gets defined when it's a GraphQL Request
	// */
	// $this->assertTrue( defined( 'GRAPHQL_HTTP_REQUEST' ) );
	// $this->assertEquals( true, GRAPHQL_HTTP_REQUEST );
	//
	// **
	// * Make sure the actions we expect to be firing are firing
	// */
	// $this->assertNotFalse( did_action( 'graphql_process_http_request' ) );
	// $this->assertNotFalse( did_action( 'graphql_process_http_request_response' ) );
	//
	// }
	//
	// **
	// * This tests the resolve_http_request method for a route that's not the
	// * /graphql endpoint to make sure that graphql isn't improperly initiated
	// * when it's not supposed to be.
	// */
	// public function testResolveHttpRequestWrongQueryVars() {
	//
	// set_query_var( 'graphql', false );
	// $GLOBALS['wp']->query_vars['graphql'] = false;
	//
	// **
	// * Instantiate the router
	// */
	// $router = new \WPGraphQL\Router();
	//
	// **
	// * Process the request using our filtered data
	// */
	// $this->assertNull( $router::resolve_http_request() );
	//
	// }
	//
	// public function testResolveHttpRequestWithEmptyQuery() {
	//
	// **
	// * Filter the request data
	// */
	// add_filter( 'graphql_request_data', function( $data ) {
	// $data['query']         = null;
	// $data['variables']     = null;
	// $data['operationName'] = null;
	//
	// return $data;
	// } );
	//
	// **
	// * Set the query var to "graphql" so we can mock like we're visiting the endpoint via
	// */
	// set_query_var( 'graphql', true );
	// $GLOBALS['wp']->query_vars['graphql'] = true;
	//
	// **
	// * Instantiate the router
	// */
	// $router = new \WPGraphQL\Router();
	//
	// **
	// * Process the request using our filtered data
	// */
	// $router::resolve_http_request();
	//
	// **
	// * Make sure the constant gets defined when it's a GraphQL Request
	// */
	// $this->assertTrue( defined( 'GRAPHQL_HTTP_REQUEST' ) );
	// $this->assertEquals( true, GRAPHQL_HTTP_REQUEST );
	//
	// **
	// * Make sure the actions we expect to be firing are firing
	// */
	// $this->assertNotFalse( did_action( 'graphql_process_http_request' ) );
	// $this->assertNotFalse( did_action( 'graphql_process_http_request_response' ) );
	//
	// }
}
