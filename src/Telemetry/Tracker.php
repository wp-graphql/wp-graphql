<?php

namespace WPGraphQL\Telemetry;

use WPGraphQL\Utils\Utils;

/**
 * Class Tracker
 *
 * @package WPGraphQL\Telemetry
 */
class Tracker {

	/**
	 * The name of the plugin being tracked
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The slugified name of the plugin being tracked
	 *
	 * @var string
	 */
	protected $plugin_slug;

	/**
	 * Whether tracking is enabled for the plugin
	 *
	 * @var mixed|void
	 */
	protected $tracking_enabled;

	/**
	 * The endpoint to send tracking data to
	 *
	 * @var string
	 */
	protected $endpoint_url;

	/**
	 * Events that have been tracked during this request
	 *
	 * @var array
	 */
	protected $events;

	/**
	 * The name of the option that tracks the last tracked timestamp
	 *
	 * @var string
	 */
	protected $identity_timestamp_option_name;

	/**
	 * Tracker constructor.
	 *
	 * @param string $plugin_name The name of the plugin
	 */
	public function __construct( string $plugin_name ) {

		$this->endpoint_url                   = 'https://analytics.gatsbyjs.com/events';
		$this->plugin_name                    = $plugin_name;
		$this->plugin_slug                    = strtolower( Utils::format_field_name( $this->plugin_name ) );
		$this->identity_timestamp_option_name = '_' . $this->plugin_slug . '_telemetry_identity_last_logged';
		$tracking_enabled                     = 'on' === get_graphql_setting( 'telemetry_enabled', 'off' );
		$this->tracking_enabled               = apply_filters( 'graphql_telemetry_enabled', $tracking_enabled, $this );
		$this->init();

	}

	/**
	 * Initialize the tracker.
	 */
	public function init() {

		// If tracking is not enabled, do nothing
		if ( ! $this->tracking_enabled ) {
			$this->delete_timestamp();

			return;
		}

		add_action( 'init', [ $this, 'track_identity' ] );
		add_action( 'shutdown', [ $this, 'send_events' ] );
	}

	/**
	 * @return void
	 */
	public function delete_timestamp() {
		delete_option( $this->get_identity_timestamp_option_name() );
	}

	/**
	 * Return the option name that stores the timestamp
	 *
	 * @return string
	 */
	public function get_identity_timestamp_option_name() {
		return $this->identity_timestamp_option_name;
	}

	/**
	 * Returns the plugin name being tracked
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Returns the slug of the plugin being tracked
	 *
	 * @return string
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Returns whether tracking is enabled or not
	 *
	 * @return bool
	 */
	public function is_tracking_enabled() {
		return $this->tracking_enabled;
	}

	/**
	 * Tracks the identity of the plugin. This is done once daily.
	 *
	 * @return void
	 */
	public function track_identity() {

		if ( ! $this->should_track_identity() ) {
			return;
		}

		update_option( $this->get_identity_timestamp_option_name(), strtotime( 'now' ) );

		graphql_debug( __( 'Identity Tracked', 'wp-graphql' ) );
		$this->track_event( 'IDENTITY' );
	}

	/**
	 * Given a string, this hashes it and returns the hashed value
	 *
	 * @param string $value The string to hash
	 *
	 * @return string
	 */
	public function hash( $value ) {
		return hash( 'sha256', $value );
	}

	/**
	 * Given a key from the $_SERVER super global, returns sanitized data
	 *
	 * @param $key
	 *
	 * @return null
	 */
	public function clean_server_data( $key ) {
		return isset( $_SERVER[ $key ] ) ? $this->sanitize( $_SERVER[ $key ] ) : null;
	}

	/**
	 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
	 * Non-scalar values are ignored.
	 *
	 * @param string|array $input Data to sanitize.
	 *
	 * @return string|array
	 */
	public function sanitize( $input ) {

		if ( is_array( $input ) ) {
			return array_map( [ $this, 'sanitize' ], $input );
		} else {
			return is_scalar( $input ) ? sanitize_text_field( $input ) : $input;
		}
	}

