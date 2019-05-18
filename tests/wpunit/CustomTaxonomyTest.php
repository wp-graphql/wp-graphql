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

}
