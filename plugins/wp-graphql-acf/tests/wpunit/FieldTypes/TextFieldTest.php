<?php

/**
 * Text Field Test
 *
 * Tests the behavior of text field mapping to the WPGraphQL Schema
 */
class TextFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @var int
	 */
	public $post_id;

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		$this->post_id = self::factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_content' => 'test',
		]);

	}

	public function tearDown(): void {
		wp_delete_post( $this->post_id, true );

		parent::tearDown();
	}

	/**
	 * @return string
	 */
	public function get_field_type():string {
		return 'text';
	}

	/**
	 * The "text" field is expected to be a "String" in the Schema
	 *
	 * @return string|null
	 */
	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testText
		}
		';
	}

	public function get_block_data_to_store(): string {
		return 'text value...';
	}

	public function get_query_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  testText
		}';
	}

	public function get_expected_value() {
		return $this->get_data_to_store();
	}

	public function get_expected_preview_value() {
		return $this->get_preview_data_to_store();
	}

	public function get_expected_block_fragment_response() {
		return $this->get_block_data_to_store();
	}

	/**
	 * Register a text field
	 * update value for the text field
	 * query for the
	 */
	public function testQueryTextField(): void {

		$field_key = $this->register_acf_field();

		$expected_text_1 = 'Some Text';

		// update value for the field on the post
		update_field( $this->get_field_name(), $expected_text_1, $this->post_id );

		$query = '
		query getPostById( $id: ID! ) {
			post( id:$id idType:DATABASE_ID) {
				id
				acfTestGroup {
					__typename
					fieldGroupName
					' . $this->get_formatted_field_name(). '
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'post.acfTestGroup.' . $this->get_formatted_field_name(), $expected_text_1 ),
		]);

		$query = '
		query GetType( $name: String! ) {
		  __type( name: $name ) {
		    fields {
		      name
		    }
		  }
		}
		';

		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'name' => 'AcfTestGroup',
			]
		]);

		codecept_debug( $actual );

		// the query should succeed
		self::assertQuerySuccessful( $actual, [
			// the instructions should be used for the description
			$this->expectedNode( '__type.fields', [ 'name' => $this->get_formatted_field_name() ] )
		] );

		// cleanup the data
		delete_field( $this->get_field_name(), $this->post_id );

		// remove the local field
		acf_remove_local_field( $field_key );

	}


	/**
	 * @throws Exception
	 */
	public function testAcfTextFieldDescriptionUsesGraphqlDescriptionIfProvided(): void {

		$graphql_description = 'this is the description of the field for display in the graphql schema';

		$field_key = $this->register_acf_field([
			'graphql_description' => $graphql_description,
			'instructions'        => 'instructions for the admin ui'
		]);

		$query = '
		query GetType( $name: String! ) {
		  __type( name: $name ) {
		    fields {
		      name
		      description
		    }
		  }
		}
		';

		$actual = $this->graphql( [
			'query' => $query,
			'variables' => [
				'name' => 'AcfTestGroup',
			]
		]);

		codecept_debug( $actual );

		self::assertQuerySuccessful( $actual, [
			$this->expectedNode( '__type.fields', [
				'name' => $this->get_formatted_field_name(),
				'description' => $graphql_description
			]),
		] );

		// remove the local field
		acf_remove_local_field( $field_key );

	}

	public function testFieldResolvesWithDefaultValueIfNoValueIsSaved() {

		$default_value = uniqid( 'test default value: ', true );

		$field_key = $this->register_acf_field([
			'default_value'       => $default_value
		]);

		$query = '
		query GetPost($id:ID!){
		  post( id: $id idType: DATABASE_ID ) {
		    databaseId
		    acfTestGroup {
		      ' . $this->get_formatted_field_name() . '
		    }
		  }
		}
		';

		$actual = $this->graphql([
			'query' => $query,
			'variables' => [
				'id' => $this->post_id,
			]
		]);


		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'post.databaseId', $this->post_id ),
			$this->expectedField( 'post.acfTestGroup.' . $this->get_formatted_field_name() , $default_value ),
		]);

		acf_remove_local_field( $field_key );

	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  ' . $this->get_formatted_clone_field_name() . '
		}
		';
	}

//
//	// leave graphql_description and instructions fields empty
//	// assert that a fallback description is output as the description in the schema
//	public function testDefaultGraphqlDescriptionIfGraphqlDescriptionAndInstructionsAreEmpty() {
//
//	}
//
//	public function testGraphqlFieldName() {
//
//	}
//
//	public function testQueryFieldOnPost() {
//
//	}
//
//	public function testQueryFieldOnComment() {
//
//	}
//
//	public function testQueryFieldOnMenuItem() {
//
//	}


}
