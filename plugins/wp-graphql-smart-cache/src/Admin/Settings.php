<?php

namespace WPGraphQL\SmartCache\Admin;

use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Document\Grant;

class Settings {

	/**
	 * Set this to true to see these in wp-admin
	 *
	 * @return bool
	 */
	public static function show_in_admin() {
		$display_admin = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'editor_display', false, 'graphql_persisted_queries_section' ) : false;
		return ( 'on' === $display_admin );
	}

	/**
	 * Whether caching is enabled, according to the settings
	 *
	 * @return bool
	 */
	public static function caching_enabled() {

		// get the cache_toggle setting
		$option = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'cache_toggle', false, 'graphql_cache_section' ) : false;

		$enabled = ( 'on' === $option );

		$enabled = apply_filters( 'wpgraphql_cache_wordpress_cache_enabled', (bool) $enabled );

		// if there's no user logged in, and GraphQL Caching is enabled
		return (bool) $enabled;
	}

	/**
	 * Whether cache maps are enabled.
	 *
	 * Cache maps are used to track which nodes and list keys are associated with which queries,
	 * and can be referenced to purge specific queries.
	 *
	 * Default behavior is to only enable building and storage of the cache maps if "WordPress Cache" (non-network cache) is enabled, but this can be filtered to be enabled without WordPress cache being enabled.
	 *
	 * @return bool
	 */
	public static function cache_maps_enabled() {

		// Whether "WordPress Cache" (object/transient) cache is enabled
		$enabled = self::caching_enabled();
		return (bool) apply_filters( 'wpgraphql_cache_enable_cache_maps', (bool) $enabled );
	}

	/**
	 * Whether logging purge events to the error log is enabled
	 *
	 * @return bool
	 */
	public static function purge_logging_enabled() {
		$option = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'log_purge_events', false, 'graphql_cache_section' ) : false;

		// if there's no user logged in, and GraphQL Caching is enabled
		return ( 'on' === $option );
	}

	/**
	 * Date/Time of the last time purge all happened through admin.
	 *
	 * @return string|false
	 */
	public static function caching_purge_timestamp() {
		return function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'purge_all_timestamp', false, 'graphql_cache_section' ) : false;
	}

	/**
	 * The graphql url endpoint with leading slash.
	 *
	 * @return string
	 */
	public static function graphql_endpoint() {
		$path = function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'graphql_endpoint', 'graphql', 'graphql_general_settings' ) : 'graphql';
		return '/' . $path;
	}

	/**
	 * @return void
	 */
	public function init() {

		// Filter the graphql_query_analyzer setting to be on if WPGraphQL Smart Cache is active
		add_filter( 'graphql_setting_field_config', [ $this, 'filter_graphql_query_analyzer_enabled_field' ], 10, 3 );
		add_filter( 'graphql_get_setting_section_field_value', [ $this, 'filter_graphql_query_analyzer_enabled_value' ], 10, 5 );

		// Add to the wp-graphql admin settings page
		add_action(
			'graphql_register_settings',
			function () {

				// Add a tab section to the graphql admin settings page
				register_graphql_settings_section(
					'graphql_persisted_queries_section',
					[
						'title' => __( 'Saved Queries', 'wp-graphql-smart-cache' ),
						'desc'  => __( 'Saved/Persisted GraphQL Queries', 'wp-graphql-smart-cache' ),
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => Grant::GLOBAL_SETTING_NAME,
						'label'             => __( 'Allow/Deny Mode', 'wp-graphql-smart-cache' ),
						'desc'              => __( 'Allow or deny specific queries. Or leave your graphql endpoint wideopen with the public option (not recommended).', 'wp-graphql-smart-cache' ),
						'type'              => 'radio',
						'default'           => Grant::GLOBAL_DEFAULT,
						'options'           => [
							Grant::GLOBAL_PUBLIC  => 'Public',
							Grant::GLOBAL_ALLOWED => 'Allow only specific queries',
							Grant::GLOBAL_DENIED  => 'Deny some specific queries',
						],
						'sanitize_callback' => function ( $value ) {
							// If the value changed, trigger cache purge
							if ( function_exists( 'get_graphql_setting' ) ) {
								$current_setting = \get_graphql_setting( Grant::GLOBAL_SETTING_NAME, Grant::GLOBAL_DEFAULT, 'graphql_persisted_queries_section' );
								if ( $current_setting !== $value ) {
									// Action for those listening to purge_all
									do_action( 'wpgraphql_cache_purge_all' );
								}
							}
							return $value;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'    => 'editor_display',
						'label'   => __( 'Display saved query documents in admin editor', 'wp-graphql-smart-cache' ),
						'desc'    => __( 'Toggle to show saved query documents in the wp-admin left side menu', 'wp-graphql-smart-cache' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => 'query_garbage_collect',
						'label'             => __( 'Delete Old Queries', 'wp-graphql-smart-cache' ),
						'desc'              => __( 'Toggle on to enable garbage collection (delete) of saved queries older than number of days specified below. Queries that are tagged in a "Group" will be excluded from garbage collection.', 'wp-graphql-smart-cache' ),
						'type'              => 'checkbox',
						'default'           => 'off',
						'sanitize_callback' => function ( $value ) {
							/**
							 * When enable garbage collection,
							 * schedule the garbage collection action/event to run once daily.
							 * Otherwise remove it.
							 */
							if ( 'on' === $value ) {
								if ( ! wp_next_scheduled( 'wpgraphql_smart_cache_query_garbage_collect' ) ) {
									// Add scheduled job to run
									$event_recurrence = apply_filters( 'wpgraphql_smart_cache_query_garbage_collect_recurrence', 'daily' );
									wp_schedule_event( time() + 60, $event_recurrence, 'wpgraphql_smart_cache_query_garbage_collect' );
								}
							} else {
								wp_clear_scheduled_hook( 'wpgraphql_smart_cache_query_garbage_collect' );
							}
							return $value;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_persisted_queries_section',
					[
						'name'              => 'query_garbage_collect_age',
						'desc'              => __( 'Age, in number of days, of saved query when it will be removed', 'wp-graphql-smart-cache' ),
						'type'              => 'number',
						'default'           => '30',
						'sanitize_callback' => function ( $value ) {
							if ( 1 > $value || ! is_numeric( $value ) ) {
								return function_exists( 'get_graphql_setting' ) ? \get_graphql_setting( 'query_garbage_collect_age', false, 'graphql_persisted_queries_section' ) : null;
							}
							return (int) $value;
						},
					]
				);

				// Add a tab section to the graphql admin settings page
				register_graphql_settings_section(
					'graphql_cache_section',
					[
						'title' => __( 'Cache', 'wp-graphql-smart-cache' ),
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'     => 'network_cache_notice',
						'type'     => 'custom',
						'callback' => static function ( array $args ) {
							echo '
							<h2>' . esc_html( __( 'Network Cache Settings', 'wp-graphql-smart-cache' ) ) . '</h2>
							<p>' . esc_html( __( 'Below are settings that will modify behavior of the headers used by network cache clients such as varnish.', 'wp-graphql-smart-cache' ) ) . '</p>
							<p>' . esc_html( __( 'Our recommendation is to use HTTP GET requests for queries and take advantage of the network cache (varnish, etc) and only enable and use Object Cache if GET requests are, for some reason, not an option.', 'wp-graphql-smart-cache' ) ) . '</p>

							';
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'global_max_age',
						'label'             => __( 'Cache-Control max-age', 'wp-graphql-smart-cache' ),
						'desc'              => __( 'Value, in seconds. (i.e. 600 = 10 minutes) If set, a Cache-Control header with max-age directive will be added to the responses GraphQL queries made by via non-authenticated HTTP GET request. Value should be an integer, greater or equal to zero. A value of 0 indicates that requests should not be cached (use with caution).', 'wp-graphql-smart-cache' ),
						'type'              => 'number',
						'sanitize_callback' => function ( $value ) {
							if ( ! is_numeric( $value ) ) {
								return null;
							}

							if ( $value < 0 ) {
								return 0;
							}

							return (int) $value;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'     => 'object_cache_notice',
						'type'     => 'custom',
						'callback' => static function ( array $args ) {
							echo '
							<hr>
							<h2>' . esc_html( __( 'Object Cache Settings', 'wp-graphql-smart-cache' ) ) . '</h2>
							<p>' . esc_html( __( 'Below are settings that will impact object cache behavior.', 'wp-graphql-smart-cache' ) ) . '</p>
							<p><strong>' . esc_html( __( 'NOTE', 'wp-graphql-smart-cache' ) ) . ':</strong> ' . esc_html( __( 'GraphQL Object Cache is only recommended if network cache cannot be used. When possible, we recommend using HTTP GET requests and network caching layers such as varnish.', 'wp-graphql-smart-cache' ) ) . '</p>

							';
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'    => 'cache_toggle',
						'label'   => __( 'Use Object Cache', 'wp-graphql-smart-cache' ),
						'desc'    => __( 'Use local object or transient cache to save entire GraphQL query results, for improved speed and performance. Store and return results of GraphQL Queries in the Object cache until they have expired (see below) or a related action has evicted the cached response.', 'wp-graphql-smart-cache' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'global_ttl',
						'label'             => __( 'Object Cache Expiration', 'wp-graphql-smart-cache' ),
						// translators: the global cache ttl default value
						'desc'              => sprintf( __( 'Time, in seconds, to store the result in cache for an individual GraphQL request. Cached results will be evicted after this amount of time, if not before by a related data eviction. Value should be an integer, greater or equal to zero. Default %1$s (%2$s minutes).', 'wp-graphql-smart-cache' ), Results::GLOBAL_DEFAULT_TTL, ( Results::GLOBAL_DEFAULT_TTL / 60 ) ),
						'type'              => 'number',
						'sanitize_callback' => function ( $value ) {
							if ( $value < 0 || ! is_numeric( $value ) ) {
								return null;
							}
							return (int) $value;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'     => 'debugging_notice',
						'type'     => 'custom',
						'callback' => static function ( array $args ) {
							echo '
							<hr>
							<h2>' . esc_html( __( 'Debugging', 'wp-graphql-smart-cache' ) ) . '</h2>
							<p>' . esc_html( __( 'Below are settings you can use to help debug', 'wp-graphql-smart-cache' ) ) . '</p>

							';
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'    => 'log_purge_events',
						'label'   => __( 'Log Purge Events', 'wp-graphql-smart-cache' ),
						'desc'    => __( 'Enabling this option will log purge events to the error log. This can be helpful when debugging what events are leading to specific purge events.', 'wp-graphql-smart-cache' ),
						'type'    => 'checkbox',
						'default' => 'off',
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'purge_all',
						'label'             => __( 'Purge Now!', 'wp-graphql-smart-cache' ),
						'desc'              => __( 'Purge GraphQL Cache. Select this box and click the save button to purge all responses stored in the GraphQL Cache. This should purge network caches and object caches (if enabled) for GraphQL Queries.', 'wp-graphql-smart-cache' ),
						'type'              => 'checkbox',
						'default'           => 'off',
						'sanitize_callback' => function ( $value ) {
							return false;
						},
					]
				);

				register_graphql_settings_field(
					'graphql_cache_section',
					[
						'name'              => 'purge_all_timestamp',
						'label'             => __( 'Did you purge the cache?', 'wp-graphql-smart-cache' ),
						'desc'              => __( 'This field displays the last time the purge all was invoked on this page.', 'wp-graphql-smart-cache' ),
						'type'              => 'text',
						'sanitize_callback' => function ( $value ) {
							$existing_purge_all_time = self::caching_purge_timestamp();

							if ( empty( $_POST ) || //phpcs:ignore
								! isset( $_POST['graphql_cache_section']['purge_all'] )  //phpcs:ignore
							) {
								return $existing_purge_all_time;
							}

							// Purge the cache, then return/save a new purge time
							 //phpcs:ignore
							if ( 'on' === $_POST['graphql_cache_section']['purge_all'] ) {

								// Trigger action when cache purge_all is invoked
								do_action( 'wpgraphql_cache_purge_all' );

								return gmdate( 'D, d M Y H:i T' );
							}

							return $existing_purge_all_time;
						},
					]
				);
			}
		);
	}

	/**
	 * Filter the config for the query_analyzer_enabled setting
	 *
	 * @param array<string,mixed>  $field_config The field config for the setting
	 * @param string               $field_name   The name of the field (unfilterable in the config)
	 * @param string               $section      The slug of the section the field is registered to
	 *
	 * @return mixed
	 */
	public function filter_graphql_query_analyzer_enabled_field( $field_config, $field_name, $section ) {
		if ( 'query_analyzer_enabled' !== $field_name || 'graphql_general_settings' !== $section ) {
			return $field_config;
		}

		$field_config['value']    = 'on';
		$field_config['disabled'] = true;
		$field_config['default']  = 'on';

		if ( ! \WPGraphQL::debug() ) {
			$field_config['desc'] = $field_config['desc'] . ' (<strong>' . __( 'Force enabled by WPGraphQL Smart Cache to properly support cache tagging and invalidation.', 'wp-graphql-smart-cache' ) . '</strong>)';
		}

		return $field_config;
	}

	/**
	 * Filter the value of the query_analyzer_enabled setting
	 *
	 * @param mixed               $value          The value of the field
	 * @param mixed               $default_value  The default value if there is no value set
	 * @param string              $option_name    The name of the option
	 * @param array<string,mixed> $section_fields The setting values within the section
	 * @param string              $section_name   The name of the section the setting belongs to
	 *
	 * @return mixed|string
	 */
	public function filter_graphql_query_analyzer_enabled_value( $value, $default_value, string $option_name, $section_fields, $section_name ) {
		if ( 'query_analyzer_enabled' !== $option_name ) {
			return $value;
		}

		// graphql_query_analyzer needs to be on for WPGraphQL Smart Cache to properly tag and invalidate caches
		return 'on';
	}
}
