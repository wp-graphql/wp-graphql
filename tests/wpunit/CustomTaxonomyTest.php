<?php

class CustomTaxonomyTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();

		register_post_type(
			'test_custom_tax_cpt',
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'bootstrapPost',
				'graphql_plural_name' => 'bootstrapPosts',
				'hierarchical'        => true,
				'taxonomies'          => [ 'test_custom_tax' ],
			]
		);
		register_taxonomy(
			'test_custom_tax',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'bootstrapTerm',
				'graphql_plural_name' => 'bootstrapTerms',
				'hierarchical'        => true,
			]
		);

		$this->clearSchema();
	}

	public function tearDown(): void {
		unregister_post_type( 'test_custom_tax_cpt' );
		unregister_taxonomy( 'test_custom_tax' );
		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * @throws Exception
	 */
	public function testQueryCustomTaxomomy() {

		$id = $this->factory()->term->create( [
			'taxonomy' => 'test_custom_tax',
			'name'     => 'Honda',
		] );

		$query = '
		query GET_CUSTOM_TAX_TERMS {
			bootstrapTerms {
				nodes {
					bootstrapTermId
				}
				edges {
					node {
						bootstrapTermId
					}
				}
			}
		}
		';

		$actual = $this->graphql( [
			'query' => $query,
		] );

		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['nodes'][0]['bootstrapTermId'] );
		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['edges'][0]['node']['bootstrapTermId'] );

	}
	public function testQueryCustomTaxomomyChildren() {

		// Just create a post of the same cpt to expose issue #905
		$this->factory()->post->create( [
			'post_content' => 'Test post content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Post QueryCustomTaxomomyChildren',
			'post_type'    => 'test_custom_tax_cpt',
		] );

		$parent_id = $this->factory()->term->create( [
			'taxonomy' => 'test_custom_tax',
			'name'     => 'parent',
		] );

		$child_id = $this->factory()->term->create( [
			'taxonomy' => 'test_custom_tax',
			'name'     => 'child',
			'parent'   => $parent_id,
		] );

		$query = '
		query TaxonomyChildren {
			bootstrapTerms(where:{parent:0}) {
				nodes {
			name
				children {
					nodes {
						name
					}
				}
				}
			}
			bootstrapPosts {
				nodes {
					title
				}
			}
		}
		';

		$actual = $this->graphql( [
			'query' => $query,
		] );

		$this->assertEquals( 'child', $actual['data']['bootstrapTerms']['nodes'][0]['children']['nodes'][0]['name'] );

	}

	public function testQueryCustomTaxonomyWithSameValueForGraphqlSingleNameAndGraphqlPluralName() {
		register_taxonomy(
			'aircraft',
			[ 'test_custom_tax_cpt' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'aircraft',
				'graphql_plural_name' => 'aircraft',
				'hierarchical'        => true,
			]
		);

		$term_id = $this->factory()->term->create( [
			'taxonomy' => 'aircraft',
			'name'     => 'Boeing 767',
		] );

		$query = '
		query GET_CUSTOM_TAX_TERMS( $id: ID! ) {
			aircraft( id: $id idType: DATABASE_ID ) {
				databaseId
			}
			allAircraft {
				nodes {
					databaseId
				}
				edges {
					node {
					databaseId
					}
				}
			}
		}
		';

		$actual = $this->graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $term_id,
			],
		] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $term_id, $actual['data']['aircraft']['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['nodes'][0]['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['edges'][0]['node']['databaseId'] );

		unregister_taxonomy( 'aircraft' );
	}

}
