<?php

use WPGraphQL\Admin\Updates\SemVer;

class SemVerTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Tests the get_release_type method.
	 */
	public function testGetReleaseType() {

		// Test from 1.x.y
		$this->assertEquals( 'major', SemVer::get_release_type( '1.2.3', '2.0.0' ) );
		$this->assertEquals( 'minor', SemVer::get_release_type( '1.2.3', '1.3.0' ) );
		$this->assertEquals( 'patch', SemVer::get_release_type( '1.2.3', '1.2.4' ) );
		$this->assertEquals( 'prerelease', SemVer::get_release_type( '1.2.3', '1.2.3-alpha.1' ) );
		$this->assertEquals( 'unknown', SemVer::get_release_type( '1.2.3', 'invalid-version' ) );

		// Test from 0.1.x
		$this->assertEquals( 'major', SemVer::get_release_type( '0.1.2', '1.0.0' ) );
		$this->assertEquals( 'minor', SemVer::get_release_type( '0.1.2', '0.2.0' ) );
		$this->assertEquals( 'patch', SemVer::get_release_type( '0.1.2', '0.1.3' ) );
		$this->assertEquals( 'prerelease', SemVer::get_release_type( '0.1.2', '0.1.2-alpha.1' ) );
		$this->assertEquals( 'unknown', SemVer::get_release_type( '0.1.2', 'invalid-version' ) );

		// Test from 0.0.1
		$this->assertEquals( 'major', SemVer::get_release_type( '0.0.1', '1.0.0' ) );
		$this->assertEquals( 'minor', SemVer::get_release_type( '0.0.1', '0.1.0' ) );
		$this->assertEquals( 'patch', SemVer::get_release_type( '0.0.1', '0.0.2' ) );
		$this->assertEquals( 'prerelease', SemVer::get_release_type( '0.0.1', '0.0.1-alpha.1' ) );
		$this->assertEquals( 'unknown', SemVer::get_release_type( '0.0.1', 'invalid-version' ) );
	}

	/**
	 * Tests the parse method.
	 */
	public function testParse() {
		// Test invalid version.
		$this->assertNull( SemVer::parse( 'invalid-version' ) );

		// Test valid version.
		$version = SemVer::parse( '1.2.3' );
		$this->assertEquals( 1, $version['major'] );
		$this->assertEquals( 2, $version['minor'] );
		$this->assertEquals( 3, $version['patch'] );
		$this->assertNull( $version['prerelease'] );
		$this->assertNull( $version['buildmetadata'] );

		// Test version with prerelease
		$version = SemVer::parse( '1.2.3-alpha' );
		$this->assertEquals( 1, $version['major'] );
		$this->assertEquals( 2, $version['minor'] );
		$this->assertEquals( 3, $version['patch'] );

		// Test version with prerelease and buildmetadata
		$version = SemVer::parse( '1.2.3-alpha.1+buildmetadata' );
		$this->assertEquals( 1, $version['major'] );
		$this->assertEquals( 2, $version['minor'] );
		$this->assertEquals( 3, $version['patch'] );
		$this->assertEquals( 'alpha.1', $version['prerelease'] );
		$this->assertEquals( 'buildmetadata', $version['buildmetadata'] );
	}
}
