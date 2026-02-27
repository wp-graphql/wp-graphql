<?php

class GroupFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * @param array $acf_field
	 * @param array $acf_field_group
	 *
	 * @return string
	 */
	public function register_acf_field( array $acf_field = [], array $acf_field_group = [] ): string {

		// set defaults on the acf field
		// using helper methods from this class.
		// this allows test cases extending this class
		// to more easily make use of repeatedly registering
		// fields of the same type and testing them
		$acf_field = array_merge( [
			'name' => $this->get_field_name(),
			'type' => $this->get_field_type(),
			'sub_fields' => [
				[
					'key' => 'field_64711a0b852e2',
					'label' => 'Nested Text Field',
					'name' => 'nested_text_field',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'show_in_graphql' => 1,
					'graphql_description' => '',
					'graphql_field_name' => 'nestedTextField',
				]
			]
		], $acf_field );

		return parent::register_acf_field( $acf_field, $acf_field_group );
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type(): string {
		return 'group';
	}

	public function get_expected_field_resolve_kind(): ?string {
		return 'OBJECT';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'AcfTestGroupTestGroup';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testGroup {
			nestedTextField
		  }
		}
		';
	}

	public function get_block_data_to_store() {
		return '';
	}

	public function get_extra_block_data_to_store( $acf_field_key = '', $acf_field_name = '' ): array {
		return [ 'test_group_nested_text_field' => 'nested text field value...' ];
	}

	public function get_expected_block_fragment_response() {
		return [
			'nestedTextField' => 'nested text field value...',
		];
	}

	/**
	 * @see: https://github.com/wp-graphql/wpgraphql-acf/issues/184
	 * @see: https://github.com/wp-graphql/wpgraphql-acf/issues/151
	 * @return void
	 */
	public function testCloneGroupFieldResolvesDataAsExpected() {

		// if ACF PRO is not active, skip the test
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$field_key = $this->register_acf_field([
			'key' => 'field_65df9360bebef',
			'label' => 'My Text Field',
			'name' => 'my_text_field',
			'type' => 'text',
			'show_in_graphql' => 1,
			'graphql_field_name' => 'myTextField',
			'graphql_non_null' => 0,
		], [
			'key' => 'group_65df9360afcb0',
			'graphql_field_name' => 'myCollectionOfFieldTypes',
			'show_in_graphql' => 1,
			'map_graphql_types_from_location_rules' => 0,
			'graphql_types' => '',
			'location' => [
				[
					[
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'post',
					],
				],
			],
			'active' => true,
		]);


		$cloned_field_key = $this->register_acf_field([
			'label' => 'My Group Field',
			'name' => 'my_group_field',
			'show_in_graphql' => 1,
			'type' => 'group',
			'graphql_field_name' => 'myGroupField',
			'graphql_non_null' => 0,
			'sub_fields' => [
				[
					'key' => 'field_65e1ff262ce23',
					'label'=> 'Clone of My Collection of Field Types (no prefix)',
					'name' => 'clone_of_my_collection_of_field_types_no_prefix',
					'type' => 'clone',
					'graphql_field_name' => 'cloneOfMyCollectionOfFieldTypesNoPrefix',
					'clone' => [
						'group_65df9360afcb0'
					],
					'prefix_name' => 0,
				]
			]
		], [
			'key' => 'group_65e1feb751e7d',
			'title' => 'My Group with Clone',
			'location' => [
				[
					[
						'param' => 'page_template',
						'operator' => '==',
						'value' => 'default',
					],
				],
			],
			'graphql_field_name' => 'myGroupWithClone',
			'show_in_graphql' => 1,
			'graphql_types' => [ 'DefaultTemplate' ],
			'active' => true,
		]);

		$query = '
		query NewQuery($id:ID!) {
		  page(id: $id, idType: DATABASE_ID) {
		    databaseId
		    template {
		      ... on DefaultTemplate {
		        myGroupWithClone {
		          myGroupField {
		            myTextField
		          }
		        }
		      }
		    }
		  }
		}
		';

		// save text value to the cloned field's meta key
		$expected_text_value = 'text field value...';
		update_field( 'my_group_field_my_text_field', $expected_text_value, $this->published_page->ID );

		$actual = $this->graphql([
			'query' => $query,
			'variables' => [
				'id' => $this->published_page->ID,
			]
		]);



		codecept_debug( [
			'$actual' => $actual,
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'page.databaseId', $this->published_page->ID ),
			$this->expectedField( 'page.template.myGroupWithClone.myGroupField.myTextField', $expected_text_value ),
		]);

	}

}
