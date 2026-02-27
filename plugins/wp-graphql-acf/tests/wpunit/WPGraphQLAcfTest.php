<?php
/**
 * Tests for WPGraphQLAcf plugin bootstrap and can_load_plugin.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit
 */

class WPGraphQLAcfTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function test_can_load_plugin_returns_true_when_acf_and_wpgraphql_active(): void {
		$plugin = new \WPGraphQLAcf();
		$this->assertTrue( $plugin->can_load_plugin() );
	}

	public function test_fire_wpgraphql_acf_init_fires_action(): void {
		$plugin  = new \WPGraphQLAcf();
		$fired   = false;
		$closure = function () use ( &$fired ) {
			$fired = true;
		};
		add_action( 'wpgraphql/acf/init', $closure, 5 );
		$plugin->fire_wpgraphql_acf_init();
		remove_action( 'wpgraphql/acf/init', $closure, 5 );
		$this->assertTrue( $fired, 'wpgraphql/acf/init action should have fired' );
	}

	public function test_acf_internal_post_type_support_registers_filters(): void {
		$plugin = new \WPGraphQLAcf();
		$plugin->acf_internal_post_type_support();
		$tabs = apply_filters( 'acf/post_type/additional_settings_tabs', [] );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$tabs = apply_filters( 'acf/taxonomy/additional_settings_tabs', [] );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$tabs = apply_filters( 'acf/ui_options_page/additional_settings_tabs', [] );
		$this->assertArrayHasKey( 'graphql', $tabs );
	}

	public function test_init_admin_settings_registers_settings_filters(): void {
		$plugin = new \WPGraphQLAcf();
		$plugin->init_admin_settings();
		$tabs = apply_filters( 'acf/field_group/additional_field_settings_tabs', [] );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$this->assertSame( 'GraphQL', $tabs['graphql'] );
	}

	public function test_init_registry_sets_registry_and_handles_empty_field_groups(): void {
		$plugin         = new \WPGraphQLAcf();
		$type_registry  = \WPGraphQL::get_type_registry();
		$plugin->init_registry( $type_registry );
		$ref = new \ReflectionClass( $plugin );
		$prop = $ref->getProperty( 'registry' );
		$prop->setAccessible( true );
		$this->assertInstanceOf( \WPGraphQL\Acf\Registry::class, $prop->getValue( $plugin ) );
	}

	public function test_init_third_party_support_runs(): void {
		$plugin = new \WPGraphQLAcf();
		$plugin->init_third_party_support();
		$this->assertTrue( true, 'init_third_party_support should run without error' );
	}

	public function test_get_plugin_load_error_messages_returns_array(): void {
		$plugin = new \WPGraphQLAcf();
		$messages = $plugin->get_plugin_load_error_messages();
		$this->assertIsArray( $messages );
	}
}
