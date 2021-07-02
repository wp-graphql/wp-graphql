<?php

class CustomTaxonomyTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	/**
	 * @throws Exception
	 */
	public function testQueryCustomTaxomomy() {

		$id = $this->factory()->term->create( [
			'taxonomy' => 'bootstrap_tax',
			'name'     => 'Honda'
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

		$actual = graphql( [
			'query'     => $query,
		] );

		codecept_debug( $actual );
		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['nodes'][0]['bootstrapTermId'] );
		$this->assertEquals( $id, $actual['data']['bootstrapTerms']['edges'][0]['node']['bootstrapTermId'] );

	}
	public function testQueryCustomTaxomomyChildren() {


		// Just create a post of the same cpt to expose issue #905
		$this->factory()->post->create( [
			'post_content'  => 'Test page content',
			'post_excerpt'  => 'Test excerpt',
			'post_status'   => 'publish',
			'post_title'    => 'Test Title',
			'post_type'     => 'bootstrap_cpt',
		] );

		$parent_id = $this->factory()->term->create( [
			'taxonomy' => 'bootstrap_tax',
			'name'     => 'parent'
		] );

		$child_id = $this->factory()->term->create( [
			'taxonomy' => 'bootstrap_tax',
			'name'     => 'child',
			'parent' => $parent_id,
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

		$actual = graphql( [
			'query'     => $query,
		] );

		codecept_debug( $actual );

		$this->assertEquals( 'child', $actual['data']['bootstrapTerms']['nodes'][0]['children']['nodes'][0]['name'] );

	}

	public function testQueryCustomTaxonomyWithSameValueForGraphqlSingleNameAndGraphqlPluralName() {
		register_taxonomy(
			'aircraft',
			[ 'bootstrap_cpt' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'aircraft',
				'graphql_plural_name' => 'aircraft',
				'hierarchical'        => true,
			]
		);

		$term_id = $this->factory()->term->create( [
			'taxonomy' => 'aircraft',
			'name'     => 'Boeing 767'
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

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'id' => $term_id
			]
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $term_id, $actual['data']['aircraft']['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['nodes'][0]['databaseId'] );
		$this->assertEquals( $term_id, $actual['data']['allAircraft']['edges'][0]['node']['databaseId'] );
	}

}
