<?php
/**
 * Tests for WPGraphQL\Acf\Admin\OptionsPageRegistration.
 * The plugin registers these filters via WPGraphQLAcf::acf_internal_post_type_support() on init.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit\Admin
 */

class OptionsPageRegistrationTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function test_add_tabs_adds_graphql_tab(): void {
		$tabs = apply_filters( 'acf/ui_options_page/additional_settings_tabs', [] );
		$this->assertIsArray( $tabs );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$this->assertSame( 'GraphQL', $tabs['graphql'] );
	}

	public function test_add_graphql_type_column_adds_columns(): void {
		$columns = apply_filters( 'manage_acf-ui-options-page_posts_columns', [] );
		$this->assertArrayHasKey( 'show_in_graphql', $columns );
		$this->assertArrayHasKey( 'graphql_type', $columns );
		$this->assertSame( 'Show in GraphQL', $columns['show_in_graphql'] );
		$this->assertSame( 'GraphQL Type', $columns['graphql_type'] );
	}

	public function test_add_registration_fields_from_args(): void {
		$args = [
			'show_in_graphql'   => true,
			'graphql_type_name' => 'ThemeSettings',
		];
		$post = [];
		$result = apply_filters( 'acf/ui_options_page/registration_args', $args, $post );
		$this->assertTrue( $result['show_in_graphql'] );
		$this->assertSame( 'ThemeSettings', $result['graphql_type_name'] );
	}

	public function test_add_registration_fields_from_post(): void {
		$args = [];
		$post = [
			'show_in_graphql'   => true,
			'graphql_type_name' => 'SiteSettings',
		];
		$result = apply_filters( 'acf/ui_options_page/registration_args', $args, $post );
		$this->assertTrue( $result['show_in_graphql'] );
		$this->assertSame( 'SiteSettings', $result['graphql_type_name'] );
	}

	public function test_add_registration_fields_fallback_from_page_title(): void {
		$args = [ 'page_title' => 'My Options' ];
		$post = [];
		$result = apply_filters( 'acf/ui_options_page/registration_args', $args, $post );
		$this->assertFalse( $result['show_in_graphql'] );
		// Utils::format_field_name( ..., false ) returns camelCase.
		$this->assertSame( 'myOptions', $result['graphql_type_name'] );
	}

	public function test_add_registration_fields_show_in_graphql_default_false(): void {
		$args = [ 'graphql_type_name' => 'Test' ];
		$post = [];
		$result = apply_filters( 'acf/ui_options_page/registration_args', $args, $post );
		$this->assertFalse( $result['show_in_graphql'] );
	}

	public function test_preserve_show_in_graphql_returns_empty_unchanged(): void {
		$options_pages = [];
		$result = apply_filters( 'acf_get_options_pages', $options_pages );
		$this->assertSame( [], $result );
	}

	public function test_preserve_show_in_graphql_sets_default_when_not_set(): void {
		$options_pages = [
			[ 'page_title' => 'General', 'menu_slug' => 'general' ],
		];
		$result = apply_filters( 'acf_get_options_pages', $options_pages );
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'show_in_graphql', $result[0] );
		$this->assertTrue( $result[0]['show_in_graphql'] );
	}

	public function test_preserve_show_in_graphql_leaves_existing_show_in_graphql(): void {
		$options_pages = [
			[ 'page_title' => 'Hidden', 'menu_slug' => 'hidden', 'show_in_graphql' => false ],
		];
		$result = apply_filters( 'acf_get_options_pages', $options_pages );
		$this->assertFalse( $result[0]['show_in_graphql'] );
	}

	public function test_render_graphql_columns_early_return_when_no_options_page(): void {
		$post_id = 0;
		ob_start();
		do_action( 'manage_acf-ui-options-page_posts_custom_column', 'graphql_type', $post_id );
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}
}
