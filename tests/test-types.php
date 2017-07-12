<?php
/**
 * WPGraphQL Test Types.php
 * @package WPGraphQL
 */

/**
 * Tests user object queries.
 */
class WP_GraphQL_Test_Types extends WP_UnitTestCase {
	/**
	 * This function is run before each method
	 *
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Runs after each method.
	 *
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function testMapInput() {

		/**
		 * Testing with invalid input
		 */
		$actual = \WPGraphQL\Types::map_input( 'string', 'another string' );
		$this->assertEquals( [], $actual );

		/**
		 * Setup some args
		 */
		$map = [
			'stringInput' => 'string_input',
			'intInput' => 'int_input',
			'boolInput' => 'bool_input',
			'inputObject' => 'input_object',
		];

		$input_args = [
			'stringInput' => 'value 2',
			'intInput' => 2,
			'boolInput' => false,
		];

		$args = [
			'stringInput' => 'value',
			'intInput' => 1,
			'boolInput' => true,
			'inputObject' => \WPGraphQL\Types::map_input( $input_args, $map ),
		];

		$expected = [
			'string_input' => 'value',
			'int_input' => 1,
			'bool_input' => true,
			'input_object' => [
				'string_input' => 'value 2',
				'int_input' => 2,
				'bool_input' => false,
			],
		];

		$actual = \WPGraphQL\Types::map_input( $args, $map );

		$this->assertEquals( $expected, $actual );

	}

}
