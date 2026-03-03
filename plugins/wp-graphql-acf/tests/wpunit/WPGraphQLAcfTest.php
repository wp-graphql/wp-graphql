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

	/**
	 * Test preview_support returns (bool) $should when registry is not set.
	 */
	public function test_preview_support_returns_should_when_registry_not_set(): void {
		$plugin = new \WPGraphQLAcf();
		$this->assertTrue( $plugin->preview_support( true, 1, 'some_meta_key', true ) );
		$this->assertFalse( $plugin->preview_support( false, 1, 'some_meta_key', false ) );
	}

	/**
	 * Test preview_support returns (bool) $should when object_id is not a preview post.
	 */
	public function test_preview_support_returns_should_when_not_preview_post(): void {
		$plugin = new \WPGraphQLAcf();
		$plugin->init_registry( \WPGraphQL::get_type_registry() );
		$post_id = $this->published_post->ID;
		$this->assertTrue( $plugin->preview_support( true, $post_id, 'some_meta_key', true ) );
		$this->assertFalse( $plugin->preview_support( false, $post_id, 'some_meta_key', null ) );
	}

	/**
	 * Test preview_support accepts nullable $single (true, false, null) without error.
	 */
	public function test_preview_support_accepts_nullable_single_parameter(): void {
		$plugin = new \WPGraphQLAcf();
		$post_id = $this->published_post->ID;
		$this->assertTrue( $plugin->preview_support( true, $post_id, 'meta_key', true ) );
		$this->assertTrue( $plugin->preview_support( true, $post_id, 'meta_key', false ) );
		$this->assertTrue( $plugin->preview_support( true, $post_id, 'meta_key', null ) );
	}

	/**
	 * Test preview_support passthrough when meta_key is null or empty (e.g. get_post_meta($id) with one argument).
	 */
	public function test_preview_support_passthrough_when_meta_key_null_or_empty(): void {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		wp_set_current_user( $this->admin->ID );

		$plugin = new \WPGraphQLAcf();
		$plugin->init_registry( \WPGraphQL::get_type_registry() );

		$preview_id = wp_create_post_autosave( [
			'post_ID'      => $this->published_post->ID,
			'post_type'    => 'post',
			'post_title'   => 'Preview for meta_key test',
		] );
		$this->assertNotWPError( $preview_id );

		$this->assertTrue( $plugin->preview_support( true, $preview_id, null, null ) );
		$this->assertFalse( $plugin->preview_support( false, $preview_id, null, true ) );
		$this->assertTrue( $plugin->preview_support( true, $preview_id, '', null ) );

		wp_delete_post( $preview_id, true );
		remove_filter( 'use_block_editor_for_post', '__return_false' );
	}

	/**
	 * Test preview_support returns false when meta_key is a registered ACF field (so meta is resolved from parent).
	 */
	public function test_preview_support_returns_false_for_registered_field(): void {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		wp_set_current_user( $this->admin->ID );

		$field_name = 'preview_test_text';
		$this->register_acf_field( [ 'name' => $field_name ] );

		$plugin = new \WPGraphQLAcf();
		$plugin->init_registry( \WPGraphQL::get_type_registry() );

		$preview_id = wp_create_post_autosave( [
			'post_ID'    => $this->published_post->ID,
			'post_type'  => 'post',
			'post_title' => 'Preview for registered field test',
		] );
		$this->assertNotWPError( $preview_id );

		$this->assertFalse( $plugin->preview_support( true, $preview_id, $field_name, true ) );

		wp_delete_post( $preview_id, true );
		remove_filter( 'use_block_editor_for_post', '__return_false' );
	}

	/**
	 * Test preview_support returns $should when meta_key is not a registered ACF field.
	 */
	public function test_preview_support_returns_should_for_unregistered_meta_key(): void {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		wp_set_current_user( $this->admin->ID );

		$this->register_acf_field( [ 'name' => 'registered_foo' ] );

		$plugin = new \WPGraphQLAcf();
		$plugin->init_registry( \WPGraphQL::get_type_registry() );

		$preview_id = wp_create_post_autosave( [
			'post_ID'    => $this->published_post->ID,
			'post_type'  => 'post',
			'post_title' => 'Preview for unregistered meta test',
		] );
		$this->assertNotWPError( $preview_id );

		$this->assertTrue( $plugin->preview_support( true, $preview_id, 'unregistered_meta_key', null ) );
		$this->assertFalse( $plugin->preview_support( false, $preview_id, 'another_unknown_key', true ) );

		wp_delete_post( $preview_id, true );
		remove_filter( 'use_block_editor_for_post', '__return_false' );
	}
}
