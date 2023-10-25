<?php

namespace WPGraphQL\Utils;

/**
 * Class QueryLog
 *
 * @package WPGraphQL\Utils
 */
class QueryLog {

	/**
	 * Whether Query Logs are enabled
	 *
	 * @var boolean
	 */
	protected $query_logs_enabled;

	/**
	 * The user role query logs should be limited to
	 *
	 * @var string
	 */
	protected $query_log_user_role;

	/**
	 * Initialize Query Logging
	 *
	 * @return void
	 */
	public function init() {

		// Check whether Query Logs have been enabled from the settings page
		$enabled                  = get_graphql_setting( 'query_logs_enabled', 'off' );
		$this->query_logs_enabled = 'on' === $enabled;

		$this->query_log_user_role = get_graphql_setting( 'query_log_user_role', 'manage_options' );

		if ( ! $this->query_logs_enabled ) {
			return;
		}

		add_action( 'init', [ $this, 'init_save_queries' ] );
		add_filter( 'graphql_request_results', [ $this, 'show_results' ], 10, 5 );
	}

	/**
	 * Tell WordPress to start saving queries.
	 *
	 * NOTE: This will affect all requests, not just GraphQL requests.
	 *
	 * @return void
	 */
	public function init_save_queries() {
		if ( is_graphql_http_request() && ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}
	}

	/**
	 * Determine if the requesting user can see logs
	 *
	 * @return boolean
	 */
	public function user_can_see_logs() {
		$can_see = false;

		// If logs are disabled, user cannot see logs
		if ( ! $this->query_logs_enabled ) {
			$can_see = false;
		} elseif ( 'any' === $this->query_log_user_role ) {
			// If "any" is the selected role, anyone can see the logs
			$can_see = true;
		} else {
			// Get the current users roles
			$user = wp_get_current_user();

			// If the user doesn't have roles or the selected role isn't one the user has, the
			// user cannot see roles;
			if ( in_array( $this->query_log_user_role, $user->roles, true ) ) {
				$can_see = true;
			}
		}

		/**
		 * Filter whether the logs can be seen in the request results or not
		 *
		 * @param boolean $can_see Whether the requester can see the logs or not
		 */
		return apply_filters( 'graphql_user_can_see_query_logs', $can_see );
	}

	/**
	 * Filter the results of the GraphQL Response to include the Query Log
	 *
	 * @param mixed    $response
	 * @param \WPGraphQL\WPSchema $schema The WPGraphQL Schema
	 * @param string   $operation_name The operation name being executed
	 * @param string   $request        The GraphQL Request being made
	 * @param array    $variables      The variables sent with the request
	 *
	 * @return array
	 */
	public function show_results( $response, $schema, $operation_name, $request, $variables ) {
		$query_log = $this->get_query_log();

		// If the user cannot see the logs, return the response as-is without the logs
		if ( ! $this->user_can_see_logs() ) {
			return $response;
		}

		if ( ! empty( $response ) ) {
			if ( is_array( $response ) ) {
				$response['extensions']['queryLog'] = $query_log;
			} elseif ( is_object( $response ) ) {
				// @phpstan-ignore-next-line
				$response->extensions['queryLog'] = $query_log;
			}
		}

		return $response;
	}

	/**
	 * Return the query log produced from the logs stored by WPDB.
	 *
	 * @return array
	 */
	public function get_query_log() {
		global $wpdb;

		$save_queries_value = defined( 'SAVEQUERIES' ) && true === SAVEQUERIES ? 'true' : 'false';
		$default_message    = sprintf(
			// translators: %s is the value of the SAVEQUERIES constant
			__( 'Query Logging has been disabled. The \'SAVEQUERIES\' Constant is set to \'%s\' on your server.', 'wp-graphql' ),
			$save_queries_value
		);

		// Default message
		$trace = [ $default_message ];

		if ( ! empty( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
			$queries = array_map(
				static function ( $query ) {
					return [
						'sql'   => $query[0],
						'time'  => $query[1],
						'stack' => $query[2],
					];
				},
				$wpdb->queries
			);

			$times      = wp_list_pluck( $queries, 'time' );
			$total_time = array_sum( $times );
			$trace      = [
				'queryCount' => count( $queries ),
				'totalTime'  => $total_time,
				'queries'    => $queries,
			];
		}

		/**
		 * Filter the trace
		 *
		 * @param array    $trace     The trace to return
		 * @param \WPGraphQL\Utils\QueryLog $instance The QueryLog class instance
		 */
		return apply_filters( 'graphql_tracing_response', $trace, $this );
	}
}
