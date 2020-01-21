<?php

namespace WPGraphQL\Data\Connection;

use WPGraphQL\Model\User;

class UserRoleConnectionResolver extends AbstractConnectionResolver {

	/**
	 * No args needed to be passed to the query
	 *
	 * @return array
	 */
	public function get_query_args() {
		return [];
	}

	/**
	 * Get the defined wp_roles
	 *
	 * @return mixed|\WP_Roles
	 */
	public function get_query() {
		return wp_roles();
	}

	/**
	 * Get the items from WP_Roles
	 *
	 * @return array|mixed|\WP_Roles
	 */
	public function get_items() {

		$current_user_roles = wp_get_current_user()->roles;

		if ( $this->source instanceof User ) {
			$roles = ! empty( $this->source->roles ) ? $this->source->roles : [];
		} else {
			$roles = ! empty( $this->query->get_names() ) ? array_keys( $this->query->get_names() ) : [];
		}

		$roles = ! empty( $roles ) ? array_filter(
			array_map(
				function( $role ) use ( $current_user_roles ) {
					if ( current_user_can( 'list_users' ) ) {
						return $role;
					}

					if ( in_array( $role, $current_user_roles, true ) ) {
						return $role;
					}

					return null;
				},
				$roles
			)
		) : $roles;

		return $roles;
	}

	/**
	 * If the request is not from an authenticated user we can prevent
	 * the connection query from being executed at all as we know they shouldn't have access
	 * to the data.
	 *
	 * @return bool
	 */
	public function should_execute() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return true;
	}

	/**
	 * TODO: Temporarily return false for all offsets, as pagination
	 * does not work for user roles. Will need to be updated when
	 * proper pagination is implemented for user roles.
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return false;
	}

}
