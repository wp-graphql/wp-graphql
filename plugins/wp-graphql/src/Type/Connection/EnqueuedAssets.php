<?php
namespace WPGraphQL\Type\Connection;

/**
 * Connection arguments shared by enqueued asset connections.
 */
class EnqueuedAssets {

	/**
	 * Returns the connection arguments for enqueued assets.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_connection_args(): array {
		return [
			'handles' => [
				'type'        => [ 'list_of' => 'String' ],
				'description' => static function () {
					return __( 'Limit results to assets with one of the specified handles.', 'wp-graphql' );
				},
			],
		];
	}
}
