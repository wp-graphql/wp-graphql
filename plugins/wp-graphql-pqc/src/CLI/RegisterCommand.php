<?php
/**
 * WP-CLI: register persisted queries (PQC index + document) without manual curl/POST.
 *
 * @package WPGraphQL\PQC\CLI
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\CLI;

use GraphQLRelay\Relay;
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
	 * Register many variable instances of one document (benchmark / long-tail URLs).
	 *
	 * Either supply `--variables-jsonl` (one JSON object per line) or scan published
	 * posts with `--relay-type` + `--id-variable` (default: post IDs as `id`).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the GraphQL query file.
	 *
	 * [--variables-jsonl=<path>]
	 * : One JSON object per line; each line becomes GraphQL variables for the same document.
	 *
	 * [--post-type=<type>]
	 * : With post scan: `post_type` for WP_Query (default: post).
	 *
	 * [--post-status=<status>]
	 * : With post scan: `post_status` (default: publish).
	 *
	 * [--limit=<n>]
	 * : With post scan: max posts (default: 100).
	 *
	 * [--offset=<n>]
	 * : With post scan: skip first N posts (default: 0).
	 *
	 * [--relay-type=<name>]
	 * : Relay type passed to `Relay::toGlobalId()` (default: post; use `page` for pages).
	 *
	 * [--id-variable=<name>]
	 * : GraphQL variable name for the global ID (default: id).
	 *
	 * [--urls-out=<path>]
	 * : Write persisted paths, one per line (paths only, no host).
	 *
	 * [--manifest-out=<path>]
	 * : Write JSON manifest with N_variable_instances and metadata for k6 runs.
	 *
	 * [--manifest-template=<path>]
	 * : Optional JSON merged into manifest-out (your knobs: s_maxage_s, steady_vus, etc.).
	 *
	 * [--edge-base=<url>]
	 * : Same as `register`; optional echo only (not written to urls-out).
	 *
	 * [--dry-run]
	 * : Print counts only; no graphql() calls.
	 *
	 * [--verbose]
	 * : Log each successful path.
	 *
	 * [--user=<id>]
	 * : Run as this user ID (optional).
	 *
	 * ## EXAMPLES
	 *
	 *     wp graphql-pqc bulk-register benchmark/k6/single-post.graphql --limit=200 --urls-out=/tmp/urls.txt --manifest-out=/tmp/manifest.json
	 *     wp graphql-pqc bulk-register q.graphql --variables-jsonl=vars.jsonl --urls-out=urls.txt
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function bulk_register( array $args, array $assoc_args ): void {
		$app = App::instance();
		if ( ! $app->can_load_plugin() ) {
			\WP_CLI::error( 'WPGraphQL Persisted Query Cache requires WPGraphQL and WPGraphQL Smart Cache to be active.' );
		}

		if ( ! function_exists( 'graphql' ) ) {
			\WP_CLI::error( 'WPGraphQL is not available (graphql() missing).' );
		}

		if ( ! class_exists( Relay::class ) ) {
			\WP_CLI::error( 'GraphQLRelay\\Relay is not available.' );
		}

		$query_path = $args[0] ?? '';
		if ( '' === $query_path || ! is_readable( $query_path ) ) {
			\WP_CLI::error( 'Provide a readable query file as the first argument.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI local path.
		$query = file_get_contents( $query_path );
		if ( false === $query || '' === trim( (string) $query ) ) {
			\WP_CLI::error( 'Query file is empty or unreadable.' );
		}

		$dry_run = isset( $assoc_args['dry-run'] );

		$variables_sets = $this->bulk_resolve_variable_sets( $assoc_args );
		if ( empty( $variables_sets ) ) {
			\WP_CLI::error( 'No variable sets: use --variables-jsonl or post scan options (--limit, --post-type).' );
		}

		$urls_out     = isset( $assoc_args['urls-out'] ) && is_string( $assoc_args['urls-out'] ) ? $assoc_args['urls-out'] : '';
		$manifest_out = isset( $assoc_args['manifest-out'] ) && is_string( $assoc_args['manifest-out'] ) ? $assoc_args['manifest-out'] : '';
		$manifest_tpl = isset( $assoc_args['manifest-template'] ) && is_string( $assoc_args['manifest-template'] ) ? $assoc_args['manifest-template'] : '';

		if ( ! $dry_run && '' !== $urls_out ) {
			$dir = dirname( $urls_out );
			if ( ! is_dir( $dir ) ) {
				\WP_CLI::error( sprintf( 'Directory for urls-out does not exist: %s', $dir ) );
			}
		}

		$previous_user_id = get_current_user_id();
		if ( isset( $assoc_args['user'] ) ) {
			$user_id = (int) $assoc_args['user'];
			if ( $user_id > 0 ) {
				wp_set_current_user( $user_id );
			}
		}

		$verbose = isset( $assoc_args['verbose'] );

		try {
			$paths    = [];
			$failures = 0;
			$total    = count( $variables_sets );

			$notify = \WP_CLI\Utils\make_progress_bar( 'Registering', $total );

			foreach ( $variables_sets as $variables ) {
				if ( $dry_run ) {
					$notify->tick();
					continue;
				}

				$result = $this->register_and_get_path( trim( $query ), $variables );
				if ( $result['path'] ) {
					$paths[] = $result['path'];
					if ( $verbose ) {
						\WP_CLI::line( $result['path'] );
					}
				} else {
					++$failures;
					if ( $verbose && ! empty( $result['message'] ) ) {
						\WP_CLI::warning( $result['message'] );
					}
				}
				$notify->tick();
			}

			$notify->finish();

			if ( $dry_run ) {
				\WP_CLI::success( sprintf( 'Dry run: would register %d variable instance(s).', $total ) );

				return;
			}

			if ( '' !== $urls_out && ! empty( $paths ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI output path.
				if ( false === file_put_contents( $urls_out, implode( "\n", $paths ) . "\n" ) ) {
					\WP_CLI::error( sprintf( 'Could not write urls-out: %s', $urls_out ) );
				}
				\WP_CLI::line( sprintf( 'Wrote %d path(s) to %s', count( $paths ), $urls_out ) );
			}

			if ( '' !== $manifest_out ) {
				$manifest = [
					'generated_at'         => gmdate( 'c' ),
					'N_templates'          => 1,
					'N_variable_instances' => count( $paths ),
					'registered_ok'        => count( $paths ),
					'registered_failures'  => $failures,
					'query_file'           => $query_path,
					'urls_file'            => '' !== $urls_out ? basename( $urls_out ) : null,
					'urls_out_absolute'    => '' !== $urls_out ? $urls_out : null,
				];
				if ( '' !== $manifest_tpl && is_readable( $manifest_tpl ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI local path.
					$tpl_json = file_get_contents( $manifest_tpl );
					$tpl      = is_string( $tpl_json ) ? json_decode( $tpl_json, true ) : null;
					if ( is_array( $tpl ) ) {
						$manifest = array_merge( $tpl, $manifest );
					}
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI output path.
				if ( false === file_put_contents( $manifest_out, wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ) ) {
					\WP_CLI::error( sprintf( 'Could not write manifest-out: %s', $manifest_out ) );
				}
				\WP_CLI::line( sprintf( 'Wrote manifest: %s', $manifest_out ) );
			}

			if ( $failures > 0 ) {
				\WP_CLI::warning( sprintf( '%d registration(s) did not return persistedQueryUrl.', $failures ) );
			}

			\WP_CLI::success( sprintf( 'Registered %d / %d persisted path(s).', count( $paths ), $total ) );

			if ( isset( $assoc_args['edge-base'] ) && is_string( $assoc_args['edge-base'] ) && '' !== $assoc_args['edge-base'] && ! empty( $paths ) ) {
				$base = rtrim( $assoc_args['edge-base'], '/' );
				\WP_CLI::line( sprintf( 'Sample edge URL: %s%s', $base, $paths[0] ) );
			}
		} finally {
			wp_set_current_user( $previous_user_id );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return array<int, array<string, mixed>>
	 */
	private function bulk_resolve_variable_sets( array $assoc_args ): array {
		if ( isset( $assoc_args['variables-jsonl'] ) && is_string( $assoc_args['variables-jsonl'] ) && '' !== $assoc_args['variables-jsonl'] ) {
			$path = $assoc_args['variables-jsonl'];
			if ( ! is_readable( $path ) ) {
				\WP_CLI::error( sprintf( 'Cannot read variables-jsonl: %s', $path ) );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI local path.
			$raw  = file_get_contents( $path );
			$sets = [];
			if ( false === $raw ) {
				return [];
			}
			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			if ( ! is_array( $lines ) ) {
				return [];
			}
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || ( isset( $line[0] ) && '#' === $line[0] ) ) {
					continue;
				}
				$decoded = json_decode( $line, true );
				if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
					\WP_CLI::error( sprintf( 'Invalid JSON line in variables-jsonl: %s', $line ) );
				}
				$sets[] = $decoded;
			}

			return $sets;
		}

		$post_type   = isset( $assoc_args['post-type'] ) && is_string( $assoc_args['post-type'] ) ? $assoc_args['post-type'] : 'post';
		$post_status = isset( $assoc_args['post-status'] ) && is_string( $assoc_args['post-status'] ) ? $assoc_args['post-status'] : 'publish';
		$limit       = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;
		$offset      = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$relay_type  = isset( $assoc_args['relay-type'] ) && is_string( $assoc_args['relay-type'] ) ? $assoc_args['relay-type'] : 'post';
		$id_var      = isset( $assoc_args['id-variable'] ) && is_string( $assoc_args['id-variable'] ) ? $assoc_args['id-variable'] : 'id';

		if ( $limit < 1 ) {
			$limit = 100;
		}

		$posts_query = new \WP_Query(
			[
				'post_type'              => $post_type,
				'post_status'            => $post_status,
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$sets = [];
		foreach ( $posts_query->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id < 1 ) {
				continue;
			}
			$gid    = Relay::toGlobalId( $relay_type, (string) $post_id );
			$sets[] = [ $id_var => $gid ];
		}

		return $sets;
	}

	/**
	 * Run one registration and return persisted path or failure detail.
	 *
	 * @param string               $query     GraphQL document.
	 * @param array<string, mixed> $variables Variables.
	 * @return array{ path: ?string, message: string }
	 */
	private function register_and_get_path( string $query, array $variables ): array {
		$normalized = Hasher::normalize_query_document( $query );
		if ( null === $normalized ) {
			return [
				'path'    => null,
				'message' => 'Query could not be parsed.',
			];
		}

		$query_hash       = hash( 'sha256', $normalized );
		$variables_hash   = Hasher::hash_variables( ! empty( $variables ) ? $variables : null );
		$nonce            = Nonce::generate( $query_hash, $variables_hash );
		$vars_hash_string = $variables_hash ? $variables_hash : '';

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
					'persistedVariablesHash' => $vars_hash_string,
				],
			]
		);

		if ( ! is_array( $response ) ) {
			return [
				'path'    => null,
				'message' => 'Unexpected GraphQL response type.',
			];
		}

		if ( ! empty( $response['errors'] ) ) {
			return [
				'path'    => null,
				'message' => wp_json_encode( $response['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			];
		}

		$url = null;
		if ( isset( $response['extensions'] ) && is_array( $response['extensions'] ) && isset( $response['extensions']['persistedQueryUrl'] ) ) {
			$url = $response['extensions']['persistedQueryUrl'];
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return [
				'path'    => null,
				'message' => 'No persistedQueryUrl in response.',
			];
		}

		return [
			'path'    => $url,
			'message' => '',
		];
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

		$result = $this->register_and_get_path( $query, $variables );

		if ( null === $result['path'] ) {
			if ( '' !== $result['message'] && 'No persistedQueryUrl in response.' !== $result['message'] ) {
				\WP_CLI::error( $result['message'] );
			}
			\WP_CLI::warning( 'Query ran but no persistedQueryUrl was returned (empty analyzer keys, grant_mode, or nonce rules may have skipped indexing).' );
			\WP_CLI::line( 'query_hash: ' . $query_hash );
			if ( $variables_hash ) {
				\WP_CLI::line( 'variables_hash: ' . $variables_hash );
			}

			return;
		}

		$url = $result['path'];

		\WP_CLI::success( $url );

		if ( isset( $assoc_args['edge-base'] ) && is_string( $assoc_args['edge-base'] ) && '' !== $assoc_args['edge-base'] ) {
			$base = rtrim( $assoc_args['edge-base'], '/' );
			\WP_CLI::line( sprintf( 'edge: %s%s', $base, $url ) );
		}

		\WP_CLI::line( sprintf( 'curl -sI "http://localhost:8081%s" | grep -i x-cache', $url ) );
	}
}
