<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Storage\Transient;

class StorageTransientTest extends \Codeception\TestCase\WPTestCase {

    public function testAddDeleteData() {
        $group = 'my-group0';
        $key = uniqid( 'test-' );
        $data = [ 'foo-bar' ];

        // Save data
        $cache = new Transient( $group );
        $cache->set( $key, $data, 600 );

        // Use WP function to verify we can get the data
        $actual = get_transient( $group . '_' . $key );
        $this->assertEquals( $data, $actual );

        // Make sure we can get the data using the class
        $actual = $cache->get( $key );
        $this->assertEquals( $data, $actual );

        // Delete the data and verify response when not there anymore
        $this->assertTrue( $cache->delete( $key ) );
        $this->assertFalse( $cache->delete( $key ) );
        $this->assertFalse( get_transient( $group . '_' . $key ) );
    }

    public function testPurgeAll() {
        // Save data
        $cache = new Transient( 'my-group1' );
        $cache->set( 'test-1', [ uniqid( 'foobar-' ) ], 600 );
        $cache->set( 'test-2', [ uniqid( 'foobar-' ) ], 600 );

        $cache->purge_all();
        $this->assertFalse( $cache->get( 'test-1') );
        $this->assertFalse( $cache->get( 'test-2') );
    }
}
