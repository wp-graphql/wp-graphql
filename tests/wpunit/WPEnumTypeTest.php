<?php

use WPGraphQL\Type\WPEnumType;

class WPEnumTypeTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// before
		parent::setUp();
		// your set up methods here
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	/**
	 * Test filter for WP enum type invokes.
	 *
	 * @throws \Exception
	 */
	public function testEnumValuesFilterRuns() {

		/**
		 * Filter fields onto the Enum
		 */
		add_filter(
			'graphql_enum_values',
			static function ( $values ) {

				foreach ( $values as $key => $value ) {
					$value['name']  = $value['name'] . '-CHANGED';
					$values[ $key ] = $value;
				}

				return $values;
			}
		);

		$values           = [
			'ONE' => [
				'name'        => 'ONE',
				'value'       => 'oneone',
				'description' => __( 'Enum for one.', 'wp-graphql' ),
			],
			'TWO' => [
				'name'        => 'TWO',
				'value'       => 'twotwo',
				'description' => __( 'Enum for two.', 'wp-graphql' ),
			],
		];
		$config['values'] = $values;
		$config['name']   = 'WPEnumTestOne';
		$enum_object      = new WPEnumType( $config );
		$actual           = $enum_object->getValues();

		$this->assertEquals( 'ONE-CHANGED', $actual[0]->name );
		$this->assertEquals( 'TWO-CHANGED', $actual[1]->name );
	}
}
