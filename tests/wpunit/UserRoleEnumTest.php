<?php

use WPGraphQL\Type\Enum\UserRoleEnum;

class UserRoleEnumTest extends \Codeception\TestCase\WPTestCase {

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
	public function testUserEdittableRoleWhenNameEmpty() {

		/**
		 * Modify the user role enums for testing null name.
		 * Test roles that don't have an explicit name, don't fail during type registration.
		 */
		add_filter(
			'editable_roles',
			static function ( $roles ) {
				return [
					'foo' => [
						'name'  => 'Foo',
						'extra' => 'hello-foo',
					],
					'bar' => [
						'name'  => null,
						'extra' => 'hello-bar',
					],
					'biz' => [
						'extra' => 'hello-biz',
					],
				];
			}
		);

		/**
		 * Invoke the user role enum registration.
		 */
		UserRoleEnum::register_type();
		$editable_roles = get_editable_roles();
		$this->assertArrayHasKey( 'foo', $editable_roles );
		$this->assertArrayHasKey( 'bar', $editable_roles );
		$this->assertArrayHasKey( 'biz', $editable_roles );
	}
}
