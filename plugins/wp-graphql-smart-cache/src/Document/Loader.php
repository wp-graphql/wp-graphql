<?php
/**
 * Storage
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache\Document;

use WPGraphQL\SmartCache\Document;
use GraphQL\Server\RequestError;

class Loader {
	/**
	 * When a queryId is found on the request, this call back is invoked to look up the query
	 * string
	 *
	 * @param string $query_id The persisted query ID
	 * @param array $operation_params The operation parameters
	 *
	 * @return string|\GraphQL\Language\AST\DocumentNode
	 */
	public static function by_query_id( string $query_id, array $operation_params ) {
		$content = new Document();
		$query   = $content->get( $query_id );

		if ( ! isset( $query ) ) {
			// Translators: The placeholder is the persisted query id hash
			throw new RequestError( __( 'PersistedQueryNotFound', 'wp-graphql-smart-cache' ) );
		}

		return $query;
	}
}
