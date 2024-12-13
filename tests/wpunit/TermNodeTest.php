<?php

class TermNodeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		flush_rewrite_rules( true );

		$this->clearSchema();
	}
	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermNodes() {

		$this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

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

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 'Category', $actual['data']['categories']['nodes'][0]['__typename'] );
		$this->assertEquals( 'Tag', $actual['data']['tags']['nodes'][0]['__typename'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTagByGlobalId() {
		$tag = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$expected = [
			'id'    => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name'  => $tag->name,
			'slug'  => $tag->slug,
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

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTagByDatabaseId() {
		$tag = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$expected = [
			'id'    => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name'  => $tag->name,
			'slug'  => $tag->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => absint( $tag->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTagByName() {
		$tag = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$expected = [
			'id'    => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name'  => $tag->name,
			'slug'  => $tag->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $tag->name,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTagBySlug() {
		$tag = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$expected = [
			'id'    => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name'  => $tag->name,
			'slug'  => $tag->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $tag->slug,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTagByUri() {
		$tag = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$expected = [
			'id'    => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name'  => $tag->name,
			'slug'  => $tag->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => get_term_link( $tag->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['tag'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryCategoryByGlobalId() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryCategoryByDatabaseId() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => absint( $cat->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryCategoryByName() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $cat->name,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryCategoryBySlug() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $cat->slug,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryCategoryByUri() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => get_term_link( $cat->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['category'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermNodeByGlobalId() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'__typename' => 'Category',
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermNodeByDatabaseId() {
		$tag = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'post_tag',
			]
		);

		$expected = [
			'__typename' => 'Tag',
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $tag->term_id ),
			'name'       => $tag->name,
			'slug'       => $tag->slug,
			'tagId'      => $tag->term_id,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => absint( $tag->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermNodeByName() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'__typename' => 'Category',
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $cat->name,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermNodeBySlug() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$expected = [
			'__typename' => 'Category',
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
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

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $cat->slug,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermNodeByUri() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		$link     = get_term_link( $cat->term_id );
		$term_uri = str_ireplace( home_url(), '', $link );

		$expected = [
			'__typename' => 'Category',
			'id'         => \GraphQLRelay\Relay::toGlobalId( 'term', $cat->term_id ),
			'name'       => $cat->name,
			'slug'       => $cat->slug,
			'categoryId' => $cat->term_id,
			'uri'        => $term_uri,
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
				uri
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => get_term_link( $cat->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );
	}

	/**
	 * @throws \Exception
	 */
	public function testQueryTermLinkCustomHostPortReplacement() {
		$cat = $this->factory()->term->create_and_get(
			[
				'taxonomy' => 'category',
			]
		);

		add_filter(
			'term_link',
			function ( $term_link ) {
				$frontend_uri = home_url() . ':3000/';
				$site_url     = trailingslashit( site_url() );

				$this->assertNotSame( $site_url, $frontend_uri );

				return str_replace( $site_url, $frontend_uri, $term_link );
			}
		);

		$link      = get_term_link( $cat->term_id );
		$parsed    = parse_url( $link );
		$term_uri  = $parsed['path'] ?? '';
		$term_uri .= isset( $parsed['query'] ) ? ( '?' . $parsed['query'] ) : '';
		$term_uri  = str_ireplace( home_url(), '', $link );

		$expected = [
			'__typename' => 'Category',
			'uri'        => trim( $term_uri ),
		];

		$query = '
		query TermByGlobalId($id:ID!){
			termNode(id: $id idType:URI) {
				__typename
				uri
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => get_term_link( $cat->term_id ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['termNode'] );
	}

	public function testQueryContentNodesOnCustomTaxonomyTest() {

		register_taxonomy(
			'no-posts',
			[],
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'NoPost',
				'graphql_plural_name' => 'NoPosts',
			]
		);

		register_taxonomy(
			'with-graphql',
			[ 'post', 'page' ],
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestTax',
				'graphql_plural_name' => 'AllTestTax',
				'public'              => true,
			]
		);

		$query = '
		{
			allTestTax {
				nodes {
					id
					contentNodes {
						__typename
					}
				}
			}
		}
		';

		$actual = graphql(
			[
				'query' => $query,
			]
		);

		// assert that the query was valid
		$this->assertArrayNotHasKey( 'errors', $actual );

		unregister_taxonomy( 'no-posts' );
		unregister_taxonomy( 'with-graphql' );
	}
}
