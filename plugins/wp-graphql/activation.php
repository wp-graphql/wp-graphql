<?php
/**
 * Runs when WPGraphQL is activated
 */
function graphql_activation_callback(): void {
	do_action( 'graphql_activate' );

	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Expose the graphql endpoint.
	flush_rewrite_rules( true );
}
