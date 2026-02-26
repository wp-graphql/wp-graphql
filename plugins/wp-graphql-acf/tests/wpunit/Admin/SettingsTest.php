<?php
/**
 * Tests for WPGraphQL\Acf\Admin\Settings.
 * Instantiates Settings and calls init() so filters/actions are registered.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit\Admin
 */

use WPGraphQL\Acf\Admin\Settings;

class SettingsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * @var Settings
	 */
	private $settings;

	public function setUp(): void {
		parent::setUp();
		$this->settings = new Settings();
		$this->settings->init();
	}

	public function test_filter_additional_field_settings_tabs_adds_graphql(): void {
		$tabs = apply_filters( 'acf/field_group/additional_field_settings_tabs', [] );
		$this->assertIsArray( $tabs );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$this->assertSame( 'GraphQL', $tabs['graphql'] );
	}

	public function test_filter_additional_group_settings_tabs_adds_graphql(): void {
		if ( ! defined( 'ACF_VERSION' ) || version_compare( ACF_VERSION, '6.1', '<' ) ) {
			$this->markTestSkipped( 'acf/field_group/additional_group_settings_tabs only registered for ACF 6.1+' );
		}
		$tabs = apply_filters( 'acf/field_group/additional_group_settings_tabs', [] );
		$this->assertIsArray( $tabs );
		$this->assertArrayHasKey( 'graphql', $tabs );
		$this->assertSame( 'GraphQL', $tabs['graphql'] );
	}

	public function test_wpgraphql_admin_table_column_headers_adds_columns_after_location(): void {
		$columns = apply_filters( 'manage_acf-field-group_posts_columns', [ 'title' => 'Title', 'acf-location' => 'Location' ] );
		$this->assertArrayHasKey( 'acf-wpgraphql-type', $columns );
		$this->assertArrayHasKey( 'acf-wpgraphql-interfaces', $columns );
		$this->assertArrayHasKey( 'acf-wpgraphql-locations', $columns );
		$this->assertSame( 'GraphQL Type', $columns['acf-wpgraphql-type'] );
		// Columns inserted after acf-location.
		$keys    = array_keys( $columns );
		$loc_pos = array_search( 'acf-location', $keys, true );
		$this->assertNotFalse( $loc_pos );
		$this->assertSame( 'acf-wpgraphql-type', $keys[ $loc_pos + 1 ] );
	}

	public function test_wpgraphql_admin_table_column_headers_adds_columns_at_end_when_no_location(): void {
		$columns = apply_filters( 'manage_acf-field-group_posts_columns', [ 'title' => 'Title' ] );
		$this->assertArrayHasKey( 'acf-wpgraphql-type', $columns );
		$this->assertArrayHasKey( 'acf-wpgraphql-interfaces', $columns );
		$this->assertArrayHasKey( 'acf-wpgraphql-locations', $columns );
	}

	public function test_wpgraphql_admin_table_columns_html_early_return_when_no_field_group(): void {
		ob_start();
		do_action( 'manage_acf-field-group_posts_custom_column', 'acf-wpgraphql-type', 0 );
		$output = ob_get_clean();
		// With post_id 0, code echoes null then returns when field_group is empty.
		$this->assertSame( '', $output );
	}

	public function test_add_field_settings_with_supported_type_runs_without_error(): void {
		$field = [ 'name' => 'testField', 'type' => 'text', 'prefix' => 'acf_fields' ];
		$screen = defined( 'ACF_VERSION' ) && version_compare( ACF_VERSION, '6.1', '>=' ) ? 'text' : null;
		$this->settings->add_field_settings( $field, $screen );
		$this->assertTrue( true, 'add_field_settings with supported type should not throw' );
	}

	public function test_add_field_settings_with_unsupported_type_runs_without_error(): void {
		// Utils::get_graphql_field_type( 'fake_type' ) returns null, so the not_supported branch runs.
		$this->assertNull( \WPGraphQL\Acf\Utils::get_graphql_field_type( 'fake_nonexistent_type' ) );
		$this->settings->add_field_settings( [ 'name' => 'test', 'prefix' => 'acf_fields' ], 'fake_nonexistent_type' );
		$this->assertTrue( true, 'add_field_settings with unsupported type should not throw' );
	}

	public function test_add_field_settings_with_empty_field_type_returns_early(): void {
		$call_count = 0;
		add_filter( 'acf/render_field_setting', function () use ( &$call_count ) {
			$call_count++;
			return func_get_arg( 0 );
		}, 10, 1 );
		$this->settings->add_field_settings( [ 'name' => 'test', 'prefix' => 'acf_fields' ], '' );
		remove_all_filters( 'acf/render_field_setting' );
		// When field_type is empty we return before any acf_render_field_setting.
		$this->assertSame( 0, $call_count );
	}

	public function test_get_graphql_resolve_type_field_config_returns_defaults(): void {
		$config = $this->settings->get_graphql_resolve_type_field_config();
		$this->assertIsArray( $config );
		$this->assertSame( 'graphql_resolve_type', $config['name'] );
		$this->assertSame( 'select', $config['type'] );
		$this->assertArrayHasKey( 'choices', $config );
		$this->assertArrayHasKey( 'string', $config['choices'] );
		$this->assertArrayHasKey( 'list:string', $config['choices'] );
	}

	public function test_get_graphql_resolve_type_field_config_merges_override(): void {
		$config = $this->settings->get_graphql_resolve_type_field_config( [ 'default_value' => 'int' ] );
		$this->assertSame( 'int', $config['default_value'] );
		$this->assertSame( 'graphql_resolve_type', $config['name'] );
	}

	public function test_enqueue_graphql_acf_scripts_enqueues_on_acf_field_group_screen(): void {
		global $post;
		wp_dequeue_script( 'graphql-acf' );
		$post = self::factory()->post->create_and_get( [
			'post_type' => 'acf-field-group',
			'post_status' => 'draft',
		] );
		$this->settings->enqueue_graphql_acf_scripts( 'post.php' );
		$this->assertTrue( wp_script_is( 'graphql-acf', 'enqueued' ), 'Script should be enqueued on acf-field-group edit screen' );
	}

	public function test_enqueue_graphql_acf_scripts_early_return_wrong_screen(): void {
		wp_dequeue_script( 'graphql-acf' );
		$this->settings->enqueue_graphql_acf_scripts( 'index.php' );
		$this->assertFalse( wp_script_is( 'graphql-acf', 'enqueued' ), 'Script should not be enqueued on index screen' );
	}

	public function test_enqueue_graphql_acf_scripts_early_return_wrong_post_type(): void {
		global $post;
		wp_dequeue_script( 'graphql-acf' );
		$post = self::factory()->post->create_and_get( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
		$this->settings->enqueue_graphql_acf_scripts( 'post.php' );
		$this->assertFalse( wp_script_is( 'graphql-acf', 'enqueued' ), 'Script should not be enqueued when post type is not acf-field-group' );
	}

	public function test_register_meta_boxes_adds_meta_box_when_acf_below_61(): void {
		if ( defined( 'ACF_VERSION' ) && version_compare( ACF_VERSION, '6.1', '>=' ) ) {
			$this->markTestSkipped( 'register_meta_boxes only runs for ACF < 6.1' );
		}
		global $wp_meta_boxes;
		$wp_meta_boxes = [];
		$this->settings->register_meta_boxes();
		$this->assertNotEmpty( $wp_meta_boxes['acf-field-group'] );
		$this->assertArrayHasKey( 'wpgraphql-acf-meta-box', $wp_meta_boxes['acf-field-group']['normal']['default'] ?? [] );
	}
}
