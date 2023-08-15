<?php
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;

class PostObjectCursorTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;

	public function setUp(): void {
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
		$this->start_count      = 100;
		$this->start_time       = time();
		$this->created_post_ids = $this->create_posts();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'   => $this->admin,
			'post_content'  => 'Test page content',
			'post_excerpt'  => 'Test excerpt',
			'post_status'   => 'publish',
			'post_title'    => 'Test Title for PostObjectCursorTest',
			'post_name'     => "test-post-{$this->start_count}",
			'post_type'     => 'post',
			'post_date'     => $this->current_date,
			'has_password'  => false,
			'post_password' => null,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 *
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		update_post_meta( $post_id, 'test_meta_date', $this->start_time - $this->start_count );
		update_post_meta( $post_id, 'test_meta_number', $this->start_count );

		$this->start_count -= 1;

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;

	}

	/**
	 * Creates several posts (with different timestamps) for use in cursor query tests
	 *
	 * @param int $count Number of posts to create.
	 * @return array
	 */
	public function create_posts( $count = 20 ) {
		// Ensure that ordering by titles is different from ordering by ids
		$titles = 'qwertyuiopasdfghjklzxcvbnm';

		// Create posts
		$created_posts = [];
		for ( $i = 1; $i <= $count; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject( [
				'post_type'   => 'post',
				'post_date'   => $date,
				'post_status' => 'publish',
				'post_title'  => $titles[ $i % strlen( $titles ) ],
			] );
		}

		return $created_posts;

	}

	private function formatNumber( $num ) {
		return sprintf( '%08d', $num );
	}

	private function numberToMysqlDate( $num ) {
		return sprintf( '2019-03-%02d', $num );
	}

	private function deleteByMetaKey( $key, $value ) {
		$args = [
			'meta_query' => [
				[
					'key'     => $key,
					'value'   => $value,
					'compare' => '=',
				],
			],
		];

		$query = new WP_Query( $args );

		foreach ( $query->posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Assert given query fields in a GraphQL post cursor against a plain WP Query
	 */
	public function assertQueryInCursor( $meta_fields, $posts_per_page = 5 ) {

		add_filter( 'graphql_map_input_fields_to_wp_query', function ( $query_args ) use ( $meta_fields ) {
			return array_merge( $query_args, $meta_fields );
		}, 10, 1 );

		// Must use dummy where args here to force
		// graphql_map_input_fields_to_wp_query to be executes
		$query = "
		query getPosts(\$cursor: String) {
			posts(after: \$cursor, first: $posts_per_page, where: {author: {$this->admin}}) {
				pageInfo {
				endCursor
				}
				edges {
					node {
						title
						postId
					}
				}
			}
			}
		";

		$first = do_graphql_request( $query, 'getPosts', [ 'cursor' => '' ] );
		$this->assertArrayNotHasKey( 'errors', $first, print_r( $first, true ) );

		$first_page_actual = array_map( function ( $edge ) {
			return $edge['node']['postId'];
		}, $first['data']['posts']['edges']);

		$cursor = $first['data']['posts']['pageInfo']['endCursor'];
		$second = do_graphql_request( $query, 'getPosts', [ 'cursor' => $cursor ] );
		$this->assertArrayNotHasKey( 'errors', $second, print_r( $second, true ) );

		$second_page_actual = array_map( function ( $edge ) {
			return $edge['node']['postId'];
		}, $second['data']['posts']['edges']);

		// Make corresponding WP_Query.
		WPGraphQL::set_is_graphql_request( true );
		$first_page = new WP_Query( array_merge( $meta_fields, [
			'graphql_cursor_compare' => '<', // Without this, the query won't hit the cursor logic.
			'post_status'            => 'publish',
			'post_type'              => 'post',
			'post_author'            => $this->admin,
			'posts_per_page'         => $posts_per_page,
			'paged'                  => 1,
		] ) );

		$second_page = new WP_Query( array_merge( $meta_fields, [
			'graphql_cursor_compare' => '<', // Without this, the query won't hit the cursor logic.
			'post_status'            => 'publish',
			'post_type'              => 'post',
			'post_author'            => $this->admin,
			'posts_per_page'         => $posts_per_page,
			'paged'                  => 2,
		] ) );
		WPGraphQL::set_is_graphql_request( true );

		$first_page_expected  = wp_list_pluck( $first_page->posts, 'ID' );
		$second_page_expected = wp_list_pluck( $second_page->posts, 'ID' );

		// Aserting like this we get more readable assertion fail message
		$this->assertEquals( implode( ',', $first_page_expected ), implode( ',', $first_page_actual ), 'First page' );
		$this->assertEquals( implode( ',', $second_page_expected ), implode( ',', $second_page_actual ), 'Second page' );
	}

	/**
	 * Test default order
	 */
	public function testDefaultPostOrdering() {
		$this->assertQueryInCursor( [] );
	}

	/**
	 * Simple title ordering test
	 */
	public function testPostOrderingByPostTitleDefault() {
		$this->assertQueryInCursor( [
			'orderby' => 'post_title',
		] );
	}

	/**
	 * Simple title ordering test by ASC
	 */
	public function testPostOrderingByPostTitleASC() {
		$this->assertQueryInCursor( [
			'orderby' => 'post_title',
			'order'   => 'ASC',
		] );
	}

	/**
	 * Simple title ordering test by ASC
	 */
	public function testPostOrderingByPostTitleDESC() {
		$this->assertQueryInCursor( [
			'orderby' => 'post_title',
			'order'   => 'DESC',
		] );
	}

	public function testPostOrderingByDuplicatePostTitles() {
		foreach ( $this->created_post_ids as $index => $post_id ) {
			wp_update_post( [
				'ID'         => $post_id,
				'post_title' => 'duptitle',

			] );
		}

		$this->assertQueryInCursor( [
			'orderby' => 'post_title',
			'order'   => 'DESC',
		] );
	}

	public function testPostOrderingByMetaString() {

		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_post_meta( $this->created_post_ids[19], 'test_meta', $this->formatNumber( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'  => [ 'meta_value' => 'ASC' ],
			'meta_key' => 'test_meta',
		] );

	}


	public function testPostOrderingByMetaDate() {

		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->numberToMysqlDate( 6 ) );
		update_post_meta( $this->created_post_ids[19], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'ASC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'DATE',
		] );
	}

	public function testPostOrderingByMetaDateDESC() {

		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		$this->deleteByMetaKey( 'test_meta', $this->numberToMysqlDate( 14 ) );
		update_post_meta( $this->created_post_ids[2], 'test_meta', $this->numberToMysqlDate( 14 ) );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'DESC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'DATE',
		] );
	}

	public function testPostOrderingByMetaNumber() {

		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $index );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 6 );
		update_post_meta( $this->created_post_ids[19], 'test_meta', 6 );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'ASC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'UNSIGNED',
		] );
	}

	public function testPostOrderingByMetaNumberDESC() {

		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $index );
		}

		$this->deleteByMetaKey( 'test_meta', 14 );
		update_post_meta( $this->created_post_ids[2], 'test_meta', 14 );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'DESC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'UNSIGNED',
		] );
	}

	public function testPostOrderingWithMetaFiltering() {
		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $index );
		}

		// Move number 2 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 15 );
		update_post_meta( $this->created_post_ids[2], 'test_meta', 15 );

		$this->assertQueryInCursor( [
			'orderby'    => [ 'meta_value' => 'ASC' ],
			'meta_key'   => 'test_meta',
			'meta_type'  => 'UNSIGNED',
			'meta_query' => [
				[
					'key'     => 'test_meta',
					'compare' => '>',
					'value'   => 10,
					'type'    => 'UNSIGNED',
				],
			],
		], 3 );

	}

	public function testPostOrderingByMetaQueryClause() {

		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_post_meta( $this->created_post_ids[19], 'test_meta', $this->formatNumber( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'    => [ 'test_clause' => 'ASC' ],
			'meta_query' => [
				'test_clause' => [
					'key'     => 'test_meta',
					'compare' => 'EXISTS',
				],
			],
		] );
	}

	public function testPostOrderingByMetaQueryClauseString() {

		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_post_meta( $this->created_post_ids[19], 'test_meta', $this->formatNumber( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'    => 'test_clause',
			'order'      => 'ASC',
			'meta_query' => [
				'test_clause' => [
					'key'     => 'test_meta',
					'compare' => 'EXISTS',
				],
			],
		] );

	}

	/**
	* When ordering posts with the same meta value the returned order can vary if
	* there isn't a second ordering field. This test does not fail every time
	* so it tries to execute the assertion multiple times to make happen more often
	*/
	public function testPostOrderingStability() {

		add_filter( 'is_graphql_request', '__return_true' );

		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		update_post_meta( $this->created_post_ids[19], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'ASC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'DATE',
		] );

		update_post_meta( $this->created_post_ids[17], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'ASC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'DATE',
		] );

		update_post_meta( $this->created_post_ids[18], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertQueryInCursor( [
			'orderby'   => [ 'meta_value' => 'ASC' ],
			'meta_key'  => 'test_meta',
			'meta_type' => 'DATE',
		] );

	}

	/**
	 * Test support for meta_value_num
	 */
	public function testPostOrderingByMetaValueNum() {

		// Add post meta to created posts
		foreach ( $this->created_post_ids as $index => $post_id ) {
			update_post_meta( $post_id, 'test_meta', $index );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 6 );
		update_post_meta( $this->created_post_ids[19], 'test_meta', 6 );

		$this->deleteByMetaKey( 'test_meta', 16 );
		update_post_meta( $this->created_post_ids[2], 'test_meta', 16 );

		$this->assertQueryInCursor( [
			'orderby'  => 'meta_value_num',
			'order'    => 'ASC',
			'meta_key' => 'test_meta',
		] );
	}

	public function testPostOrderingByMultipleMeta() {
		// Add fields to Post type.
		register_graphql_fields(
			'Post',
			[
				'testMetaDate' => [
					'type'    => 'String',
					'resolve' => function( $source ) {
						return get_post_meta( $source->ID, 'test_meta_date', true );
					}
				],
				'testMetaNumber' => [
					'type'    => 'Number',
					'resolve' => function( $source ) {
						return get_post_meta( $source->ID, 'test_meta_number', true );
					}
				],
			]
		);

		// Register orderby enum values.
		add_filter(
			'graphql_PostObjectsConnectionOrderbyEnum_values',
			function( $values ) {
				$values['TEST_META_DATE'] = 'test_meta_date';
				$values['TEST_META_NUMBER'] = 'test_meta_number';
				return $values;
			}
		);

		// Process meta fields in orderby.
		add_filter(
			'graphql_post_object_connection_query_args',
			function ( $query_args ) {
				if ( isset( $query_args['orderby'] ) && is_array( $query_args['orderby'] ) ) {
					foreach( $query_args['orderby'] as $field => $order ) {
						if ( in_array( $field, [ 'test_meta_date', 'test_meta_number' ], true ) ) {
							if ( empty( $query_args['meta_query'] ) ) {
								$query_args['meta_query'] = [];
							}
							$query_args['meta_query'][] = [
								'key' => $field,
								'type' => 'numeric',
								'compare' => 'EXISTS',
							];
						}
					}
				} elseif ( isset( $query_args['orderby'] ) && is_string( $query_args['orderby'] ) ) {
					if ( in_array( $query_args['orderby'], [ 'test_meta_date', 'test_meta_number' ], true ) ) {
						if ( empty( $query_args['meta_query'] ) ) {
							$query_args['meta_query'] = [];
						}
						$query_args['meta_query'][] = [
							'key' => $query_args['orderby'],
							'compare' => 'EXISTS',
						];
					}
				} 

				return $query_args;
			},
		);
		// Clear cached schema so new fields are seen.
		$this->clearSchema();

		$query    = '
			query ($first: Int, $last: Int, $before: String, $after: String, $where: RootQueryToPostConnectionWhereArgs!) {
				posts(first: $first, last: $last, before: $before, after: $after, where: $where) {
					nodes {
						id
						databaseId
						testMetaDate
						testMetaNumber
					}
				}
			}
		';

		$variables = [
			'first' => 5,
			'where' => [
				'orderby' => [
					[
						'field' => 'TEST_META_NUMBER',
						'order' => 'ASC',
					],
					[
						'field' => 'TEST_META_DATE',
						'order' => 'DESC',
					],
				]
			]
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[20] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 19) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 19) ),
				],
				0
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[19] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 18) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 18) ),
				],
				1
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[18] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 17) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 17) ),
				],
				2
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[17] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 16) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 16) ),
				],
				3
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[16] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 15) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 15) ),
				],
				4
			),
		];

		$this->assertQuerySuccessful( $response, $expected );

		$variables = [
			'first' => 5,
			'where' => [
				'orderby' => [
					[
						'field' => 'TEST_META_NUMBER',
						'order' => 'ASC',
					],
					[
						'field' => 'TEST_META_DATE',
						'order' => 'DESC',
					],
				],
			],
			'after' => base64_encode( 'arrayconnection:' . $this->created_post_ids[16] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[15] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 14) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 14) ),
				],
				0
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[14] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 13) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 13) ),
				],
				1
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[13] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 12) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 12) ),
				],
				2
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[12] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 11) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 11) ),
				],
				3
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[11] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - (100 - 10) ) ),
					$this->expectedField( 'testMetaNumber', floatval(100 - 10) ),
				],
				4
			),
		];

		$this->assertQuerySuccessful( $response, $expected );

		$variables = [
			'last' => 5,
			'where' => [
				'orderby' => [
					[
						'field' => 'TEST_META_DATE',
						'order' => 'DESC',
					],
					[
						'field' => 'TEST_META_NUMBER',
						'order' => 'ASC',
					],
				],
			],
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [			
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[5] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 96 ) ),
					$this->expectedField( 'testMetaNumber', floatval(96) ),
				],
				0
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[4] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 97 ) ),
					$this->expectedField( 'testMetaNumber', floatval(97) ),
				],
				1
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[3] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 98 ) ),
					$this->expectedField( 'testMetaNumber', floatval(98) ),
				],
				2
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[2] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 99 ) ),
					$this->expectedField( 'testMetaNumber', floatval(99) ),
				],
				3
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[1] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 100 ) ),
					$this->expectedField( 'testMetaNumber', floatval(100) ),
				],
				4
			),
		];

		$this->assertQuerySuccessful( $response, $expected );

		$variables = [
			'last' => 5,
			'where' => [
				'orderby' => [
					[
						'field' => 'TEST_META_DATE',
						'order' => 'DESC',
					],
					[
						'field' => 'TEST_META_NUMBER',
						'order' => 'ASC',
					],
				],
			],
			'before' => base64_encode( 'arrayconnection:' . $this->created_post_ids[5] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[10] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 91 ) ),
					$this->expectedField( 'testMetaNumber', floatval(91) ),
				],
				0
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[9] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 92 ) ),
					$this->expectedField( 'testMetaNumber', floatval(92) ),
				],
				1
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[8] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 93 ) ),
					$this->expectedField( 'testMetaNumber', floatval(93) ),
				],
				2
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[7] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 94 ) ),
					$this->expectedField( 'testMetaNumber', floatval(94) ),
				],
				3
			),
			$this->expectedNode(
				'posts.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[6] ) ),
					$this->expectedField( 'testMetaDate', strval( $this->start_time - 95 ) ),
					$this->expectedField( 'testMetaNumber', floatval(95) ),
				],
				4
			),
		];

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testThresholdFieldsQueryVar() {
		// Register new posts connection.
		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'Post',
				'fromFieldName'  => 'postsOrderedBySlug',
				'resolve'        => function ( $source, $args, $context, $info ) {
					global $wpdb;
					$resolver = new PostObjectConnectionResolver( $source, $args, $context, $info, 'post' );

					// Get cursor node
					$cursor  = $args['after'] ?? null;
					$cursor  = $cursor ?: ( $args['before'] ?? null );
					$post_id = substr( base64_decode( $cursor ), strlen( 'arrayconnection:' ) );
					$post    = get_post( $post_id );

					// Get order.
					$order   = ! empty( $args['last'] ) ? 'ASC' : 'DESC';

					$resolver->set_query_arg(
						'graphql_cursor_threshold_fields',
						[
							[
								'key'   => "{$wpdb->posts}.post_name",
								'value' => null !== $post ? $post->post_name : null,
								'type'  => 'CHAR',
								'order' => $order,
							],
						]
					);

					// Set default ordering.
					if ( empty( $args['where']['orderby'] ) ) {
						$resolver->set_query_arg( 'orderby', 'post_name' );
					}

					if ( empty( $args['where']['order'] ) ) {
						$resolver->set_query_arg( 'order', $order );
					}

					return $resolver->get_connection();
				}
			]
		);

		// Clear cached schema so new fields are seen.
		$this->clearSchema();

		// Create query.
		$query = '
			query ($first: Int, $last: Int, $before: String, $after: String) {
				postsOrderedBySlug(first: $first, last: $last, before: $before, after: $after) {
					nodes {
						id
						databaseId
						slug
						date
					}
				}
			}
		';

		

		/**
		 * Assert that the query is successful.
		 */
		$variables = [ 'first' => 5 ];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[2] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[2] ),
					$this->expectedField( 'slug', 'test-post-99' ),
				],
				0
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[3] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[3] ),
					$this->expectedField( 'slug', 'test-post-98' ),
				],
				1
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[4] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[4] ),
					$this->expectedField( 'slug', 'test-post-97' ),
				],
				2
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[5] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[5] ),
					$this->expectedField( 'slug', 'test-post-96' ),
				],
				3
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[6] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[6] ),
					$this->expectedField( 'slug', 'test-post-95' ),
				],
				4
			),
		];
		$this->assertQuerySuccessful( $response, $expected );

		/**
		 * Assert that the query for second batch is successful.
		 */
		$variables = [
			'first' => 5,
			'after' => base64_encode( 'arrayconnection:' . $this->created_post_ids[6] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[7] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[7] ),
					$this->expectedField( 'slug', 'test-post-94' ),
				],
				0
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[8] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[8] ),
					$this->expectedField( 'slug', 'test-post-93' ),
				],
				1
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[9] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[9] ),
					$this->expectedField( 'slug', 'test-post-92' ),
				],
				2
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[10] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[10] ),
					$this->expectedField( 'slug', 'test-post-91' ),
				],
				3
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[11] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[11] ),
					$this->expectedField( 'slug', 'test-post-90' ),
				],
				4
			),
		];
		$this->assertQuerySuccessful( $response, $expected );

		/**
		 * Assert that the reverse query is successful.
		 */
		$variables = [ 'last' => 5 ];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[17] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[17] ),
					$this->expectedField( 'slug', 'test-post-84' ),
				],
				0
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[18] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[18] ),
					$this->expectedField( 'slug', 'test-post-83' ),
				],
				1
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[19] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[19] ),
					$this->expectedField( 'slug', 'test-post-82' ),
				],
				2
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[20] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[20] ),
					$this->expectedField( 'slug', 'test-post-81' ),
				],
				3
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[1] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[1] ),
					$this->expectedField( 'slug', 'test-post-100' ),
				],
				4
			),
			
			
		];
		$this->assertQuerySuccessful( $response, $expected );

		/**
		 * Assert that the query for second batch is successful.
		 */
		$variables = [
			'last'   => 5,
			'before' => base64_encode( 'arrayconnection:' . $this->created_post_ids[17] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[12] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[12] ),
					$this->expectedField( 'slug', 'test-post-89' ),
				],
				0
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[13] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[13] ),
					$this->expectedField( 'slug', 'test-post-88' ),
				],
				1
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[14] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[14] ),
					$this->expectedField( 'slug', 'test-post-87' ),
				],
				2
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[15] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[15] ),
					$this->expectedField( 'slug', 'test-post-86' ),
				],
				3
			),
			$this->expectedNode(
				'postsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'post', $this->created_post_ids[16] ) ),
					$this->expectedField( 'databaseId', $this->created_post_ids[16] ),
					$this->expectedField( 'slug', 'test-post-85' ),
				],
				4
			),
		];
		$this->assertQuerySuccessful( $response, $expected );
	}
}
