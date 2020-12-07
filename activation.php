<?php
/**
 * Runs when WPGraphQL is activated
 *
 * @return void
 */
function graphql_activation_callback() {
	do_action( 'graphql_activate' );

	// store the current version of WPGraphQL
	update_option( 'wp_graphql_version', WPGRAPHQL_VERSION );
}
