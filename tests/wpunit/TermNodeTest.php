<?php

class TermNodeTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		parent::setUp();
	}
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Test to make sure all Taxonomies that show in GraphQL
	 * are possibleTypes of the TermNode interface
	 *
	 * @throws Exception
	 */
	public function testTermTypesImplementTermNode() {

		$query = '
		{
		  __type(name: "TermNode") {
		    name
		    kind
		    possibleTypes {
		      name
		    }
		  }
		}
		';

		$actual = graphql([ 'query' => $query ]);

		$this->assertArrayNotHasKey( 'Errors', $actual );

		$possible_type_names = [];
		foreach ( $actual['data']['__type']['possibleTypes'] as $possible_type ) {
			$possible_type_names[] = $possible_type['name'];
		}

		$taxonomies = get_taxonomies([ 'show_in_graphql' => true ], 'objects' );
		$expected_type_names = [];
		foreach( $taxonomies as $taxonomy ) {
			$expected_type_names[] = ucfirst( $taxonomy->graphql_single_name );
		}

		sort($possible_type_names);
		sort($expected_type_names);

		$this->assertSame( $expected_type_names, $possible_type_names );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodes() {

		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
		]);

		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category',
		]);

		$query = '
		{
		  categories: terms(first: 1, where: {taxonomies: [CATEGORY]}) {
		    nodes {
		      ...TermFields
		    }
		  }
		  tags: terms(first: 1, where: {taxonomies: [TAG]}) {
		    nodes {
		      ...TermFields
		    }
		  }
		}
		
		fragment TermFields on TermNode {
		  __typename
		  ... on Category {
		    categoryId
		  }
		  ... on Tag {
            tagId
          }
		}
		';

		$actual = graphql([ 'query' => $query ]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals(  'Category', $actual['data']['categories']['nodes'][0]['__typename'] );
		$this->assertEquals(  'Tag', $actual['data']['tags']['nodes'][0]['__typename'] );

	}

}
