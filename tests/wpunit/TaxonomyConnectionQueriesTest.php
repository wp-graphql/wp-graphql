<?php

class TaxonomyConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var array|string[]|WP_Taxonomy[]
	 */
	public $taxonomies = [];

	public function setUp(): void {
		parent::setUp();


		$this->clearSchema();

		$alphabet = range( 'A', 'Z' );

		// Create posts
		for ( $i = 0; $i <= count( $alphabet ) - 1; $i ++ ) {
			register_taxonomy( $alphabet[ $i ], 'post', [
				'public' => true,
				'show_in_graphql' => true,
				'graphql_single_name' => $alphabet[ $i ],
				'graphql_plural_name' => 'all' . lcfirst( $alphabet[ $i ] ),
			] );
		}

		$this->taxonomies = get_taxonomies([ 'show_in_graphql' => true ] );

	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function taxonomyQuery() {
		return '
			query testTaxonomies($first: Int, $after: String, $last: Int, $before: String ) {
				taxonomies(first: $first, last: $last, before: $before, after: $after) {
					pageInfo {
						endCursor
						hasNextPage
						hasPreviousPage
						startCursor
					}
					nodes {
						id
						name
					}
				}
			}
		';
	}

	public function testTaxonomyForwardPagination() {

		$first = 5;

		$variables = [
			'first' => $first
		];

		$actual = $this->graphql([
			'query' => $this->taxonomyQuery(),
			'variables' => $variables,
		]);

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 5, $actual['data']['taxonomies']['nodes'] );
		$names = wp_list_pluck( $actual['data']['taxonomies']['nodes'], 'name' );

		// assert that the first 5 taxonomies from the query are the first 5 from get_taxonomies
		$this->assertSame( $names, array_slice( array_values( $this->taxonomies ), 0, $first ) );


		$endCursor = $actual['data']['taxonomies']['pageInfo']['endCursor'];

		$variables = [
			'first' => $first,
			'after' => $endCursor
		];

		$page_2 = $this->graphql([
			'query' => $this->taxonomyQuery(),
			'variables' => $variables,
		]);

		$page_2_names = wp_list_pluck( $page_2['data']['taxonomies']['nodes'], 'name' );

		$page_2_endCursor = $page_2['data']['taxonomies']['pageInfo']['endCursor'];

		// assert that the page 2 names are the same as the 2nd set of 5 names from the taxonomies list
		$this->assertSame( $page_2_names, array_slice( array_values( $this->taxonomies ), 5, $first ) );

		$variables = [
			'first' => $first,
			'after' => $page_2_endCursor
		];

		$page_3 = $this->graphql([
			'query' => $this->taxonomyQuery(),
			'variables' => $variables,
		]);

		$page_3_names = wp_list_pluck( $page_3['data']['taxonomies']['nodes'], 'name' );

		// assert that the page 2 names are the same as the 3rd set of 5 names from the taxonomies list
		$this->assertSame( $page_3_names, array_slice( array_values( $this->taxonomies ), 10, $first ) );

	}

	public function testTaxonomiesBackwardPagination() {

		$backward_taxonomies = array_reverse( $this->taxonomies );

		$last = 5;

		$page_1 = $this->graphql([
			'query' => $this->taxonomyQuery(),
			'variables' => [
				'last' => $last,
				'before' => null,
			]
		]);

		$this->assertIsValidQueryResponse( $page_1 );

		$page_1_names = wp_list_pluck( $page_1['data']['taxonomies']['nodes'], 'name' );


		// the first page should be the first 5 taxonomies
		$this->assertSame( $page_1_names, array_reverse( array_slice( array_values( $backward_taxonomies ), 0, $last ) ) );

		$page_1_start_cursor =  $page_1['data']['taxonomies']['pageInfo']['startCursor'];

		$page_2 = $this->graphql([
			'query' => $this->taxonomyQuery(),
			'variables' => [
				'last' => $last,
				'before' => $page_1_start_cursor
			]
		]);

		$this->assertIsValidQueryResponse( $page_2 );

		$page_2_names = wp_list_pluck( $page_2['data']['taxonomies']['nodes'], 'name' );

		$this->assertSame( $page_2_names, array_reverse( array_slice( array_values( $backward_taxonomies ), 5, $last ) ) );

		$page_2_start_cursor =  $page_2['data']['taxonomies']['pageInfo']['startCursor'];

		$page_3 = $this->graphql([
			'query' => $this->taxonomyQuery(),
			'variables' => [
				'last' => $last,
				'before' => $page_2_start_cursor
			]
		]);

		$this->assertIsValidQueryResponse( $page_3 );

		$page_3_names = wp_list_pluck( $page_3['data']['taxonomies']['nodes'], 'name' );

		// page 3 should be the 3rd set of 5 names from the taxonomies list
		$this->assertSame( $page_3_names, array_reverse( array_slice( array_values( $backward_taxonomies ), 10, $last ) ) );

	}

}
