<?php
use WPGraphQL\Data\Connection\CommentConnectionResolver;

class CommentObjectCursorTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public function testThresholdFieldsQueryVar() {
		// Skip until bugs in CommentObjectCursor class are resolved.
		$this->markTestIncomplete();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		// Set admin as current user to authorize query commenter's email.
		wp_set_current_user( $this->admin );

		// Create a post.
		$post_id = self::factory()->post->create();

		// Create users and comments.
		$alphabet = range( 'a', 'z' );
		$users    = [];
		$comments = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$letter      = $alphabet[ $i ];
			$users[ $i ] = $this->factory()->user->create(
				[
					'user_email' => "test_{$letter}@test.com",
				]
			);
			for ( $j = 1; $j <= 3; $j++ ) {
				$comments[] = $this->factory()->comment->create(
					[
						'comment_post_ID'  => $post_id,
						'user_id'          => $users[ $i ],
						'comment_content'  => "Test comment content $j",
						'comment_approved' => 1,
					]
				);
			}
		}

		// Register new posts connection.
		register_graphql_connection(
			[
				'fromType'      => 'Post',
				'toType'        => 'Comment',
				'fromFieldName' => 'commentsOrderedByAuthorEmail',
				'resolve'       => static function ( $source, $args, $context, $info ) {
					global $wpdb;
					if ( $source->isRevision ) {
						$id = $source->parentDatabaseId;
					} else {
						$id = $source->ID;
					}

					$resolver = new CommentConnectionResolver( $source, $args, $context, $info );

					$resolver->set_query_arg( 'post_id', absint( $id ) );

					// Get cursor node
					$cursor     = $args['after'] ?? null;
					$cursor     = $cursor ?: ( $args['before'] ?? null );
					$comment_id = substr( base64_decode( $cursor ), strlen( 'arrayconnection:' ) );
					$comment    = get_comment( $comment_id );

					// Get order.
					$order = ! empty( $args['last'] ) ? 'ASC' : 'DESC';

					$resolver->set_query_arg(
						'graphql_cursor_threshold_fields',
						[
							[
								'key'   => "{$wpdb->comments}.comment_author_email",
								'value' => null !== $comment ? $comment->comment_author_email : null,
								'type'  => 'CHAR',
								'order' => $order,
							],
						]
					);

					// Set default ordering.
					if ( empty( $args['where']['orderby'] ) ) {
						$resolver->set_query_arg( 'orderby', 'comment_author_email' );
					}

					if ( empty( $args['where']['order'] ) ) {
						$resolver->set_query_arg( 'order', $order );
					}

					return $resolver->get_connection();
				},
			]
		);

		// Clear cached schema so new fields are seen.
		$this->clearSchema();

		// Create query.
		$query = '
            query ($id: ID!, $first: Int, $after: String, $last: Int, $before: String) {
                post(id: $id) {
                    commentsOrderedByAuthorEmail(first: $first, after: $after, last: $last, before: $before) {
                        nodes {
                            id
                            databaseId
                            content
                            author { node { email } }
                        }
                    }
                }
            }
        ';

		/**
		 * Assert that the query is successful.
		 */
		$variables = [
			'id'    => $this->toRelayId( 'post', $post_id ),
			'first' => 5,
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[2] ) ),
					$this->expectedField( 'databaseId', $comments[2] ),
					$this->expectedField( 'content', 'Test comment content 3' ),
					$this->expectedField( 'author.node.email', 'test_a@test.com' ),
				],
				0
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[1] ) ),
					$this->expectedField( 'databaseId', $comments[1] ),
					$this->expectedField( 'content', 'Test comment content 2' ),
					$this->expectedField( 'author.node.email', 'test_a@test.com' ),
				],
				1
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[0] ) ),
					$this->expectedField( 'databaseId', $comments[0] ),
					$this->expectedField( 'content', 'Test comment content 1' ),
					$this->expectedField( 'author.node.email', 'test_a@test.com' ),
				],
				2
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[5] ) ),
					$this->expectedField( 'databaseId', $comments[5] ),
					$this->expectedField( 'content', 'Test comment content 3' ),
					$this->expectedField( 'author.node.email', 'test_b@test.com' ),
				],
				3
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[4] ) ),
					$this->expectedField( 'databaseId', $comments[4] ),
					$this->expectedField( 'content', 'Test comment content 2' ),
					$this->expectedField( 'author.node.email', 'test_b@test.com' ),
				],
				4
			),
		];
		$this->assertQuerySuccessful( $response, $expected );

		/**
		 * Assert that the query for second batch is successful.
		 */
		$variables = [
			'id'    => $this->toRelayId( 'post', $post_id ),
			'first' => 5,
			'after' => base64_encode( 'arrayconnection:' . $comments[4] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[3] ) ),
					$this->expectedField( 'databaseId', $comments[3] ),
					$this->expectedField( 'content', 'Test comment content 1' ),
					$this->expectedField( 'author.node.email', 'test_b@test.com' ),
				],
				0
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[8] ) ),
					$this->expectedField( 'databaseId', $comments[8] ),
					$this->expectedField( 'content', 'Test comment content 3' ),
					$this->expectedField( 'author.node.email', 'test_c@test.com' ),
				],
				1
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[7] ) ),
					$this->expectedField( 'databaseId', $comments[7] ),
					$this->expectedField( 'content', 'Test comment content 2' ),
					$this->expectedField( 'author.node.email', 'test_c@test.com' ),
				],
				2
			),
			$this->expectedNode(
				'post.commentsOrderedByAuthorEmail.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'comment', $comments[6] ) ),
					$this->expectedField( 'databaseId', $comments[6] ),
					$this->expectedField( 'content', 'Test comment content 1' ),
					$this->expectedField( 'author.node.email', 'test_c@test.com' ),
				],
				3
			),
		];
		$this->assertQuerySuccessful( $response, $expected );
	}
}
