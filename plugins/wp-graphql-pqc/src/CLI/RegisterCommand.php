<?php
/**
 * WP-CLI: register persisted queries (PQC index + document) without manual curl/POST.
 *
 * @package WPGraphQL\PQC\CLI
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\CLI;

use WPGraphQL\PQC\App;
use WPGraphQL\PQC\Utils\Hasher;
use WPGraphQL\PQC\Utils\Nonce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RegisterCommand
 *
 * @package WPGraphQL\PQC\CLI
 */
class RegisterCommand extends \WP_CLI_Command {

	/**
	 * Execute a GraphQL query and persist it for warm GET (same rules as HTTP POST + nonce).
	 *
	 * Generates a one-time nonce, runs the query through `graphql()` with PQC extensions, and
	 * prints `persistedQueryUrl` on success. Requires the query analyzer to produce cache keys.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Path to a file containing the GraphQL query. Use `-` to read from STDIN.
	 *
	 * [--query=<query>]
	 * : Query string (alternative to `<file>`). Use carefully with shell quoting.
	 *
	 * [--variables=<json>]
	 * : JSON object of GraphQL variables (optional).
	 *
	 * [--variables-file=<path>]
	 * : Path to a JSON file of variables (optional).
	 *
	 * [--user=<id>]
	 * : Run as this user ID (optional). Useful if resolver data depends on capabilities.
	 *
	 * [--edge-base=<url>]
	 * : If set, also print a full URL for edge tests, e.g. `http://localhost:8081` + persisted path.
	 *
	 * ## EXAMPLES
	 *
	 *     wp graphql-pqc register query.graphql
	 *     wp graphql-pqc register --query='query { posts { nodes { id } } }'
	 *     wp graphql-pqc register single-post.graphql --variables='{"id":"cG9zdDox"}'
	 *     wp graphql-pqc register query.graphql --edge-base=http://localhost:8081
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function register( array $args, array $assoc_args ): void {
		$app = App::instance();
		if ( ! $app->can_load_plugin() ) {
			\WP_CLI::error( 'WPGraphQL Persisted Query Cache requires WPGraphQL and WPGraphQL Smart Cache to be active.' );
		}

		if ( ! function_exists( 'graphql' ) ) {
			\WP_CLI::error( 'WPGraphQL is not available (graphql() missing).' );
		}

		$query = $this->resolve_query_string( $args, $assoc_args );
		if ( null === $query ) {
			\WP_CLI::error( 'Provide a query via <file>, --query=..., or pipe to STDIN with `-`.' );
		}

		$query = trim( $query );
		if ( '' === $query ) {
			\WP_CLI::error( 'Query is empty.' );
		}

		$variables = $this->resolve_variables( $assoc_args );

		$previous_user_id = get_current_user_id();
		if ( isset( $assoc_args['user'] ) ) {
			$user_id = (int) $assoc_args['user'];
			if ( $user_id > 0 ) {
				wp_set_current_user( $user_id );
			}
		}

		try {
			$this->run_registration( $query, $variables, $assoc_args );
		} finally {
			wp_set_current_user( $previous_user_id );
		}
	}

	/**
	 * @param array<int, string>   $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return string|null
	 */
	private function resolve_query_string( array $args, array $assoc_args ): ?string {
		if ( isset( $assoc_args['query'] ) && is_string( $assoc_args['query'] ) && '' !== $assoc_args['query'] ) {
			return $assoc_args['query'];
		}

		$path = $args[0] ?? null;
		if ( null === $path || '' === $path ) {
			return null;
		}

		if ( '-' === $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- STDIN is intentional.
			$stdin = file_get_contents( 'php://stdin' );

			return false !== $stdin ? $stdin : null;
		}

		if ( ! is_readable( $path ) ) {
			\WP_CLI::error( sprintf( 'Cannot read query file: %s', $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local CLI file path.
		$contents = file_get_contents( $path );

		return false !== $contents ? $contents : null;
	}

	/**
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return array<string, mixed>
	 */
	private function resolve_variables( array $assoc_args ): array {
		if ( isset( $assoc_args['variables-file'] ) && is_string( $assoc_args['variables-file'] ) ) {
			$path = $assoc_args['variables-file'];
			if ( ! is_readable( $path ) ) {
				\WP_CLI::error( sprintf( 'Cannot read variables file: %s', $path ) );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local CLI file path.
			$json = file_get_contents( $path );
			if ( false === $json ) {
				\WP_CLI::error( 'Failed to read variables file.' );
			}
			$decoded = json_decode( $json, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				\WP_CLI::error( 'Variables file must contain a JSON object.' );
			}

			return $decoded;
		}

		if ( isset( $assoc_args['variables'] ) && is_string( $assoc_args['variables'] ) && '' !== $assoc_args['variables'] ) {
			$decoded = json_decode( $assoc_args['variables'], true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				\WP_CLI::error( '--variables must be a JSON object, e.g. {"id":"cG9zdDox"}' );
			}

			return $decoded;
		}

		return [];
	}

	/**
	 * @param string               $query      GraphQL document.
	 * @param array<string, mixed> $variables  Variables.
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	private function run_registration( string $query, array $variables, array $assoc_args ): void {
		$normalized = Hasher::normalize_query_document( $query );
		if ( null === $normalized ) {
			\WP_CLI::error( 'Query could not be parsed as valid GraphQL.' );
		}

		$query_hash     = hash( 'sha256', $normalized );
		$variables_hash = Hasher::hash_variables( ! empty( $variables ) ? $variables : null );
		$nonce          = Nonce::generate( $query_hash, $variables_hash );

		if ( ! defined( 'WPGRAPHQL_PQC_INTERNAL_CALL' ) ) {
			define( 'WPGRAPHQL_PQC_INTERNAL_CALL', true );
		}

		$response = graphql(
			[
				'query'      => $query,
				'variables'  => $variables,
				'extensions' => [
					'persistedQueryNonce'    => $nonce,
					'persistedQueryHash'     => $query_hash,
					'persistedVariablesHash' => $variables_hash ? $variables_hash : '',
				],
			]
		);

		if ( ! is_array( $response ) ) {
			\WP_CLI::error( 'Unexpected GraphQL response type.' );
		}

		if ( ! empty( $response['errors'] ) ) {
			\WP_CLI::error( wp_json_encode( $response['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}

		$url = null;
		if ( isset( $response['extensions'] ) && is_array( $response['extensions'] ) && isset( $response['extensions']['persistedQueryUrl'] ) ) {
			$url = $response['extensions']['persistedQueryUrl'];
		}

		if ( ! is_string( $url ) || '' === $url ) {
			\WP_CLI::warning( 'Query ran but no persistedQueryUrl was returned (empty analyzer keys, grant_mode, or nonce rules may have skipped indexing).' );
			\WP_CLI::line( 'query_hash: ' . $query_hash );
			if ( $variables_hash ) {
				\WP_CLI::line( 'variables_hash: ' . $variables_hash );
			}

			return;
		}

		\WP_CLI::success( $url );

		if ( isset( $assoc_args['edge-base'] ) && is_string( $assoc_args['edge-base'] ) && '' !== $assoc_args['edge-base'] ) {
			$base = rtrim( $assoc_args['edge-base'], '/' );
			\WP_CLI::line( sprintf( 'edge: %s%s', $base, $url ) );
		}

		\WP_CLI::line( sprintf( 'curl -sI "http://localhost:8081%s" | grep -i x-cache', $url ) );
	}
}
