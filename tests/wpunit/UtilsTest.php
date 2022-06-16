<?php

use WPGraphQL\Utils\Utils;

class UtilsTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// before
		WPGraphQL::clear_schema();
		parent::setUp();
		// your set up methods here
	}

	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	/**
	 * Tests Utils::get_home_url()
	 */
	public function testGetHomeUrl() : void {
		// Test method works the same as home_url().
		$this->assertEquals( home_url(), Utils::get_home_url() );

		$this->assertEquals( home_url( 'example' ), Utils::get_home_url( 'example' ) );

		// Test with filtered frontend url.
		$frontend_url = 'https://example.com';
		add_filter( 'graphql_home_url', function ( $url, $path ) use ( $frontend_url ) {
			return $frontend_url . '/' . ltrim( $path, '/' );
		}, 10, 2);

		$actual = Utils::get_home_url( 'example' );
		codecept_debug( $actual );

		$this->assertNotEquals( home_url( 'example' ), $actual );
		$this->assertStringStartsWith( $frontend_url, $actual );
	}

	/**
	 * Tests Utils::get_relative_uri();
	 */
	public function testGetRelativeUri() : void {
		// Test empty url
		$this->assertNull( Utils::get_relative_uri( '' ) );

		// Test external url.
		$expected = 'https://example.com/example';

		$actual = Utils::get_relative_uri( $expected );

		$this->assertEquals( $expected, $actual );

		// Test relative to current site.
		$expected = '/example';
		$url      = home_url( $expected );

		$actual = Utils::get_relative_uri( $url );

		$this->assertEquals( $expected, $actual );

		// Test with TLD.
		add_filter( 'graphql_home_url', function ( $home_url ) {
			return str_ireplace( home_url(), 'https://example.com', $home_url );
		} );

		$url    = Utils::get_home_url( $expected );
		$actual = Utils::get_relative_uri( $url );
		codecept_debug( $actual );

		$this->assertEquals( $expected, $actual );

		// Test with subdomain.
		add_filter( 'graphql_home_url', function ( $home_url ) {
			return str_ireplace( home_url(), 'https://mysubdomain.example.com', $home_url );
		} );

		$url    = Utils::get_home_url( $expected );
		$actual = Utils::get_relative_uri( $url );

		$this->assertEquals( $expected, $actual );

		// Test with subdir.
		add_filter( 'graphql_home_url', function ( $home_url ) {
			return str_ireplace( home_url(), 'https://www.example.com/my-subdir', $home_url );
		} );

		$url    = Utils::get_home_url( $expected );
		$actual = Utils::get_relative_uri( $url );

		$this->assertEquals( $expected, $actual );
	}

}
