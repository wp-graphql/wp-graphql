<?php
/**
 * Runs when WPGraphQL is de-activated
 *
 * This cleans up data that WPGraphQL stores
 *
 * @return void
 */
function graphql_deactivation_callback() {

	if ( ! graphql_can_load_plugin() ) {
		return;
	}

	// Fire an action when WPGraphQL is de-activating
	do_action( 'graphql_deactivate' );

	// Delete data during activation
	delete_graphql_data();
}

/**
 * Delete data on deactivation
 *
 * @return void
 */
function delete_graphql_data() {

	if ( ! class_exists( 'WPGraphQL' ) ) {
		return;
	}

	// Check if the plugin is set to delete data or not
	$delete_data = get_graphql_setting( 'delete_data_on_deactivate' );

	// If data is not set to delete, stop now
	if ( 'on' !== $delete_data ) {
		return;
	}

	// Delete graphql version
	delete_option( 'wp_graphql_version' );

	// Initialize the settings API
	$settings = new WPGraphQL\Admin\Settings\Settings();
	$settings->init();
	$settings->register_settings();

	// Get all the registered settings fields
	$fields = $settings->settings_api->get_settings_fields();

	// Loop over the registered settings fields and delete the options
	if ( ! empty( $fields ) && is_array( $fields ) ) {
		foreach ( $fields as $group => $fields ) {
			delete_option( $group );
		}
	}

	do_action( 'graphql_delete_data' );
}
