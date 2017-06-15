<?php

class Test_Term_Object_Mutations extends WP_UnitTestCase {

	public $category_name;
	public $tag_name;
	public $description;
	public $client_mutation_id;
	public $admin;
	public $subscriber;

	public function setUp() {

		$this->category_name = 'Test Category';
		$this->tag_name = 'Test Tag';
		$this->description = 'Test Term Description';
		$this->client_mutation_id = 'someUniqueId';

		$this->admin = $this->factory->user->create([
			'role' => 'administrator',
		]);

		$this->subscriber = $this->factory->user->create([
			'role' => 'subscriber',
		]);

		parent::setUp();

	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Function that executes the mutation
	 */
	public function createCategoryMutation() {

		$mutation = '
		mutation createCategory( $clientMutationId:String!, $title:String!, $content:String! ) {
		  createCategory(
			input: {
			  clientMutationId: $clientMutationId
			  name: $title
			  description: $description
			}
		  ) {
			clientMutationId
			category {
			  name
			  description
			}
		  }
		}
		';

		$variables = wp_json_encode([
			'clientMutationId' => $this->client_mutation_id,
			'name' => $this->category_name,
			'description' => $this->description,
		]);

		return do_graphql_request( $mutation, 'createCategory', $variables );

	}

	/**
	 * Function that executes the mutation
	 */
	public function createTagMutation() {

		$mutation = '
		mutation createTag( $clientMutationId:String!, $title:String!, $content:String! ) {
		  createTag(
		    input: {
			  clientMutationId: $clientMutationId
			    name: $title
				description: $description
			}
		  ) {
			clientMutationId
			postTag {
			  name
			  description
			}
		  }
		}
		';

		$variables = wp_json_encode([
			'clientMutationId' => $this->client_mutation_id,
			'name' => $this->tag_name,
			'description' => $this->description,
		]);

		return do_graphql_request( $mutation, 'createTag', $variables );

	}

	/**
	 * Test creating a category
	 */
	public function testCreateCategory() {

		/**
		 * Set the current user as a subscriber, who deosn't have permission
		 * to create terms
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createCategoryMutation();

		$expected = [
			'data' => [
				'clientMutationId' => $this->client_mutation_id,
				'category' => [
					'name' => $this->tag_name,
					'description' => $this->description,
				],
			],
		];

		$this->assertEquals( $actual, $expected );
	}

	/**
	 * Test creating a tag
	 */
	public function testCreateTag() {

		/**
		 * Set the current user as a subscriber, who deosn't have permission
		 * to create terms
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createTagMutation();

		$expected = [
			'data' => [
				'clientMutationId' => $this->client_mutation_id,
				'postTag' => [
					'name' => $this->tag_name,
					'description' => $this->description,
				],
			],
		];

		$this->assertEquals( $actual, $expected );
	}

	/**
	 * Test creating a tag without proper capabilitites
	 */
	public function testCreateTagWithoutProperCapabilities() {

		/**
		 * Set the current user as a subscriber, who deosn't have permission
		 * to create terms
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Run the mutation
		 */
		$actual = $this->createTagMutation();

		/**
		 * We're asserting that this will properly return an error
		 * because this user doesn't have permissions to create a term as a
		 * subscriber
		 */
		$this->assertNotEmpty( $actual['errors'] );

	}

	/**
	 * Test creating a category without proper capabilitites
	 */
	public function testCreateCategoryWithoutProperCapabilities() {

		/**
		 * Set the current user as a subscriber, who deosn't have permission
		 * to create terms
		 */
		wp_set_current_user( $this->subscriber );

		/**
		 * Run the mutation
		 */
		$actual = $this->createCategoryMutation();

		/**
		 * We're asserting that this will properly return an error
		 * because this user doesn't have permissions to create a term as a
		 * subscriber
		 */
		$this->assertNotEmpty( $actual['errors'] );

	}

}
