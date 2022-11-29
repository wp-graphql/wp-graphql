<?php

namespace WPGraphQL\Connection;

/**
 * Deprecated class for backwards compatibility.
 */
class Taxonomies extends \WPGraphQL\Type\Connection\Taxonomies {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated @todo
	 */
	public static function register_connections() {
		_deprecated_function( __METHOD__, '@todo', '\WPGraphQL\Type\Connection\Taxonomies::register_connections' );
		parent::register_connections();
	}
}
