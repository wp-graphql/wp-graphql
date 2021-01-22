<?php

class TermNodeTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
	}
	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodes() {

		$this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
		]);

		$this->factory()->term->create_and_get([
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

	/**
	 * @throws Exception
	 */
	public function testQueryTagByGlobalId() {
		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name' => $tag->name,
			'slug' => $tag->slug,
			'tagId' => $tag->term_id,
		];

		$query = '
		query TagByGlobalId($id:ID!){
		  tag(id: $id) {
		    id
		    name
		    slug
		    tagId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTagByDatabaseId() {
		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name' => $tag->name,
			'slug' => $tag->slug,
			'tagId' => $tag->term_id,
		];

		$query = '
		query TagByGlobalId($id:ID!){
		  tag(id: $id idType:DATABASE_ID) {
		    id
		    name
		    slug
		    tagId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => absint( $tag->term_id ),
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTagByName() {
		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name' => $tag->name,
			'slug' => $tag->slug,
			'tagId' => $tag->term_id,
		];

		$query = '
		query TagByGlobalId($id:ID!){
		  tag(id: $id idType:NAME) {
		    id
		    name
		    slug
		    tagId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $tag->name,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTagBySlug() {
		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name' => $tag->name,
			'slug' => $tag->slug,
			'tagId' => $tag->term_id,
		];

		$query = '
		query TagByGlobalId($id:ID!){
		  tag(id: $id idType:SLUG) {
		    id
		    name
		    slug
		    tagId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $tag->slug,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTagByUri() {
		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name' => $tag->name,
			'slug' => $tag->slug,
			'tagId' => $tag->term_id,
		];

		$query = '
		query TagByGlobalId($id:ID!){
		  tag(id: $id idType:URI) {
		    id
		    name
		    slug
		    tagId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => get_term_link( $tag->term_id ),
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryCategoryByGlobalId() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category',
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query CatByGlobalId($id:ID!){
		  category(id: $id) {
		    id
		    name
		    slug
		    categoryId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryCategoryByDatabaseId() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category',
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query CategoryByDatabaseId($id:ID!){
		  category(id: $id idType:DATABASE_ID) {
		    id
		    name
		    slug
		    categoryId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => absint( $cat->term_id ),
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryCategoryByName() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query CatByGlobalId($id:ID!){
		  category(id: $id idType:NAME) {
		    id
		    name
		    slug
		    categoryId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $cat->name,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryCategoryBySlug() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query CategoryByGlobalId($id:ID!){
		  category(id: $id idType:SLUG) {
		    id
		    name
		    slug
		    categoryId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $cat->slug,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryCategoryByUri() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category'
		]);

		$expected = [
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query CatByGlobalId($id:ID!){
		  category(id: $id idType:URI) {
		    id
		    name
		    slug
		    categoryId
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => get_term_link( $cat->term_id ),
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodeByGlobalId() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category',
		]);

		$expected = [
			'__typename' => 'Category',
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query TermByGlobal($id:ID!){
		  termNode(id: $id) {
		    __typename
		    id
		    name
		    slug
		    ...on Category {
		        categoryId
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			]
		]);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodeByDatabaseId() {
		$tag = $this->factory()->term->create_and_get([
			'taxonomy' => 'post_tag',
		]);

		$expected = [
			'__typename' => 'Tag',
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name' => $tag->name,
			'slug' => $tag->slug,
			'tagId' => $tag->term_id,
		];

		$query = '
		query TermNodeByDatabaseId($id:ID!){
		  termNode(id: $id idType:DATABASE_ID) {
		    __typename
		    id
		    name
		    slug
		    ...on Tag {
		      tagId
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => absint( $tag->term_id ),
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodeByName() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category'
		]);

		$expected = [
			'__typename' => 'Category',
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query TermByGlobalId($id:ID!){
		  termNode(id: $id idType:NAME, taxonomy: CATEGORY) {
		    __typename
		    id
		    name
		    slug
		    ...on Category {
		        categoryId
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $cat->name,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodeBySlug() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category'
		]);

		$expected = [
			'__typename' => 'Category',
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query TermByGlobalId($id:ID!){
		  termNode(id: $id idType:SLUG, taxonomy: CATEGORY) {
		    __typename
		    id
		    name
		    slug
		    ...on Category {
		        categoryId
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $cat->slug,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );

	}

	/**
	 * @throws Exception
	 */
	public function testQueryTermNodeByUri() {
		$cat = $this->factory()->term->create_and_get([
			'taxonomy' => 'category'
		]);

		$expected = [
			'__typename' => 'Category',
			'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name' => $cat->name,
			'slug' => $cat->slug,
			'categoryId' => $cat->term_id,
		];

		$query = '
		query TermByGlobalId($id:ID!){
		  termNode(id: $id idType:URI) {
		    __typename
		    id
		    name
		    slug
		    ...on Category {
		        categoryId
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => get_term_link( $cat->term_id ),
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );

	}

}
