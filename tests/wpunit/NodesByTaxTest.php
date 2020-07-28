<?php

class NodesByTaxTest extends \Codeception\TestCase\WPTestCase {

	public $post;
	public $page;
	public $user;
	public $tag;
	public $category;
	public $custom_type;
	public $custom_taxonomy;

	public function setUp(): void {

		WPGraphQL::clear_schema();

		register_post_type( 'custom_type', [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'CustomType',
			'graphql_plural_name' => 'CustomTypes',
			'public'              => true,
		] );

		register_taxonomy( 'custom_tax', [ 'post', 'custom_type' ], [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'CustomTax',
			'graphql_plural_name' => 'CustomTaxes',
		] );

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->user = $this->factory()->user->create( [
			'role' => 'administrator',
		] );

		$this->tag = $this->factory()->term->create( [
			'taxonomy' => 'post_tag',
		] );

		$this->category = $this->factory()->term->create( [
			'taxonomy' => 'category',
		] );

		$this->custom_taxonomy = $this->factory()->term->create( [
			'taxonomy' => 'custom_tax',
		] );

		$this->post = $this->factory()->post->create( [
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => 'Test',
			'post_author'   => $this->user,
			'post_category' => [ $this->category ],
			'tags_input'    => [ $this->tag ]
		] );

		wp_set_post_terms( $this->post, [ $this->custom_taxonomy ], 'custom_tax' );

		$this->custom_type = $this->factory()->post->create( [
			'post_type'   => 'custom_type',
			'post_status' => 'publish',
			'post_title'  => 'Test Page',
			'post_author' => $this->user,
			'tax_input'   => [
				'custom_tax' => [ $this->custom_taxonomy ]
			]
		] );

		wp_set_post_terms( $this->custom_type, [ $this->custom_taxonomy ], 'custom_tax' );

		parent::setUp();

	}

	public function tearDown(): void {

		unregister_post_type( 'custom_type' );
		WPGraphQL::clear_schema();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		parent::tearDown();
		wp_delete_post( $this->post );
		wp_delete_post( $this->page );
		wp_delete_post( $this->custom_post_type );
		wp_delete_term( $this->tag, 'post_tag' );
		wp_delete_term( $this->category, 'category' );
		wp_delete_term( $this->custom_taxonomy, 'custom_tax' );
		wp_delete_user( $this->user );

	}

