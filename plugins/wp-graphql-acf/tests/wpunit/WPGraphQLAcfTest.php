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
}
