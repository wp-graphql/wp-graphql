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
	 * @param string $method The method of the request (GET, POST, etc).
	 * @param array  $bodyParams The params passed to the body of the request.
	 * @param array  $queryParams The query params passed to the request.
	 * @return OperationParams|OperationParams[]
	 */
	public function parseRequestParams( $method, array $bodyParams, array $queryParams ) {
		// Apply wp_unslash to query (GET) variables to undo wp_magic_quotes. We
		// don't need to do this for POST variables because graphql-php reads the
		// HTTP body directly.
		$parsed_body_params  = $this->parse_params( $bodyParams );
		$parsed_query_params = $this->parse_extensions( wp_unslash( $queryParams ) );
		$request_context     = [
			'method'       => $method,
			'query_params' => ! empty( $parsed_query_params ) ? $parsed_query_params : null,
			'body_params'  => ! empty( $parsed_body_params ) ? $parsed_body_params : null,
		];

		/**
		 * Allow the request data to be filtered. Previously this filter was only
		 * applied to non-HTTP requests. Since 0.2.0, we will apply it to all
		 * requests.
		 *
		 * This is a great place to hook if you are interested in implementing
		 * persisted queries (and ends up being a bit more flexible than
		 * graphql-php's built-in persistentQueryLoader).
		 *
		 * @param array  $parsed_body_params An array containing the pieces of the data of the GraphQL request.
		 * @param array  $request_context    An array containing the both body and query params.
		 * @param string $method             The method of the request (GET, POST, etc).
		 */
		$filtered_parsed_body_params = apply_filters( 'graphql_request_data', $parsed_body_params, $request_context, $method );

		/**
		 * Process multi-part requests.
		 * 
		 * This is only run if custom Upload types from plugins are not registered.
		 */
		$parsed_body_params = $this->process_multi_part_requests( $method, $filtered_parsed_body_params, $parsed_body_params );

		// In GET requests there cannot be any body params to be parsed, so it's empty.
		if ( 'GET' === $method ) {
			return parent::parseRequestParams( $method, [], $parsed_query_params );
		}

		// In POST requests the query params are ignored by default but users can
		// merge them into the body params manually using the $request_context if
		// needed.
		return parent::parseRequestParams( $method, $parsed_body_params, [] );
	}

	/**
	 * Process multi part requests.
	 *
	 * @throws RequestError Throws RequestError.
	 *
	 * @param string $method                      The method of the request (GET, POST, etc).
	 * @param array  $filtered_parsed_body_params An array containing the pieces of the data of the GraphQL request
	 * @param array  $parsed_body_params          An array containing the pieces of the data of the GraphQL request
	 * @return mixed
	 */
	private function process_multi_part_requests( string $method, array $filtered_parsed_body_params, $parsed_body_params ) {
		$contentType = wp_unslash( $_SERVER['CONTENT_TYPE'] ?? null ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Bail early.
		if ( empty( $filtered_parsed_body_params ) || 'POST' !== $method || stripos( $contentType, 'multipart/form-data' ) === false ) {
			return $filtered_parsed_body_params;
		}

		if ( empty( $parsed_body_params['map'] ) ) {
			throw new RequestError( __( 'The request must define a `map`', 'wp-graphql' ) );
		}

		$map    = $this->decode_json( $parsed_body_params['map'] );
		$result = $this->decode_json( $parsed_body_params['operations'] );

		foreach ( $map as $fileKey => $locations ) {
			$items = &$result;

			foreach ( $locations as $location ) {
				foreach ( explode( '.', $location ) as $key ) {
					if ( ! isset( $items[ $key ] ) || ! is_array( $items[ $key ] ) ) {
						$items[ $key ] = [];
					}

					$items = &$items[ $key ];
				}

				$items = $_FILES[ $fileKey ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}

		return $result;
	}

	/**
	 * Decode json.
	 * 
	 * @throws RequestError Throws RequestError.
	 *
	 * @param mixed $json Json to be decoded.
	 * @return mixed
	 */
	private function decode_json( $json ) {

		if ( ! is_string( $json ) ) {
			return $json;
		}

		$result = json_decode( stripslashes( $json ), true );

		switch ( json_last_error() ) {
			case JSON_ERROR_DEPTH:
				throw new RequestError( __( 'Maximum stack depth exceeded.', 'wp-graphql' ) );

			case JSON_ERROR_STATE_MISMATCH:
				throw new RequestError( __( 'Underflow or the modes mismatch.', 'wp-graphql' ) );

			case JSON_ERROR_CTRL_CHAR:
				throw new RequestError( __( 'Unexpected control character found.', 'wp-graphql' ) );

			case JSON_ERROR_SYNTAX:
				throw new RequestError( __( 'Syntax error, malformed JSON.', 'wp-graphql' ) );

			case JSON_ERROR_UTF8:
				throw new RequestError( __( 'Malformed UTF-8 characters, possibly incorrectly encoded.', 'wp-graphql' ) );

			case JSON_ERROR_NONE:
			default:
				return $result;
		}
	}

	/**
	 * Parse parameters and proxy to parse_extensions.
	 *
	 * @param array $params Request parameters.
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
	 * @param array $params Request parameters.
	 * @return array
	 */
	private function parse_extensions( $params ) {
		if ( isset( $params['extensions'] ) && is_string( $params['extensions'] ) ) {
			$tmp = json_decode( $params['extensions'], true );
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
