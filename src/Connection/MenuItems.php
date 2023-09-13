<?php

namespace WPGraphQL\Connection;

/**
 * Deprecated class for backwards compatibility.
 */
class MenuItems extends \WPGraphQL\Type\Connection\MenuItems {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function register_connections() {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\MenuItems::register_connections' );
		parent::register_connections();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function get_connection_config( $args = [] ) {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\MenuItems::get_connection_config' );
		return parent::get_connection_config( $args );
	}
}
