<?php
namespace WPGraphQL\SmartCache;

use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;

class TermCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// category term is created
	public function testCategoryTermIsCreated() {

		// evictions should be empty to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// creating a category should evict the listCategory query
		self::factory()->category->create_and_get([
			'slug' => 'New Category',
		]);

		$this->assertEqualSets([
			// this should be evicted because creating a new category should
			// show the category in the category list query
			'listCategory',
		], $this->getEvictedCaches() );

	}
	// category term is updated
	public function testCategoryTermIsUpdated() {

		// evictions should be empty to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating a category should evict caches
		// note: for whatever reason the factory method for this wasn't working 🤷‍♂️
		wp_update_term( $this->category->term_id, 'category', [
			'name' => 'updated name...'
		]);

		$this->assertEqualSets([
			// this should be evicted because the category in this query was updated
			'singleCategory',

			// this should be evicted because updating a category in this list should
			// evict this list
			'listCategory',
		], $this->getEvictedCaches() );

	}
	// category term is deleted
	public function testCategoryTermIsDeleted() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		wp_delete_term( $this->category->term_id, 'category' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a category invalidate the list the category is in
			'listCategory',

			// when term meta is updated on a category, invalidate the single query for the category
			'singleCategory',

		], $evicted_caches );

	}
	// category term is added to a published post
	public function testCategoryTermIsAddedToPublishedPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->empty_category->term_id ], 'category'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the empty term was added to a post, so the listCategory query is
			// evicted as the term count needs to be updated and the emptyCategory
			// is in this query
			'listCategory',

		], $evicted_caches );
	}

	// category term is added to a draft post
	public function testCategoryTermIsAddedToDraftPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_set_object_terms( $this->draft_post->ID, [ $this->empty_category->term_id ], 'category'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'testCategoryTermIsAddedToDraftPost' => $evicted_caches ]);

		// adding a category to a draft post should not evict any caches
		// as it's not considered a public event
		$this->assertEqualSets( [

			// when a term is added to a post, even a draft post,
			// the term will be updated which should invalidate the
			// list category query as the empty category is in the list query results
			'listCategory'

		], $evicted_caches );

	}
	// category term is removed from a published post
	public function testCategoryTermIsRemovedFromPublishedPost() {
		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_remove_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the category was removed so the singleCategory query will be evicted
			'singleCategory',

			// the category was removed from post, so the listCategory query is
			// evicted as the term count needs to be updated and the category
			// is in this query
			'listCategory',

		], $evicted_caches );
	}

	// category term is removed from a draft post
	public function testCategoryTermIsRemovedFromDraftPost() {

		wp_set_object_terms( $this->draft_post->ID, [ $this->empty_category->term_id ], 'category' );

		// since we just did an action that will trigger evictions we're re-populating the caches
		$this->_populateCaches();

		// assert there are no evicted caches now
		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_remove_object_terms( $this->draft_post->ID, [ $this->empty_category->term_id ], 'category'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the category was removed from a post, so the listCategory query is
			// evicted as the term count needs to be updated and the emptyCategory
			// is in this query
			'listCategory',

		], $evicted_caches );
	}


	// update category meta
	public function testUpdateTermMetaOnCategoryWithPosts() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		update_term_meta( $this->category->term_id, 'meta_key', uniqid( null, true ) );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a category invalidate the list the category is in
			'listCategory',

			// when term meta is invalidated, invalidate the single query for the category
			'singleCategory',

		], $evicted_caches );

	}

	public function testUpdateTermMetaOnCategoryWithNoPosts() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		update_term_meta( $this->empty_category->term_id, 'meta_key', uniqid( null, true ) );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a category (even one without posts) invalidate the list the category is in
			'listCategory',

		], $evicted_caches );
	}

	// delete category meta
	public function testDeleteTermMetaOnCategoryWithPosts() {

		// updating meta of a term as seed data
		update_term_meta( $this->category->term_id, 'meta_key', uniqid( null, true ) );

		// reset caches
		$this->_populateCaches();

		// ensure there are no evictions now
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		delete_term_meta( $this->category->term_id, 'meta_key' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is deleted on a category, invalidate the single category query for that category
			'singleCategory',

			// when term meta is deleted on a category invalidate the list the category is in
			'listCategory',

		], $evicted_caches );
	}

	public function testDeleteCategoryMetaOnCategoryWithNoPosts() {
		// updating meta of a term as seed data
		update_term_meta( $this->empty_category->term_id, 'meta_key', uniqid( null, true ) );

		// reset caches
		$this->_populateCaches();

		// ensure there are no evictions now
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		delete_term_meta( $this->empty_category->term_id, 'meta_key' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a category (even one without posts) invalidate the list the category is in
			'listCategory',

		], $evicted_caches );
	}

	// create child category
	public function testCreateChildCategory() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// creating a child category should evict the list of categories query
		$child_category = self::factory()->category->create_and_get([
			'name' => 'child',
			'parent' => $this->category->term_id,
		]);

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when a child category is created, the list of categories should be evicted
			'listCategory',

		], $evicted_caches );



	}
	// update child category
	public function testUpdateChildCategory() {

		// creating a child category should evict the list of categories query
		$child_category = self::factory()->category->create_and_get([
			'name' => 'child',
			'parent' => $this->category->term_id,
		]);

		// reset caches
		$this->_populateCaches();

		// ensure evictions are empty
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_update_term( $child_category->term_id, 'category', [
			'name' => 'new child name'
		]);

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when a child category is updated, the list of categories should be evicted
			'listCategory',

		], $evicted_caches );
	}

	// delete child category
	public function testDeleteChildCategory() {

		// creating a child category should evict the list of categories query
		$child_category = self::factory()->category->create_and_get([
			'name' => 'child category',
			'parent' => $this->category->term_id,
		]);

		// reset caches
		$this->_populateCaches();

		// ensure evictions are empty
		$this->assertEmpty( $this->getEvictedCaches() );

		$actual = $this->graphql([
			'query' => $this->getQuery( 'listCategory' ),
		]);

		codecept_debug( [ 'listCategory' => $actual ]);

		wp_delete_term( $child_category->term_id, 'category' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when a child category is deleted, the list of categories should be evicted
			'listCategory',

		], $evicted_caches );
	}


	// tag term is created
	public function testTagTermIsCreated() {

		// evictions should be empty to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// creating a category should evict the listCategory query
		self::factory()->tag->create_and_get([
			'slug' => 'New Tag',
		]);

		$this->assertEqualSets([
			// this should be evicted because creating a new category should
			// show the category in the tags list query
			'listTag',
		], $this->getEvictedCaches() );

	}

	// tag term is updated
	public function testTagTermIsUpdated() {

		// evictions should be empty to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating a tag should evict caches
		// note: for whatever reason the factory method for this wasn't working 🤷‍♂️
		wp_update_term( $this->tag->term_id, 'post_tag', [
			'name' => 'updated tag name...'
		]);

		$this->assertEqualSets([
			// this should be evicted because the tag in this query was updated
			'singleTag',

			// this should be evicted because updating a tag in this list should
			// evict this list
			'listTag',
		], $this->getEvictedCaches() );

	}

	// tag term is deleted
	public function testTagTermIsDeleted() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		wp_delete_term( $this->tag->term_id, 'post_tag' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a tag invalidate the list the tag is in
			'listTag',

			// when term meta is invalidated, invalidate the single query for the tag
			'singleTag',

		], $evicted_caches );

	}

	// tag term is added to a published post
	public function testTagTermIsAddedToPublishedPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->empty_tag->term_id ], 'post_tag'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the empty term was added to a post, so the listTag query is
			// evicted as the term count needs to be updated and the emptyTag
			// is in this query
			'listTag',

		], $evicted_caches );
	}

	// tag term is added to a draft post
	public function testTagTermIsAddedToDraftPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_set_object_terms( $this->draft_post->ID, [ $this->empty_tag->term_id ], 'post_tag'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'testTagTermIsAddedToDraftPost' => $evicted_caches ]);

		// adding a category to a draft post should not evict any caches
		// as it's not considered a public event
		$this->assertEqualSets( [

			// when a term is added to a post, even a draft post,
			// the term will be updated which should invalidate the
			// list tag query as the empty tag is in the list query results
			'listTag'

		], $evicted_caches );

	}

	// tag term is removed from a published post
	public function testTagTermIsRemovedFromPublishedPost() {
		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_remove_object_terms( $this->published_post->ID, [ $this->tag->term_id ], 'post_tag'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the category was removed so the singleCategory query will be evicted
			'singleTag',

			// the category was removed from post, so the listCategory query is
			// evicted as the term count needs to be updated and the emptyCategory
			// is in this query
			'listTag',

		], $evicted_caches );
	}

	// tag term is removed from a draft post
	public function testTagTermIsRemovedFromDraftPost() {

		wp_set_object_terms( $this->draft_post->ID, [ $this->empty_tag->term_id ], 'post_tag' );

		// since we just did an action that will trigger evictions we're re-populating the caches
		$this->_populateCaches();

		// assert there are no evicted caches now
		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_remove_object_terms( $this->draft_post->ID, [ $this->empty_tag->term_id ], 'post_tag'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the category was removed from a post, so the listTag query is
			// evicted as the term count needs to be updated and the emptyTag
			// is in this query
			'listTag',

		], $evicted_caches );
	}

	// update tag meta
	public function testUpdateTermMetaOnTag() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		update_term_meta( $this->tag->term_id, 'meta_key', uniqid( null, true ) );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a tag invalidate the list the tag is in
			'listTag',

			// when term meta is invalidated, invalidate the single query for the tag
			'singleTag',

		], $evicted_caches );

	}
	// delete tag meta
	public function testDeleteTermMetaOnTag() {
		// updating meta of a term as seed data
		update_term_meta( $this->tag->term_id, 'meta_key', uniqid( null, true ) );

		// reset caches
		$this->_populateCaches();

		// ensure there are no evictions now
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		delete_term_meta( $this->tag->term_id, 'meta_key' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is invalidated, invalidate the single query for the tag
			'singleTag',

			// when term meta is updated on a tag (even one without posts) invalidate the list the tag is in
			'listTag',

		], $evicted_caches );
	}


	// custom tax (show_in_graphql) term is created
	public function testTermOfTestTaxonomyIsCreated() {

		// evictions should be empty to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// creating a category should evict the listCategory query
		self::factory()->term->create_and_get([
			'slug' => 'New Category',
			'taxonomy' => 'test_taxonomy'
		]);

		$this->assertEqualSets([
			// this should be evicted because creating a new category should
			// show the category in the category list query
			'listTestTaxonomyTerm',
		], $this->getEvictedCaches() );

	}

	// custom tax (show_in_graphql) term is updated
	public function testTermOfTestTaxonomyIsUpdated() {

		// evictions should be empty to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating a test_taxonomy term should evict caches
		// note: for whatever reason the factory method for this wasn't working 🤷‍♂️
		wp_update_term( $this->test_taxonomy_term->term_id, 'test_taxonomy', [
			'name' => 'updated name...'
		]);

		$this->assertEqualSets([
			// this should be evicted because the test_taxonomy in this query was updated
			'singleTestTaxonomyTerm',

			// this should be evicted because updating a test_taxonomy in this list should
			// evict this list
			'listTestTaxonomyTerm',
		], $this->getEvictedCaches() );

	}

	// custom tax (show_in_graphql) term is deleted
	public function testTermOfTestTaxonomyIsDeleted() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		wp_delete_term( $this->test_taxonomy_term->term_id, 'test_taxonomy' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a test_taxonomy invalidate the list the test_taxonomy is in
			'listTestTaxonomyTerm',

			// when term meta is updated on a test_taxonomy term, invalidate the single query for the test_taxonomy
			'singleTestTaxonomyTerm',

		], $evicted_caches );

	}

	// custom tax (show_in_graphql) term is added to a published post of custom post type
	public function testTermOfTestTaxonmoyIsAddedToPublishedPostOfTestPostType() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_set_object_terms( $this->published_test_post_type->ID, [ $this->empty_test_taxonomy_term->term_id ], 'test_taxonomy'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the empty term was added to a post, so the listTestTaxonomyTerm query is
			// evicted as the term count needs to be updated and the empty_test_taxonomy_term
			// is in this query
			'listTestTaxonomyTerm',

		], $evicted_caches );
	}

	// custom tax (show_in_graphql) term is added to a draft post
	public function testTermOfTestTaxonomyIsAddedToDraftPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_set_object_terms( $this->draft_test_post_type->ID, [ $this->empty_test_taxonomy_term->term_id ], 'test_taxonomy'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'testTermOfTestTaxonomyIsAddedToDraftPost' => $evicted_caches ]);

		// adding a category to a draft post should not evict any caches
		// as it's not considered a public event
		$this->assertEqualSets( [

			// when a term is added to a post, even a draft post,
			// the term will be updated which should invalidate the
			// list category query as the empty test taxonomy term is in the list query results
			'listTestTaxonomyTerm'

		], $evicted_caches );

	}

	// custom tax (show_in_graphql) term is removed from a published post
	public function testTermOfTestTaxonomyTypeIsRemovedFromPublishedPost() {
		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_remove_object_terms( $this->published_test_post_type->ID, [ $this->test_taxonomy_term->term_id ], 'test_taxonomy'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the term was removed so the singleTestTaxonomyTerm query will be evicted
			'singleTestTaxonomyTerm',

			// the term was removed from post, so the listTestTaxonomyTerm query is
			// evicted as the term count needs to be updated and the test_taxonomy_term
			// is in this query
			'listTestTaxonomyTerm',

		], $evicted_caches );
	}
	// custom tax (show_in_graphql) term is removed from a draft post
	public function testTermOfTestTaxonomyIsRemovedFromDraftPost() {

		wp_set_object_terms( $this->draft_test_post_type->ID, [ $this->empty_test_taxonomy_term->term_id ], 'test_taxonomy' );

		// since we just did an action that will trigger evictions we're re-populating the caches
		$this->_populateCaches();

		// assert there are no evicted caches now
		$this->assertEmpty( $this->getEvictedCaches() );

		// add the empty category to the published post
		wp_remove_object_terms( $this->draft_test_post_type->ID, [ $this->empty_test_taxonomy_term->term_id ], 'test_taxonomy'  );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// the term was removed from a post, so the listTestTaxonomyTerm query is
			// evicted as the term count needs to be updated and the empty_test_taxonomy_term
			// is in this query
			'listTestTaxonomyTerm',

		], $evicted_caches );
	}
	// update custom tax (show_in_graphql) term meta (of allowed meta key)
	public function testUpdateTermMetaOnTermOfTestTaxonomyTypeWithPosts() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		update_term_meta( $this->test_taxonomy_term->term_id, 'meta_key', uniqid( null, true ) );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a term invalidate the list the term is in
			'listTestTaxonomyTerm',

			// when term meta is updated, invalidate the single query for the term
			'singleTestTaxonomyTerm',

		], $evicted_caches );

	}
	// delete custom tax (show_in_graphql) term meta (of allowed meta key)
	public function testDeleteTermMetaOnTermOfTestTaxonomyWithPosts() {

		// updating meta of a term as seed data
		update_term_meta( $this->test_taxonomy_term->term_id, 'meta_key', uniqid( null, true ) );

		// reset caches
		$this->_populateCaches();

		// ensure there are no evictions now
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		delete_term_meta( $this->test_taxonomy_term->term_id, 'meta_key' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets( [

			// when term meta is deleted on a term, invalidate the list the term is in
			'listTestTaxonomyTerm',

			// when term meta is deleted, invalidate the single query for the term
			'singleTestTaxonomyTerm',

		], $evicted_caches );

	}

	public function testDeleteTermMetaOnTermOfTestTaxonomyWithNoPosts() {
		// updating meta of a term as seed data
		update_term_meta( $this->empty_test_taxonomy_term->term_id, 'meta_key', uniqid( null, true ) );

		// reset caches
		$this->_populateCaches();

		// ensure there are no evictions now
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating meta of a term should evict caches
		delete_term_meta( $this->empty_test_taxonomy_term->term_id, 'meta_key' );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		codecept_debug( $evicted_caches );

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// when term meta is updated on a term (even one without posts) invalidate the list the term is in
			'listTestTaxonomyTerm',

		], $evicted_caches );
	}
}
