<?php
/**
 * Runs when WPGraphQL is activated
 *
 * @return void
 */
function graphql_activation_callback() {
	do_action( 'graphql_activate' );
}
