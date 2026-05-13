<?php
/**
 * Public access functions for the WPGraphQL IDE plugin.
 *
 * Declared in the global namespace to mirror WPGraphQL core's
 * `register_graphql_*()` style — extensions can call these without
 * importing namespaces.
 *
 * @package WPGraphQLIDE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpgraphql_ide_get_capability' ) ) {
	/**
	 * Get the capability string required to use the IDE.
	 *
	 * Filterable via `wpgraphql_ide_capability_required`. This is the
	 * single source of truth — every IDE permission check, registration
	 * capability map, post-meta auth callback, and REST permission
	 * callback consults it (directly or via {@see wpgraphql_ide_user_can()}).
	 *
	 * Falls back to the default `'manage_graphql_ide'` if the filter
	 * returns a non-string or empty value, so a misconfigured filter
	 * never accidentally opens the gate to everyone.
	 *
	 * @since x-release-please-version
	 *
	 * @return string Capability slug.
	 */
	function wpgraphql_ide_get_capability(): string {
		/**
		 * Override the capability required to use the IDE.
		 *
		 * @param string $capability Default `manage_graphql_ide`.
		 */
		$capability = apply_filters( 'wpgraphql_ide_capability_required', 'manage_graphql_ide' );

		return is_string( $capability ) && '' !== $capability
			? $capability
			: 'manage_graphql_ide';
	}
}

if ( ! function_exists( 'wpgraphql_ide_user_can' ) ) {
	/**
	 * Whether the current user passes the IDE capability check.
	 *
	 * Equivalent to `current_user_can( wpgraphql_ide_get_capability() )`.
	 *
	 * @since x-release-please-version
	 */
	function wpgraphql_ide_user_can(): bool {
		return current_user_can( wpgraphql_ide_get_capability() );
	}
}
