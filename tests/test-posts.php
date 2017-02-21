<?php
/**
 * WPGraphQL Test Post Queries
 *
 * This tests post queries (singular and plural) checking to see if the available fields return the expected response
 *
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_Post_Queries extends WP_UnitTestCase {

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
		$this->admin = $this->factory->user->create( [
			'role' => 'admin',
		] );
	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testFullPostQuery
	 * @since 0.0.5
	 */
	public function testPostId() {

		$current_time = strtotime( 'now' );
		$date = date( 'Y-m-d H:i:s', $current_time );
		$gmdate = gmdate( 'Y-m-d H:i:s', $current_time );

		/**
		 * Set up the $args
		 */
		$args = array(
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_date'    => $date,
			'post_excerpt' => 'Test post excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Page Title',
			'post_type'    => 'page',
		);

		/**
		 * Create the page
		 */
		$page_id = $this->factory->post->create( $args );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'page', $page_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query { 
			post(id: \"{$global_id}\") { 
				id
				author{
					userId
				}
				content
				date
				dateGmt
				desiredSlug
				editLast
				editLock
				enclosure
				excerpt
				title
			} 
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'post' => [
					'id' => $global_id,
					'author' => [
						'userId' => $this->admin,
					],
					'content' => apply_filters( 'the_content', 'Test page content' ),
					'date' => $date,
					'dateGmt' => $gmdate,
					'desiredSlug' => null,
					'editLast' => null,
					'editLock' => null,
					'enclosure' => null,
					'excerpt' => apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', 'Test post excerpt' ) ),
					'title' => 'Test Page Title',
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

}
