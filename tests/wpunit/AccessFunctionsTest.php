<?php

class AccessFunctionsTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function testGraphqlFormatFieldName() {
		$actual   = graphql_format_field_name( 'This is some field name' );
		$expected = 'thisIsSomeFieldName';
		self::assertEquals( $expected, $actual );
	}

}