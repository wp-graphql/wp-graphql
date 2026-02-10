<?php

use WPGraphQL\Utils\Utils;

class TaxonomyFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}


	/**
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type(): string {
		return 'taxonomy';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfTermNodeConnection';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	public function get_clone_value_to_save(): array {
		return [
			$this->category->term_id
		];
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
			clonedTestTaxonomy {
			  nodes {
			    __typename
			    databaseId
			  }
			}
		}
		';
	}

	public function get_expected_clone_value(): array {
		return [
			'nodes' => [
				[
					'__typename' => 'Category',
					'databaseId' => $this->category->term_id,
				]
			]
		];
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testTaxonomy {
		    nodes {
		      __typename
		      databaseId
		    }
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return [ $this->category->term_id, $this->tag->term_id ];
	}

	public function get_expected_block_fragment_response() {
		return [
			'nodes' => [
				[
					'__typename' => 'Category',
					'databaseId' => $this->category->term_id,
				],
				[
					'__typename' => 'Tag',
					'databaseId' => $this->tag->term_id,
				],
			]
		];
	}

	public function testQueryReturnsTermsInOrderTheyWereSaved() {

		$field_key = $this->register_acf_field([
			'type' => 'taxonomy',
			'name' => 'tax_term_order_test',
			'show_in_graphql' => true,
			'graphql_field_name' => 'termOrderTest',
			'required' => 1,
			'taxonomy' => 'category',
			'add_term' => 0,
			'save_terms' => 0,
			'load_terms' => 0,
			'return_format' => 'id',
			'field_type' => 'multi_select',
			'multiple' => 1,
			'bidirectonal' => 0,
			'bidirectional_target' => [],
		], [
			'name' => 'Taxonomy Order Test',
			'graphql_field_name' => 'TaxonomyOrderTest',
			'location' => [
				[
					[
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'post',
					]
				]
			],
			'graphql_types' => [ 'Post' ],
		]);

		$cat_aaa = self::factory()->category->create([
			'name' => 'AAA'
		]);

		$cat_bbb = self::factory()->category->create([
			'name' => 'BBB'
		]);

		$cat_ccc = self::factory()->category->create([
			'name' => 'CCC'
		]);

		$cats = [$cat_ccc, $cat_aaa, $cat_bbb];

		update_field( $field_key, $cats, $this->published_post );

		$query = '
		query GetPost($id:ID!) {
		  post(id:$id idType:DATABASE_ID) {
		    id
		    databaseId
		    taxonomyOrderTest {
		      termOrderTest {
		        nodes {
		          __typename
		          databaseId
		        }
		      }
		    }
		  }
		}
		';

		$actual = $this->graphql([
			'query' => $query,
			'variables' => [
				'id' => $this->published_post->ID,
			]
		]);

		codecept_debug( [
			'$actual' => $actual,
			'$cats' => $cats,
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedNode( 'post.taxonomyOrderTest.termOrderTest.nodes', [
				'__typename' => 'Category',
				'databaseId' => $cats[0]
			], 0 ),
			$this->expectedNode( 'post.taxonomyOrderTest.termOrderTest.nodes', [
				'__typename' => 'Category',
				'databaseId' => $cats[1]
			], 1 ),
			$this->expectedNode( 'post.taxonomyOrderTest.termOrderTest.nodes', [
				'__typename' => 'Category',
				'databaseId' => $cats[2]
			], 2 ),
		]);

		foreach ( $cats as $cat ) {
			wp_delete_term( $cat, 'category' );
		}

	}

	public function testQueryTaxononomyFieldOnBlock() {

		// if ACF PRO is not active, skip the test
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		// If WPGraphQL Content Blocks couldn't be activated, skip
		if ( ! defined( 'WPGRAPHQL_CONTENT_BLOCKS_DIR' ) ) {
			$this->markTestSkipped( 'This test is skipped when WPGraphQL Content Blocks is not active' );
		}

		acf_register_block_type([
			'name' => 'block_with_category_field',
			'title' => 'Block with Category Field',
			'post_types' => [ 'post' ],
		]);

		$field_key = $this->register_acf_field([
			'type' => 'taxonomy',
			'name' => 'Category Test',
			'show_in_graphql' => true,
			'graphql_field_name' => 'category',
			'required' => 1,
			'taxonomy' => 'category',
			'add_term' => 0,
			'save_terms' => 0,
			'load_terms' => 0,
			'return_format' => 'object',
			'field_type' => 'select',
			'multiple' => 0,
			'bidirectonal' => 0,
			'bidirectional_target' => [],
		], [
			'name' => 'Block Taxonomy Test',
			'graphql_field_name' => 'BlockTaxonomyTest',
			'location' => [
				[
					[
						'param' => 'block',
						'operator' => '==',
						'value' => 'acf/block-with-category-field',
					]
				]
			],
			'graphql_types' => [ 'AcfBlockWithCategoryField' ],
		]);

		$query = '
		query GetType( $name: String! ) {
		  __type( name: $name ) {
		    fields {
		      name
		    }
		    interfaces {
		      name
		    }
		  }
		}
		';

		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'name' => 'AcfBlockWithCategoryField',
			]
		]);

		codecept_debug( [
			'$actual' => $actual,
		]);

		// Assert that the AcfBlock is in the Schema
		// Assert the field group shows on the block as expected
		self::assertQuerySuccessful( $actual, [
			$this->expectedNode( '__type.fields', [
				'name' => Utils::format_field_name( 'blockTaxonomyTest' ),
			]),
			$this->expectedNode( '__type.interfaces', [
				'name' => 'AcfBlock',
			]),
			// Should implement the With${FieldGroup} Interface
			$this->expectedNode( '__type.interfaces', [
				'name' => 'WithAcfBlockTaxonomyTest',
			])
		]);

		$category_id = self::factory()->category->create([
			'name' => uniqid( 'Test Category', true ),
		]);

		codecept_debug( [
			'$field_key' => $field_key,
			'$category_id' => $category_id,
		]);

		$content = '
		<!-- wp:acf/block-with-category-field {"name":"acf/block-with-category-field","data":{"' . $field_key . '":"' . $category_id . '"},"align":"","mode":"edit"} /-->
		';

		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Block With Taxonomy Field',
			'post_content' => $content,
		]);

		$query = '
		query GetPostWithBlocks( $postId: ID! ){
		  post(id:$postId idType:DATABASE_ID) {
		    id
		    title
		    ...Blocks
		  }
		}

		fragment Blocks on NodeWithEditorBlocks {
		  editorBlocks {
		    __typename
		    ...on AcfBlockWithCategoryField {
		      blockTaxonomyTest {
		        category {
		          nodes {
		            __typename
		            databaseId
		          }
		        }
		      }
		    }
		  }
		}
		';

		$variables = [
			'postId' => $post_id,
		];

		$actual = self::graphql([
			'query'     => $query,
			'variables' => $variables,
		]);

		codecept_debug( [
			'$actual' => $actual,
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedNode( 'post.editorBlocks', [
				// Expect a block with the __typename AcfBlockWithCategoryField
				$this->expectedField('__typename', 'AcfBlockWithCategoryField' ),
				$this->expectedNode( 'blockTaxonomyTest.category.nodes', [
					'__typename' => 'Category',
					'databaseId' => $category_id,
				], 0 ),
			], 0 ),
		]);

		$category_2_id = self::factory()->category->create([
			'name' => uniqid( 'Test Category 2', true ),
		]);

		$content = '
		<!-- wp:acf/block-with-category-field {"name":"acf/block-with-category-field","data":{"' . $field_key . '":[' . $category_id . ', ' . $category_2_id . ']},"align":"","mode":"edit"} /-->
		';

		$post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test Block With Taxonomy Field',
			'post_content' => $content,
		]);

		$actual = self::graphql([
			'query'     => $query,
			'variables' => $variables,
		]);

		codecept_debug( [
			'$actual' => $actual,
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedNode( 'post.editorBlocks', [
				// Expect a block with the __typename AcfBlockWithCategoryField
				$this->expectedField('__typename', 'AcfBlockWithCategoryField' ),
				$this->expectedNode( 'blockTaxonomyTest.category.nodes', [
					'__typename' => 'Category',
					'databaseId' => $category_id,
				], 0 ),
//              Only the first node will be returned because the taxonomy field is set to "multiple: 0" so ACF will only return a single value
//				$this->expectedNode( 'blockTaxonomyTest.category.nodes', [
//					'__typename' => 'Category',
//					'databaseId' => $category_2_id,
//				], 1 ),
			], 0 ),
		]);

		wp_delete_post( $post_id );
		wp_delete_term( $category_id, 'category' );
		wp_delete_term( $category_2_id, 'category' );
	}

	public function testTaxonomyFieldReturnsExpectedNodeWhenObjectIsSavedInsteadOfId() {

		register_taxonomy( 'test_taxonomy', 'post', [
			'show_in_graphql' => true,
			'graphql_single_name' => 'TestCustomTaxonomy',
			'graphql_plural_name' => 'TestCustomTaxonomies',
		] );

		$term_object = self::factory()->term->create_and_get( [
			'taxonomy' => 'test_taxonomy',
			'name' => 'Test Term',
		] );

		$field_key = $this->register_acf_field([
			'type' => 'taxonomy',
			'name' => 'taxonomy_field_test',
			'show_in_graphql' => true,
			'graphql_field_name' => 'testTaxonomyField',
			'required' => 1,
			'taxonomy' => 'category',
			'add_term' => 0,
			'save_terms' => 0,
			'load_terms' => 0,
			'return_format' => 'object',
			'field_type' => 'select',
			'multiple' => 0,
			'bidirectonal' => 0,
			'bidirectional_target' => [],
		], [
			'name' => 'Post Taxonomy Test',
			'graphql_field_name' => 'TaxonomyFieldTest',
			'location' => [
				[
					[
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'post',
					]
				]
			],
			'graphql_types' => [ 'Post' ],
		]);

		update_field( $field_key, $term_object->term_id, $this->published_post->ID );

		$query = '
		query GetPostAndTaxonomyField( $id: ID! ){
		  post(id:$id idType:DATABASE_ID) {
		    id
		    title
		      taxonomyFieldTest {
		        testTaxonomyField {
		          nodes {
		            __typename
		            databaseId
		          }
		        }
		      }
           }
        }';

		$variables = [
			'id' => $this->published_post->ID,
		];

		$actual = $this->graphql([
			'query'     => $query,
			'variables' => $variables,
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedNode( 'post.taxonomyFieldTest.testTaxonomyField.nodes', [
				'__typename' => 'TestCustomTaxonomy',
				'databaseId' => $term_object->term_id,
			], 0 ),
		]);

		// cleanup
		wp_delete_term( $term_object->term_id, 'test_taxonomy' );
		unregister_taxonomy( 'test_taxonomy' );

	}


}
