<?php
/**
 * Runs when WPGraphQL is activated
 *
 * @return void
 */
function graphql_activation_callback() {

	do_action( 'graphql_activate' );

	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		return;
	}

	// Run any activation scripts before updating the version.
	graphql_migrate_1_20_0();

	// store the current version of WPGraphQL
	update_option( 'wp_graphql_version', WPGRAPHQL_VERSION );
}


/**
 * Handles compatibility when updating from pre-v1.20.0 versions of WPGraphQL.
 *
 * @todo Remove this function in v2.0.0, when the default value for `query_analyzer_enabled` is set to `true`.
 */
function graphql_migrate_1_20_0(): void {
	// If the version is already set, we don't need to do anything.
	$version = get_option( 'wp_graphql_version' );
	if ( ! $version ) {
		return;
	}

	// If the previous version is higher than 1.20.0, we don't need to do anything.
	if ( version_compare( $version, '1.20.0', '>=' ) ) {
		return;
	}

	/**
	 * Set `query_analyzer_enabled` to `true` for preexisting installs.
	 *
	 * This is to prevent breaking changes in caching solutions that rely on the Query Analyzer, but aren't insuring that the `graphql_should_analyze_queries` filter is set to true.
	 */

	$graphql_settings = get_option( 'graphql_general_settings' );

	$graphql_settings['query_analyzer_enabled'] = 'on';

	update_option( 'graphql_general_settings', $graphql_settings );
}
