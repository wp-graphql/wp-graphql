<?php
/**
 * Tests for WPGraphQL\Acf\Admin\PostTypeRegistration.
 * The plugin registers these filters via WPGraphQLAcf::acf_internal_post_type_support() on init.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit\Admin
 */

class PostTypeRegistrationTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function test_add_tabs_adds_graphql_tab(): void {
		$tabs = apply_filters( 'acf/post_type/additional_settings_tabs', [] );
		$this->assertIsArray( $tabs );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$this->assertSame( 'GraphQL', $tabs['graphql'] );
	}

	public function test_add_graphql_type_column_adds_columns(): void {
		$columns = apply_filters( 'manage_acf-post-type_posts_columns', [] );
		$this->assertArrayHasKey( 'show_in_graphql', $columns );
		$this->assertArrayHasKey( 'graphql_type', $columns );
		$this->assertSame( 'Show in GraphQL', $columns['show_in_graphql'] );
		$this->assertSame( 'GraphQL Type', $columns['graphql_type'] );
	}

	public function test_add_cpt_registration_fields_from_post_type(): void {
		$args     = [
			'public' => true,
			'labels' => [
				'singular_name' => 'Book',
				'name'          => 'Books',
			],
		];
		$post_type = [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'Book',
			'graphql_plural_name' => 'Books',
		];
		$result = apply_filters( 'acf/post_type/registration_args', $args, $post_type );
		$this->assertTrue( $result['show_in_graphql'] );
		$this->assertSame( 'Book', $result['graphql_single_name'] );
		$this->assertSame( 'Books', $result['graphql_plural_name'] );
	}

	public function test_add_cpt_registration_fields_fallback_to_args_labels(): void {
		$args = [
			'public' => false,
			'labels' => [
				'singular_name' => 'Book',
				'name'          => 'Books',
			],
		];
		$post_type = [];
		$result    = apply_filters( 'acf/post_type/registration_args', $args, $post_type );
		$this->assertFalse( $result['show_in_graphql'] ); // follows $args['public']
		// Utils::format_field_name( ..., true ) returns lowercase GraphQL-safe name.
		$this->assertSame( 'book', $result['graphql_single_name'] );
		$this->assertSame( 'books', $result['graphql_plural_name'] );
	}

	public function test_add_cpt_registration_fields_show_in_graphql_from_post_type(): void {
		$args      = [ 'public' => true, 'labels' => [] ];
		$post_type = [ 'show_in_graphql' => false ];
		$result    = apply_filters( 'acf/post_type/registration_args', $args, $post_type );
		$this->assertFalse( $result['show_in_graphql'] );
	}

	public function test_add_cpt_registration_fields_polyfill_filter(): void {
		$args      = [
			'public' => true,
			'labels' => [
				'singular_name' => 'Item',
				'name'          => 'Items',
			],
		];
		$post_type = [];
		$result    = apply_filters( 'acf/post_type_args', $args, $post_type );
		$this->assertSame( 'item', $result['graphql_single_name'] );
		$this->assertSame( 'items', $result['graphql_plural_name'] );
	}

	public function test_render_graphql_columns_early_return_when_no_post_type(): void {
		$post_id = 0;
		ob_start();
		do_action( 'manage_acf-post-type_posts_custom_column', 'graphql_type', $post_id );
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}
}
