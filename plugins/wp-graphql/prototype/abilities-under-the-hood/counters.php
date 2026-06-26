<?php
/**
 * WPGraphQL Abilities Prototype — measurement counters.
 *
 * PROTOTYPE ONLY. Tracks the costs that decide whether resolving through
 * abilities is better or worse than WPGraphQL's loader/model:
 *   - dbQueries:     total SQL queries for the request ($wpdb->num_queries delta)
 *   - wpQueryRuns:   number of WP_Query DB hits (posts_request)
 *   - abilityExec:   ability execute() calls, by ability name (wp_after_execute_ability)
 *   - theContent:    apply_filters('the_content') invocations (over-process signal)
 *
 * Counters reset at the start of each GraphQL request and are surfaced back in
 * the response under extensions.abilitiesPrototype so an A/B (resolve on vs off)
 * is visible right in the query result.
 *
 * @package WPGraphQL\Prototype\Abilities
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple static counter bag for the prototype.
 */
final class WPGraphQL_Proto_Counters {

	/** @var array<string,int> */
	public static $ability_exec = [];

	/** @var int */
	public static $wp_query_runs = 0;

	/** @var int */
	public static $the_content = 0;

	/** @var int */
	public static $start_queries = 0;

	/**
	 * Reset all counters and snapshot the current query count.
	 */
	public static function reset(): void {
		global $wpdb;
		self::$ability_exec  = [];
		self::$wp_query_runs = 0;
		self::$the_content   = 0;
		self::$start_queries = $wpdb instanceof wpdb ? (int) $wpdb->num_queries : 0;
	}

	/**
	 * Snapshot of the counters, including the total-query delta.
	 *
	 * @return array<string,mixed>
	 */
	public static function snapshot(): array {
		global $wpdb;
		$db_queries = ( $wpdb instanceof wpdb ? (int) $wpdb->num_queries : 0 ) - self::$start_queries;
		return [
			'dbQueries'   => $db_queries,
			'wpQueryRuns' => self::$wp_query_runs,
			'abilityExec' => (object) self::$ability_exec,
			'theContent'  => self::$the_content,
			'resolveMode' => ( function_exists( 'wpgraphql_proto_resolve_enabled' ) && wpgraphql_proto_resolve_enabled() ) ? 'abilities' : 'native',
		];
	}
}

// Count WP_Query DB hits (fires only when the query actually hits the DB).
add_filter(
	'posts_request',
	static function ( $request ) {
		++WPGraphQL_Proto_Counters::$wp_query_runs;
		return $request;
	}
);

// Count the_content invocations (the expensive render path).
add_filter(
	'the_content',
	static function ( $content ) {
		++WPGraphQL_Proto_Counters::$the_content;
		return $content;
	},
	0
);

// Count ability executions by name.
add_action(
	'wp_after_execute_ability',
	static function ( $ability_name ): void {
		$name = (string) $ability_name;
		WPGraphQL_Proto_Counters::$ability_exec[ $name ] = ( WPGraphQL_Proto_Counters::$ability_exec[ $name ] ?? 0 ) + 1;
	}
);

// Reset at the start of every GraphQL request.
add_action(
	'do_graphql_request',
	static function (): void {
		WPGraphQL_Proto_Counters::reset();
	}
);

// Surface the counters in the GraphQL response extensions.
add_filter(
	'graphql_request_results',
	static function ( $response ) {
		$snapshot = WPGraphQL_Proto_Counters::snapshot();
		if ( is_array( $response ) ) {
			if ( ! isset( $response['extensions'] ) || ! is_array( $response['extensions'] ) ) {
				$response['extensions'] = [];
			}
			$response['extensions']['abilitiesPrototype'] = $snapshot;
		} elseif ( is_object( $response ) && property_exists( $response, 'extensions' ) ) {
			$extensions                       = is_array( $response->extensions ) ? $response->extensions : [];
			$extensions['abilitiesPrototype'] = $snapshot;
			$response->extensions             = $extensions;
		}
		return $response;
	}
);
