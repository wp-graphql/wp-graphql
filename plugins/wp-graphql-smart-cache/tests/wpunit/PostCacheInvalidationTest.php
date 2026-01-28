<?php

class PostCacheInvalidationTest extends \TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}


	public function testCreateDraftPostDoesNotInvalidatePostCache() {

		// all queries should be in the cache, non should be empty
		$this->assertEmpty( $this->getEvictedCaches() );

		// create an auto draft post
		self::factory()->post->create([
			'post_status' => 'auto-draft'
		]);

		// after creating an auto-draft post, there should be no caches that were emptied
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	public function testPublishingScheduledPostWithoutAssociatedTerm() {

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the scheduled post
		wp_publish_post( $this->scheduled_post );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		$this->assertEqualSets([
			'listPost',
			'listContentNode',
		], $evicted_caches );

	}

	/**
	 * Test behavior when a scheduled post (that has a category assigned to it) is published
	 *
	 * - given:
	 *   - a query for a single pre-existing post is in the cache
	 *   - a query for a list of posts is in the cache
	 *   - a query for contentNodes is in the cache
	 *   - a query for a page is in the cache
	 *   - a query for a list of pages is in the cache
	 *   - a query for a tag is in the cache
	 *   - a query for a list of tags is in the cache
	 *   - a query for a list of users is in the cache
	 *   - a query for the author of the post is in the cache
	 *
	 * - when:
	 *   - a scheduled post is published
	 *
	 * - assert:
	 *   - query for list of posts is invalidated
	 *   - query for contentNodes is invalidated
	 *   - query for list of categories is invalidated
	 *   - query for single category is invalidated
	 *   - query for single pre-exising post remains cached
	 *   - query for a page remains cached
	 *   - query for list of pages remains cached
	 *   - query for tag remains cached
	 *   - query for list of tags remains cached
	 *   - query for list of users remains cached
	 *   - query for the author of the post remains cached
	 *
	 * @throws Exception
	 */
	public function testPublishingScheduledPostWithCategoryAssigned() {

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// the single category query should be in the cache
		$this->assertNotEmpty( $this->collection->get( $this->query_results['singleCategory']['cacheKey'] ) );

		// publish the post
		wp_publish_post( $this->scheduled_post_with_category->ID );

		//codecept_debug( [ 'set_associated_term' => wp_get_object_terms( $this->scheduled_post_with_category->ID, 'category' ) ]);

		// get the evicted caches _after_ publish
		$evicted_caches = $this->getEvictedCaches();

		// when publishing a scheduled post with an associated category,
		// the listPost and listContentNode queries should have been cleared
		// but also the listCategory and singleCategory as the termCount
		// needs to be updated on the terms
		$this->assertEqualSets([
			'listPost',
			'listContentNode',
			'listCategory',
			'singleCategory',
		], $evicted_caches );


	}

	// published post is changed to draft
	public function testPublishedPostIsChangedToDraft() {

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'draft'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'singlePost',
			'listPost',
			'singleContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'userWithPostsConnection',
			'listContentNode',
		], $evicted_caches );

	}

	/**
	 * When a post is updated that has a category already assigned,
	 *
	 * @return void
	 */
	public function testUpdatePostWithCategory() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_title' => 'updated title',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listPost',
			'singleContentNode',
			'singlePost',
			'singleNodeByUri',
			'singleNodeById',
			'listContentNode',
			'userWithPostsConnection',
		], $evicted_caches );

	}

	public function testPublishedPostWithCategoryIsChangedToDraft() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'draft',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listPost',
			'singleContentNode',
			'singlePost',
			'singleNodeByUri',
			'singleNodeById',
			'listContentNode',
			'userWithPostsConnection',
			'singleCategory',
			'listCategory',
		], $evicted_caches );

	}


	// published post is changed to private
	public function testPublishPostChangedToPrivate() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'private'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'singlePost',
			'listPost',
			'singleContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'listContentNode',
			'userWithPostsConnection',
		], $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsChangedToPrivate() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'private',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listPost',
			'singleContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'singlePost',
			'listContentNode',
			'userWithPostsConnection',
			'singleCategory',
			'listCategory',
		], $evicted_caches );

	}
	// published post is trashed
	public function testPublishPostIsTrashed() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'trash'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'singlePost',
			'listPost',
			'singleContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'listContentNode',
			'userWithPostsConnection'
		], $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsTrashed() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_post->ID, [
			'post_status' => 'trash',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets( [
			'singlePost',
			'listPost',
			'singleContentNode',
			'listContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'listCategory',
			'singleCategory',
			'userWithPostsConnection'
		], $evicted_caches );

	}

	// published post is force deleted
	public function testPublishPostIsForceDeleted() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->published_post->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();

		$this->assertEqualSets([
			'userWithPostsConnection',
			'singlePost',
			'listPost',
			'listContentNode',
			'singleContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'singleApprovedCommentByGlobalId',
			'listComment',
			'listMenuItem', // the single menu item is linked to the published post, so this query should be evicted because when the post is deleted it deletes the associated menu item
			'singleMenuItem' // the single menu item is linked to the published post, so this query should be evicted because when the post is deleted it deletes the associated menu item
		], $evicted_caches );
	}

	public function testPublishedPostWithCategoryIsForceDeleted() {

		// set the object terms on the published post
		wp_set_object_terms( $this->published_post->ID, [ $this->category->term_id ], 'category' );

		// purge all caches (since we just added a term to a published post and we want to start in a clean state again)
		$this->collection->purge_all();

		// re-populate the caches
		$this->_populateCaches();

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// force delete the post
		wp_delete_post( $this->published_post->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'userWithPostsConnection',
			'singleCategory',
			'listCategory',
			'singleNodeByUri',
			'singleNodeById',
			'listContentNode',
			'singleContentNode',
			'listPost',
			'singlePost',
			'singleApprovedCommentByGlobalId',
			'listComment',
			'listMenuItem', // the single menu item is linked to the published post, so this query should be evicted because when the post is deleted it deletes the associated menu item
			'singleMenuItem' // the single menu item is linked to the published post, so this query should be evicted because when the post is deleted it deletes the associated menu item
		], $evicted_caches );

	}

	// delete draft post (doesnt evoke purge action)
	public function testDraftPostIsForceDeleted() {

		// no caches should be evicted to start
		$non_evicted_caches_before_delete = $this->getNonEvictedCaches();
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete the draft post
		// this shouldn't evict any caches as the draft post shouldn't
		// be in the cache in the first place
		wp_delete_post( $this->draft_post->ID, true );

		// assert that no caches have been evicted
		// as a draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before_delete, $this->getNonEvictedCaches() );
	}

	// trashed post is restored
	public function testTrashedPostIsRestored() {

		// ensure we have no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// trash a post
		wp_trash_post( $this->draft_post->ID );

		// trashing the draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the trashed post
		wp_publish_post( $this->draft_post->ID );

		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );
		$this->assertNotEmpty( $non_evicted_caches );

		$this->assertEqualSets([
			'listContentNode',
			'listPost',
		], $evicted_caches );

	}

	// page is created as auto draft
	public function testPageIsCreatedAsAutoDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'draft'
		]);

		// creating a draft post should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// page is published from draft
	public function testDraftPageIsPublished() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_publish_post( $this->draft_page );

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// publishing a draft page should evict the list of pages
			// as we want the newly published page in the list
			'listPage',

			// publishing a draft page should evict the list of content nodes
			// as the newly published page should be in the list
			'listContentNode'
		], $evicted_caches );

	}

	// published page is changed to draft
	public function testPublishedPageIsChangedToDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published page to draft
		self::factory()->post->update_object( $this->published_page->ID, [
			'post_status' => 'draft'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listPage',
			'listContentNode',
			'singlePage'
		], $evicted_caches );

	}

	// published page is changed to private
	public function testPublishedPageIsChangedToPrivate() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published page to draft
		self::factory()->post->update_object( $this->published_page->ID, [
			'post_status' => 'private'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'singlePage',
			'listPage',
			'listContentNode'
		], $evicted_caches );

	}

	// published page is trashed
	public function testPublishedPageIsTrashed() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published page to trash
		self::factory()->post->update_object( $this->published_page->ID, [
			'post_status' => 'trash'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'singlePage',
			'listPage',
			'listContentNode'
		], $evicted_caches );

	}

	// published page is force deleted
	public function testPublishedPageIsForceDeleted() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// force delete the page
		wp_delete_post( $this->published_page->ID, true );

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listPage',
			'singlePage',
			'listContentNode',
			'listMenuItem', // the menu item links to the published page
			'singleChildMenuItem', // the menu item links to the published page
		], $evicted_caches );

	}

	// delete draft page (doesnt evoke purge action)
	public function testDeleteDraftPage() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->draft_page->ID, true );

		// deleting a draft page should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}


	// trashed page is restored
	public function testTrashedPageIsRestored() {

		// ensure we have no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// trash a page
		wp_trash_post( $this->draft_page->ID );

		// trashing the draft page shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the trashed page
		wp_publish_post( $this->draft_page->ID );

		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );
		$this->assertNotEmpty( $non_evicted_caches );

		//codecept_debug( [
