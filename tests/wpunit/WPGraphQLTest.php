<?php

class WPGraphQLTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $instance;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		$this->instance = graphql_init();
	}

	public function tearDown(): void {
		// your tear down methods here

		$this->clearSchema();

		// then
		parent::tearDown();
	}

	/**
	 * Ensure that graphql_init() returns an instance of WPGraphQL
	 *
	 * @covers WPGraphQL::instance()
	 */
	public function testInstance() {
		$this->assertTrue( $this->instance instanceof WPGraphQL );
	}

	/**
	 * @covers WPGraphQL::__wakeup()
	 * @covers WPGraphQL::__clone()
	 */
	public function testCloneWPGraphQL() {
		$rc = new ReflectionClass( $this->instance );
		$this->assertTrue( $rc->hasMethod( '__clone' ) );
		$this->assertTrue( $rc->hasMethod( '__wakeup' ) );
	}

	/**
	 * @covers WPGraphQL::setup_constants()
	 */
	public function testSetupConstants() {
		do_action( 'init' );
		$this->assertTrue( defined( 'WPGRAPHQL_VERSION' ) );
		$this->assertTrue( defined( 'WPGRAPHQL_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'WPGRAPHQL_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WPGRAPHQL_PLUGIN_FILE' ) );
	}

	/**
	 * @covers WPGraphQL::filters()
	 */
	public function testFilters() {

		global $wp_filter;
		graphql_init();
		$this->assertTrue( isset( $wp_filter['graphql_get_type']->callbacks ) );
	}

	/**
	 * @covers WPGraphQL::get_static_schema()
	 */
	public function testGetStaticSchema() {

		/**
		 * Set the file path for where to save the static schema
		 */
		$file_path = WPGRAPHQL_PLUGIN_DIR . 'schema.graphql';
		$contents  = 'test';
		file_put_contents( $file_path, $contents );
		$this->assertFileExists( $file_path );
		$static_schema = WPGraphQL::get_static_schema();
		$this->assertEquals( $contents, $static_schema );
	}

	/**
	 * Tests WPGraphQL::get_allowed_post_types()
	 */
	public function testGetAllowedPostTypes() {
		// Test names.
		$expected = get_post_types( [ 'show_in_graphql' => true ] );

		$actual = WPGraphQL::get_allowed_post_types();

		codecept_debug( $actual );

		$this->assertEqualSets( $expected, $actual );

		// Test objects.
		$expected = get_post_types( [ 'show_in_graphql' => true ], 'objects' );

		$actual = WPGraphQL::get_allowed_post_types( 'objects' );

		$this->assertEqualSets( $expected, $actual );

		// Test args.
		$expected = get_post_types(
			[
				'name' => 'page',
			],
		);

		codecept_debug( $expected );

		$actual = WPGraphQL::get_allowed_post_types( 'names', [ 'name' => 'page' ] );
		codecept_debug( $actual );

		$this->assertEqualSets( $expected, $actual );

		// Test filter.
		$expected = 'post';

		add_filter(
			'graphql_post_entities_allowed_post_types',
			function ( $names, $objects ) use ( $expected ) {
				$this->assertContains( $expected, $names );
				$this->assertInstanceOf( \WP_Post_Type::class, $objects[ $expected ] );

				return [ $expected => $expected ];
			},
			10,
			2
		);

		// Clear cached types.
		WPGraphQL::clear_schema();

		$actual = WPGraphQL::get_allowed_post_types();

		codecept_debug( $actual );

		$this->assertEquals( [ $expected => $expected ], $actual );

		// Test query.
		$query = '
			query contentTypes {
				contentTypes {
					nodes {
						name
					}
				}
			}
		';

		$actual = graphql( [ 'query' => $query ] );
		codecept_debug( $actual );

		$this->assertEquals( [ $expected ], array_column( $actual['data']['contentTypes']['nodes'], 'name' ) );
	}

	/**
	 * Tests WPGraphQL::get_allowed_taxonomies()
	 */
	public function testGetAllowedTaxonomies() {
		// Test names.
		$expected = get_taxonomies( [ 'show_in_graphql' => true ] );

		$actual = WPGraphQL::get_allowed_taxonomies();

		codecept_debug( $actual );

		$this->assertEqualSets( $expected, $actual );

		// Test objects.
		$expected = get_taxonomies( [ 'show_in_graphql' => true ], 'objects' );

		$actual = WPGraphQL::get_allowed_taxonomies( 'objects' );

		$this->assertEqualSets( $expected, $actual, 'objects not equal' );

		// Test args.
		$expected = get_taxonomies(
			[
				'name' => 'category',
			],
		);

		codecept_debug( $expected );

		$actual = WPGraphQL::get_allowed_taxonomies( 'names', [ 'name' => 'category' ] );
		codecept_debug( $actual );

		$this->assertEqualSets( $expected, $actual );

		// Test filter.
		$expected = 'category';

		add_filter(
			'graphql_term_entities_allowed_taxonomies',
			function ( $names = null, $objects = null ) use ( $expected ) {
				$this->assertContains( $expected, $names );
				$this->assertInstanceOf( \WP_Taxonomy::class, $objects[ $expected ] );

				return [ $expected => $expected ];
			},
			10,
			2
		);

		// Clear cached types.
		WPGraphQL::clear_schema();
		$actual = WPGraphQL::get_allowed_taxonomies();
		$this->assertEquals( [ $expected => $expected ], $actual, 'filter not equal' );

		// Test query.
		$query = '
			query taxonomies {
				taxonomies {
					nodes {
						name
					}
				}
			}
		';

		$actual = graphql( [ 'query' => $query ] );
		codecept_debug( $actual );

		$this->assertEquals( [ $expected ], array_column( $actual['data']['taxonomies']['nodes'], 'name' ) );
	}
}
