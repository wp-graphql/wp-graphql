<?php
namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Collection;

class UserCacheInvalidationTest extends \TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

    public $collection;

    public function setUp(): void {
        \WPGraphQL::clear_schema();

        if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
            define( 'GRAPHQL_DEBUG', true );
        }

        $this->collection = new Collection();

        // enable caching for the whole test suite
        add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

        parent::setUp();
    }

    public function tearDown(): void {
        \WPGraphQL::clear_schema();
        // disable caching
        delete_option( 'graphql_cache_section' );
        parent::tearDown();
    }

    public function testItWorks() {
        $this->assertTrue( true );
    }

    // create user (no purge, not public yet)
    public function testCreateUserDoesNotInvalidateUserCache() {
        // all queries should be in the cache, non should be empty
        $this->assertEmpty( $this->getEvictedCaches() );

        // Create a user for this test
        self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        // there should be no caches that were emptied
        $this->assertEmpty( $this->getEvictedCaches() );
    }

    // delete user with no published posts (no purge)
    public function testDeleteUserWithNoPostsDoesNotInvalidateUserCache() {
        // Create a user for this test
        $user_id = self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        wp_delete_user( $user_id );

        // there should be no caches that were emptied
        $this->assertEmpty( $this->getEvictedCaches() );
    }

    // delete user without re-assign (what should happen here?)
    // - call purge for each post the author was the author of?
    public function testDeleteUserAndPosts() {

        // delete user
        wp_delete_user( $this->editor->ID );

        // caches that were emptied because the user and it's created posts were delete
        $evicted = $this->getEvictedCaches();

		$this->assertEqualSets([
			'listPost',
			'listContentNode',
			'editorUserWithPostsConnection',
			'listUser',
			'singlePostByEditor'
		], $evicted);
    }

    // delete user and re-assign posts
    // - purge user
    // - purge for each post (of each post type) transferred
    // - purge for the new author being assigned
    public function testDeleteUserAndReassignPostsToUserWithNoPosts() {
        $user_id = self::factory()->user->create( [
            'role' => 'editor',
            'first_name' => 'foo',
            'last_name' => 'bar',
        ] );

        // Because we created the above user, start over because we want to isolate the delete/reassign evictions
        $this->_populateCaches();

		// delete the user and re-assign the posts to a new user
        wp_delete_user( $this->editor->ID, $user_id );

        codecept_debug( $this->getEvictedCaches() );

        // expect query for specific user, either the one being deleted or the assignment to be evicted
        $evicted = $this->getEvictedCaches();

		// The only query that should have been evicted is
	    // the editorUserWithPostsConnection
        $this->assertEqualSets( [

			// since the editor user has posts assigned to it, we can expect this query to be purged
			'editorUserWithPostsConnection',

			// since the deleted user was part of the listUser results, we can
	        // expect this query to be evicted
	        'listUser',

	        // the list of posts should include the post that was authored by the editor and re-assigned
	        'listPost',

			// the post authored by the editor was in this list, so this list should be evicted
	        'listContentNode',

			// the editor was deleted so the cache for the post authored by the editor should be evicted
	        'singlePostByEditor'

        ], $evicted );

    }

	public function testDeleteUserAndReassignPostsToUserWithOtherPublishedPosts() {

		$user_with_one_post = self::factory()->user->create_and_get([
			'role' => 'administrator',
		]);

		$post_by_user_with_one_post = self::factory()->post->create_and_get([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'test',
			'post_author' => $user_with_one_post->ID
		]);

		// since we just created some new mock data, lets refresh the caches
		$this->_populateCaches();

		// delete the user and re-assign the posts to the $post_by_user_with_one_post user
		wp_delete_user( $this->editor->ID, $post_by_user_with_one_post->ID );

		codecept_debug( $this->getEvictedCaches() );

		// expect query for specific user, either the one being deleted or the assignment to be evicted
		$evicted = $this->getEvictedCaches();

		// The user->posts connection should be invalidated
		// since the authors changed
		$this->assertEqualSets( [
			// this is invalidated because it's the user being deleted
			'editorUserWithPostsConnection',

			// the posts being reassigned were in the listContentNode
			// results so this should be evicted
			'listContentNode',

			// the posts being re-assigned were in the listPost results
			// so this should be evicted
			'listPost',

			// the editor user (that's being deleted) was part of the
			// listUser query so this query should be evicted
			'listUser',

			// This is invalidated because the editor was deleted and its post was re-assigned
			'singlePostByEditor',


		], $evicted );
	}

    // update user that has published posts
    public function testUpdateUserNameAndPurgeCache() {
        $fields['first_name'] = 'biz';

        self::factory()->user->update_object( $this->editor->ID, $fields );

        codecept_debug( $this->getEvictedCaches() );

        // caches that were emptied because the user was deleted and posts reassigned
        $evicted = $this->getEvictedCaches();

		$this->assertEqualSets([
			// the editor user was updated, this should be evicted
			'editorUserWithPostsConnection',

			// the editor user was part of the listUser query so this query
			// should be evicted
			'listUser'
		], $evicted );

    }

    // update user meta (with allowed meta key)
    public function testUpdateAllowedUserMetaAndPurgeCache() {

		// assert that there are no evicted caches to start
		$this->assertEmpty( $this->getEvictedCaches() );

		// updating "allowed" user meta should purge the cache for queries containing that user
		update_user_meta( $this->editor->ID, 'foo_data', 'bar-biz-bang' );

		// get the evicted caches after the update user meta action
		$evicted = $this->getEvictedCaches();
        codecept_debug( $this->getEvictedCaches() );

		$this->assertEqualSets([
			// this should be purged because it was a query for the editor, which had meta changed
			'editorUserWithPostsConnection',

			// the editor user was part of the listUser query so this query
			// should be evicted
			'listUser'
        ], $evicted );
    }

    // update user meta (with non-allowed meta key)
    public function testUpdateNonAllowedUserMetaAndPurgeCache() {

	    // assert that there are no evicted caches to start
	    $this->assertEmpty( $this->getEvictedCaches() );

		// private meta is meta that starts with an underscore
	    // updating it shouldn't evict the cache
        update_user_meta( $this->editor->ID, '_private_meta', 'foo@example.com' );

		// assert that there are no evicted caches after private user meta is updated
	    $this->assertEmpty( $this->getEvictedCaches() );
    }

    // delete user meta (with allowed meta key)
    public function testDeleteAllowedUserMetaAndPurgeCache() {

		// add some meta to the user to start
		update_user_meta( $this->editor->ID, 'foo_data', uniqid( null, true ) );

		// re-populate the caches since we just ran an action that would evict caches
		$this->_populateCaches();

		// there should be no evicted caches to start
        $this->assertEmpty( $this->getEvictedCaches() );

		// delete the user meta
		delete_user_meta( $this->editor->ID, 'foo_data' );

		$evicted = $this->getEvictedCaches();

        $this->assertEqualSets( [
	        // this should have be evicted because the editor user's meta changed
			'editorUserWithPostsConnection',

	        // the editor user was part of the listUser query so this query
	        // should be evicted
	        'listUser'
        ], $evicted );
    }

    // delete user meta (with non-allowed meta key)
    public function testDeleteNonAllowedUserMetaAndPurgeCache() {

		// add some meta to the user to start
	    update_user_meta( $this->editor->ID, '_private_meta', uniqid( null, true ) );

	    // re-populate the caches since we just ran an action that would evict caches
	    $this->_populateCaches();

	    // there should be no evicted caches to start
	    $this->assertEmpty( $this->getEvictedCaches() );

	    // private meta is meta that starts with an underscore
	    // deleiting it shouldn't evict the cache
	    delete_user_meta( $this->editor->ID, '_private_meta' );

	    $evicted = $this->getEvictedCaches();

	    $this->assertEmpty( $evicted );
    }

	// update user meta (with ignored meta key)
	public function testUpdateIgnoredUserMetaAndPurgeCache() {

		// re-populate the caches since we just ran an action that would evict caches
		$this->_populateCaches();

		// add some meta to the user to start
		update_user_meta( $this->editor->ID, 'apple_news_notice', uniqid( null, true ) );

		$evicted = $this->getEvictedCaches();

		$this->assertEmpty( $evicted );
	}

}
