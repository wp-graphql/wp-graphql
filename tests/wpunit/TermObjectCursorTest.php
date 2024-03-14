<?php
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;

class TermObjectCursorTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public function tearDown(): void {
		unregister_taxonomy( 'letter' );
		parent::tearDown();
	}

	public function testThresholdFieldsQueryVar() {
		// Create taxonomy.
		register_taxonomy(
			'letter',
			[ 'post' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'Letter',
				'graphql_plural_name' => 'Letters',
			]
		);
		// Create category terms.
		$alphabet = range( 'a', 'z' );
		$term_ids = [];
		foreach ( $alphabet as $letter ) {
			$term_ids[] = $this->factory()->term->create(
				[
					'name'     => ucwords( "Letter {$letter}" ),
					'taxonomy' => 'letter',
					'slug'     => "letter-{$letter}",
				]
			);
		}

		// Create connection.
		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'TermNode',
				'fromFieldName' => 'termsOrderedBySlug',
				'resolve'       => static function ( $source, $args, $context, $info ) {
					global $wpdb;
					$resolver = new TermObjectConnectionResolver( $source, $args, $context, $info, 'letter' );

					// Get cursor node
					$cursor  = $args['after'] ?? null;
					$cursor  = $cursor ?: ( $args['before'] ?? null );
					$term_id = substr( base64_decode( $cursor ), strlen( 'arrayconnection:' ) );
					$term    = $term_id ? get_term( $term_id ) : null;

					// Get order.
					$order = ! empty( $args['last'] ) ? 'ASC' : 'DESC';

					$resolver->set_query_arg(
						'graphql_cursor_threshold_fields',
						[
							[
								'key'   => "{$wpdb->terms}.slug",
								'value' => ( null !== $term && ! is_wp_error( $term ) ) ? $term->name : null,
								'type'  => 'CHAR',
							],
						]
					);

					// Set default ordering.
					if ( empty( $args['where']['orderby'] ) ) {
						$resolver->set_query_arg( 'orderby', 'slug' );
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
			query ($first: Int, $last: Int, $before: String, $after: String) {
				termsOrderedBySlug(first: $first, last: $last, before: $before, after: $after) {
					nodes {
						id
						databaseId
						slug
						name
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
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[25] ) ),
					$this->expectedField( 'databaseId', $term_ids[25] ),
					$this->expectedField( 'slug', 'letter-z' ),
					$this->expectedField( 'name', 'Letter Z' ),
				],
				0
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[24] ) ),
					$this->expectedField( 'databaseId', $term_ids[24] ),
					$this->expectedField( 'slug', 'letter-y' ),
					$this->expectedField( 'name', 'Letter Y' ),
				],
				1
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[23] ) ),
					$this->expectedField( 'databaseId', $term_ids[23] ),
					$this->expectedField( 'slug', 'letter-x' ),
					$this->expectedField( 'name', 'Letter X' ),
				],
				2
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[22] ) ),
					$this->expectedField( 'databaseId', $term_ids[22] ),
					$this->expectedField( 'slug', 'letter-w' ),
					$this->expectedField( 'name', 'Letter W' ),
				],
				3
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[21] ) ),
					$this->expectedField( 'databaseId', $term_ids[21] ),
					$this->expectedField( 'slug', 'letter-v' ),
					$this->expectedField( 'name', 'Letter V' ),
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
			'after' => base64_encode( 'arrayconnection:' . $term_ids[21] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[20] ) ),
					$this->expectedField( 'databaseId', $term_ids[20] ),
					$this->expectedField( 'slug', 'letter-u' ),
					$this->expectedField( 'name', 'Letter U' ),
				],
				0
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[19] ) ),
					$this->expectedField( 'databaseId', $term_ids[19] ),
					$this->expectedField( 'slug', 'letter-t' ),
					$this->expectedField( 'name', 'Letter T' ),
				],
				1
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[18] ) ),
					$this->expectedField( 'databaseId', $term_ids[18] ),
					$this->expectedField( 'slug', 'letter-s' ),
					$this->expectedField( 'name', 'Letter S' ),
				],
				2
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[17] ) ),
					$this->expectedField( 'databaseId', $term_ids[17] ),
					$this->expectedField( 'slug', 'letter-r' ),
					$this->expectedField( 'name', 'Letter R' ),
				],
				3
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[16] ) ),
					$this->expectedField( 'databaseId', $term_ids[16] ),
					$this->expectedField( 'slug', 'letter-q' ),
					$this->expectedField( 'name', 'Letter Q' ),
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
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[4] ) ),
					$this->expectedField( 'databaseId', $term_ids[4] ),
					$this->expectedField( 'slug', 'letter-e' ),
					$this->expectedField( 'name', 'Letter E' ),
				],
				0
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[3] ) ),
					$this->expectedField( 'databaseId', $term_ids[3] ),
					$this->expectedField( 'slug', 'letter-d' ),
					$this->expectedField( 'name', 'Letter D' ),
				],
				1
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[2] ) ),
					$this->expectedField( 'databaseId', $term_ids[2] ),
					$this->expectedField( 'slug', 'letter-c' ),
					$this->expectedField( 'name', 'Letter C' ),
				],
				2
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[1] ) ),
					$this->expectedField( 'databaseId', $term_ids[1] ),
					$this->expectedField( 'slug', 'letter-b' ),
					$this->expectedField( 'name', 'Letter B' ),
				],
				3
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[0] ) ),
					$this->expectedField( 'databaseId', $term_ids[0] ),
					$this->expectedField( 'slug', 'letter-a' ),
					$this->expectedField( 'name', 'Letter A' ),
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
			'before' => base64_encode( 'arrayconnection:' . $term_ids[4] ),
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[9] ) ),
					$this->expectedField( 'databaseId', $term_ids[9] ),
					$this->expectedField( 'slug', 'letter-j' ),
					$this->expectedField( 'name', 'Letter J' ),
				],
				0
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[8] ) ),
					$this->expectedField( 'databaseId', $term_ids[8] ),
					$this->expectedField( 'slug', 'letter-i' ),
					$this->expectedField( 'name', 'Letter I' ),
				],
				1
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[7] ) ),
					$this->expectedField( 'databaseId', $term_ids[7] ),
					$this->expectedField( 'slug', 'letter-h' ),
					$this->expectedField( 'name', 'Letter H' ),
				],
				2
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[6] ) ),
					$this->expectedField( 'databaseId', $term_ids[6] ),
					$this->expectedField( 'slug', 'letter-g' ),
					$this->expectedField( 'name', 'Letter G' ),
				],
				3
			),
			$this->expectedNode(
				'termsOrderedBySlug.nodes',
				[
					$this->expectedField( 'id', $this->toRelayId( 'term', $term_ids[5] ) ),
					$this->expectedField( 'databaseId', $term_ids[5] ),
					$this->expectedField( 'slug', 'letter-f' ),
					$this->expectedField( 'name', 'Letter F' ),
				],
				4
			),
		];

		$this->assertQuerySuccessful( $response, $expected );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/pull/3063
	 * @return void
	 */
	public function testWpTermQueryCursorPaginationSupport() {

		$category = self::factory()->term->create( [ 'taxonomy' => 'category' ] );
		$child_category = self::factory()->term->create( [ 'taxonomy' => 'category', 'parent' => $category ] );

		$actual_pieces = null;

		// hook into the terms_clauses filter after WPGraphQL hooks in
		add_filter( 'terms_clauses', function( $pieces, $taxonomies, $args ) use ( &$actual_pieces ) {
			$actual_pieces = $pieces;
			return $pieces;
		} , 99, 3 );

		$query = '
		query terms ($parent: Int) {
		  categories(where: {
		    parent: $parent
		  }) {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';

		$actual = $this->graphql([
			'query' => $query,
			'variables' => [
				'parent' => $category
			]
		]);

//		This is a note of what the "pieces" looked like in v1.22.0 before
//      this fix: https://github.com/wp-graphql/wp-graphql/pull/3063
//
//      The "where" arg was empty, but now it's not empty.
//
//		$prev_pieces = [
//			'fields' => 't.term_id',
//			'join' => 'INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id',
//			'where' => '',
//			'distinct' => null,
//			'order_by' => 'ORDER BY FIELD( t.term_id, 29 )',
//			'order' => 'ASC',
//			'limits' => null,
//		];

//		codecept_debug( [
//			'$actual' => $actual,
//			'$actual_pieces' => $actual_pieces,
//		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedNode(
				'categories.nodes',
				[
					$this->expectedField( '__typename', 'Category' ),
					$this->expectedField( 'databaseId', $child_category ),
				],
				0
			),
		] );

		$this->assertNotEmpty( $actual_pieces['where'] );

	}
}
