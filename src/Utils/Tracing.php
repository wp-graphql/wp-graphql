<?php

namespace WPGraphQL\Utils;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\WPSchema;

/**
 * Class Tracing
 *
 * Sets up trace data to track how long individual fields take to resolve in WPGraphQL
 *
 * @package WPGraphQL\Utils
 */
class Tracing {

	/**
	 * Whether Tracing is enabled
	 *
	 * @var boolean
	 */
	public $tracing_enabled;

	/**
	 * Stores the logs for the trace
	 *
	 * @var array
	 */
	public $trace_logs = [];

	/**
	 * The start microtime
	 *
	 * @var float
	 */
	public $request_start_microtime;

	/**
	 * The start timestamp
	 *
	 * @var float
	 */
	public $request_start_timestamp;

	/**
	 * The end microtime
	 *
	 * @var float
	 */
	public $request_end_microtime;

	/**
	 * The end timestamp
	 *
	 * @var float
	 */
	public $request_end_timestamp;

	/**
	 * The trace for the current field being resolved
	 *
	 * @var array
	 */
	public $field_trace = [];

	/**
	 * The version of the Apollo Tracing Spec
	 *
	 * @var int
	 */
	public $trace_spec_version = 1;

	/**
	 * The user role tracing is limited to
	 *
	 * @var string
	 */
	public $tracing_user_role;

	/**
	 * Initialize tracing
	 */
	public function init() {

		// Check whether Query Logs have been enabled from the settings page
		$enabled               = get_graphql_setting( 'tracing_enabled', 'off' );
		$this->tracing_enabled = 'on' === $enabled ? true : false;

		$this->tracing_user_role = get_graphql_setting( 'tracing_user_role', 'manage_options' );

		if ( ! $this->tracing_enabled ) {
			return;
		}

		add_filter( 'do_graphql_request', [ $this, 'init_trace' ] );
		add_action( 'graphql_execute', [ $this, 'end_trace' ], 99, 5 );
		add_filter( 'graphql_access_control_allow_headers', [ $this, 'return_tracing_headers' ] );
		add_filter( 'graphql_request_results', [
			$this,
			'add_tracing_to_response_extensions',
		], 10, 5 );
		add_action( 'graphql_before_resolve_field', [ $this, 'init_field_resolver_trace' ], 10, 8 );
		add_action( 'graphql_after_resolve_field', [ $this, 'end_field_resolver_trace' ], 10, 8 );
	}

	/**
	 * Sets the timestamp and microtime for the start of the request
	 *
	 * @return string
	 */
	public function init_trace() {
		$this->request_start_microtime = microtime( true );
		$this->request_start_timestamp = $this->format_timestamp( $this->request_start_microtime );

		return $this->request_start_timestamp;
	}

	/**
	 * Sets the timestamp and microtime for the end of the request
	 *
	 * @return string
	 */
	public function end_trace() {
		$this->request_end_microtime = microtime( true );
		$this->request_end_timestamp = $this->format_timestamp( $this->request_end_microtime );

		return $this->request_end_timestamp;
	}

	/**
	 * Initialize tracing for an individual field
	 */
	public function init_field_resolver_trace( $source, $args, $context, ResolveInfo $info, $field_resolver, $type_name, $field_key, $field ) {

		$this->field_trace = [
			'path'           => $info->path,
			'parentType'     => $info->parentType->name,
			'fieldName'      => $info->fieldName,
			'returnType'     => $info->returnType->name ? $info->returnType->name : $info->returnType,
			'startOffset'    => $this->get_start_offset(),
			'startMicrotime' => microtime( true ),
		];

	}

	/**
	 * End the tracing for a resolver
	 */
	public function end_field_resolver_trace() {

		if ( ! empty( $this->field_trace ) ) {
			$this->field_trace['duration'] = $this->get_field_resolver_duration();
			$sanitized_trace               = $this->sanitize_resolver_trace( $this->field_trace );
			$this->trace_logs[]            = $sanitized_trace;
		}

		// reset the field trace
		$this->field_trace = [];
	}

	/**
	 * Given a resolver start time, returns the duration of a resolver
	 *
	 * @return float|int
	 */
	public function get_field_resolver_duration() {
		return ( microtime( true ) - $this->field_trace['startMicrotime'] ) * 1000000;
	}

	/**
	 * Get the offset between the start of the request and now
	 *
	 * @return float|int
	 */
	public function get_start_offset() {
		return ( microtime( true ) - $this->request_start_microtime ) * 1000000;
	}

