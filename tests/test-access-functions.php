<?php

/**
 * WPGraphQL Test Access Functions
 * @package WPGraphQL
 */

class WP_GraphQL_Test_Access_Functions extends WP_UnitTestCase {

	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Tests the access function that is available to format strings to GraphQL friendly format
	 */
	public function testGraphQLFormatFieldName() {

		$actual = graphql_format_field_name( 'This is some field name' );
		$expected = 'thisIsSomeFieldName';

		$this->assertEquals( $expected, $actual );

	}
}
