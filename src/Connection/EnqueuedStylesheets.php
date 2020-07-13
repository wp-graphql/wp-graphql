<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;

/**
 * Class EnqueuedStylesheets
 *
 * @package WPGraphQL\Connection
 */
class EnqueuedStylesheets {

	/**
	 * Register connections to Enqueued Assets
	 */
	public static function register_connections() {

		register_graphql_connection([
			'fromType'      => 'ContentNode',
			'toType'        => 'EnqueuedStylesheet',
			'fromFieldName' => 'enqueuedStylesheets',
			'resolve'       => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

		register_graphql_connection([
			'fromType'      => 'TermNode',
			'toType'        => 'EnqueuedStylesheet',
			'fromFieldName' => 'enqueuedStylesheets',
			'resolve'       => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

		register_graphql_connection([
			'fromType'      => 'RootQuery',
			'toType'        => 'EnqueuedStylesheet',
			'fromFieldName' => 'registeredStylesheets',
			'resolve'       => function( $source, $args, $context, $info ) {

				// The connection resolver expects the source to include
				// enqueuedStylesheetsQueue
				$source                           = new \stdClass();
				$source->enqueuedStylesheetsQueue = [];
				global $wp_styles;
				do_action( 'wp_enqueue_scripts' );
				$source->enqueuedStylesheetsQueue = array_keys( $wp_styles->registered );
				$resolver                         = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

		register_graphql_connection([
			'fromType'      => 'User',
			'toType'        => 'EnqueuedStylesheet',
			'fromFieldName' => 'enqueuedStylesheets',
			'resolve'       => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

	}
}
