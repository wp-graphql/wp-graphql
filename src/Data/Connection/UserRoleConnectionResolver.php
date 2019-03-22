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
		return ! empty( $this->get_query()->get_names() ) ? array_keys( $this->get_query()->get_names() ) : [];
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

//	/**
//	 * We're overriding the default connection resolver (for now) and returning
//	 * the shape we
//	 * @return array|null
//	 */
//	public function get_connection() {
//		$roles = $this->get_items();
//		$clean_roles = [];
//		if ( is_a( $roles, 'WP_Roles' ) && is_array( $roles->roles ) && ! empty( $roles->roles ) ) {
//
//			foreach ( $roles->roles as $role_name => $data ) {
//				$data['id'] = $role_name;
//				$clean_roles[] = $data;
//			}
//
//			$connection = Relay::connectionFromArray( $clean_roles, $this->args );
//
//			$nodes = [];
//
//			if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
//				foreach ( $connection['edges'] as $edge ) {
//					$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
//				}
//			}
//
//			$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;
//
//			return ! empty( $clean_roles ) ? $connection : null;
//
//		} else {
//			throw new UserError( __( 'No user roles could be found given the input', 'wp-graphql' ) );
//		}
//	}
}
