<?php

namespace WPGraphQL\Connection;

/**
 * Deprecated class for backwards compatibility.
 */
class PostObjects extends \WPGraphQL\Type\Connection\PostObjects {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function register_connections() {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\PostObjects::register_connections' );
		parent::register_connections();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function get_connection_config( $graphql_object, $args = [] ) {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\PostObjects::get_connection_config' );
		return parent::get_connection_config( $graphql_object, $args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function get_connection_args( $args = [], $post_type_object = null ) {
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\PostObjects::get_connection_args' );
		return parent::get_connection_args( $args, $post_type_object );
	}
}
