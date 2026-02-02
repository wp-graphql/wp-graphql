<?php
namespace WPGraphQL\SmartCache;

use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;

class MediaItemCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// upload media item
	public function testUploadMediaItemEvictsCache() {

		// uploading a media item should evict cache for list of media items
		$filename = WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . '/tests/_data/images/test.png';
		codecept_debug( $filename );

		$this->assertEmpty( $this->getEvictedCaches() );

		$image_id = self::factory()->attachment->create_upload_object( $filename );

		$evicted_caches = $this->getEvictedCaches();
		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets([

			// purge list of media items when a new image is uploaded
			'listMediaItem',

			// should media items be content nodes? 🤔

			'listContentNode'
		], $evicted_caches );

	}

	// update media item
	public function testUpdateMediaItemEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// updating a media item should evict cache for single media item and list media items
		wp_update_post([ 'ID' => $this->mediaItem->ID, 'post_content' => 'test...' ]);

		$evicted = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted );

		codecept_debug( [
			'id' => $this->mediaItem->ID,
			'listMediaItem' => $this->query_results['listMediaItem'],
			'singleMediaItem' => $this->query_results['singleMediaItem']
		]);

		$this->assertEqualSets([
			// updating a media item should evict the single query for it
			'singleMediaItem',

			// should evict the list that had the item in it
			'listMediaItem'
		], $evicted );

	}

	// delete media item
	public function testDeleteMediaItem() {

		// evict cache for single media item, list media item

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_attachment($this->mediaItem->ID);

		$evicted = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted );
		$this->assertEqualSets([
			// deleting a media item should evict the single query for it
			'singleMediaItem',

			// should evict the list that had the item in it
			'listMediaItem'
		], $evicted );

	}

	// update media item meta
	public function updateMediaItemMetaShouldEvictCache() {

	}

	// delete media item meta
	public function deleteMediaItemMetaShouldEvictCache() {

	}


}
