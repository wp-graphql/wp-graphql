<?php

namespace WPGraphQL\Connection;

/**
 * Deprecated class for backwards compatibility.
 */
class Users extends \WPGraphQL\Type\Connection\Users {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated @todo
	 */
	public static function register_connections() {
		_deprecated_function( __METHOD__, '@todo', '\WPGraphQL\Type\Connection\Users::register_connections' );
		parent::register_connections();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated @todo
	 */
	public static function get_connection_args() {
		_deprecated_function( __METHOD__, '@todo', '\WPGraphQL\Type\Connection\Users::get_connection_args' );
		return parent::get_connection_args();
	}
}
