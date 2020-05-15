<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;

/**
 * Class EnqueuedScripts
 *
 * @package WPGraphQL\Connection
 */
class EnqueuedScripts {

	/**
	 * Register connections to Enqueued Assets
	 */
	public static function register_connections() {

		register_graphql_connection([
			'fromType'      => 'ContentNode',
			'toType'        => 'EnqueuedScript',
			'fromFieldName' => 'enqueuedScripts',
			'resolve'       => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

		register_graphql_connection([
			'fromType'      => 'TermNode',
			'toType'        => 'EnqueuedScript',
			'fromFieldName' => 'enqueuedScripts',
			'resolve'       => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

		register_graphql_connection([
			'fromType'      => 'RootQuery',
			'toType'        => 'EnqueuedScript',
			'fromFieldName' => 'registeredScripts',
			'resolve'       => function( $source, $args, $context, $info ) {

				// The connection resolver expects the source to include
				// enqueuedScriptsQueue
				$source                       = new \stdClass();
				$source->enqueuedScriptsQueue = [];
				global $wp_scripts;
				do_action( 'wp_enqueue_scripts' );
				$source->enqueuedScriptsQueue = array_keys( $wp_scripts->registered );
				$resolver                     = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

		register_graphql_connection([
			'fromType'      => 'User',
			'toType'        => 'EnqueuedScript',
			'fromFieldName' => 'enqueuedScripts',
			'resolve'       => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			},
		]);

	}
}
