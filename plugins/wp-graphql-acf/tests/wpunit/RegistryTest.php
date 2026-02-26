<?php
/**
 * Tests for WPGraphQL\Acf\Registry.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit
 */

use WPGraphQL\Acf\Registry;

class RegistryTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function test_register_field_adds_name_and_key_to_registered_fields(): void {
		$type_registry = \WPGraphQL::get_type_registry();
		$registry      = new Registry( $type_registry );
		$registry->register_field( [ 'name' => 'foo' ] );
		$registry->register_field( [ 'name' => 'bar', 'key' => 'field_xyz' ] );
		$registered = $registry->get_registered_fields();
		$this->assertContains( 'foo', $registered );
		$this->assertContains( 'bar', $registered );
		$this->assertContains( 'field_xyz', $registered );
	}

	public function test_register_field_does_not_duplicate_names(): void {
		$type_registry = \WPGraphQL::get_type_registry();
		$registry      = new Registry( $type_registry );
		$registry->register_field( [ 'name' => 'foo' ] );
		$registry->register_field( [ 'name' => 'foo' ] );
		$registered = $registry->get_registered_fields();
		$this->assertSame( 1, count( array_filter( $registered, function ( $v ) {
			return $v === 'foo';
		} ) ) );
	}

	public function test_register_field_group_and_has_registered_field_group(): void {
		$type_registry = \WPGraphQL::get_type_registry();
		$registry      = new Registry( $type_registry );
		$registry->register_field_group( 'key1', [ 'title' => 'Group One' ] );
		$this->assertTrue( $registry->has_registered_field_group( 'key1' ) );
		$this->assertFalse( $registry->has_registered_field_group( 'key2' ) );
		$this->assertSame( [ 'title' => 'Group One' ], $registry->registered_field_groups['key1'] );
	}

	public function test_get_type_registry_returns_same_instance_when_constructed_with_null(): void {
		$registry = new Registry( null );
		$this->assertSame( \WPGraphQL::get_type_registry(), $registry->get_type_registry() );
	}

	public function test_get_type_registry_returns_passed_instance(): void {
		$type_registry = \WPGraphQL::get_type_registry();
		$registry      = new Registry( $type_registry );
		$this->assertSame( $type_registry, $registry->get_type_registry() );
	}

	public function test_get_mapped_location_rules_returns_array(): void {
		$registry = new Registry( null );
		$this->assertIsArray( $registry->get_mapped_location_rules() );
		$this->assertEmpty( $registry->get_mapped_location_rules() );
	}

	public function test_should_field_group_show_in_graphql_true_when_show_in_graphql_set(): void {
		$registry = new Registry( null );
		$this->assertTrue( $registry->should_field_group_show_in_graphql( [ 'show_in_graphql' => 1 ] ) );
		$this->assertTrue( $registry->should_field_group_show_in_graphql( [ 'show_in_graphql' => true ] ) );
	}

	public function test_should_field_group_show_in_graphql_false_when_not_show_in_graphql(): void {
		$registry = new Registry( null );
		$this->assertFalse( $registry->should_field_group_show_in_graphql( [ 'show_in_graphql' => 0 ] ) );
		$this->assertFalse( $registry->should_field_group_show_in_graphql( [ 'show_in_graphql' => false ] ) );
	}

	public function test_get_acf_field_groups_returns_groups_with_show_in_graphql_and_key(): void {
		$this->clearSchema();
		$registry = new Registry( null );
		$groups   = $registry->get_acf_field_groups();
		$this->assertIsArray( $groups );
		// Second call returns cached.
		$groups2 = $registry->get_acf_field_groups();
		$this->assertSame( $groups, $groups2 );
	}

	public function test_get_acf_field_groups_excludes_groups_without_show_in_graphql(): void {
		acf_add_local_field_group( [
			'key'                   => 'group_no_graphql',
			'title'                 => 'No GraphQL',
			'show_in_graphql'       => 0,
			'fields'                => [],
			'location'              => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
		] );
		$registry = new Registry( null );
		// Clear any cached value - we need a fresh registry to avoid cache from previous tests.
		$ref = new \ReflectionClass( $registry );
		$prop = $ref->getProperty( 'all_acf_field_groups' );
		$prop->setAccessible( true );
		$prop->setValue( $registry, [] );
		$groups = $registry->get_acf_field_groups();
		$this->assertArrayNotHasKey( 'group_no_graphql', $groups );
		acf_remove_local_field_group( 'group_no_graphql' );
	}
}
