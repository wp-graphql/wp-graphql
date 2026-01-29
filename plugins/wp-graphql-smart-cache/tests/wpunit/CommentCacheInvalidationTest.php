<?php

namespace WPGraphQL\SmartCache;

use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;

class CommentCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// create comment (unapproved)
	public function testCreateUnapprovedCommentDoesNotEvictCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->create_object( [
			'comment_approved' => 0
		] );

		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// create comment (approved)
	public function testCreateApprovedCommentEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->create_object( [
			'comment_approved' => 1,
			'comment_post_ID' => $this->published_post->ID,
		] );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment'
		], $evicted_caches );

	}

	// approve comment
	public function testTransitionCommentToApprovedEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->update_object( $this->unapproved_comment->comment_ID, [
			'comment_approved' => 1
		] );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
		], $evicted_caches );

	}

	// unapprove comment
	public function testTransitionCommentToUnapprovedEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->update_object( $this->approved_comment->comment_ID, [
			'comment_approved' => 0
		] );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
			'singleApprovedCommentByGlobalId'
		], $evicted_caches );

	}

	// delete comment
	public function testDeleteApprovedCommentEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_comment( $this->approved_comment->comment_ID );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([
			'listComment',
			'singleApprovedCommentByGlobalId'
		], $evicted_caches );

	}

	/**
	 * transition not-approved comment to another not-approved status
	 * See WP transition_comment_status().
	 * unapproved -> spam
	 */
	public function testTransitionUnApprovedCommentToSpamDoesNotEvictCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		self::factory()->comment->update_object( $this->unapproved_comment->comment_ID, [
			'comment_approved' => 'spam'
		] );

		$this->assertEmpty( $this->getEvictedCaches() );
	}

}
