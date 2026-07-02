<?php

// Ensure WPGraphQL is running in debug mode
if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
	define( 'GRAPHQL_DEBUG', true );
}

// The in-repo WPGraphQL core carries unreleased changes but reports the last
// released version, so version-gated compatibility branches in extensions
// (e.g. the post-2.17.0 canonical hook names) would take the legacy path in
// tests. Report a version above the last release so suites exercise the code
// paths that ship with the next core release.
// TODO: remove together with the legacy hook-name fallbacks.
if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
	define( 'WPGRAPHQL_VERSION', '2.17.1' );
}
