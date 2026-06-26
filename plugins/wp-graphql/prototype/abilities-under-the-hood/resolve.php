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
 *   - 'output'     : Experiment B — the ability is the data source. The loader
 *                    requests a fixed payload from the ability (it cannot see the
 *                    GraphQL selection set — loaders batch by ID only), so the
 *                    ability eagerly produces those fields, including rendering
 *                    the_content, regardless of what the query selected.
 */
function wpgraphql_proto_resolve_mode(): string {
	if ( defined( 'WPGRAPHQL_ABILITIES_RESOLVE_OUTPUT' ) && constant( 'WPGRAPHQL_ABILITIES_RESOLVE_OUTPUT' ) ) {
		return 'output';
	}
	if ( defined( 'WPGRAPHQL_ABILITIES_RESOLVE' ) && constant( 'WPGRAPHQL_ABILITIES_RESOLVE' ) ) {
		return 'permission';
	}
	$filtered = apply_filters( 'graphql_abilities_resolve_mode', null );
	if ( in_array( $filtered, [ 'off', 'permission', 'output' ], true ) ) {
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
 * The fixed field set the loader must request from the ability in 'output' mode.
 *
 * This is the crux of Experiment B: because the loader batches by ID and has no
 * access to the per-request selection set, it has to ask for a fixed payload. Any
 * field a downstream selection *might* want has to be in here — including
 * `content`, whose computation runs the_content. So a query selecting only
 * `title` still pays to render content for every node. The alternative (omit
 * content) under-fetches: a query selecting `content` would get nothing.
 *
 * @return string[]
 */
function wpgraphql_proto_output_fields(): array {
	return [
		'id',
		'databaseId',
		'slug',
		'title',
		'date',
		'status',
		'type',
		'authorDatabaseId',
		'parentDatabaseId',
		'link',
		'content',
		'excerpt',
	];
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
