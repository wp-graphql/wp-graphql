<?php
/**
 * Tests for the "GraphQL Capability" settings in the ACF admin UI.
 *
 * @see GraphqlCapabilityTest for how the setting is enforced.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit\Admin
 */

class GraphqlCapabilitySettingsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function testFieldsHaveAGraphqlCapabilitySetting(): void {
		$field_type = \WPGraphQL\Acf\Utils::get_graphql_field_type( 'text' );
		$settings   = $field_type->get_admin_field_settings( [ 'type' => 'text' ], new \WPGraphQL\Acf\Admin\Settings() );

		$this->assertArrayHasKey( 'graphql_capability', $settings );
		$this->assertSame( 'graphql_capability', $settings['graphql_capability']['name'] );
		$this->assertSame( 'text', $settings['graphql_capability']['type'] );

		// Shown when the field is shown in GraphQL, like the other GraphQL settings.
		$this->assertSame( 'show_in_graphql', $settings['graphql_capability']['conditions']['field'] );
	}

	public function testFieldGroupsHaveAGraphqlCapabilitySetting(): void {
		ob_start();
		( new \WPGraphQL\Acf\Admin\Settings() )->display_graphql_field_group_fields(
			[
				'key'                => 'group_capability_ui',
				'title'              => 'Capability Ui',
				'graphql_capability' => 'edit_posts',
			]
		);
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'acf_field_group[graphql_capability]', $output );
		$this->assertStringContainsString( 'value="edit_posts"', $output );
	}

	public function testOptionsPagesHaveAGraphqlCapabilitySetting(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			$this->markTestSkipped( 'ACF Options Pages need ACF PRO (or Secure Custom Fields)' );
		}

		ob_start();
		( new \WPGraphQL\Acf\Admin\OptionsPageRegistration() )->render_settings_tab(
			[
				'page_title'         => 'Capability Ui',
				'show_in_graphql'    => 1,
				'graphql_capability' => 'manage_options',
			]
		);
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'acf_ui_options_page[graphql_capability]', $output );
		$this->assertStringContainsString( 'value="manage_options"', $output );

		// The "Show in GraphQL" instructions describe an options page, not a post type.
		$this->assertStringContainsString( 'Whether to show the Options Page in the WPGraphQL Schema.', $output );
		$this->assertStringNotContainsString( 'Post Type', $output );
	}
}
