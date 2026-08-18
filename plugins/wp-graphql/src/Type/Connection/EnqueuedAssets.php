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
			'handlesIn' => [
				'type'        => [ 'list_of' => 'String' ],
				'description' => static function () {
					return __( 'Limit results to assets whose handle is in the provided list. Handles that do not match an asset are ignored. An empty list matches no assets, while omitting the argument (or passing null) leaves the connection unfiltered.', 'wp-graphql' );
				},
			],
		];
	}
}
