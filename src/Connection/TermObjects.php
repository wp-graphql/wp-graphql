<?php

namespace WPGraphQL\Connection;

/**
 * Deprecated class for backwards compatibility.
 */
class TermObjects extends \WPGraphQL\Type\Connection\TermObjects {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated @todo
	 */
	public static function register_connections() {
		_deprecated_function( __METHOD__, '@todo', '\WPGraphQL\Type\Connection\TermObjects::register_connections' );
		parent::register_connections();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated @todo
	 */
	public static function get_connection_config( $tax_object, $args = [] ) {
		_deprecated_function( __METHOD__, '@todo', '\WPGraphQL\Type\Connection\TermObjects::get_connection_config' );
		return parent::get_connection_config( $tax_object, $args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated @todo
	 */
	public static function get_connection_args( $args = [] ) {
		_deprecated_function( __METHOD__, '@todo', '\WPGraphQL\Type\Connection\TermObjects::get_connection_args' );
		return parent::get_connection_args( $args );
	}
}
