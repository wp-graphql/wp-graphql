<?php

namespace WPGraphQL\Server;

use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;

/**
 * Extends GraphQL\Server\Helper to apply filters and parse query extensions.
 *
 * @package WPGraphQL\Server
 */
class WPHelper extends Helper {
	/**
	 * Parses normalized request params and returns instance of OperationParams
	 * or array of OperationParams in case of batch operation.
	 *
	 * @param string $method
	 * @param array  $bodyParams
	 * @param array  $queryParams
	 * @return OperationParams|OperationParams[]
	 * @throws RequestError
	 */
	public function parseRequestParams( $method, array $bodyParams, array $queryParams ) {
		$parsed_body_params = $this->parse_params( $bodyParams );
		$parsed_query_params = $this->parse_extensions( $queryParams );

		/**
		 * Allow the request data to be filtered. Previously this filter was only
		 * applied to non-HTTP requests. Since 0.2.0, we will apply it to all
		 * requests.
		 *
		 * This is a great place to hook if you are interested in implementing
		 * persisted queries (and ends up being a bit more flexible than
		 * graphql-php's built-in persistentQueryLoader).
		 *
		 * @param array $data An array containing the pieces of the data of the GraphQL request
		 */
		$parsed_body_params = apply_filters( 'graphql_request_data', $parsed_body_params );
		if ( ! empty( $parsed_query_params ) ) {
			$parsed_query_params = apply_filters( 'graphql_request_data', $parsed_query_params );
		}

		return parent::parseRequestParams( $method, $parsed_body_params, $parsed_query_params );
	}

	/**
	 * Parse parameters and proxy to parse_extensions.
	 *
	 * @param  array $params Request parameters.
	 * @return array
	 */
	private function parse_params( $params ) {
		if ( isset( $params[0] ) ) {
			return array_map( [ $this, 'parse_extensions' ], $params );
		}

		return $this->parse_extensions( $params );
	}

	/**
	 * Parse query extensions.
	 *
	 * @param  array $params Request parameters.
	 * @return array
	 */
	private function parse_extensions( $params ) {
		if ( isset( $params['extensions'] ) && is_string( $params['extensions'] ) ) {
			$tmp = json_decode( stripslashes( $params['extensions'] ), true );
			if ( ! json_last_error() ) {
				$params['extensions'] = $tmp;
			}
		}

		// Apollo server/client compatibility: look for the query id in extensions
		if ( isset( $params['extensions']['persistedQuery']['sha256Hash'] ) && ! isset( $params['queryId'] ) ) {
			$params['queryId'] = $params['extensions']['persistedQuery']['sha256Hash'];
			unset( $params['extensions']['persistedQuery'] );
		}

		return $params;
	}
}