	/**
	 * Given a trace, sanitizes the values and returns the sanitized_trace
	 *
	 * @param array $trace
	 *
	 * @return mixed
	 */
	public function sanitize_resolver_trace( array $trace ) {

		$sanitized_trace                = [];
		$sanitized_trace['path']        = ! empty( $trace['path'] ) && is_array( $trace['path'] ) ? array_map( [
			$this,
			'sanitize_trace_resolver_path',
		], $trace['path'] ) : [];
		$sanitized_trace['parentType']  = ! empty( $trace['parentType'] ) ? esc_html( $trace['parentType'] ) : '';
		$sanitized_trace['fieldName']   = ! empty( $trace['fieldName'] ) ? esc_html( $trace['fieldName'] ) : '';
		$sanitized_trace['returnType']  = ! empty( $trace['returnType'] ) ? esc_html( $trace['returnType'] ) : '';
		$sanitized_trace['startOffset'] = ! empty( $trace['startOffset'] ) ? absint( $trace['startOffset'] ) : '';
		$sanitized_trace['duration']    = ! empty( $trace['duration'] ) ? absint( $trace['duration'] ) : '';

		return $sanitized_trace;
	}

	/**
	 * Given input from a Resolver Path, this sanitizes the input for output in the trace
	 *
	 * @param $input
	 *
	 * @return int|null|string
	 */
	public static function sanitize_trace_resolver_path( $input ) {
		$sanitized_input = null;
		if ( is_numeric( $input ) ) {
			$sanitized_input = absint( $input );
		} else {
			$sanitized_input = esc_html( $input );
		}

		return $sanitized_input;
	}

	/**
	 * Formats a timestamp to be RFC 3339 compliant
	 *
	 * @see https://github.com/apollographql/apollo-tracing
	 *
	 * @param $time
	 *
	 * @return string
	 */
	public function format_timestamp( $time ) {
		$time_as_float = sprintf( '%.4f', $time );
		$timestamp     = \DateTime::createFromFormat( 'U.u', $time_as_float );

		return $timestamp->format( 'Y-m-d\TH:i:s.uP' );
	}

	/**
	 * Filter the headers that WPGraphQL returns to include headers that indicate the WPGraphQL
	 * server supports Apollo Tracing and Credentials
	 *
	 * @param $headers
	 *
	 * @return array
	 */
	public function return_tracing_headers( $headers ): array {
		$headers[] = 'X-Insights-Include-Tracing';
		$headers[] = 'X-Apollo-Tracing';
		$headers[] = 'Credentials';

		return (array) $headers;
	}

	/**
	 * Filter the results of the GraphQL Response to include the Query Log
	 *
	 * @param mixed    $response
	 * @param WPSchema $schema         The WPGraphQL Schema
	 * @param string   $operation_name The operation name being executed
	 * @param string   $request        The GraphQL Request being made
	 * @param array    $variables      The variables sent with the request
	 *
	 * @return mixed $response
	 */
	public function add_tracing_to_response_extensions( $response, $schema, $operation_name, $request, $variables ) {

		// Get the trace
		$trace = $this->get_trace();

		// If a specific capability is set for tracing and the requesting user
		// doesn't have the capability, return the unmodified response
		if ( ! $this->user_can_see_trace_data() ) {
			return $response;
		}

		if ( ! empty( $response ) ) {
			if ( is_array( $response ) ) {
				$response['extensions']['tracing'] = $trace;
			} elseif ( is_object( $response ) ) {
				$response->extensions['tracing'] = $trace;
			}
		}

		return $response;
	}

	/**
	 * Returns the request duration calculated from the start and end times
	 *
	 * @return float|int
	 */
	public function get_request_duration() {
		return ( $this->request_end_microtime - $this->request_start_microtime ) * 1000000;
	}

	/**
	 * Determine if the requesting user can see trace data
	 *
	 * @return boolean
	 */
	public function user_can_see_trace_data(): bool {

		$can_see = false;

		// If logs are disabled, user cannot see logs
		if ( ! $this->tracing_enabled ) {
			$can_see = false;
		} else {
			// If "all" is the selected role, anyone can see the logs
			if ( 'all' === $this->tracing_user_role ) {
				$can_see = true;
			} else {
				// Get the current users roles
				$user = wp_get_current_user();

				// If the user doesn't have roles or the selected role isn't one the user has, the
				// user cannot see roles;
				if ( isset( $user->roles ) && in_array( $this->tracing_user_role, (array) $user->roles, true ) ) {
					$can_see = true;
				}
			}
		}

		/**
		 * Filter whether the logs can be seen in the request results or not
		 *
		 * @param boolean $can_see Whether the requestor can see the logs or not
		 */
		return apply_filters( 'graphql_user_can_see_trace_data', $can_see );

	}

	/**
	 * Get the trace to add to the response
	 *
	 * @return array
	 */
	public function get_trace(): array {

		// Compile the trace to return with the GraphQL Response
		$trace = [
			'version'   => absint( $this->trace_spec_version ),
			'startTime' => esc_html( $this->request_start_microtime ),
			'endTime'   => esc_html( $this->request_end_microtime ),
			'duration'  => absint( $this->get_request_duration() ),
			'execution' => [
				'resolvers' => $this->trace_logs,
			],
		];

		/**
		 * Filter the trace
		 *
		 * @param array   $trace The trace to return
		 * @param Tracing $this  The Tracing class instance
		 */
		return apply_filters( 'graphql_tracing_response', (array) $trace, $this );
	}
}
