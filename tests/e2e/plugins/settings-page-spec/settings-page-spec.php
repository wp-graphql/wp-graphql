<?php
/**
 * Plugin Name: Settings Page Spec
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: This plugin is specifically used for end-to-end (e2e) testing of the WPGraphQL plugin. It registers settings sections and fields for testing purposes.
 * Version: 1.0.0
 * Author: WPGraphQL Team
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: settings-page-spec
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register settings sections and fields for testing.
add_action(
	'graphql_register_settings',
	static function () {
		register_graphql_settings_section(
			'graphql_section_a_settings',
			[
				'title' => __( 'Section A Settings', 'settings-page-spec' ),
				'desc'  => __( 'Settings for section A', 'settings-page-spec' ),
			]
		);

		register_graphql_settings_field(
			'graphql_section_a_settings',
			[
				'name'  => 'graphql_section_a_checkbox',
				'label' => __( 'Section A Checkbox Option', 'settings-page-spec' ),
				'desc'  => __( 'This is a checkbox option for section A', 'settings-page-spec' ),
				'type'  => 'checkbox',
			]
		);

		register_graphql_settings_section(
			'graphql_section_b_settings',
			[
				'title' => __( 'Section B Settings', 'settings-page-spec' ),
				'desc'  => __( 'Settings for section B', 'settings-page-spec' ),
			]
		);

		register_graphql_settings_field(
			'graphql_section_b_settings',
			[
				'name'  => 'graphql_section_b_checkbox',
				'label' => __( 'Section B Checkbox Option', 'settings-page-spec' ),
				'desc'  => __( 'This is a checkbox option for section B', 'settings-page-spec' ),
				'type'  => 'checkbox',
			]
		);
	}
);
