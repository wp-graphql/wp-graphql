<?php

use WPGraphQL\Type\Enum\UserRoleEnum;

class UserRoleEnumTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

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

	/**
	 * Each built-in role should expose its own role-specific description on the
	 * UserRoleEnum, not the generic fallback description.
	 *
	 * Regression: register_type() switched on $role (the role array) instead of
	 * $key (the role slug), so every value matched the default case and shared
	 * the generic "User role with specific capabilities" description.
	 *
	 * @throws \Exception
	 */
	public function testBuiltInRolesHaveRoleSpecificDescriptions() {
		$query = '
		query GetUserRoleEnum {
			__type(name: "UserRoleEnum") {
				enumValues {
					name
					description
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$descriptions = [];
		foreach ( $actual['data']['__type']['enumValues'] as $value ) {
			$descriptions[ $value['name'] ] = $value['description'];
		}

		$this->assertSame(
			'Full system access with ability to manage all aspects of the site.',
			$descriptions['ADMINISTRATOR'] ?? null,
			'The ADMINISTRATOR role should have its own description, not the generic fallback.'
		);
		$this->assertSame(
			'Can only manage their profile and read content.',
			$descriptions['SUBSCRIBER'] ?? null,
			'The SUBSCRIBER role should have its own description, not the generic fallback.'
		);
	}
}
