<?php

namespace WPGraphQL\Test\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use WPGraphQL\Type\Scalar\Timezone;

class TimezoneTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::serialize
	 */
	public function testSerializeValidTimezone() {
		$this->assertEquals( 'Europe/London', Timezone::serialize( 'Europe/London' ) );
		$this->assertEquals( 'UTC', Timezone::serialize( 'UTC' ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::serialize
	 */
	public function testSerializeInvalidTimezone() {
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
		Timezone::serialize( 'Mars/Olympus_Mons' );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::parseValue
	 */
	public function testParseValueValidTimezone() {
		$this->assertEquals( 'Australia/Sydney', Timezone::parseValue( 'Australia/Sydney' ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::parseValue
	 */
	public function testParseValueInvalidTimezone() {
		$this->expectException( Error::class );
		Timezone::parseValue( 'pluto' );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::parseLiteral
	 */
	public function testParseLiteralValidTimezone() {
		$node = new StringValueNode( [ 'value' => 'America/Chicago' ] );
		$this->assertEquals( 'America/Chicago', Timezone::parseLiteral( $node, null ) );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::parseLiteral
	 */
	public function testParseLiteralInvalidTimezone() {
		$this->expectException( Error::class );
		$node = new StringValueNode( [ 'value' => 'not-a-timezone' ] );
		Timezone::parseLiteral( $node, null );
	}

	/**
	 * @covers \WPGraphQL\Type\Scalar\Timezone::parseLiteral
	 */
	public function testParseLiteralInvalidNode() {
		$this->expectException( Error::class );
		Timezone::parseLiteral( new IntValueNode( [ 'value' => '1' ] ), null );
	}
}