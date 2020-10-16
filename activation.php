<?php
/**
 * Runs when WPGraphQL is activated
 *
 * This cleans up data that WPGraphQL stores
 *
 * @return void
 */
function graphql_activation_callback() {
	do_action( 'graphql_activate' );
	update_option( 'wp_graphql_version', WPGRAPHQL_VERSION );
}
