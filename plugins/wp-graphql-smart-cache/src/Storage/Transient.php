<?php

namespace WPGraphQL\SmartCache\Storage;

class Transient {

	/**
	 * @var string
	 */
	public $group_name;

	/**
	 * @param string $group_name
	 * @return void
	 */
	public function __construct( $group_name ) {
		$this->group_name = $group_name;
	}

	/**
	 * Get the data from cache/transient based on the provided key
	 *
	 * @param string $key unique id for this request
	 * @return mixed|array|object|null  The graphql response or false if not found
	 */
	public function get( $key ) {
		return get_transient( $this->group_name . '_' . $key );
	}

	/**
	 * @param string $key unique id for this request
	 * @param mixed|array|object|null $data The graphql response
	 * @param int $expire Time in seconds for the data to persist in cache. Zero means no expiration.
	 *
	 * @return bool False if value was not set and true if value was set.
	 */
	public function set( $key, $data, $expire ) {
		return set_transient(
			$this->group_name . '_' . $key,
			is_array( $data ) ? $data : $data->toArray(),
			$expire
		);
	}

	/**
	 * Searches the database for all graphql transients matching our prefix
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all() {
		global $wpdb;

		$prefix = $this->group_name;

		// The transient string + our prefix as it is stored in the options database
		$transient_option_name = $wpdb->esc_like( '_transient_' . $prefix . '_' ) . '%';

		// Make database query to get out transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_results( $wpdb->prepare( "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE %s", $transient_option_name ), ARRAY_A ); //db call ok

		if ( is_wp_error( $transients ) ) {
			return false;
		}

		// Loop through our transients
		if ( is_array( $transients ) ) {
			foreach ( $transients as $transient ) {
				// Remove this string from the option_name to get the name we will use on delete
				$key = str_replace( '_transient_', '', $transient['option_name'] );
				delete_transient( $key );
			}
		}

		return true;
	}

	/**
	 * @param string $key unique id for this request
	 * @return bool True on successful removal, false on failure.
	 */
	public function delete( $key ) {
		return delete_transient( $this->group_name . '_' . $key );
	}

}
