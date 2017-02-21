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

		$this->current_time = strtotime( 'now' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
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

	public function createPostObject( $args ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = array(
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_date'    => $this->current_date,
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Title',
			'post_type'    => 'post',
		);

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = wp_parse_args( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
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
	 * testPostQuery
	 * @since 0.0.5
	 */
	public function testPostQuery() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject( [ 'post_type' => 'post' ] );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

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
				commentCount
				commentStatus
				content
				date
				dateGmt
				desiredSlug
				editLast{
					userId
				}
				editLock{
					editTime
					user{
						userId
					}
				}
				enclosure
				excerpt
				link
				menuOrder
				mimeType
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
					'commentCount' => 0,
					'commentStatus' => 'open',
					'content' => apply_filters( 'the_content', 'Test page content' ),
					'date' => $this->current_date,
					'dateGmt' => $this->current_date_gmt,
					'desiredSlug' => null,
					'editLast' => [
						'userId' => $this->admin,
					],
					'editLock' => [
						'editTime' => $this->current_date,
						'user' => [
							'userId' => $this->admin,
						],
					],
					'enclosure' => null,
					'excerpt' => apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', 'Test excerpt' ) ),
					'link' => get_permalink( $post_id ),
					'menuOrder' => null,
					'mimeType' => null,
					'title' => 'Test Title',
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

}
