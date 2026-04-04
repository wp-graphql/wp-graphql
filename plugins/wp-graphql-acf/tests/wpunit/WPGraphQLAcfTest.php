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

	/**
	 * Regression test for wp-graphql/wpgraphql-acf#267 and wp-graphql/wp-graphql#3606: ACF post types,
	 * taxonomies, and options pages must be registered with our registration_args filters before ACF
	 * registers them (acf/init at priority 5). So acf_internal_post_type_support must run at init
	 * priority 0. This test fails if the priority is changed back to 20 (or any value > 5).
	 * Fixing #267 (CPT/taxonomy in schema) also fixes #3606 (options page graphql_type_name ignored).
	 */
	public function test_acf_internal_post_type_support_registered_at_init_priority_zero(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/src/WPGraphQLAcf.php';
		$this->assertFileExists( $plugin_file );
		$content = (string) file_get_contents( $plugin_file );
		$this->assertStringContainsString(
			"add_action( 'init', [ \$this, 'acf_internal_post_type_support' ], 0 )",
			$content,
			'acf_internal_post_type_support must be registered at init priority 0 so our filters run before ACF (acf/init at 5). See wp-graphql/wpgraphql-acf#267.'
		);
	}

	/**
	 * When our acf/post_type/registration_args filter is applied (as it is when running at init 0),
	 * a post type registered with the resulting args must have show_in_graphql and graphql names
	 * on its WP_Post_Type object, so WPGraphQL will include it in the schema. With init priority 20
	 * the filter runs after ACF, so ACF CPTs never get these args.
	 */
	public function test_acf_style_post_type_with_show_in_graphql_has_graphql_args_on_registered_object(): void {
		$post_type_key = 'wpgacf_testcpt'; // Must be ≤20 chars (WordPress 4.2+).
		$args          = [
			'public'       => true,
			'label'        => 'Test CPT',
			'labels'       => [
				'singular_name' => 'Test Item',
				'name'          => 'Test Items',
			],
			'show_in_rest' => true,
		];
		$acf_post_type = [
			'show_in_graphql'     => true,
			'graphql_single_name' => 'testItem',
			'graphql_plural_name' => 'testItems',
		];
		$registration_args = apply_filters( 'acf/post_type/registration_args', $args, $acf_post_type );
		$this->assertTrue( $registration_args['show_in_graphql'], 'Filter must add show_in_graphql true' );
		register_post_type( $post_type_key, $registration_args );

		try {
			$obj = get_post_type_object( $post_type_key );
			$this->assertNotNull( $obj, 'Post type should be registered' );
			$this->assertTrue( $obj->show_in_graphql, 'Post type registered with filter output must have show_in_graphql true so it appears in the GraphQL schema (init 0).' );
			$this->assertSame( 'testItem', $obj->graphql_single_name );
			$this->assertSame( 'testItems', $obj->graphql_plural_name );
		} finally {
			unregister_post_type( $post_type_key );
		}
	}

	/**
	 * Regression test for wp-graphql/wp-graphql#3606: When an ACF Options Page has a custom
	 * "GraphQL Type Name" set, that name must be used in the schema (not page_title). Our
	 * acf/ui_options_page/registration_args filter (run at init 0) adds graphql_type_name to the
	 * options page args; get_field_group_name() then uses it. This test asserts the contract:
	 * options page with graphql_type_name yields that name, not page_title.
	 * Options pages are an ACF Pro feature; skip when ACF Free is active.
	 */
	public function test_options_page_custom_graphql_type_name_used_for_schema_name(): void {
		if ( ! $this->is_acf_pro ) {
			$this->markTestSkipped( 'ACF Pro is not active. Options pages are an ACF Pro feature.' );
		}
		$args = [
			'page_title' => 'General Settings',
		];
		$post = [
			'show_in_graphql'   => true,
			'graphql_type_name' => 'MyCustomSettings',
		];
		$registration_args = apply_filters( 'acf/ui_options_page/registration_args', $args, $post );
		$this->assertSame( 'MyCustomSettings', $registration_args['graphql_type_name'], 'Filter must pass through custom graphql_type_name (wp-graphql/wp-graphql#3606).' );

		// Simulate the options page array as stored by ACF after registration (with our filter at init 0).
		$options_page = array_merge( $args, $post, $registration_args );
		$schema_name  = \WPGraphQL\Acf\Utils::get_field_group_name( $options_page );
		$this->assertSame( 'myCustomSettings', $schema_name, 'get_field_group_name() must use graphql_type_name for options pages, not page_title (wp-graphql/wp-graphql#3606).' );
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
