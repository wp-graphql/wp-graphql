<?php

namespace Helper;

/**
 * Grants the IDE capability to the administrator role for a test.
 *
 * WHY THIS SHIM EXISTS
 * --------------------
 * In production, `manage_graphql_ide` reaches administrators two ways:
 *   1. the plugin activation hook (wpgraphql_ide_activate), and
 *   2. add_custom_capabilities() on `plugins_loaded` — which also covers
 *      installs that never "activate" (must-use plugins, Composer loads).
 *
 * The WPLoader test bootstrap defeats BOTH for the purpose of a running test:
 * it loads/activates plugins without firing activation hooks, and while
 * add_custom_capabilities() does run on `plugins_loaded`, it grants the cap
 * *after* the test framework has already resolved roles for the suite. The net
 * effect is that REST/authorization tests run as an administrator who can't
 * pass the `manage_graphql_ide` gate — producing spurious 403s and empty
 * result sets that have nothing to do with the behavior under test.
 *
 * Calling grantIdeCapability() from a test's setUp() — AFTER parent::setUp() —
 * re-applies the cap at the one point that is reliably effective: per-test,
 * after the framework's role setup. It mirrors a real activated install. It is
 * admin-only and idempotent, so the subscriber/anonymous deny-path assertions
 * still assert the negative case.
 *
 * If a new IDE wpunit test exercises a `manage_graphql_ide`-gated path (REST
 * routes, document authorization, import/export) and 403s only in CI, add this
 * trait + the setUp() call rather than reaching for the cap directly.
 */
trait GrantsIdeCapability {

	/**
	 * Replicate the plugin's activation-time capability grant for tests.
	 */
	protected function grantIdeCapability(): void {
		if ( function_exists( 'WPGraphQLIDE\\wpgraphql_ide_activate' ) ) {
			\WPGraphQLIDE\wpgraphql_ide_activate();
		}
	}
}
