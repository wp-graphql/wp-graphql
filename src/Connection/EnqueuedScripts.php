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
			'fromType' => 'ContentNode',
			'toType' => 'EnqueuedScript',
			'fromFieldName' => 'enqueuedScripts',
			'resolve' => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			}
		]);

		register_graphql_connection([
			'fromType' => 'TermNode',
			'toType' => 'EnqueuedScript',
			'fromFieldName' => 'enqueuedScripts',
			'resolve' => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			}
		]);

		register_graphql_connection([
			'fromType' => 'User',
			'toType' => 'EnqueuedScript',
			'fromFieldName' => 'enqueuedScripts',
			'resolve' => function( $source, $args, $context, $info ) {
				$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );
				return $resolver->get_connection();
			}
		]);

	}
}
