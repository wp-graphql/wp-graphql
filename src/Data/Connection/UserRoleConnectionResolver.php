<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;

class UserRoleConnectionResolver extends AbstractConnectionResolver {

	/**
	 * No args needed to be passed to the query
	 * @return array
	 */
	public function get_query_args() {
		return [];
	}

	/**
	 * Get the defined wp_roles
	 * @return mixed|\WP_Roles
	 */
	public function get_query() {
		return wp_roles();
	}

	/**
	 * Get the items from WP_Roles
	 * @return array|mixed|\WP_Roles
	 */
	public function get_items() {
		return ! empty( $this->query->get_names() ) ? array_keys( $this->query->get_names() ) : [];
	}

	/**
	 * If the request is not from an authenticated user with "list_users" capability, we can prevent
	 * the connection query from being executed at all as we know they shouldn't have access
	 * to the data.
	 *
	 * @return bool
	 */
	public function should_execute() {
		if ( ! current_user_can( 'list_users' ) ) {
			return false;
		}
		return true;
	}

}