	public function set_permalink_structure( $structure = '' ) {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );
		$wp_rewrite->flush_rules( true );
	}

	/**
	 * Get posts by a single category.
	 * @throws Exception
	 */
	public function testPostsByCategory() {
		$query = '
		query GET_NODES_BY_CATEGORY( $terms: [String]! ) {
			posts(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( $this->category );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ $this->category ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ),
			$actual['data']['posts']['nodes'][0]['__typename'] );
		$this->assertSame( $this->post, $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	/**
	 * Doesn't get posts by a fake incorrect category.
	 * @throws Exception
	 */
	public function testNoPostsByInvalidCategory() {
		$query = '
		query GET_NODES_BY_CATEGORY( $terms: [String]! ) {
			posts(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 1000000 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ 1000000 ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertEmpty( $actual['data']['posts']['nodes'] );
	}

	/**
	 * Get posts by a single tag.
	 * @throws Exception
	 */
	public function testPostsByTag() {
		$query = '
		query GET_NODES_BY_TAG( $terms: [String]! ) {
			posts(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( $this->tag );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ $this->tag ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ),
			$actual['data']['posts']['nodes'][0]['__typename'] );
		$this->assertSame( $this->post, $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	/**
	 * Doesn't get posts by a fake incorrect tag.
	 * @throws Exception
	 */
	public function testNoPostsByInvalidTag() {
		$query = '
		query GET_NODES_BY_TAG( $terms: [String]! ) {
			posts(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 1000000 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ 1000000 ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertEmpty( $actual['data']['posts']['nodes'] );
	}

	/**
	 * Get posts by a single category and single tag.
	 * @throws Exception
	 */
	public function testPostsByCategoryAndTag() {
		$query = '
		query GET_NODES_BY_TAG( $categoryTerms: [String]!, $tagTerms: [String]! ) {
			posts(where: {
				taxQuery: {
					relation: AND,
					taxArray: [
						{
							terms: $categoryTerms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						},
						{
							terms: $tagTerms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 'Category: ' . $this->category );
		codecept_debug( 'Tag: ' . $this->tag );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'categoryTerms' => [ $this->category ],
				'tagTerms' => [ $this->tag ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ),
			$actual['data']['posts']['nodes'][0]['__typename'] );
		$this->assertSame( $this->post, $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	/**
	 * Doesn't get posts by a fake incorrect tag or incorrect category when using AND relation.
	 * @throws Exception
	 */
	public function testNoPostsByInvalidTagOrInvalidCategory() {
		$query = '
		query GET_NODES_BY_TAG( $categoryTerms: [String]!, $tagTerms: [String]! ) {
			posts(where: {
				taxQuery: {
					relation: AND,
					taxArray: [
						{
							terms: $categoryTerms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						},
						{
							terms: $tagTerms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 'Category: ' . $this->category );
		codecept_debug( 'Tag: ' . 100000 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'categoryTerms' => [ $this->category ],
				'tagTerms' => [ 100000 ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertEmpty( $actual['data']['posts']['nodes'] );


		$query = '
		query GET_NODES_BY_TAG( $categoryTerms: [String]!, $tagTerms: [String]! ) {
			posts(where: {
				taxQuery: {
					relation: AND,
					taxArray: [
						{
							terms: $categoryTerms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						},
						{
							terms: $tagTerms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 'Category: ' . 100000 );
		codecept_debug( 'Tag: ' . $this->tag );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'categoryTerms' => [ 100000 ],
				'tagTerms' => [ $this->tag ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertEmpty( $actual['data']['posts']['nodes'] );
	}

	/**
	 * Get posts by a single category and single tag.
	 * @throws Exception
	 */
	public function testPostsByCategoryOrTag() {
		$query = '
		query GET_NODES_BY_TAG( $categoryTerms: [String]!, $tagTerms: [String]! ) {
			posts(where: {
				taxQuery: {
					relation: OR,
					taxArray: [
						{
							terms: $categoryTerms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						},
						{
							terms: $tagTerms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 'Category: ' . 10000 );
		codecept_debug( 'Tag: ' . $this->tag );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'categoryTerms' => [ 10000 ],
				'tagTerms' => [ $this->tag ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ),
			$actual['data']['posts']['nodes'][0]['__typename'] );
		$this->assertSame( $this->post, $actual['data']['posts']['nodes'][0]['databaseId'] );

		$query = '
		query GET_NODES_BY_TAG( $categoryTerms: [String]!, $tagTerms: [String]! ) {
			posts(where: {
				taxQuery: {
					relation: OR,
					taxArray: [
						{
							terms: $categoryTerms,
							taxonomy: CATEGORY,
							operator: IN,
							field: ID
						},
						{
							terms: $tagTerms,
							taxonomy: TAG,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 'Category: ' . $this->category );
		codecept_debug( 'Tag: ' . 10000 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'categoryTerms' => [ $this->category ],
				'tagTerms' => [ 10000 ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ),
			$actual['data']['posts']['nodes'][0]['__typename'] );
		$this->assertSame( $this->post, $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	/**
	 * Get posts by a single custom tax.
	 * @throws Exception
	 */
	public function testPostsByCustomTax() {
		$query = '
		query GET_NODES_BY_CUSTOM_TAX( $terms: [String]! ) {
			posts(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: CUSTOMTAX,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( $this->custom_taxonomy );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ $this->custom_taxonomy ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'post' )->graphql_single_name ),
			$actual['data']['posts']['nodes'][0]['__typename'] );
		$this->assertSame( $this->post, $actual['data']['posts']['nodes'][0]['databaseId'] );
	}

	/**
	 * Doesn't get posts by a fake incorrect custom tax.
	 * @throws Exception
	 */
	public function testNoPostsByInvalidCustomTax() {
		$query = '
		query GET_NODES_BY_CUSTOM_TAX( $terms: [String]! ) {
			posts(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: CUSTOMTAX,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on Post {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 1000000 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ 1000000 ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertEmpty( $actual['data']['posts']['nodes'] );
	}

	/**
	 * Get posts by a single custom tax.
	 * @throws Exception
	 */
	public function testCustomTypesByCustomTax() {
		$query = '
		query GET_NODES_BY_CUSTOM_TAX( $terms: [String]! ) {
			customTypes(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: CUSTOMTAX,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on CustomType {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( $this->custom_taxonomy );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ $this->custom_taxonomy ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'custom_type' )->graphql_single_name ),
			$actual['data']['customTypes']['nodes'][0]['__typename'] );
		$this->assertSame( $this->custom_type, $actual['data']['customTypes']['nodes'][0]['databaseId'] );
	}

	/**
	 * Doesn't get posts by a fake incorrect custom tax.
	 * @throws Exception
	 */
	public function testNoCustomTypesByInvalidCustomTax() {
		$query = '
		query GET_NODES_BY_CUSTOM_TAX( $terms: [String]! ) {
			customTypes(where: {
				taxQuery: {
					taxArray: [
						{
							terms: $terms,
							taxonomy: CUSTOMTAX,
							operator: IN,
							field: ID
						}
					]
				} 
			}) {
				nodes {
					__typename
					...on CustomType {
						databaseId
					}
				}
			}
		}
		';

		codecept_debug( 1000000 );

		$actual = graphql( [
			'query'     => $query,
			'variables' => [
				'terms' => [ 1000000 ],
			],
		] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'Errors', $actual );
		$this->assertEmpty( $actual['data']['customTypes']['nodes'] );
	}
}
