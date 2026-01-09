<?php

namespace WPGraphQL\Connection;

/**
 * @todo Remove in 3.0.0
 * @deprecated 1.13.0
 * @codeCoverageIgnore
 */
class Taxonomies extends \WPGraphQL\Type\Connection\Taxonomies {
	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function register_connections() {
		_doing_it_wrong(
			__METHOD__,
			sprintf( /* translators: %s is the current class name, %s is the new class name */
				esc_html__( 'The %1$s class is deprecated and will be removed in the next major version of WPGraphQL. Use %2$s instead.', 'wp-graphql' ),
				esc_html( self::class ),
				esc_html( \WPGraphQL\Type\Connection\Taxonomies::class )
			),
			'1.13.0'
		);
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\Taxonomies::register_connections' );
		parent::register_connections();
	}
}
