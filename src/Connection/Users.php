<?php

namespace WPGraphQL\Connection;

/**
 * Deprecated class for backwards compatibility.
 */
class Users extends \WPGraphQL\Type\Connection\Users {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function register_connections() {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\Users::register_connections' );
		parent::register_connections();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function get_connection_args() {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\Users::get_connection_args' );
		return parent::get_connection_args();
	}
}