	/**
	 * Get the info to track
	 *
	 * @param string $event_type The type of event being tracked
	 * @param array  $extra_info Additional info to track for the event
	 *
	 * @return array
	 */
	public function get_info( $event_type = 'IDENTITY', array $extra_info ) {

		global $_SERVER;

		$active_plugins = get_option( 'active_plugins', [] );
		$active_plugins = array_map( function( $plugin ) {
			return [
				'path' => $plugin,
			];
		}, $active_plugins );

		// This data is required
		$data = [
			'phpVersion'         => phpversion(),
			'serverSoftware'     => $this->clean_server_data( 'SERVER_SOFTWARE' ),
			'userName'           => $this->hash( $this->clean_server_data( 'USER' ) ),
			'sessionId'          => '',
			'httpAcceptLanguage' => $this->clean_server_data( 'HTTP_ACCEPT_LANGUAGE' ),
			'userAgent'          => $this->clean_server_data( 'HTTP_USER_AGENT' ),
			'host'               => $this->sanitize( site_url() ),
			'pwd'                => $this->hash( $this->clean_server_data( 'DOCUMENT_ROOT' ) ),
			'filename'           => $this->hash( $this->clean_server_data( 'SCRIPT_FILENAME' ) ),
			'activePlugins'      => ! empty( $active_plugins ) ? $this->sanitize( $active_plugins ) : null,
			'name'               => $this->sanitize( $this->plugin_name ),
			'adminEmail'         => $this->sanitize( get_option( 'admin_email' ) ),
			'installationId'     => $this->sanitize( site_url() ) . ':' . $this->hash( $this->clean_server_data( 'DOCUMENT_ROOT' ) ),
		];

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$data['wpEnvironmentType'] = wp_get_environment_type();
		}

		$data = array_merge( $extra_info, $data );

		/**
		 * This data is mandatory and cannot be merged/overridden by $extra_data
		 */
		$data['time']        = date( DATE_RFC3339 );
		$data['componentId'] = $this->plugin_name;
		$data['eventType']   = $event_type;
		$data['version']     = 1;

		return $data;

	}

	/**
	 * Given an Event Type and optional array of extra info, this will track an event
	 * by adding it to an in-memory array of tracked events.
	 *
	 * The events will be sent to the remote endpoint on shutdown.
	 *
	 * @param string $event_type The name of the event to track
	 * @param array  $extra_info Extra info to track with the event
	 *
	 * @return void
	 */
	public function track_event( string $event_type, $extra_info = [] ) {
		if ( ! $this->tracking_enabled ) {
			return;
		}

		$this->events[] = $this->get_info( $event_type, $extra_info );

	}

	/**
	 * Determines whether the event should be tracked
	 *
	 * @return bool
	 */
	public function should_track_identity() {

		$last_logged = get_option( $this->get_identity_timestamp_option_name(), null );

		// If the last time the identity was logged is less than 24 hours ago,
		// Don't log again
		if ( ! empty( $last_logged ) && ( (int) $last_logged > strtotime( '-1 day' ) ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Send any tracked events
	 *
	 * @return void
	 */
	public function send_events() {
		if ( ! $this->tracking_enabled ) {
			return;
		}

		if ( ! empty( $this->events ) && is_array( $this->events ) ) {
			foreach ( $this->events as $event_info ) {
				$this->send_request( $event_info );
			}
		}

	}

	/**
	 * Given an array of event info, this sends a request for the event to be logged
	 *
	 * @param array $event_info The event info to log
	 */
	protected function send_request( array $event_info ) {

		if ( ! $this->tracking_enabled ) {
			return;
		}

		if ( empty( $event_info ) || ! is_array( $event_info ) ) {
			return;
		}

		wp_remote_post( $this->endpoint_url, [
			'headers'  => [
				'Content-Type' => 'application/json',
			],
			'blocking' => false,
			'body'     => wp_json_encode( $event_info ),
		] );

	}

}
