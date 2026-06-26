<?php
/**
 * WPGraphQL Abilities Prototype — resolution integration.
 *
 * PROTOTYPE ONLY. Provides the toggle that flips PostObjectLoader between its
 * native WP_Query + Model path and the "resolve through the get-posts ability"
 * path, plus the glue that lets the Post Model *trust* the ability's per-row
 * permission filtering instead of running its own is_private() logic.
 *
 * Enable for a request via either:
 *   define( 'WPGRAPHQL_ABILITIES_RESOLVE', true );              // global
 *   add_filter( 'graphql_abilities_resolve', '__return_true' ); // per-request
 *
 * @package WPGraphQL\Prototype\Abilities
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The resolve mode for PostObjectLoader:
 *   - 'off'        : native WP_Query + Model (default).
 *   - 'permission' : Experiment A — the ability owns fetch + per-row permission;
 *                    the Model still resolves every field from the WP_Post.
 */
function wpgraphql_proto_resolve_mode(): string {
	if ( defined( 'WPGRAPHQL_ABILITIES_RESOLVE' ) && constant( 'WPGRAPHQL_ABILITIES_RESOLVE' ) ) {
		return 'permission';
	}
	$filtered = apply_filters( 'graphql_abilities_resolve_mode', null );
	if ( in_array( $filtered, [ 'off', 'permission' ], true ) ) {
		return $filtered;
	}
	return apply_filters( 'graphql_abilities_resolve', false ) ? 'permission' : 'off';
}

/**
 * Whether PostObjectLoader should resolve through the get-posts ability at all.
 */
function wpgraphql_proto_resolve_enabled(): bool {
	return 'off' !== wpgraphql_proto_resolve_mode();
}

/**
 * When resolving through abilities, the loader has already dropped any post the
 * current user may not view (the ability filtered them out, returning null to
 * the loader). So the Post Model can trust visibility and skip its own
 * is_private() logic — i.e. the ability *replaces* that code path.
 *
 * This is the crux of the experiment: permission ownership moves from the Model
 * to the ability. Caveat: this trusts the ability for ALL PostObject models
 * while enabled; safe here because the only posts that reach the Model are ones
 * the ability approved.
 *
 * @param bool|null $is_private  Whether the model should be considered private.
 * @param string    $model_name  The model name (e.g. "PostObject").
 * @return bool|null
 */
add_filter(
	'graphql_pre_model_data_is_private',
	static function ( $is_private, $model_name ) {
		if ( ! wpgraphql_proto_resolve_enabled() ) {
			return $is_private;
		}
		if ( 'PostObject' === $model_name ) {
			return false;
		}
		return $is_private;
	},
	10,
	2
);
