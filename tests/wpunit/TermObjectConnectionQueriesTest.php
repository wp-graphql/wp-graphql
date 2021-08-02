<?php

class TermObjectConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->created_term_ids = $this->create_terms();
	}

	public function tearDown(): void {
		// your tear down methods here

		// then
		parent::tearDown();
	}

	public function createTermObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'taxonomy'    => 'category',
			'description' => 'just a description',
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$term_id = $this->factory()->term->create( $args );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $term_id;

	}

	/**
	 * Creates several posts (with different timestamps) for use in cursor query tests
	 *
	 * @return array
	 */
	public function create_terms() {

		// Create 20 posts
		$created_terms = [];
		for ( $i = 1; $i <= 20; $i ++ ) {
			$term_id             = $this->createTermObject(
				[
					'taxonomy'    => 'category',
					'description' => $i,
					'name'        => $i,
				]
			);
			$created_terms[ $i ] = $term_id;
		}

		return $created_terms;

	}

	public function categoriesQuery( $variables ) {

		$query = 'query categoriesQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToCategoryConnectionWhereArgs){
			categories( first:$first last:$last after:$after before:$before where:$where ) {
				pageInfo {
					hasNextPage
					hasPreviousPage
					startCursor
					endCursor
				}
				edges {
					cursor
					node {
						id
                        categoryId
				        name
				        description
				        slug
					}
				}
				nodes {
				  categoryId
				}
			}
		}';

		return do_graphql_request( $query, 'categoriesQuery', $variables );

	}

	public function testfirstCategory() {
		/**
		 * Here we're querying the first category in our dataset
		 */
		$variables = [
			'first' => 1,
		];
		$results   = $this->categoriesQuery( $variables );

		/**
		 * Let's query the first post in our data set so we can test against it
		 */
		$query = new WP_Term_Query(
			[
				'taxonomy'   => 'category',
				'number'     => 1,
				'parent'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
			]
		);
		$terms = $query->get_terms();

		$first_term_id   = $terms[0]->term_id;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $first_term_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['categories']['edges'] ) );
		$this->assertEquals( $first_term_id, $results['data']['categories']['edges'][0]['node']['categoryId'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['endCursor'] );
		$this->assertEquals( $first_term_id, $results['data']['categories']['nodes'][0]['categoryId'] );
		$this->assertEquals( false, $results['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $results['data']['categories']['pageInfo']['hasNextPage'] );
		$this->forwardPagination( $results['data']['categories']['pageInfo']['endCursor'] );

	}

	public function forwardPagination( $cursor ) {

		$variables = [
			'first' => 1,
			'after' => $cursor,
		];

		$results = $this->categoriesQuery( $variables );

		codecept_debug(  $results );

		$offset = 1;
		$query  = new WP_Term_Query(
			[
				'taxonomy'   => 'category',
				'number'     => 1,
				'offset'     => $offset,
				'parent'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
			]
		);
		$terms  = $query->get_terms();

		$second_term_id  = $terms[ $offset ]->term_id;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $second_term_id );
		$this->assertNotEmpty( $results );

		$this->assertEquals( 1, count( $results['data']['categories']['edges'] ) );
		$this->assertEquals( $second_term_id, $results['data']['categories']['edges'][0]['node']['categoryId'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['endCursor'] );
		$this->assertEquals( true, $results['data']['categories']['pageInfo']['hasPreviousPage'] );
	}

	public function testLastPost() {
		/**
		 * Here we're trying to query the last post in our dataset
		 */
		$variables = [
			'last' => 1,
		];
		$results   = $this->categoriesQuery( $variables );

		/**
		 * Let's query the last post in our data set so we can test against it
		 */
		$query = new WP_Term_Query(
			[
				'taxonomy'   => 'category',
				'number'     => 1,
				'parent'     => 0,
				'orderby'    => 'name',
				'order'      => 'DESC',
				'hide_empty' => false,
			]
		);
		$terms = $query->get_terms();

		$last_term_id    = $terms[0]->term_id;
		$expected_cursor = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $last_term_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['categories']['edges'] ) );
		$this->assertEquals( $last_term_id, $results['data']['categories']['edges'][0]['node']['categoryId'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['endCursor'] );
		$this->assertEquals( true, $results['data']['categories']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $results['data']['categories']['pageInfo']['hasNextPage'] );

		$this->backwardPagination( $expected_cursor );

	}

	public function backwardPagination( $cursor ) {

		$variables = [
			'last'   => 1,
			'before' => $cursor,
		];

		$results = $this->categoriesQuery( $variables );

		$offset = 1;
		$query  = new WP_Term_Query(
			[
				'taxonomy'   => 'category',
				'number'     => 1,
				'parent'     => 0,
				'offset'     => $offset,
				'orderby'    => 'name',
				'order'      => 'DESC',
				'hide_empty' => false,
			]
		);
		$terms  = $query->get_terms();

		$second_last_term_id = $terms[ $offset ]->term_id;
		$expected_cursor     = \GraphQLRelay\Connection\ArrayConnection::offsetToCursor( $second_last_term_id );
		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, count( $results['data']['categories']['edges'] ) );
		$this->assertEquals( $second_last_term_id, $results['data']['categories']['edges'][0]['node']['categoryId'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['edges'][0]['cursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['startCursor'] );
		$this->assertEquals( $expected_cursor, $results['data']['categories']['pageInfo']['endCursor'] );
		$this->assertEquals( true, $results['data']['categories']['pageInfo']['hasNextPage'] );

	}

	public function testQueryTermsWithOrderbyAndOrder() {

		$category_id = $this->factory()->term->create([
			'taxonomy' => 'category',
			'name' => 'high count'
		]);

		for ( $x = 0; $x <= 10; $x++) {
			$post_id = $this->factory()->post->create([
				'post_type' => 'post',
				'post_status' => 'publish',
			]);

			wp_set_object_terms( $post_id, [ $category_id ], 'category' );
		}

		$query = '
		query GetCategoriesWithCustomOrder( $order:OrderEnum ){
		  categories( where: { orderby: COUNT order: $order } ) {
		    nodes {
		      id
		      databaseId
		      name
		      count
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'order' => 'DESC'
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $category_id, $actual['data']['categories']['nodes'][0]['databaseId'] );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'order' => 'ASC'
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $category_id !== $actual['data']['categories']['nodes'][0]['databaseId'] );

	}

}
