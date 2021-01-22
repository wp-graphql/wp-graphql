<?php

class WPGraphQLAccessFunctionsTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Tests the access function that is available to format strings to GraphQL friendly format
	 */
	public function testGraphQLFormatFieldName() {

		$actual   = graphql_format_field_name( 'This is some field name' );
		$expected = 'thisIsSomeFieldName';

		$this->assertEquals( $expected, $actual );

	}

}
