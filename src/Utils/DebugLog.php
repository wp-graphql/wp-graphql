<?php
namespace WPGraphQL\Utils;

/**
 * Class DebugLog
 *
 * @package WPGraphQL\Utils
 */
class DebugLog {

	/**
	 * @var array
	 */
	protected $logs;

	/**
	 * Whether logs are enabled
	 *
	 * @var boolean
	 */
	protected $logs_enabled;

	/**
	 * DebugLog constructor.
	 */
	public function __construct() {

		// Instantiate array to start capturing logs
		$this->logs = [];

		// Whether WPGraphQL Debug is enabled
		$enabled = \WPGraphQL::debug();

		/**
		 * Filters whether GraphQL Debug is enabled enabled. Serves as the default state for enabling debug logs.
		 *
		 * @param bool $enabled Whether logs are enabled or not
		 * @param \WPGraphQL\Utils\DebugLog $debug_log The DebugLog class instance
		 */
		$this->logs_enabled = apply_filters( 'graphql_debug_logs_enabled', $enabled, $this );
	}

	/**
	 * Given a message and a config, a log entry is added to the log
	 *
	 * @param mixed|string|array $message The debug log message
	 * @param array  $config Config for the debug log. Set type and any additional information to log
	 *
	 * @return array
	 */
	public function add_log_entry( $message, $config = [] ) {

		if ( empty( $message ) ) {
			return [];
		}

		$type = 'GRAPHQL_DEBUG';

		if ( ! is_array( $config ) ) {
			$config = [];
		}

		if ( isset( $config['type'] ) ) {
			unset( $config['message'] );
		}

		if ( isset( $config['backtrace'] ) ) {
			$config['stack'] = $config['backtrace'];
			unset( $config['backtrace'] );
		}

		if ( isset( $config['type'] ) ) {
			$type = $config['type'];
			unset( $config['type'] );
		}

		if ( ! isset( $this->logs[ wp_json_encode( $message ) ] ) ) {
			$log_entry = array_merge( [
				'type'    => $type,
				'message' => $message,
			], $config );

			$this->logs[ wp_json_encode( $message ) ] = $log_entry;

			/**
			 * Filter the log entry for the debug log
			 *
			 * @param array $log The log entry
			 * @param array $config The config passed in with the log entry
			 */
			return apply_filters( 'graphql_debug_log_entry', $log_entry, $config );
		}

		return [];

	}

	/**
	 * Returns the debug log
	 *
	 * @return array
	 */
	public function get_logs() {

		/**
		 * Init the debug logger
		 *
		 * @param \WPGraphQL\Utils\DebugLog $instance The DebugLog instance
		 */
		do_action( 'graphql_get_debug_log', $this );

		// If GRAPHQL_DEBUG is not enabled on the server, set a default message
		if ( ! $this->logs_enabled ) {
			$this->logs = [
				[
					'type'    => 'DEBUG_LOGS_INACTIVE',
					'message' => __( 'GraphQL Debug logging is not active. To see debug logs, GRAPHQL_DEBUG must be enabled.', 'wp-graphql' ),
				],
			];
		}

		/**
		 * Return the filtered debug log
		 *
		 * @param array    $logs     The logs to be output with the request
		 * @param \WPGraphQL\Utils\DebugLog $instance The Debug Log class
		 */
		return apply_filters( 'graphql_debug_log', array_values( $this->logs ), $this );
	}

}
