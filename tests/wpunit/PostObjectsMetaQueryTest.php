<?php

class PostObjectConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {
	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $created_post_ids;
	public $admin;

	public function setUp() {
		parent::setUp();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
		$this->created_post_ids = $this->create_posts();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Title',
			'post_type'    => 'post',
			'post_date'    => $this->current_date,
			'has_password' => false,
			'post_password'=> null,
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

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;

	}

	/**
	 * Creates several posts (with different timestamps) for use in cursor query tests
	 *
	 * @param  int $count Number of posts to create.
	 * @return array
	 */
	public function create_posts( $count = 20 ) {

		// Create posts
		$created_posts = [];
		for ( $i = 1; $i <= $count; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_posts[ $i ] = $this->createPostObject( [
				'post_type'   => 'post',
				'post_date'   => $date,
				'post_status' => 'publish',
				'post_title'  => $i,
			] );
		}

		return $created_posts;

	}

	private function formatNumber($num) {
		return sprintf('%08d', $num);
	}

	private function numberToMysqlDate($num) {
		return sprintf('2019-03-%02d', $num);
	}

	private function deleteByMetaKey( $key, $value ) {
		$args = array(
			'meta_query' => array(
				array(
					'key' => $key,
					'value' => $value,
					'compare' => '=',
				)
			)
		 );

		 $query = new WP_Query($args);

		 foreach ( $query->posts as $post ) {
			wp_delete_post( $post->ID, true );
		 }
	}

	public function assertMetaQuery( $meta_fields, $posts_per_page = 5 ) {

		add_filter( 'graphql_map_input_fields_to_wp_query', function( $query_args ) use ( $meta_fields ) {
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
				}
			  }
			}
		  }
		";

		$first = do_graphql_request( $query, 'getPosts', [ 'cursor' => '' ] );
		$cursor = $first['data']['posts']['pageInfo']['endCursor'];
		$second = do_graphql_request( $query, 'getPosts', [ 'cursor' => $cursor ] );

		$actual = array_map( function( $edge ) {
			return $edge['node']['title'];
		}, $second['data']['posts']['edges']);

		// Make correspondig WP_Query
		$q = new WP_Query( array_merge( $meta_fields, [
			'post_status' => 'publish',
			'post_type' => 'post',
			'post_author' => $this->admin,
			'posts_per_page' => $posts_per_page,
			'paged' => 2,
		] ) );

		$expected = wp_list_pluck($q->posts, 'post_title');
		// error_log(print_r($expected, true));

		// Aserting like this we get more readable assertion fail message
		$this->assertEquals( implode(',', $expected), implode(',', $actual) );
	}

	public function testPostOrderingByStringMetaKey() {

		// Add post meta to created posts
		foreach ($this->created_post_ids as $index => $post_id) {
			update_post_meta($post_id, 'test_meta', $this->formatNumber( $index ) );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->formatNumber( 6 ) );
		update_post_meta($this->created_post_ids[19], 'test_meta', $this->formatNumber( 6 ) );

		$this->assertMetaQuery( [
			'orderby' => [ 'meta_value' => 'ASC', ],
			'meta_key' => 'test_meta',
		] );

	}


	public function testPostOrderingByDateMetaKey() {

		// Add post meta to created posts
		foreach ($this->created_post_ids as $index => $post_id) {
			update_post_meta( $post_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', $this->numberToMysqlDate( 6 ) );
		update_post_meta( $this->created_post_ids[19], 'test_meta', $this->numberToMysqlDate( 6 ) );

		$this->assertMetaQuery( [
			'orderby' => [ 'meta_value' => 'ASC', ],
			'meta_key' => 'test_meta',
			'meta_type' => 'DATE',
		] );
	}

	public function testPostOrderingByDateMetaKeyDESC() {

		// Add post meta to created posts
		foreach ($this->created_post_ids as $index => $post_id) {
			update_post_meta( $post_id, 'test_meta', $this->numberToMysqlDate( $index ) );
		}

		$this->deleteByMetaKey( 'test_meta', $this->numberToMysqlDate( 14 ) );
		update_post_meta( $this->created_post_ids[2], 'test_meta', $this->numberToMysqlDate( 14 ) );

		$this->assertMetaQuery( [
			'orderby' => [ 'meta_value' => 'DESC', ],
			'meta_key' => 'test_meta',
			'meta_type' => 'DATE',
		] );
	}

	public function testPostOrderingByNumberMetaKey() {

		// Add post meta to created posts
		foreach ($this->created_post_ids as $index => $post_id) {
			update_post_meta($post_id, 'test_meta', $index );
		}

		// Move number 19 to the second page when ordering by test_meta
		$this->deleteByMetaKey( 'test_meta', 6 );
		update_post_meta($this->created_post_ids[19], 'test_meta', 6 );

		$this->assertMetaQuery( [
			'orderby' => [ 'meta_value' => 'ASC', ],
			'meta_key' => 'test_meta',
			'meta_type' => 'UNSIGNED',
		] );
	}

	public function testPostOrderingByNumberMetaKeyDESC() {

		// Add post meta to created posts
		foreach ($this->created_post_ids as $index => $post_id) {
			update_post_meta( $post_id, 'test_meta', $index );
		}

		$this->deleteByMetaKey( 'test_meta', 14 );
		update_post_meta( $this->created_post_ids[2], 'test_meta', 14 );

		$this->assertMetaQuery( [
			'orderby' => [ 'meta_value' => 'DESC', ],
			'meta_key' => 'test_meta',
			'meta_type' => 'UNSIGNED',
		] );
	}

}
