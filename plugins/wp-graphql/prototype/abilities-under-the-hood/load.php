<?php
/**
 * WPGraphQL Abilities Prototype — bootstrap.
 *
 * PROTOTYPE ONLY. `require_once`d from wp-graphql.php so the experiment loads
 * with the plugin (no mu-plugin mount or .wp-env.json config required).
 *
 * The whole module is self-contained and would be deleted wholesale when the
 * prototype concludes. Everything here is designed as if it were a standalone
 * plugin: the abilities (data-abilities.php) stand in for read abilities we
 * assume WordPress core will ship, and the WPGraphQL-side wiring lives alongside
 * so the diff reads like "what core WPGraphQL would change to adopt them."
 *
 * Disable without removing the require by defining, before WPGraphQL loads:
 *   define( 'WPGRAPHQL_DISABLE_ABILITIES_PROTOTYPE', true );
 *
 * @package WPGraphQL\Prototype\Abilities
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WPGRAPHQL_DISABLE_ABILITIES_PROTOTYPE' ) && WPGRAPHQL_DISABLE_ABILITIES_PROTOTYPE ) {
	return;
}

// Stand-in read abilities (simulating future core abilities). Self-guards on the
// Abilities API being present (WordPress 6.9+), so this is a no-op on older WP.
require_once __DIR__ . '/data-abilities.php';

// Resolution toggle + Model trust glue (flips PostObjectLoader to the ability path).
require_once __DIR__ . '/resolve.php';

// Measurement counters, surfaced in extensions.abilitiesPrototype.
require_once __DIR__ . '/counters.php';

// POC: generate an ability from a persisted GraphQL query (the constructive
// "abilities above the resolver" direction).
require_once __DIR__ . '/poc-persisted-query-ability/persisted-query-ability.php';
