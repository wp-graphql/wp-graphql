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
}
