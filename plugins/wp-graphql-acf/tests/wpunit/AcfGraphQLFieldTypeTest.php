<?php
/**
 * Tests for WPGraphQL\Acf\AcfGraphQLFieldType.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit
 */

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\Admin\Settings;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\Acf\Registry;
use WPGraphQL\Acf\Utils;

class AcfGraphQLFieldTypeTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function testConstructorSetsAcfFieldType(): void {
		$instance = new AcfGraphQLFieldType( 'text', [] );
		$this->assertSame( 'text', $instance->get_acf_field_type() );
	}

	public function testSetConfigWithArrayStoresConfig(): void {
		$config = [ 'graphql_type' => 'String' ];
		$instance = new AcfGraphQLFieldType( 'text', $config );
		$this->assertSame( 'String', $instance->get_config( 'graphql_type' ) );
	}

	public function testSetConfigWithCallableInvokesAndStoresResult(): void {
		$callable = function ( $type, $instance ) {
			$this->assertSame( 'text', $type );
			return [ 'graphql_type' => 'Int' ];
		};
		$instance = new AcfGraphQLFieldType( 'text', $callable );
		$this->assertSame( 'Int', $instance->get_config( 'graphql_type' ) );
	}

	public function testGetConfigWithNullReturnsFullConfig(): void {
		$config = [ 'graphql_type' => 'String', 'resolve' => null ];
		$instance = new AcfGraphQLFieldType( 'text', $config );
		$full = $instance->get_config( null );
		$this->assertIsArray( $full );
		$this->assertArrayHasKey( 'graphql_type', $full );
		$this->assertSame( 'String', $full['graphql_type'] );
	}

	public function testGetConfigWithNonexistentKeyReturnsNull(): void {
		$instance = new AcfGraphQLFieldType( 'text', [] );
		$this->assertNull( $instance->get_config( 'nonexistent_key' ) );
	}

	public function testGetConfigWithSettingNameReturnsValue(): void {
		$instance = new AcfGraphQLFieldType( 'text', [ 'graphql_type' => 'Float' ] );
		$this->assertSame( 'Float', $instance->get_config( 'graphql_type' ) );
	}

	public function testGetAdminFieldSettingsContainsDefaultKeys(): void {
		$settings = new Settings();
		$settings->init();
		$field_type = Utils::get_graphql_field_type( 'text' );
		$this->assertInstanceOf( AcfGraphQLFieldType::class, $field_type );
		$result = $field_type->get_admin_field_settings( [ 'name' => 'testField' ], $settings );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'show_in_graphql', $result );
		$this->assertArrayHasKey( 'graphql_description', $result );
		$this->assertArrayHasKey( 'graphql_field_name', $result );
		$this->assertArrayHasKey( 'graphql_non_null', $result );
	}

	public function testGetAdminFieldSettingsFilterDefaultAdminSettingsApplied(): void {
		$filter_ran = false;
		add_filter( 'wpgraphql/acf/field_type_default_admin_settings', function ( $settings ) use ( &$filter_ran ) {
			$filter_ran = true;
			$settings['show_in_graphql']['label'] = 'Filtered Label';
			return $settings;
		}, 10, 1 );
		$settings = new Settings();
		$settings->init();
		$field_type = Utils::get_graphql_field_type( 'text' );
		$result = $field_type->get_admin_field_settings( [], $settings );
		remove_all_filters( 'wpgraphql/acf/field_type_default_admin_settings' );
		$this->assertTrue( $filter_ran );
		$this->assertSame( 'Filtered Label', $result['show_in_graphql']['label'] );
	}

	public function testGetAdminFieldSettingsExcludeAdminFieldsRemovesKeys(): void {
		$settings = new Settings();
		$settings->init();
		$instance = new AcfGraphQLFieldType( 'text', [
			'exclude_admin_fields' => [ 'graphql_description' ],
		] );
		$result = $instance->get_admin_field_settings( [], $settings );
		$this->assertArrayNotHasKey( 'graphql_description', $result );
		$this->assertArrayHasKey( 'show_in_graphql', $result );
	}

	public function testGetAdminFieldsWithArrayConfigReturnsThatArray(): void {
		$custom = [ 'custom_setting' => [ 'type' => 'text', 'name' => 'custom' ] ];
		$instance = new AcfGraphQLFieldType( 'text', [ 'admin_fields' => $custom ] );
		$settings = new Settings();
		$settings->init();
		$result = $instance->get_admin_fields( [], [], $settings );
		$this->assertSame( $custom, $result );
	}

	public function testGetAdminFieldsWithCallableInvokesAndReturns(): void {
		$instance = new AcfGraphQLFieldType( 'text', [
			'admin_fields' => function ( $defaults, $acf_field, $config, $settings ) {
				return array_merge( $defaults, [ 'extra' => [ 'name' => 'extra' ] ] );
			},
		] );
		$settings = new Settings();
		$settings->init();
		$defaults = [ 'show_in_graphql' => [ 'name' => 'show_in_graphql' ] ];
		$result = $instance->get_admin_fields( [], $defaults, $settings );
		$this->assertArrayHasKey( 'extra', $result );
		$this->assertArrayHasKey( 'show_in_graphql', $result );
	}

	public function testGetAdminFieldsWithNoConfigReturnsDefaults(): void {
		$instance = new AcfGraphQLFieldType( 'text', [] );
		$settings = new Settings();
		$settings->init();
		$defaults = [ 'show_in_graphql' => [ 'name' => 'show_in_graphql' ] ];
		$result = $instance->get_admin_fields( [], $defaults, $settings );
		$this->assertSame( $defaults, $result );
	}

	public function testGetResolveTypeFilterReturnsFilteredValue(): void {
		$registry = new Registry();
		$field_config = new FieldConfig(
			[ 'type' => 'text', 'name' => 'myField' ],
			[ 'key' => 'group_1', 'title' => 'Test', 'graphql_field_name' => 'TestGroup' ],
			$registry
		);
		$field_type = Utils::get_graphql_field_type( 'text' );
		add_filter( 'wpgraphql/acf/field_type_resolve_type', function () {
			return 'FilteredType';
		}, 10, 0 );
		$result = $field_type->get_resolve_type( $field_config );
		remove_all_filters( 'wpgraphql/acf/field_type_resolve_type' );
		$this->assertSame( 'FilteredType', $result );
	}

	public function testGetResolveTypeWithGraphqlResolveTypeOnField(): void {
		$registry = new Registry();
		$field_config = new FieldConfig(
			[ 'type' => 'text', 'name' => 'myField', 'graphql_resolve_type' => 'Int' ],
			[ 'key' => 'group_1', 'title' => 'Test', 'graphql_field_name' => 'TestGroup' ],
			$registry
		);
		$field_type = Utils::get_graphql_field_type( 'text' );
		$result = $field_type->get_resolve_type( $field_config );
		$this->assertSame( 'Int', $result );
	}

	public function testGetResolveTypeWithGraphqlNonNullWrapsInNonNull(): void {
		$registry = new Registry();
		$field_config = new FieldConfig(
			[ 'type' => 'text', 'name' => 'myField', 'graphql_non_null' => true ],
			[ 'key' => 'group_1', 'title' => 'Test', 'graphql_field_name' => 'TestGroup' ],
			$registry
		);
		$field_type = Utils::get_graphql_field_type( 'text' );
		$result = $field_type->get_resolve_type( $field_config );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'non_null', $result );
		$this->assertSame( 'String', $result['non_null'] );
	}

	public function testGetResolveTypeConnectionReturnsConnection(): void {
		$registry = new Registry();
		$field_config = new FieldConfig(
			[ 'type' => 'relationship', 'name' => 'myRel', 'graphql_resolve_type' => 'connection' ],
			[ 'key' => 'group_1', 'title' => 'Test', 'graphql_field_name' => 'TestGroup' ],
			$registry
		);
		$field_type = Utils::get_graphql_field_type( 'relationship' );
		$this->assertNotNull( $field_type );
		$result = $field_type->get_resolve_type( $field_config );
		$this->assertSame( 'connection', $result );
	}

	public function testGetResolverFilterReturnsFilteredValue(): void {
		$registry = new Registry();
		$field_config = new FieldConfig(
			[ 'type' => 'text', 'name' => 'myField' ],
			[ 'key' => 'group_1', 'title' => 'Test', 'graphql_field_name' => 'TestGroup' ],
			$registry
		);
		$field_type = Utils::get_graphql_field_type( 'text' );
		$context = new \WPGraphQL\AppContext( (object) [ 'viewer' => null ] );
		$info = $this->createMock( \GraphQL\Type\Definition\ResolveInfo::class );
		add_filter( 'wpgraphql/acf/field_type_resolver', function () {
			return 'filtered_resolver';
		}, 10, 0 );
		$result = $field_type->get_resolver( null, [], $context, $info, $field_type, $field_config );
		remove_all_filters( 'wpgraphql/acf/field_type_resolver' );
		$this->assertSame( 'filtered_resolver', $result );
	}

	public function testGetExcludedAdminFieldSettingsFilterApplied(): void {
		$instance = new AcfGraphQLFieldType( 'text', [ 'exclude_admin_fields' => [ 'graphql_description' ] ] );
		add_filter( 'wpgraphql/acf/excluded_admin_field_settings', function ( $excluded ) {
			$excluded[] = 'extra_excluded';
			return $excluded;
		}, 10, 1 );
		$result = $instance->get_excluded_admin_field_settings();
		remove_all_filters( 'wpgraphql/acf/excluded_admin_field_settings' );
		$this->assertContains( 'graphql_description', $result );
		$this->assertContains( 'extra_excluded', $result );
	}
}
