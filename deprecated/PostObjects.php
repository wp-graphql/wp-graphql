<?php

namespace WPGraphQL\Connection;

/**
 * @todo Remove in 3.0.0
 * @deprecated 1.13.0
 * @codeCoverageIgnore
 */
class PostObjects extends \WPGraphQL\Type\Connection\PostObjects {
	/**
	 * Message that this class will be removed in 3.0.0.
	 *
	 * @param string $function_name
	 */
	private static function doing_it_wrong( string $function_name ): void {
		_doing_it_wrong(
			__METHOD__,
			sprintf( /* translators: %s is the current class name, %s is the new class name */
				esc_html__( 'The %1$s class is deprecated and will be removed in the next major version of WPGraphQL. Use %2$s instead.', 'wp-graphql' ),
				esc_html( self::class ),
				esc_html( \WPGraphQL\Type\Connection\PostObjects::class )
			),
			'1.13.0'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function register_connections() {
		self::doing_it_wrong( __METHOD__ );
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\PostObjects::register_connections' );
		parent::register_connections();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function get_connection_config( $graphql_object, $args = [] ) {
		self::doing_it_wrong( __METHOD__ );
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\PostObjects::get_connection_config' );
		return parent::get_connection_config( $graphql_object, $args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated 1.13.0
	 */
	public static function get_connection_args( $args = [], $post_type_object = null ) {
		self::doing_it_wrong( __METHOD__ );
		_deprecated_function( __METHOD__, '1.13.0', '\WPGraphQL\Type\Connection\PostObjects::get_connection_args' );
		return parent::get_connection_args( $args, $post_type_object );
	}
}
