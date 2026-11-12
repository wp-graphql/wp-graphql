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

			// Set the opt-in notice text just before Appsero renders it (priority 10), since translations can't load this early.
			add_action(
				'admin_notices',
				static function () use ( $insights ): void {
					$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=graphql-settings#graphql_ide_settings' ) ) . '">' . esc_html__( 'GraphQL → Settings → IDE Settings', 'wpgraphql-ide' ) . '</a>';

					$insights->notice(
						sprintf(
							// translators: %s is a link to the IDE settings tab.
							__( 'Want to help make <strong>WPGraphQL IDE</strong> even more awesome? Allow WPGraphQL IDE to collect diagnostic data and usage information, including this site\'s admin email address and an administrator\'s name. This doesn\'t sign anyone up for emails. You can stop sharing at any time under %s.', 'wpgraphql-ide' ),
							$settings_link
						)
					);
				},
				9
			);

			$insights->init();

			// The settings toggle and the deactivation gate live in WPGraphQL core (loaded after this
			// plugin). Gate locally when core's UsageTracking lacks gate_deactivation_feedback() so an
			// older WPGraphQL (class present, method missing) cannot fatal the site.
			add_action(
				'plugins_loaded',
				static function () use ( $insights, $client ): void {
					$gated_by_core = false;

					if ( class_exists( '\WPGraphQL\Admin\UsageTracking' ) ) {
						$tracker = new \WPGraphQL\Admin\UsageTracking( $insights, $client->slug, 'graphql_ide_settings', 'WPGraphQL IDE' );
						$tracker->init();

						if ( method_exists( $tracker, 'gate_deactivation_feedback' ) ) {
							$gated_by_core = true;
						}
					}

					if ( ! $gated_by_core ) {
						self::gate_deactivation_feedback( $insights, $client->slug );
					}
				}
			);
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
	 * Only show Appsero's deactivation feedback form (and only accept its AJAX submit) when
	 * this plugin's `{slug}_allow_tracking` option is opted in.
	 *
	 * @param \Appsero\Insights $insights The initialized Appsero insights instance.
	 * @param string            $slug     The Appsero client slug.
	 */
	public static function gate_deactivation_feedback( $insights, string $slug ): void {
		remove_action( 'admin_footer', [ $insights, 'deactivate_scripts' ] );
		add_action(
			'admin_footer',
			static function () use ( $insights ): void {
				if ( $insights->tracking_allowed() ) {
					$insights->deactivate_scripts();
				}
			}
		);

		$ajax_hook = 'wp_ajax_' . $slug . '_submit-uninstall-reason';
		remove_action( $ajax_hook, [ $insights, 'uninstall_reason_submission' ] );
		add_action(
			$ajax_hook,
			static function () use ( $insights ): void {
				if ( ! $insights->tracking_allowed() ) {
					wp_send_json_error( 'Usage tracking is not enabled for this site.' );
				}

				$insights->uninstall_reason_submission();
			}
		);
	}

	/**
	 * Mirror Appsero requests to wpgraphql.com's telemetry server.
	 *
	 * Scoped to this plugin's hash so we don't mirror other Appsero
	 * plugins' payloads on the same site. Lets the real Appsero request
	 * proceed unchanged.
	 *
	 * @param bool|\WP_Error       $preempt Whether to preempt the request.
	 * @param array<string, mixed> $args    The request arguments.
	 * @param string               $url     The request URL.
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

		// The copy sent to the WPGraphQL team leaves out the site's admin email address and an
		// administrator's name. Usage data doesn't need them, and nothing on telemetry.wpgraphql.com
		// should treat them as contact details. Appsero's own request is sent unchanged.
		unset( $body['admin_email'], $body['first_name'], $body['last_name'] );

		wp_remote_post(
			$mirror,
			array_merge(
				$args,
				[
					'body'     => $body,
					'blocking' => false,
					'timeout'  => 3,
				]
			)
		);

		return $preempt; // Let the real Appsero request proceed.
	}
}
