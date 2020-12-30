<?php

namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\UserRole;

/**
 * Class UserRoleLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class UserRoleLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return mixed|UserRole
	 * @throws Exception
	 */
	protected function get_model( $entry, $key ) {
		return new UserRole( $entry );
	}

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws Exception
	 */
	public function loadKeys( array $keys ) {
		$wp_roles = wp_roles()->roles;

		$loaded = [];
		if ( ! empty( $wp_roles ) && is_array( $wp_roles ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $wp_roles[ $key ] ) ) {
					$role                = $wp_roles[ $key ];
					$role['slug']        = $key;
					$role['id']          = $key;
					$role['displayName'] = $role['name'];
					$role['name']        = $key;
					$loaded[ $key ]      = $role;
				} else {
					$loaded[ $key ] = null;
				}
			}
		}

		return $loaded;
	}
}
