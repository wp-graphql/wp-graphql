<?php
/**
 * Tests for WPGraphQL\Acf\Admin\TaxonomyRegistration.
 * The plugin registers these filters via WPGraphQLAcf::acf_internal_post_type_support() on init.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit\Admin
 */

class TaxonomyRegistrationTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function test_add_tabs_adds_graphql_tab(): void {
		$tabs = apply_filters( 'acf/taxonomy/additional_settings_tabs', [] );
		$this->assertIsArray( $tabs );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$this->assertSame( 'GraphQL', $tabs['graphql'] );
	}

	public function test_add_graphql_type_column_adds_columns(): void {
		$columns = apply_filters( 'manage_acf-taxonomy_posts_columns', [] );
		$this->assertArrayHasKey( 'show_in_graphql', $columns );
		$this->assertArrayHasKey( 'graphql_type', $columns );
		$this->assertSame( 'Show in GraphQL', $columns['show_in_graphql'] );
		$this->assertSame( 'GraphQL Type', $columns['graphql_type'] );
	}

	public function test_add_taxonomy_registration_fields_from_taxonomy(): void {
		$args = [
			'public' => true,
			'labels' => [
				'singular_name' => 'Genre',
				'name'          => 'Genres',
			],
		];
		$taxonomy = [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'Genre',
			'graphql_plural_name' => 'Genres',
		];
		$result = apply_filters( 'acf/taxonomy/registration_args', $args, $taxonomy );
		$this->assertTrue( $result['show_in_graphql'] );
		$this->assertSame( 'Genre', $result['graphql_single_name'] );
		$this->assertSame( 'Genres', $result['graphql_plural_name'] );
	}

	public function test_add_taxonomy_registration_fields_fallback_to_args_labels(): void {
		$args = [
			'public' => false,
			'labels' => [
				'singular_name' => 'Genre',
				'name'          => 'Genres',
			],
		];
		$taxonomy = [];
		$result   = apply_filters( 'acf/taxonomy/registration_args', $args, $taxonomy );
		$this->assertFalse( $result['show_in_graphql'] );
		$this->assertSame( 'genre', $result['graphql_single_name'] );
		$this->assertSame( 'genres', $result['graphql_plural_name'] );
	}

	public function test_add_taxonomy_registration_fields_show_in_graphql_from_taxonomy(): void {
		$args     = [ 'public' => true, 'labels' => [] ];
		$taxonomy = [ 'show_in_graphql' => false ];
		$result   = apply_filters( 'acf/taxonomy/registration_args', $args, $taxonomy );
		$this->assertFalse( $result['show_in_graphql'] );
	}

	public function test_add_taxonomy_registration_fields_polyfill_filter(): void {
		$args = [
			'public' => true,
			'labels' => [
				'singular_name' => 'Tag',
				'name'          => 'Tags',
			],
		];
		$taxonomy = [];
		$result   = apply_filters( 'acf/taxonomy_args', $args, $taxonomy );
		$this->assertSame( 'tag', $result['graphql_single_name'] );
		$this->assertSame( 'tags', $result['graphql_plural_name'] );
	}

	public function test_render_graphql_columns_early_return_when_no_taxonomy(): void {
		$post_id = 0;
		ob_start();
		do_action( 'manage_acf-taxonomy_posts_custom_column', 'graphql_type', $post_id );
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}
}
