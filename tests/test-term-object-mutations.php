<?php

class Test_Term_Object_Mutations extends WP_UnitTestCase {

	public $category_name;
	public $tag_name;
	public $description;
	public $description_update;
	public $client_mutation_id;
	public $admin;
	public $subscriber;

	public function setUp() {

		$this->category_name = 'Test Category';
		$this->tag_name = 'Test Tag';
		$this->description = 'Test Term Description';
		$this->description_update = 'Description Update';
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
		mutation createCategory( $clientMutationId:String!, $name:String!, $description:String ) {
		  createCategory(
			input: {
			  clientMutationId: $clientMutationId
			  name: $name
			  description: $description
			}
		  ) {
			clientMutationId
			category {
			  id
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
		mutation createTag( $clientMutationId:String!, $name:String!, $description:String ) {
		  createPostTag(
		    input: {
			  clientMutationId: $clientMutationId
			    name: $name
				description: $description
			}
		  ) {
			clientMutationId
			postTag {
			  id
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
	 * Update Tag
	 *
	 * @param string $id The ID of the term to update
	 * @return array
	 */
	public function updateTagMutation( $id ) {

		$mutation = '
		mutation updateTag( $clientMutationId:String!, $id:ID! $description:String ) {
		  updatePostTag(
		    input: {
			  clientMutationId: $clientMutationId
			  description: $description
			  id: $id
			}
		  ) {
			clientMutationId
			postTag {
			  id
			  name
			  description
			}
		  }
		}
		';

		$variables = wp_json_encode([
			'clientMutationId' => $this->client_mutation_id,
			'id' => $id,
			'description' => $this->description_update,
		]);

		return do_graphql_request( $mutation, 'updateTag', $variables );

	}

	public function deleteTagMutation( $id ) {

		$mutation = '
		mutation deleteTag( $clientMutationId:String!, $id:ID! ) {
		  deletePostTag(
		    input: {
			  id: $id
			  clientMutationId: $clientMutationId
		    }
		  ) {
		    clientMutationId
		    postTag {
		        id
		        name
		    }
		  }
		}
		';

		$variables = wp_json_encode([
			'id' => $id,
			'clientMutationId' => $this->client_mutation_id,
		]);

		return do_graphql_request( $mutation, 'deleteTag', $variables );

	}

	/**
	 * Update Category
	 *
	 * @param string $id The ID of the term to update
	 * @return array
	 */
	public function updateCategoryMutation( $id ) {

		$mutation = '
		mutation updateCategory( $clientMutationId:String!, $id:ID! $description:String ) {
		  updateCategory(
		    input: {
			  clientMutationId: $clientMutationId
			  description: $description
			  id: $id
			}
		  ) {
			clientMutationId
			category {
			  id
			  name
			  description
			}
		  }
		}
		';

		$variables = wp_json_encode([
			'clientMutationId' => $this->client_mutation_id,
			'id' => $id,
			'description' => $this->description_update,
		]);

		return do_graphql_request( $mutation, 'updateCategory', $variables );

	}

	public function deleteCategoryMutation( $id ) {

		$mutation = '
		mutation deleteCategory( $clientMutationId:String!, $id:ID! ) {
		  deleteCategory(
		    input: {
			  id: $id
			  clientMutationId: $clientMutationId
		    }
		  ) {
		    clientMutationId
		    category {
		        id
		        name
		    }
		  }
		}
		';

		$variables = wp_json_encode([
			'id' => $id,
			'clientMutationId' => $this->client_mutation_id,
		]);

		return do_graphql_request( $mutation, 'deleteCategory', $variables );

	}


	/**
	 * Test creating a category
	 */
	public function testCategoryMutations() {

		/**
		 * Set the current user as a subscriber, who doesn't have permission
		 * to create terms
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createCategoryMutation();

		/**
		 * Assert that the created tag is what it should be
		 */
		$this->assertEquals( $actual['data']['createCategory']['clientMutationId'], $this->client_mutation_id );
		$this->assertNotEmpty( $actual['data']['createCategory']['category']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $actual['data']['createCategory']['category']['id'] );
		$this->assertEquals( $id_parts['type'], 'category' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $actual['data']['createCategory']['category']['name'], $this->category_name );
		$this->assertEquals( $actual['data']['createCategory']['category']['description'], $this->description );

		/**
		 * Try to update a Tag using a category ID, which should return errors
		 */
		$try_to_update_tag = $this->updateTagMutation( $actual['data']['createCategory']['category']['id'] );
		$this->assertNotEmpty( $try_to_update_tag['errors'] );

		/**
		 * Try to update a Tag using a category ID, which should return errors
		 */
		$try_to_delete_tag = $this->deleteTagMutation( $actual['data']['createCategory']['category']['id'] );
		$this->assertNotEmpty( $try_to_delete_tag['errors'] );

		/**
		 * Run the update mutation with the ID of the created tag
		 */
		$updated_category = $this->updateCategoryMutation( $actual['data']['createCategory']['category']['id'] );

		/**
		 * Make some assertions on the response
		 */
		$this->assertEquals( $updated_category['data']['updateCategory']['clientMutationId'], $this->client_mutation_id );
		$this->assertNotEmpty( $updated_category['data']['updateCategory']['category']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $updated_category['data']['updateCategory']['category']['id'] );
		$this->assertEquals( $id_parts['type'], 'category' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $updated_category['data']['updateCategory']['category']['name'], $this->category_name );
		$this->assertEquals( $updated_category['data']['updateCategory']['category']['description'], $this->description_update );

		/**
		 * Delete the tag
		 */
		$deleted_category = $this->deleteCategoryMutation( $updated_category['data']['updateCategory']['category']['id'] );

		/**
		 * Make some assertions on the response
		 */
		$this->assertNotEmpty( $deleted_category );
		$this->assertEquals( $deleted_category['data']['deleteCategory']['clientMutationId'], $this->client_mutation_id );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $deleted_category['data']['deleteCategory']['category']['id'] );
		$this->assertEquals( $id_parts['type'], 'category' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $deleted_category['data']['deleteCategory']['category']['name'], $this->category_name );
	}

	/**
	 * Test creating a tag
	 */
	public function testTagMutations() {

		/**
		 * Set the current user as a subscriber, who doesn't have permission
		 * to create terms
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createTagMutation();

		/**
		 * Assert that the created tag is what it should be
		 */
		$this->assertEquals( $actual['data']['createPostTag']['clientMutationId'], $this->client_mutation_id );
		$this->assertNotEmpty( $actual['data']['createPostTag']['postTag']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $actual['data']['createPostTag']['postTag']['id'] );
		$this->assertEquals( $id_parts['type'], 'post_tag' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $actual['data']['createPostTag']['postTag']['name'], $this->tag_name );
		$this->assertEquals( $actual['data']['createPostTag']['postTag']['description'], $this->description );

		/**
		 * Try to update a Cagegory using a Tag ID, which should return errors
		 */
		$try_to_update_tag = $this->updateCategoryMutation( $actual['data']['createPostTag']['category']['id'] );
		$this->assertNotEmpty( $try_to_update_tag['errors'] );

		/**
		 * Try to update a Category using a Tag ID, which should return errors
		 */
		$try_to_delete_tag = $this->deleteCategoryMutation( $actual['data']['createCategory']['category']['id'] );
		$this->assertNotEmpty( $try_to_delete_tag['errors'] );

		/**
		 * Run the update mutation with the ID of the created tag
		 */
		$updated_tag = $this->updateTagMutation( $actual['data']['createPostTag']['postTag']['id'] );

		/**
		 * Make some assertions on the response
		 */
		$this->assertEquals( $updated_tag['data']['updatePostTag']['clientMutationId'], $this->client_mutation_id );
		$this->assertNotEmpty( $updated_tag['data']['updatePostTag']['postTag']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $updated_tag['data']['updatePostTag']['postTag']['id'] );
		$this->assertEquals( $id_parts['type'], 'post_tag' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $updated_tag['data']['updatePostTag']['postTag']['name'], $this->tag_name );
		$this->assertEquals( $updated_tag['data']['updatePostTag']['postTag']['description'], $this->description_update );

		/**
		 * Delete the tag
		 */
		$deleted_tag = $this->deleteTagMutation( $updated_tag['data']['updatePostTag']['postTag']['id'] );

		/**
		 * Make some assertions on the response
		 */
		$this->assertNotEmpty( $deleted_tag );
		$this->assertEquals( $deleted_tag['data']['deletePostTag']['clientMutationId'], $this->client_mutation_id );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $deleted_tag['data']['deletePostTag']['postTag']['id'] );
		$this->assertEquals( $id_parts['type'], 'post_tag' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $deleted_tag['data']['deletePostTag']['postTag']['name'], $this->tag_name );

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
