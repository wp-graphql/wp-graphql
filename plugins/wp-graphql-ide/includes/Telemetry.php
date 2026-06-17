<?php
/**
 * Plugin telemetry — Appsero client init plus a mirror filter that
 * forwards this plugin's anonymized telemetry to wpgraphql.com.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Wires Appsero plus the pre_http_request mirror.
 */
class Telemetry {

	// phpcs:ignore SlevomatCodingStandard.TypeHints.ClassConstantTypeHint.MissingNativeTypeHint -- Typed class constants require PHP 8.3+; this plugin's floor is 7.4.
	private const APPSERO_HASH = 'e90103d6-2c09-4152-96e0-eb7d0d3b5c74';

	/**
	 * Boot the Appsero client and register the mirror filter.
	 */
	public static function init(): void {
		add_filter( 'pre_http_request', [ self::class, 'mirror_request' ], 10, 3 );
		self::init_appsero();
	}

	/**
	 * Initialize the Appsero client.
	 */
	private static function init_appsero(): void {
		if ( ! class_exists( 'Appsero\Client' ) || defined( 'PHPSTAN' ) ) {
			return;
		}

		try {
			$client = new \Appsero\Client( self::APPSERO_HASH, 'WPGraphQL IDE', WPGRAPHQL_IDE_PLUGIN_FILE );

			/**
			 * @var \Appsero\Insights $insights
			 *
			 * @phpstan-ignore varTag.type (The doctype for Appsero\Client::insights() is wrong.)
			 */
			$insights = $client->insights();

			if ( method_exists( $insights, 'add_plugin_data' ) ) {
				$insights->add_plugin_data();
			}

			$insights->init();
		} catch ( \Throwable $e ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is intentional here.
				sprintf(
					// translators: %s is the error message
					__( 'Error initializing Appsero: %s', 'wpgraphql-ide' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Mirror Appsero requests to wpgraphql.com's telemetry server.
	 *
	 * Scoped to this plugin's hash so we don't mirror other Appsero
	 * plugins' payloads on the same site. Lets the real Appsero request
	 * proceed unchanged.
	 *
	 * @param bool|\WP_Error       $preempt Whether to preempt the request.
	 * @param array<string, mixed> $args    The arguments for the request.
	 * @param string               $url     The URL for the request.
	 * @return bool|\WP_Error Whether to preempt the request.
	 */
	public static function mirror_request( $preempt, $args, $url ) {
		if ( strpos( $url, 'api.appsero.com' ) === false ) {
			return $preempt;
		}

		// Scope: only mirror this plugin's payloads, not other Appsero plugins on the site.
		$body = is_array( $args['body'] ?? null ) ? $args['body'] : [];
		if ( ( $body['hash'] ?? null ) !== self::APPSERO_HASH ) {
			return $preempt;
		}

		$mirror = str_replace(
			'https://api.appsero.com/',
			'https://telemetry.wpgraphql.com/api/appsero/',
			$url
		);

		wp_remote_post(
			$mirror,
			array_merge(
				$args,
				[
					'blocking' => false,
					'timeout'  => 3,
				]
			)
		);

		return $preempt; // Let the real Appsero request proceed.
	}
}