//			'evicted' => $evicted_caches,
//			'non' => $non_evicted_caches
//		]);

		// publishing a page should evict the listContentNode cache
		$this->assertContains( 'listContentNode', $evicted_caches );

		// publishing a page should evict the listPage cache
		$this->assertContains( 'listPage', $evicted_caches );

		$this->assertEqualSets([
			'listContentNode',
			'listPage'
		], $evicted_caches );

		$this->assertNotEmpty( $non_evicted_caches );

	}

	// publish first post to a user (user->post connection should purge)
	public function testPublishFirstPostToUserShouldPurgeUserToPostConnection() {

		/**
		 * We could call purge() on the post->post_author node when a post is published, but that
		 * would purge any query that had the author node returned, such as queries for
		 * a single post and the post's author.
		 *
		 * Currently, by dropping the nested "list:$type" keys and _not_ calling purge( $author) when a post is published
		 * author archives will not purge on-demand but wait for natural expiration.
		 *
		 * Not ideal and something I think we should re-visit, hence marking this test incomplete.
		 */
		$this->markTestIncomplete( 'With WPGraphQL v1.14.5 nested list:type keys are not tracked in the X-GraphQL-Keys sp user.posts connections are not purged when a new post is published as that would purge every page the user is displayed on which could be a lot of individual pages being purged.' );

		$new_user = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		$query = $this->getQuery('userWithPostsConnection' );
		$variables = [ 'id' => $new_user->ID ];

		$cache_key = $this->collection->build_key( null, $query, $variables );

		$this->assertEmpty( $this->collection->get( $cache_key ) );

		$actual = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		codecept_debug( [
			'$query' => $query,
			'$variables' => $variables,
			'$actual' => $actual
		] );

		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'user', self::IS_NULL )
		]);

		// ensure the query is cached now
		$this->assertNotEmpty( $this->collection->get( $cache_key ) );
		$this->assertSame( $actual['data'], $this->collection->get( $cache_key )['data'] );

		$new_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $new_user->ID,
		]);

		// assert that the query for a user and the users post has been evicted
		$this->assertEmpty( $this->collection->get( $cache_key ) );

		$query_again = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		codecept_debug( $query_again );

		// the query should be cached again
		$this->assertNotEmpty( $this->collection->get( $cache_key ) );
		$this->assertSame( $query_again['data'], $this->collection->get( $cache_key )['data'] );

		// the results should have the user data
		self::assertQuerySuccessful( $query_again, [
			$this->expectedField( 'user.__typename', 'User' ),
			$this->expectedNode( 'user.posts.nodes', [
				'__typename' => 'Post',
				'databaseId' => $new_post->ID,
			]),
		]);

	}

	// delete only post of a user (user->post connection should purge)
	public function testDeleteOnlyPostOfUserShouldPurgeUserToPostConnection() {

		$new_user = self::factory()->user->create_and_get([
			'role' => 'administrator'
		]);

		$new_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => $new_user->ID,
		]);

		$query = $this->getQuery( 'userWithPostsConnection' );
		$variables = [ 'id' => $new_user->ID ];

		$cache_key = $this->collection->build_key( null, $query, $variables );

		$this->assertEmpty( $this->collection->get( $cache_key ) );

		$actual = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		codecept_debug( [
			'$query' => $query,
			'$variables' => $variables,
			'$actual' => $actual
		] );

		// the query should be cached again
		$this->assertNotEmpty( $this->collection->get( $cache_key ) );
		$this->assertSame( $actual['data'], $this->collection->get( $cache_key )['data'] );

		// the results should have the user data
		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'user.__typename', 'User' ),
			$this->expectedNode( 'user.posts.nodes', [
				'__typename' => 'Post',
				'databaseId' => $new_post->ID,
			]),
		]);


		self::factory()->post->update_object( $new_post->ID, [
			'post_status' => 'draft'
		]);

		// after setting the only post of the author to draft, the cache should be cleared
		$this->assertEmpty( $this->collection->get( $cache_key ) );


		$actual = graphql([
			'query' => $query,
			'variables' => $variables
		]);

		codecept_debug( $actual );

		// the results should now be null for the user as it's a private entity again
		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'user', self::IS_NULL )
		]);

	}

	// @todo
	// change only post of a user from publish to draft (user->post connection should purge)

	// change post author (user->post connection should purge)
	public function testChangeAuthorOfPost() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->post->update_object( $this->published_post->ID, [
			'post_author' => $this->editor->ID
		]);

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'editorUserWithPostsConnection',
			'userWithPostsConnection',
			'listPost',
			'singlePost',
			'singleContentNode',
			'listContentNode',
			'singleNodeByUri',
			'singleNodeById',

			// changing authors will evict caches for the new/old user
			'listUser',

			// changing authors will evict caches for the new/old user
			'adminUserByDatabaseId'
		], $evicted_caches );

	}

	// update post meta of draft post does not evict cache
	public function testUpdatePostMetaOfDraftPostDoesntEvictCache() {

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update meta on a draft post
		update_post_meta( $this->draft_post->ID, 'meta_key', uniqid( null, true ) );

		// there should be no evicted cache after updating meta of a draft post
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertEqualSets( $non_evicted_caches_before, $this->getNonEvictedCaches() );

	}

	// delete post meta of draft post does not evoke purge action
	public function testUpdatePostMetaOnDraftPost() {

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->draft_post->ID, 'test_key', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );

	}

	// update allowed (meta without underscore at the front) post meta on published post
	public function testUpdateAllowedPostMetaOnPost() {

		// there should be no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// meta is considered public if the key doesn't start win an underscore
		$key = 'test_meta_key';

		// we ensure the value is unique so that it properly triggers the updated_post_meta hook
		// if the value were the same as the previous value the hook wouldn't fire and we wouldn't
		// need to purge cache
		$value = 'value' . uniqid( 'test_', true );

		// update post meta on the published post.
		// if the meta doesn't exist yet, it will fire the "added_post_meta" hook
		update_post_meta( $this->published_post->ID, $key, $value );

		// this event SHOULD evict caches that contain the published post
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		//codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertEqualSets( [
			'listPost',
			'userWithPostsConnection',
			'listContentNode',
			'singleContentNode',
			'singleNodeById',
			'singleNodeByUri',
			'singlePost'
		], $evicted_caches );

		$this->assertNotSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );

	}

	public function testUpdateAllowedPostMetaOnPage() {

		// there should be no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// meta is considered public if the key doesn't start win an underscore
		$key = 'test_meta_key';

		// we ensure the value is unique so that it properly triggers the updated_post_meta hook
		// if the value were the same as the previous value the hook wouldn't fire and we wouldn't
		// need to purge cache
		$value = 'value' . uniqid( 'test_', true );

		// update post meta on the published PAGE.
		// if the meta doesn't exist yet, it will fire the "added_post_meta" hook
		update_post_meta( $this->published_page->ID, $key, $value );

		// this event SHOULD evict caches that contain the published PAGE
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		//codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertEqualSets( [
			'listPage',
			'singlePage',
			'listContentNode'
		], $evicted_caches );

		$non_evicted_caches_after = $this->getNonEvictedCaches();
		$this->assertNotSame( $non_evicted_caches_before, $non_evicted_caches_after );

	}

	public function testUpdateAllowedPostMetaOnCustomPostType() {

		// there should be no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// meta is considered public if the key doesn't start win an underscore
		$key = 'test_meta_key';

		// we ensure the value is unique so that it properly triggers the updated_post_meta hook
		// if the value were the same as the previous value the hook wouldn't fire and we wouldn't
		// need to purge cache
		$value = 'value' . uniqid( 'test_', true );

		// update post meta on the published PAGE.
		// if the meta doesn't exist yet, it will fire the "added_post_meta" hook
		update_post_meta( $this->published_test_post_type->ID, $key, $value );

		// this event SHOULD evict caches that contain the published PAGE
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		//codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertEqualSets( [
			'singleTestPostType',
			'listTestPostType',
			'listContentNode',
		], $evicted_caches );


		$non_evicted_caches_after = $this->getNonEvictedCaches();
		$this->assertNotSame( $non_evicted_caches_before, $non_evicted_caches_after );

	}

	public function testUpdateDisallowedPostMetaOnPost() {
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->published_post->ID, '_private_meta', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	public function testUpdateDisallowedPostMetaOnPage() {
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->published_page->ID, '_private_meta', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	public function testUpdateDisallowedPostMetaOnCustomPostType() {
		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the draft post
		update_post_meta( $this->published_test_post_type->ID, '_private_meta', uniqid( null, true ) );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}


	// delete post meta of published post
	public function testDeleteAllowedPostMetaOnPost() {

		// update post meta on the draft post
		update_post_meta( $this->published_post->ID, 'test_meta', uniqid( null, true ) );

		// the update post meta would have purged some caches, so we're going to repopulate the caches
		$this->_populateCaches();

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// delete the private meta, should not evict any caches
		delete_post_meta( $this->published_post->ID, 'test_meta' );

		// this event should not evict any caches
		$this->assertNotEmpty( $this->getEvictedCaches() );
		$this->assertNotSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	public function testDeleteAllowedPostMetaOnPage() {

		// setup the data
		update_post_meta( $this->published_page->ID, 'test_meta', uniqid( null, true ) );

		// the update post meta would have purged some caches, so we're going to repopulate the caches
		$this->_populateCaches();

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the published page, should not evict cache
		delete_post_meta( $this->published_page->ID, 'test_meta' );

		$evicted_caches = $this->getEvictedCaches();

		// this event SHOULD evict caches
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets( [
			'singlePage',
			'listPage',
			'listContentNode'
		], $evicted_caches );
	}

	public function testDeleteAllowedPostMetaOnCustomPostType() {

		// setup the data
		update_post_meta( $this->published_test_post_type->ID, 'test_meta', uniqid( null, true ) );

		// the update post meta would have purged some caches, so we're going to repopulate the caches
		$this->_populateCaches();

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// delete post meta on the test post type, should not evict caches
		delete_post_meta( $this->published_test_post_type->ID, 'test_meta' );

		$evicted_caches = $this->getEvictedCaches();

		// this event SHOULD evict caches
		$this->assertNotEmpty( $evicted_caches );
		$this->assertEqualSets( [
			'singleTestPostType',
			'listTestPostType',
			'listContentNode'
		], $evicted_caches );
	}

	public function testDeleteDisallowedPostMetaOnPost() {

		// update post meta on the draft post
		update_post_meta( $this->published_post->ID, '_private_meta', uniqid( null, true ) );

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// delete the private meta, should not evict any caches
		delete_post_meta( $this->published_post->ID, '_private_meta' );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	public function testDeleteDisallowedPostMetaOnPage() {

		// setup the data
		update_post_meta( $this->published_page->ID, '_private_meta', uniqid( null, true ) );

		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// update post meta on the published page, should not evict cache
		delete_post_meta( $this->published_page->ID, '_private_meta' );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	public function testDeleteDisallowedPostMetaOnCustomPostType() {

		// setup the data
		update_post_meta( $this->published_test_post_type->ID, '_private_meta', uniqid( null, true ) );


		$this->assertEmpty( $this->getEvictedCaches() );
		$non_evicted_caches_before = $this->getNonEvictedCaches();

		// delete post meta on the test post type, should not evict caches
		delete_post_meta( $this->published_test_post_type->ID, '_private_meta' );

		// this event should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before, $this->getNonEvictedCaches() );
	}

	// post of publicly queryable/show in graphql cpt is created as auto draft
	public function testCreateDraftCustomPostTypePostDoesNotInvalidatePostCache() {

		// all queries should be in the cache, non should be empty
		$this->assertEmpty( $this->getEvictedCaches() );

		// create an auto draft post
		self::factory()->post->create([
			'post_status' => 'auto-draft',
			'post_type' => 'test_post_type'
		]);

		// after creating an auto-draft post, there should be no caches that were emptied
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// post of publicly queryable/show in graphql cpt is published from draft
	public function testPublishingScheduledCustomPostTypePostWithoutAssociatedTerm() {

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the scheduled post
		wp_publish_post( $this->scheduled_post );

		// get the evicted caches
		$evicted_caches = $this->getEvictedCaches();

		// when publishing a scheduled post, the listPost and listContentNode queries should have been cleared
		$this->assertEqualSets([
			'listPost',
			'listContentNode',
		], $evicted_caches );

	}

	public function testPublishingScheduledPostWithCustomTaxTermAssigned() {

		// ensure all queries have a cache
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the scheduled post type with associated term
		wp_publish_post( $this->scheduled_custom_post_type_with_term->ID );

		// check to see if the term is associated 👀
		//codecept_debug( [ 'associated_term' => wp_get_object_terms( $this->scheduled_custom_post_type_with_term->ID, 'test_taxonomy' ) ]);

		// get the evicted caches _after_ publish
		$evicted_caches = $this->getEvictedCaches();

		//codecept_debug( [ 'evicted' => $evicted_caches ] );

		// when publishing a scheduled post of the test_post_type with an associated term,
		// the listTestPostType and listContentNode queries should have been evicted
		// but also the listTestTaxonomyTerm and singleTestTaxonomyTerm as the termCount
		// needs to be updated on the terms
		$this->assertEqualSets([
			'listTestPostType',
			'listContentNode',
			'listTestTaxonomyTerm',
			'singleTestTaxonomyTerm'
		], $evicted_caches );


	}

	// published post of publicly queryable/show in graphql cpt is changed to draft
	public function testPublishedPostOfTestPostTypeWithAssociatedTermIsChangedToDraft() {

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_test_post_type_with_term->ID, [
			'post_status' => 'draft',
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();

		//codecept_debug( [ 'evicted' => $evicted_caches ]);
		$non_evicted_caches = $this->getNonEvictedCaches();

		// make assertions about the evicted caches
		$this->assertNotEmpty( $evicted_caches );

		// assert all the caches that should have been evicted
		$this->assertEqualSets( [
			// the post that was unpublished was part of the list, and should be evicted
			'listTestPostType',
			// the post was part of the content node list and should be evicted
			'listContentNode',
			// the post was associated with a test taxonomy term
			'listTestTaxonomyTerm',
			// the post was part of the content node list and should be evicted
			'singleTestTaxonomyTerm'
		], $evicted_caches );


	}

	// published post of publicly queryable/show in graphql cpt is changed to private
	public function testPublishPostOfTestPostTypeChangedToPrivate() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_test_post_type->ID, [
			'post_status' => 'private'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		//codecept_debug( [ 'evicted' => $evicted_caches ]);

		$this->assertEqualSets( [
			'singleTestPostType',
			'listTestPostType',
			'listContentNode',
		], $evicted_caches );

	}

	// published post of publicly queryable/show in graphql cpt is trashed
	public function testPublishedPostOfTestPostTypeIsTrashed() {

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_test_post_type->ID, [
			'post_status' => 'trash'
		] );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'singleTestPostType',
			'listTestPostType',
			'listContentNode',
		], $evicted_caches );
	}


	// published post of publicly queryable/show in graphql cpt is force deleted
	public function testPublishedPostOfTestPostTypeIsForceDeleted() {

		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->published_test_post_type->ID, true );

		// assert that caches have been evicted
		$evicted_caches = $this->getEvictedCaches();

		$this->assertEqualSets([
			'singleTestPostType',
			'listTestPostType',
			'listContentNode',
		], $evicted_caches );
	}

	// delete draft post of publicly queryable/show in graphql post type (doesn't evoke purge action)
	public function testDraftPostOfTestPostTypeIsForceDeleted() {

		// no caches should be evicted to start
		$non_evicted_caches_before_delete = $this->getNonEvictedCaches();
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete the draft post
		// this shouldn't evict any caches as the draft post shouldn't
		// be in the cache in the first place
		wp_delete_post( $this->draft_test_post_type->ID, true );

		// assert that caches have been evicted
		// as a draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before_delete, $this->getNonEvictedCaches() );
	}

	// trashed post of publicly queryable/show in graphql post type
	public function testTrashedPostOfTestPostTypeIsRestored() {

		// ensure we have no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// trash a post
		wp_trash_post( $this->draft_test_post_type->ID );

		// trashing the draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish the trashed post
		wp_publish_post( $this->draft_test_post_type->ID );

		$evicted_caches = $this->getEvictedCaches();
		$non_evicted_caches = $this->getNonEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );
		$this->assertNotEmpty( $non_evicted_caches );

		$this->assertEqualSets([
			'listContentNode',
			'listTestPostType',
		], $evicted_caches );

	}

	// post of non-gql post type cpt is created as auto draft
	public function testPrivatePostTypePostIsCreatedAsAutoDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->post->create([
			'post_type' => 'private_post_type',
			'post_status' => 'draft'
		]);

		// creating a draft post should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// post of private cpt is published from draft
	public function testDraftPrivatePostTypeIsPublished() {

		// evicted caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish a draft of a private post type
		wp_publish_post( $this->draft_private_post_type );

		// there should be no evicted caches because the post type is
		// private and not publicly queryable, so therefore
		// a publish event shouldn't invalidate the cache
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// scheduled post of private cpt is published
	// @todo

	// published post of private cpt is changed to draft
	public function testPublishedPrivatePostTypePostIsChangedToDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published private post to draft
		self::factory()->post->update_object( $this->published_private_post_type->ID, [
			'post_status' => 'draft'
		]);

		$evicted_caches = $this->getEvictedCaches();

		// since a private post was updated, cache shouldn't have changed
		// because it's not publicly queryable
		$this->assertEmpty( $evicted_caches );

	}

	// published post of private cpt is changed to private
	public function testPublishPostOfPrivatePostTypeChangedToPrivate() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_private_post_type->ID, [
			'post_status' => 'private'
		] );

		// assert that caches remain unchanged.
		// changing status of a private post type shouldn't evict caches
		// as it's not publicly queryable
		$this->assertEmpty( $this->getEvictedCaches() );
	}

	// published post of private cpt is trashed
	public function testPublishPostOfPrivatePostTypeIsTrashed() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post of private post type to draft status
		self::factory()->post->update_object( $this->published_private_post_type->ID, [
			'post_status' => 'trash'
		] );

		// trashing a post of a private post type shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// published post of private cpt is force deleted
	public function testPublishPostOfPrivatePostTypeIsForceDeleted() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->published_private_post_type->ID, true );

		// force deleting a post of a private post type shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// delete draft post of private post type (doesnt evoke purge action)
	public function testDraftPostOfPrivatePostTypeIsForceDeleted() {

		// no caches should be evicted to start
		$non_evicted_caches_before_delete = $this->getNonEvictedCaches();
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete the draft post
		// this shouldn't evict any caches as the draft post shouldn't
		// be in the cache in the first place
		wp_delete_post( $this->draft_private_post_type->ID, true );

		// assert that no caches have been evicted
		// as a draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before_delete, $this->getNonEvictedCaches() );
	}

	// post of public=>false/publicly_queryable=>true post type cpt is created as auto draft
	public function testPubliclyQueryablePostTypePostIsCreatedAsAutoDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->post->create([
			'post_type' => 'publicly_queryable',
			'post_status' => 'draft'
		]);

		// creating a draft should not evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// post of public=>false/publicly_queryable=>true cpt is published from draft
	public function testDraftPubliclyQueryablePostTypeIsPublished() {

		// evicted caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// publish a draft of a public=>false/publicly_queryable=>true post type
		wp_publish_post( $this->draft_publicly_queryable_post_type );


		$this->assertEqualSets( [
			// should be evicted to show the new post
			'listPubliclyQueryablePostType',

			// listContentNodes should be evicted because the publicly queryable
			'listContentNode'
		], $this->getEvictedCaches() );

	}

	// scheduled post of public=>false/publicly_queryable=>true cpt is published
	// @todo

	// published post of public=>false/publicly_queryable=>true cpt is changed to draft
	public function testPublishedPubliclyQueryablePostTypePostIsChangedToDraft() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// set the published public=>false/publicly_queryable=>true post to draft
		self::factory()->post->update_object( $this->published_publicly_queryable_post_type->ID, [
			'post_status' => 'draft'
		]);

		$evicted_caches = $this->getEvictedCaches();

		// since a public=>false/publicly_queryable=>true post was updated,
		// cache should be evicted for list of content nodes
		$this->assertEqualSets([
			'listPubliclyQueryablePostType',

			// listContentNodes should be evicted because the publicly queryable
			'listContentNode'
		], $evicted_caches );

	}

	// published post of public=>false/publicly_queryable=>true cpt is changed to private
	public function testPublishPostOfPubliclyQueryablePostTypeChangedToPrivate() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post to draft status
		self::factory()->post->update_object( $this->published_publicly_queryable_post_type->ID, [
			'post_status' => 'private'
		] );

		$this->assertEqualSets( [

			// should be evicted because the publicly queryable post would have been in the list
			'listPubliclyQueryablePostType',

			// should be evicted because the publicly queryable post would have been in the list
			'listContentNode'
		], $this->getEvictedCaches() );
	}

	// published post of public=>false/publicly_queryable=>true cpt is trashed
	public function testPublishPostOfPubliclyQueryablePostTypeIsTrashed() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// update a published post of private post type to draft status
		self::factory()->post->update_object( $this->published_publicly_queryable_post_type->ID, [
			'post_status' => 'trash'
		] );

		// trashing a post of a private post type shouldn't evict any caches
		$this->assertEqualSets( [

			// should be evicted because the publicly queryable post would have been in the list
			'listPubliclyQueryablePostType',

			// should be evicted because the publicly queryable post would have been in the list
			'listContentNode'
		], $this->getEvictedCaches() );

	}

	// published post of public=>false/publicly_queryable=>true cpt is force deleted
	public function testPublishPostOfPubliclyQueryablePostTypeIsForceDeleted() {
		// no caches should be evicted to start
		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_post( $this->published_publicly_queryable_post_type->ID, true );

		$this->assertEqualSets( [

			// should be evicted because the publicly queryable post would have been in the list
			'listPubliclyQueryablePostType',

			// should be evicted because the publicly queryable post would have been in the list
			'listContentNode'
		], $this->getEvictedCaches() );

	}

	// delete draft post of public=>false/publicly_queryable=>true post type (doesnt evoke purge action)
	public function testDraftPostOfPubliclyQueryablePostTypeIsForceDeleted() {

		// no caches should be evicted to start
		$non_evicted_caches_before_delete = $this->getNonEvictedCaches();
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete the draft post
		// this shouldn't evict any caches as the draft post shouldn't
		// be in the cache in the first place
		wp_delete_post( $this->draft_publicly_queryable_post_type->ID, true );

		// assert that no caches have been evicted
		// as a draft post shouldn't evict any caches
		$this->assertEmpty( $this->getEvictedCaches() );
		$this->assertSame( $non_evicted_caches_before_delete, $this->getNonEvictedCaches() );
	}

}
