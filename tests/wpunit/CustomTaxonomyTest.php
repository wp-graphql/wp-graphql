<?php

class CustomTaxonomyTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		parent::setUp();

	}

	public function tearDown() {
		parent::tearDown();

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
		$this->factory->post->create( [
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
		  bootstrapTerms {
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

		$this->assertEquals( 'child', $actual['data']['bootstrapTerms']['nodes'][0]['children']['nodes'][0]['name'] );

	}

}
