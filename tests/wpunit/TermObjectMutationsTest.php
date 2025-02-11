<?php

class TermObjectMutationsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $category_name;
	public $tag_name;
	public $description;
	public $description_update;
	public $client_mutation_id;
	public $admin;
	public $subscriber;

	public function setUp(): void {
		parent::setUp();

		WPGraphQL::clear_schema();

		$this->category_name      = 'Test Category';
		$this->tag_name           = 'Test Tag';
		$this->description        = 'Test Term Description';
		$this->description_update = 'Description Update';
		$this->client_mutation_id = 'someUniqueId';

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->subscriber = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);
	}

	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	/**
	 * Function that executes the mutation
	 */
	public function createCategoryMutation() {

		$query = '
		mutation createCategory( $name:String!, $description:String ) {
			createCategory(
				input: {
					name: $name
					description: $description
				}
			) {
				category {
					id
					databaseId
					name
					description
				}
			}
		}
		';

		$variables = [
			'name'        => $this->category_name,
			'description' => $this->description,
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	/**
	 * Function that executes the mutation
	 */
	public function createTagMutation() {

		$query = '
		mutation createTag( $name:String!, $description:String ) {
			createTag(
				input: {
					name: $name
					description: $description
				}
			) {
				tag {
					id
					databaseId
					name
					description
				}
			}
		}
		';

		$variables = [
			'name'        => $this->tag_name,
			'description' => $this->description,
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	/**
	 * Update Tag
	 *
	 * @param string $id The ID of the term to update
	 * @return array
	 */
	public function updateTagMutation( $id ) {

		$query = '
		mutation updateTag( $id:ID! $description:String ) {
			updateTag(
				input: {
					description: $description
					id: $id
				}
			) {
			tag {
					id
					databaseId
					name
					description
				}
			}
		}
		';

		$variables = [
			'id'          => $id,
			'description' => $this->description_update,
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	public function deleteTagMutation( $id ) {

		$query = '
		mutation deleteTag( $id:ID! ) {
			deleteTag(
				input: {
					id: $id
				}
			) {
				deletedId
				tag {
					databaseId
					id
					name
				}
			}
		}
		';

		$variables = [
			'id' => $id,
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	/**
	 * Update Category
	 *
	 * @param string $id The ID of the term to update
	 * @return array
	 */
	public function updateCategoryMutation( $id ) {

		$query = '
		mutation updateCategory( $id:ID! $description:String ) {
			updateCategory(
				input: {
					description: $description
					id: $id
				}
			) {
				category {
					databaseId
					id
					name
					description
				}
			}
		}
		';

		$variables = [
			'id'          => $id,
			'description' => $this->description_update,
		];

		return graphql( compact( 'query', 'variables' ) );
	}

	public function deleteCategoryMutation( $id ) {

		$query = '
		mutation deleteCategory( $id:ID! ) {
			deleteCategory(
				input: {
					id: $id
				}
			) {
				category {
					databaseId
					id
					name
				}
			}
		}
		';

		$variables = [
			'id' => $id,
		];

		return graphql( compact( 'query', 'variables' ) );
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
		codecept_debug( $actual );

		/**
		 * Assert that the created tag is what it should be
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotEmpty( $actual['data']['createCategory']['category']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $actual['data']['createCategory']['category']['id'] );
		$this->assertEquals( $id_parts['type'], 'term' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $actual['data']['createCategory']['category']['name'], $this->category_name );
		$this->assertEquals( $actual['data']['createCategory']['category']['description'], $this->description );

		/**
		 * Try to update a Tag using a category ID, which should return errors
		 */
		$try_to_update_tag = $this->updateTagMutation( $actual['data']['createCategory']['category']['id'] );
		$this->assertArrayHasKey( 'errors', $try_to_update_tag );

		/**
		 * Try to update a Tag using a category ID, which should return errors
		 */
		$try_to_delete_tag = $this->deleteTagMutation( $actual['data']['createCategory']['category']['id'] );
		$this->assertArrayHasKey( 'errors', $try_to_delete_tag );

		/**
		 * Run the update mutation with the ID of the created created
		 */
		$updated_category = $this->updateCategoryMutation( $actual['data']['createCategory']['category']['id'] );
		codecept_debug( $updated_category );
		$this->assertArrayNotHasKey( 'errors', $updated_category );

		/**
		 * Make some assertions on the response
		 */
		$this->assertNotEmpty( $updated_category['data']['updateCategory']['category']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $updated_category['data']['updateCategory']['category']['id'] );
		$this->assertEquals( $id_parts['type'], 'term' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $updated_category['data']['updateCategory']['category']['name'], $this->category_name );
		$this->assertEquals( $updated_category['data']['updateCategory']['category']['description'], $this->description_update );

		/**
		 * Run the update mutation with the databaseID
		 */
		$updated_category = $this->updateCategoryMutation( $actual['data']['createCategory']['category']['databaseId'] );
		codecept_debug( $updated_category );
		$this->assertArrayNotHasKey( 'errors', $updated_category );
		$this->assertEquals( $actual['data']['createCategory']['category']['databaseId'], $updated_category['data']['updateCategory']['category']['databaseId'] );

		/**
		 * Delete the tag
		 */
		wp_set_current_user( $this->subscriber );
		$deleted_category = $this->deleteCategoryMutation( $updated_category['data']['updateCategory']['category']['id'] );

		/**
		 * A subscriber shouldn't be able to delete, so we should get an error
		 */
		$this->assertArrayHasKey( 'errors', $deleted_category );

		/**
		 * Set the user back to admin and delete again
		 */
		wp_set_current_user( $this->admin );
		$deleted_category = $this->deleteCategoryMutation( $updated_category['data']['updateCategory']['category']['id'] );
		codecept_debug( $deleted_category );

		/**
		 * Make some assertions on the response
		 */
		$this->assertArrayNotHasKey( 'errors', $deleted_category );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $deleted_category['data']['deleteCategory']['category']['id'] );
		$this->assertEquals( $id_parts['type'], 'term' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $deleted_category['data']['deleteCategory']['category']['name'], $this->category_name );

		/**
		 * Test delete with databaseId
		 */
		$category_id      = $this->factory()->category->create();
		$deleted_category = $this->deleteCategoryMutation( $category_id );
		$this->assertArrayNotHasKey( 'errors', $deleted_category );
		$this->assertEquals( $category_id, $deleted_category['data']['deleteCategory']['category']['databaseId'] );
	}

	public function testCreateTagThatAlreadyExists() {

		/**
		 * Set the user as the admin
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual1 = $this->createCategoryMutation();
		$actual2 = $this->createCategoryMutation();

		/**
		 * Create the term
		 */
		$this->assertNotEmpty( $actual1 );

		/**
		 * Make sure there were no errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual1 );
		$this->assertArrayHasKey( 'data', $actual1 );

		/**
		 * Try to create the exact same term
		 */
		$this->assertNotEmpty( $actual2 );

		/**
		 * Now we should expect an error
		 */
		$this->assertArrayHasKey( 'errors', $actual2 );
		$this->assertArrayHasKey( 'data', $actual2 );
	}

	public function testTermIdNotReturningAfterCreate() {

		/**
		 * Filter the term response to simulate a failure with the response of a term creation mutation
		 */
		add_filter( 'term_id_filter', '__return_false' );

		/**
		 * Set the user as the admin
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Run the mutation
		 */
		$actual = $this->createCategoryMutation();
		$this->assertArrayHasKey( 'errors', $actual );
		$actual = $this->deleteTagMutation( 'someInvalidId' );

		/**
		 * We should get an error because the ID is invalid
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Cleanup by removing the filter
		 */
		remove_filter( 'term_id_filter', '__return_false' );

		/**
		 * Now let's filter to mimic the response returning a WP_Error to make sure we also respond with an error
		 */
		add_filter(
			'get_post_tag',
			static function () {
				return new \WP_Error( 'this is a test error' );
			}
		);

		/**
		 * Create a term
		 */
		$term = $this->factory()->term->create(
			[
				'taxonomy' => 'post_tag',
				'name'     => 'some random name',
			]
		);

		/**
		 * Now try and delete it.
		 */
		$id     = \GraphQLRelay\Relay::toGlobalId( 'term', $term );
		$actual = $this->deleteTagMutation( $id );

		/**
		 * Assert that we have an error because the response to the deletion responded with a WP_Error
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testCreateTagWithNoName() {

		$query = '
		mutation createTag( $name:String!, $description:String ) {
			createTag(
				input: {
					name: $name
					description: $description
				}
			) {
				tag {
					id
					name
					description
				}
			}
		}
		';

		$variables = [
			'description' => $this->description,
		];

		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertNotEmpty( $actual );
		$this->assertArrayHasKey( 'errors', $actual );
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
		codecept_debug( $actual );

		/**
		 * Assert that the created tag is what it should be
		 */
		$this->assertNotEmpty( $actual['data']['createTag']['tag']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $actual['data']['createTag']['tag']['id'] );
		$this->assertEquals( $id_parts['type'], 'term' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $actual['data']['createTag']['tag']['name'], $this->tag_name );
		$this->assertEquals( $actual['data']['createTag']['tag']['description'], $this->description );

		/**
		 * Try to update a Cagegory using a Tag ID, which should return errors
		 */
		$try_to_update_category = $this->updateCategoryMutation( $actual['data']['createTag']['tag']['id'] );
		$this->assertNotEmpty( $try_to_update_category['errors'] );

		/**
		 * Try to update the tag as a user with improper permissions
		 */
		wp_set_current_user( $this->subscriber );
		$try_to_delete_category = $this->updateTagMutation( $actual['data']['createTag']['tag']['id'] );
		$this->assertNotEmpty( $try_to_delete_category['errors'] );
		wp_set_current_user( $this->admin );

		/**
		 * Try to update a Category using a Tag ID, which should return errors
		 */
		$try_to_delete_category = $this->updateCategoryMutation( $actual['data']['createTag']['tag']['id'] );
		$this->assertNotEmpty( $try_to_delete_category['errors'] );

		/**
		 * Run the update mutation with the ID of the created tag
		 */
		$updated_tag = $this->updateTagMutation( $actual['data']['createTag']['tag']['id'] );
		codecept_debug( $updated_tag );

		/**
		 * Make some assertions on the response
		 */
		$this->assertNotEmpty( $updated_tag['data']['updateTag']['tag']['id'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $updated_tag['data']['updateTag']['tag']['id'] );
		$this->assertEquals( $id_parts['type'], 'term' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $updated_tag['data']['updateTag']['tag']['name'], $this->tag_name );
		$this->assertEquals( $updated_tag['data']['updateTag']['tag']['description'], $this->description_update );

		/**
		 * Run the update mutation with the databaseID
		 */
		$updated_tag = $this->updateTagMutation( $actual['data']['createTag']['tag']['databaseId'] );
		codecept_debug( $updated_tag );

		$this->assertArrayNotHasKey( 'errors', $updated_tag );
		$this->assertEquals( $actual['data']['createTag']['tag']['databaseId'], $updated_tag['data']['updateTag']['tag']['databaseId'] );

		/**
		 * Delete the tag
		 */
		$deleted_tag = $this->deleteTagMutation( $updated_tag['data']['updateTag']['tag']['id'] );
		codecept_debug( $deleted_tag );

		/**
		 * Make some assertions on the response
		 */
		$this->assertNotEmpty( $deleted_tag );
		$this->assertNotEmpty( $deleted_tag['data']['deleteTag']['deletedId'] );
		$id_parts = \GraphQLRelay\Relay::fromGlobalId( $deleted_tag['data']['deleteTag']['tag']['id'] );
		$this->assertEquals( $id_parts['type'], 'term' );
		$this->assertNotEmpty( $id_parts['id'] );
		$this->assertEquals( $deleted_tag['data']['deleteTag']['tag']['name'], $this->tag_name );

		// Test delete with database_id
		$tag_id      = $this->factory()->tag->create();
		$deleted_tag = $this->deleteTagMutation( $tag_id );
		codecept_debug( $deleted_tag );

		$this->assertArrayNotHasKey( 'errors', $deleted_tag );
		$this->assertEquals( $tag_id, $deleted_tag['data']['deleteTag']['tag']['databaseId'] );
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

	/**
	 * Tests updating a category with bad id.
	 */
	public function testUpdateCategoryWithBadId() {
		$query =
		'mutation updateCategory( $id:ID! $description:String ) {
			updateCategory(
				input: {
					description: $description
					id: $id
				}
			) {
				category {
					databaseId
					id
					name
					description
				}
			}
		}
		';

		// Test no id.
		$variables = [
			'id'          => '',
			'description' => 'Some description',
		];
		$actual    = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test non-existent id.
		$variables['id'] = 99999999;
		$actual          = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Tests category mutations with a parent category.
	 */
	public function testCategoryParent() {

		wp_set_current_user( $this->admin );

		$parent_term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Parent Category',
			]
		);

		$query = '
		mutation createChildCategory($input: CreateCategoryInput!) {
			createCategory(input: $input) {
				category {
					databaseId
					id
					parent {
						node {
							databaseId
							id
						}
					}
				}
			}
		}
		';

		// Test with bad parent ID
		$variables = [
			'input' => [
				'name'     => 'Child Category 1',
				'parentId' => 'notarealid',
			],
		];

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with non-existent parent ID
		$variables['input']['parentId'] = 999999;

		$actual = graphql( compact( 'query', 'variables' ) );
		$this->assertArrayHasKey( 'errors', $actual );

		// Test with database ID
		$variables['input']['parentId'] = $parent_term_id;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $parent_term_id, $actual['data']['createCategory']['category']['parent']['node']['databaseId'] );

		// Test with globalId ID
		$parent_id = \GraphQLRelay\Relay::toGlobalId( 'term', $parent_term_id );
		// Test with database ID
		$variables = [
			'input' => [
				'name'     => 'Child Category 2',
				'parentId' => $parent_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $parent_id, $actual['data']['createCategory']['category']['parent']['node']['id'] );

		// Test changing parent ID.
		$database_id        = $actual['data']['createCategory']['category']['databaseId'];
		$new_parent_term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
				'name'     => 'Parent Category 2',
			]
		);

		$query = '
		mutation updateChildCategory($input: UpdateCategoryInput!) {
			updateCategory(input: $input) {
				category {
					parent{
						node {
							databaseId
							id
						}
					}
				}
			}
		}
		';

		// Test with database ID
		$variables = [
			'input' => [
				'id'       => $database_id,
				'parentId' => $new_parent_term_id,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $new_parent_term_id, $actual['data']['updateCategory']['category']['parent']['node']['databaseId'] );

		// Test with global ID
		$variables['input']['parentId'] = $parent_id; // Switch back to previous parent.

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $parent_id, $actual['data']['updateCategory']['category']['parent']['node']['id'] );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2378
	 * @return void
	 * @throws \Exception
	 */
	public function testCreateTermWithApostropheInNameDoesntStoreSlashInDatabase() {

		$mutation = '
		mutation ($input: CreateTagInput!) {
			createTag(input: $input) {
				tag {
					databaseId
					name
					slug
					description
				}
			}
		}
		';

		$expected_name        = "what's up";
		$slug_input           = "what's-up"; // includes apostrophe
		$expected_slug        = sanitize_title( $slug_input ); // wp should strip it on save
		$expected_description = "what's up, description";

		wp_set_current_user( $this->admin );

		$actual = graphql(
			[
				'query'     => $mutation,
				'variables' => [
					'input' => [
						'name'        => $expected_name,
						'slug'        => $slug_input,
						'description' => $expected_description,
					],
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_name, $actual['data']['createTag']['tag']['name'] );
		$this->assertSame( $expected_slug, $actual['data']['createTag']['tag']['slug'] );
		$this->assertSame( $expected_description, $actual['data']['createTag']['tag']['description'] );
		$this->assertSame( $expected_name, get_term( $actual['data']['createTag']['tag']['databaseId'], 'post_tag' )->name );
		$this->assertSame( $expected_slug, get_term( $actual['data']['createTag']['tag']['databaseId'], 'post_tag' )->slug );
		$this->assertSame( $expected_description, get_term( $actual['data']['createTag']['tag']['databaseId'], 'post_tag' )->description );
	}

	/**
	 * Tests that a child category can be updated to have no parent
	 */
	public function testChildCategoryCanBeUpdatedWithNoParent() {
		wp_set_current_user( $this->admin );

		// First create a parent category
		$parent_term_id = $this->factory()->term->create([
			'taxonomy' => 'category',
			'name'     => 'Parent Category',
		]);

		// Then create a child category
		$child_term_id = $this->factory()->term->create([
			'taxonomy' => 'category',
			'name'     => 'Child Category',
			'parent'   => $parent_term_id,
		]);

		$query = '
		mutation updateChildCategory($input: UpdateCategoryInput!) {
			updateCategory(input: $input) {
				category {
					id
					databaseId
					parentDatabaseId
				}
			}
		}
		';

		// Test updating with parentId: "0"
		$variables = [
			'input' => [
				'id'       => $child_term_id,
				'parentId' => "0",
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['updateCategory']['category']['parentDatabaseId'] );

		// Reset the parent
		wp_update_term( $child_term_id, 'category', [ 'parent' => $parent_term_id ] );

		// Verify the parent was actually set
		$term = get_term( $child_term_id, 'category' );
		$this->assertEquals( $parent_term_id, $term->parent, 'Parent was not properly reset between tests' );

		// Test updating with encoded ID "term:0"
		$variables = [
			'input' => [
				'id'       => $child_term_id,
				'parentId' => 'dGVybTow', // base64 encoded "term:0"
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['updateCategory']['category']['parentDatabaseId'] );
	}
}
