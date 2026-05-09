<?php
/**
 * IDE settings registration on the WPGraphQL settings page.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Registers the "IDE Settings" tab inside the WPGraphQL settings page
 * (admin bar link behavior, legacy editor toggle) plus the filters that
 * rewrite the legacy `show_graphiql_link_in_admin_bar` field on the
 * adjacent General Settings tab so users see why it's disabled.
 *
 * Named `SettingsPage` to avoid colliding with the procedural
 * `WPGraphQLIDE\Settings` namespace already used by includes/settings.php.
 */
class SettingsPage {

	/**
	 * Register IDE settings section + fields on the WPGraphQL settings page.
	 */
	public static function register(): void {
		// Add a tab section to the GraphQL admin settings page.
		if ( function_exists( 'register_graphql_settings_section' ) ) {
			register_graphql_settings_section(
				'graphql_ide_settings',
				[
					'title' => __( 'IDE Settings', 'wpgraphql-ide' ),
					'desc'  => __( 'Customize your WPGraphQL IDE experience sitewide. Individual users can override these settings in their user profile.', 'wpgraphql-ide' ),
				]
			);
		}

		if ( function_exists( 'register_graphql_settings_field' ) ) {
			register_graphql_settings_field(
				'graphql_ide_settings',
				[
					'name'              => 'graphql_ide_link_behavior',
					'label'             => __( 'Admin Bar Link Behavior', 'wpgraphql-ide' ),
					'desc'              => __( 'How would you like to access the GraphQL IDE from the admin bar?', 'wpgraphql-ide' ),
					'type'              => 'radio',
					'options'           => [
						'drawer'         => __( 'Drawer (recommended) — open the IDE in a slide up drawer from any page', 'wpgraphql-ide' ),
						'dedicated_page' => sprintf(
							wp_kses_post(
								sprintf(
									/* translators: %s: URL to the GraphQL IDE page */
									__( 'Dedicated Page — direct link to <a href="%1$s">%1$s</a>', 'wpgraphql-ide' ),
									esc_url( admin_url( 'admin.php?page=graphql-ide' ) )
								)
							)
						),
						'disabled'       => __( 'Disabled — remove the IDE link from the admin bar', 'wpgraphql-ide' ),
					],
					'default'           => 'drawer',
					'sanitize_callback' => [ self::class, 'sanitize_link_behavior' ],
				]
			);

			register_graphql_settings_field(
				'graphql_ide_settings',
				[
					'name'  => 'graphql_ide_show_legacy_editor',
					'label' => __( 'Show Legacy Editor', 'wpgraphql-ide' ),
					'desc'  => __( 'Show the legacy editor', 'wpgraphql-ide' ),
					'type'  => 'checkbox',
				]
			);
		}
	}

	/**
	 * Update the existing GraphiQL link field configuration to say "Legacy".
	 *
	 * @param array<string, mixed> $field_config The field configuration array.
	 * @param string               $field_name   The name of the field.
	 * @param string               $section      The section the field belongs to.
	 * @return array<string, mixed> The modified field configuration array.
	 */
	public static function rewrite_legacy_graphiql_link( array $field_config, string $field_name, string $section ): array {
		if ( 'show_graphiql_link_in_admin_bar' === $field_name && 'graphql_general_settings' === $section ) {
			$field_config['desc'] = sprintf(
				'%1$s<br><p class="description">%2$s</p>',
				__( 'Show the GraphiQL IDE link in the WordPress Admin Bar.', 'wpgraphql-ide' ),
				sprintf(
					/* translators: 1: Strong opening tag, 2: Strong closing tag */
					__( '%1$sNote:%2$s This setting has been disabled by the new WPGraphQL IDE. Related settings are now available under the "IDE Settings" tab.', 'wpgraphql-ide' ),
					'<strong>',
					'</strong>'
				)
			);
			$field_config['disabled'] = true;
			$field_config['value']    = 'off';
		}
		return $field_config;
	}

	/**
	 * Ensure the `show_graphiql_link_in_admin_bar` setting is always unchecked.
	 *
	 * @param mixed                $value          The value of the field.
	 * @param mixed                $default_value  The default value if there is no value set.
	 * @param string               $option_name    The name of the option.
	 * @param array<string, mixed> $section_fields The setting values within the section.
	 * @param string               $section_name   The name of the section the setting belongs to.
	 * @return mixed The modified value of the field.
	 */
	public static function force_legacy_graphiql_off( $value, $default_value, $option_name, $section_fields, $section_name ) {
		unset( $default_value, $section_fields );
		if ( 'show_graphiql_link_in_admin_bar' === $option_name && 'graphql_general_settings' === $section_name ) {
			return 'off';
		}
		return $value;
	}

	/**
	 * Sanitize the input value for the IDE link-behavior setting.
	 *
	 * @param string $value The input value.
	 * @return string The sanitized value.
	 */
	public static function sanitize_link_behavior( string $value ): string {
		$valid_values = [ 'drawer', 'dedicated_page', 'disabled' ];

		if ( in_array( $value, $valid_values, true ) ) {
			return $value;
		}

		return 'drawer';
	}
}
