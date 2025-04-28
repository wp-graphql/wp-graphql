<?php
/**
 * Runs when WPGraphQL is activated
 */
function graphql_activation_callback(): void {
	do_action( 'graphql_activate' );
}
