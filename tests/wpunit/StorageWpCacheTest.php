<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Storage\WpCache;

class StorageWpCacheTest extends \Codeception\TestCase\WPTestCase {

    public function testAddDeleteData() {
        $key = uniqid( 'test-' );
        $data = [ 'foo-bar' ];

        // Save data
        $cache = new WpCache( 'wpcache-group0' );
        $cache->set( $key, $data, 600 );

        // Make sure we can get the data using the class
        $actual = $cache->get( $key );
        $this->assertEquals( $data, $actual );

        // Delete the data and verify response when not there anymore
        $this->assertTrue( $cache->delete( $key ) );
        $this->assertFalse( $cache->delete( $key ) );
    }

    public function testPurgeAll() {
        // Save data
        $cache = new WpCache( 'wpcache-group1' );
        $cache->set( 'test-1', [ uniqid( 'foobar-' ) ], 600 );
        $cache->set( 'test-2', [ uniqid( 'foobar-' ) ], 600 );

        $cache->purge_all();
        $this->assertFalse( $cache->get( 'test-1') );
        $this->assertFalse( $cache->get( 'test-2') );
    }
}
