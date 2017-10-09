<?php

/**
 * WPGraphQL Test Comment Object Queries
 * This tests comment queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_Comment_Object_Queries extends WP_UnitTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;

	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function createCommentObject( $args = [] ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'comment_author'   => $this->admin,
			'comment_content'  => 'Test comment content',
			'comment_approved' => 1,
			'comment_date'     => $this->current_date,
			'comment_date_gmt' => $this->current_date_gmt,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$comment_id = $this->factory->comment->create( $args );

		/**
		 * Return the $id of the comment_object that was created
		 */
		return $comment_id;

	}

	/**
	 * testCommentQuery
	 *
	 * This tests creating a single comment with data and retrieving said comment via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testCommentQuery() {

		/**
		 * Create a comment
		 */
		$comment_id = $this->createCommentObject( [
			'user_id' => $this->admin,
		] );

		/**
		 * Create the global ID based on the comment_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			comment(id: \"{$global_id}\") {
				agent
				approved
				author {
					userId
					email
					name
				}
				authorIp
				children {
					edges {
						node {
							id
						}
					}
				}
				commentId
				commentedOn {
					... on post {
						id
					}
				}
				content
				date
				dateGmt
				id
				karma
				parent {
					id
				}
				type
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
				'comment' => [
					'agent'       => '',
					'approved'    => '1',
					'author'      => [
						'userId'  => $this->admin,
						'email'   => get_userdata( $this->admin )->user_email,
						'name'    => get_userdata( $this->admin )->display_name,
					],
					'authorIp'    => '',
					'children'    => [
						'edges' => [],
					],
					'commentId'   => $comment_id,
					'commentedOn' => null,
					'content'     => 'Test comment content',
					'date'        => $this->current_date,
					'dateGmt'     => $this->current_date_gmt,
					'id'          => $global_id,
					'karma'       => 0,
					'parent'      => null,
					'type'        => null,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testNotUserCommentQuery
	 *
	 * This tests creating a single comment that is not associated to a user. With data and retrieving said comment via a
	 * GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testNotUserCommentQuery() {

		/**
		 * Create a comment that is not associated to a user
		 */
		$comment_id = $this->createCommentObject( [
			'user_id' => 0,
			'comment_author_email' => 'test@example.org',
			'comment_author_url'   => 'https://example.org',
		] );

		/**
		 * Create the global ID based on the comment_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			comment(id: \"{$global_id}\") {
				agent
				approved
				author {
					userId
					email
					url
				}
				authorIp
				children {
					edges {
						node {
							id
						}
					}
				}
				commentId
				commentedOn {
					... on post {
						id
					}
				}
				content
				date
				dateGmt
				id
				karma
				parent {
					id
				}
				type
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
				'comment' => [
					'agent'       => '',
					'approved'    => '1',
					'author'      => [
						'userId'  => null,
						'email'   => 'test@example.org',
						//'name'    => get_userdata( $this->admin )->display_name,
						//'name'    => 'Test Not a User',
						'url'     => 'https://example.org',
					],
					'authorIp'    => '',
					'children'    => [
						'edges' => [],
					],
					'commentId'   => $comment_id,
					'commentedOn' => null,
					'content'     => 'Test comment content',
					'date'        => $this->current_date,
					'dateGmt'     => $this->current_date_gmt,
					'id'          => $global_id,
					'karma'       => 0,
					'parent'      => null,
					'type'        => '',
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testCommentQueryWithChildrenAssignedPostAndParent
	 *
	 * This tests creating a single comment with data and retrieving said comment via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testCommentQueryWithChildrenAssignedPostAndParent() {

		// Post object to assign comments to.
		$post_id = $this->factory->post->create([
			'post_content' => 'Post object',
		]);

		// Parent comment.
		$parent_comment = $this->createCommentObject(
			[
				'comment_post_ID' => $post_id,
				'comment_content' => 'Parent comment',
			]
		);

		/**
		 * Create a comment
		 */
		$comment_id = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_content' => 'Test comment',
			'comment_parent'  => $parent_comment,
		] );

		// Create child comments.
		$child_1 = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_content' => 'Child 1',
			'comment_parent'  => $comment_id,
		] );

		$child_2 = $this->createCommentObject( [
			'comment_post_ID' => $post_id,
			'comment_content' => 'Child 2',
			'comment_parent'  => $comment_id,
		] );

		/**
		 * Create the global ID based on the comment_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'comment', $comment_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			comment(id: \"{$global_id}\") {
				children {
					edges {
						node {
							commentId
							content
						}
					}
				}
				commentId
				commentedOn {
					... on post {
						content
					}
				}
				content
				parent {
					commentId
					content
				}
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
				'comment' => [
					'children' => [
						'edges' => [
							[
								'node' => [
									'commentId' => $child_2,
									'content' => 'Child 2',
								],
							],
							[
								'node' => [
									'commentId' => $child_1,
									'content' => 'Child 1',
								],
							],
						],
					],
					'commentId' => $comment_id,
					'commentedOn' => [
						'content' => apply_filters( 'the_content', 'Post object' ),
					],
					'content' => 'Test comment',
					'parent' => [
						'commentId' => $parent_comment,
						'content' => 'Parent comment',
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

}
