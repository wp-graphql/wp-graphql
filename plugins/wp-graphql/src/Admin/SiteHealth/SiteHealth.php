<?php

namespace WPGraphQL\Admin\SiteHealth;

/**
 * Class SiteHealth
 *
 * Adds WPGraphQL recommendations to Tools > Site Health on production sites.
 *
 * Whether these settings are right depends on how a site uses its API, so the tests never report a
 * critical issue. They report "recommended" to point at something worth checking, explain the
 * tradeoff, and link to the setting.
 *
 * The tests are registered with the `site_status_tests` filter, so a site that has decided
 * differently can remove any of them by its identifier.
 *
 * @package WPGraphQL\Admin\SiteHealth
 * @since 2.26.0
 *
 * phpcs:disable -- For phpstan type hinting
 * @phpstan-type SiteHealthResult array{
 *   label: string,
 *   status: string,
 *   badge: array{label: string, color: string},
 *   description: string,
 *   actions: string,
 *   test: string,
 * }
 * phpcs:enable
 */
class SiteHealth {

	/**
	 * The URL of the WPGraphQL security docs, which cover each of these settings.
	 */
	private const DOCS_URL = 'https://www.wpgraphql.com/docs/security';

	/**
	 * Initializes the Site Health tests.
	 *
	 * @since 2.26.0
	 */
	public function init(): void {
		add_filter( 'site_status_tests', [ $this, 'register_tests' ] );
	}

	/**
	 * Adds the WPGraphQL tests to Site Health on production sites.
	 *
	 * @param array<string,array<string,mixed>> $tests The Site Health tests, keyed by type.
	 *
	 * @return array<string,array<string,mixed>>
	 *
	 * @since 2.26.0
	 */
	public function register_tests( $tests ) {
		// Another callback on the filter may have returned something unexpected. Leave it alone.
		if ( ! is_array( $tests ) || 'production' !== $this->get_environment_type() ) {
			return $tests;
		}

		$tests['direct']['wpgraphql_public_introspection'] = [
			'label' => __( 'WPGraphQL public introspection', 'wp-graphql' ),
			'test'  => [ $this, 'test_public_introspection' ],
		];
		$tests['direct']['wpgraphql_debug_mode']           = [
			'label' => __( 'WPGraphQL debug mode', 'wp-graphql' ),
			'test'  => [ $this, 'test_debug_mode' ],
		];
		$tests['direct']['wpgraphql_query_depth']          = [
			'label' => __( 'WPGraphQL query depth limit', 'wp-graphql' ),
			'test'  => [ $this, 'test_query_depth' ],
		];
		$tests['direct']['wpgraphql_debug_output']         = [
			'label' => __( 'WPGraphQL tracing and query logs', 'wp-graphql' ),
			'test'  => [ $this, 'test_debug_output' ],
		];

		return $tests;
	}

	/**
	 * Checks whether logged-out visitors can read the schema.
	 *
	 * Follows the same rules as the DisableIntrospection validation rule, and passes when the API
	 * only accepts requests from logged-in users, since logged-out visitors then can't run any query.
	 *
	 * @return SiteHealthResult
	 *
	 * @since 2.26.0
	 */
	public function test_public_introspection(): array {
		$restricted = $this->is_restricted_to_logged_in_users();
		$debug      = \WPGraphQL::debug();
		$setting_on = 'off' !== get_graphql_setting( 'public_introspection_enabled', 'off' );

		if ( $restricted || ( ! $debug && ! $setting_on ) ) {
			return $this->result(
				'wpgraphql_public_introspection',
				'good',
				__( 'WPGraphQL only shows the schema to logged-in users', 'wp-graphql' ),
				'<p>' . __( 'Logged-out visitors can\'t ask the GraphQL API for its schema, the list of every type, field and argument it offers.', 'wp-graphql' ) . '</p>'
			);
		}

		$description = '<p>' . __( 'Anyone can ask the GraphQL API for its schema: the names and descriptions of every type, field and argument it offers, including ones added by other plugins. What each field returns still depends on who is asking.', 'wp-graphql' ) . '</p>';

		if ( $debug && $setting_on ) {
			$description .= '<p>' . __( 'Both debug mode and the introspection setting let logged-out visitors read the schema, so both have to be off to stop it.', 'wp-graphql' ) . '</p>';
		} elseif ( $debug ) {
			$description .= '<p>' . __( 'The introspection setting is off, but debug mode is on, and debug mode lets logged-out visitors read the schema whatever that setting is.', 'wp-graphql' ) . '</p>';
		}

		$description .= '<p>' . __( 'Keeping the schema private doesn\'t have to break apps or builds that need it. They can:', 'wp-graphql' ) . '</p>';
		$description .= '<ul>';
		$description .= '<li>' . __( 'send introspection queries with credentials, such as an application password, since logged-in users can always read the schema', 'wp-graphql' ) . '</li>';
		$description .= '<li>' . sprintf(
			/* translators: %s: the WP-CLI command that writes the schema to a file */
			__( 'or generate the schema at build time with %s, and point code generators at that file', 'wp-graphql' ),
			'<code>wp graphql generate-static-schema</code>'
		) . '</li>';
		$description .= '</ul>';

		return $this->result(
			'wpgraphql_public_introspection',
			'recommended',
			__( 'WPGraphQL shows the schema to logged-out visitors', 'wp-graphql' ),
			$description,
			$debug ? __( 'Review debug mode', 'wp-graphql' ) : __( 'Review the introspection setting', 'wp-graphql' ),
			'#getting-the-schema-without-public-introspection'
		);
	}

	/**
	 * Checks whether GraphQL debug mode is on.
	 *
	 * @return SiteHealthResult
	 *
	 * @since 2.26.0
	 */
	public function test_debug_mode(): array {
		if ( ! \WPGraphQL::debug() ) {
			return $this->result(
				'wpgraphql_debug_mode',
				'good',
				__( 'WPGraphQL debug mode is off', 'wp-graphql' ),
				'<p>' . __( 'GraphQL errors don\'t include internal details meant for developers.', 'wp-graphql' ) . '</p>'
			);
		}

		$description  = '<p>' . __( 'Debug mode makes problems easier to find while building an app, but it has costs on a live site:', 'wp-graphql' ) . '</p>';
		$description .= '<ul>';

		// When only logged-in users can use the API, logged-out visitors can't read the schema, and can
		// only query the root fields a site allows without logging in, so don't say errors reach anyone.
		if ( $this->is_restricted_to_logged_in_users() ) {
			$description .= '<li>' . __( 'Errors include internal messages meant for developers, and logged-in users also get stack traces with file paths.', 'wp-graphql' ) . '</li>';
		} else {
			$description .= '<li>' . __( 'Errors include internal messages meant for developers, for anyone who sends a request. Logged-in users also get stack traces with file paths.', 'wp-graphql' ) . '</li>';
			$description .= '<li>' . __( 'Logged-out visitors can read the schema, even when public introspection is off.', 'wp-graphql' ) . '</li>';
		}

		$description .= '</ul>';

		$source = $this->get_debug_mode_source();

		if ( 'constant' === $source ) {
			$description .= '<p>' . sprintf(
				/* translators: %s: the GRAPHQL_DEBUG constant */
				__( 'Debug mode is turned on in code with the %s constant, usually in wp-config.php, so it has to be turned off there.', 'wp-graphql' ),
				'<code>GRAPHQL_DEBUG</code>'
			) . '</p>';
		} elseif ( 'filter' === $source ) {
			$description .= '<p>' . sprintf(
				/* translators: %s: the name of the filter that turns debug mode on in code */
				__( 'Debug mode is turned on in code with the %s filter, so it has to be turned off there. The debug mode setting is off.', 'wp-graphql' ),
				'<code>graphql_debug_enabled</code>'
			) . '</p>';
		}

		return $this->result(
			'wpgraphql_debug_mode',
			'recommended',
			__( 'WPGraphQL debug mode is on', 'wp-graphql' ),
			$description,
			__( 'Review debug mode', 'wp-graphql' )
		);
	}

	/**
	 * Checks whether the query depth limit setting is on.
	 *
	 * The graphql_query_depth_max filter can also set a limit, but its value can depend on who is
	 * asking, so it isn't evaluated here.
	 *
	 * @return SiteHealthResult
	 *
	 * @since 2.26.0
	 */
	public function test_query_depth(): array {
		if ( 'on' === get_graphql_setting( 'query_depth_enabled', 'off' ) ) {
			return $this->result(
				'wpgraphql_query_depth',
				'good',
				__( 'WPGraphQL limits query depth', 'wp-graphql' ),
				'<p>' . __( 'Queries nested deeper than the maximum depth are rejected before they run.', 'wp-graphql' ) . '</p>'
			);
		}

		$description  = '<p>' . __( 'A query can nest fields inside fields, such as posts, then their authors, then those authors\' posts, over and over. Every level adds work, so a deeply nested query can use a lot of memory and CPU.', 'wp-graphql' ) . '</p>';
		$description .= '<p>' . __( 'Limiting query depth rejects those queries before they run. Apps that send queries deeper than the limit get errors instead of data, so check the queries your apps send before turning it on.', 'wp-graphql' ) . '</p>';
		$description .= '<p>' . sprintf(
			/* translators: %s: the name of the filter that sets the query depth limit in code */
			__( 'This checks the query depth setting. If your site limits depth in code with the %s filter instead, you can ignore this recommendation.', 'wp-graphql' ),
			'<code>graphql_query_depth_max</code>'
		) . '</p>';

		return $this->result(
			'wpgraphql_query_depth',
			'recommended',
			__( 'WPGraphQL does not limit query depth', 'wp-graphql' ),
			$description,
			__( 'Review the query depth limit', 'wp-graphql' ),
			'#limiting-query-depth'
		);
	}

	/**
	 * Checks whether tracing or query logs are added to responses for any user.
	 *
	 * @return SiteHealthResult
	 *
	 * @since 2.26.0
	 */
	public function test_debug_output(): array {
		$items = [];

		if ( 'on' === get_graphql_setting( 'tracing_enabled', 'off' ) && 'any' === get_graphql_setting( 'tracing_user_role', 'administrator' ) ) {
			$items[] = __( 'Tracing adds timing information for each field, and makes every traced response larger.', 'wp-graphql' );
		}

		if ( 'on' === get_graphql_setting( 'query_logs_enabled', 'off' ) && 'any' === get_graphql_setting( 'query_log_user_role', 'administrator' ) ) {
			$items[] = __( 'Query logs add the SQL for every database query, with table names and the values used, and slow down every logged request.', 'wp-graphql' );
		}

		if ( empty( $items ) ) {
			return $this->result(
				'wpgraphql_debug_output',
				'good',
				__( 'WPGraphQL tracing and query logs are not shown to everyone', 'wp-graphql' ),
				'<p>' . __( 'Tracing and query logs are off, or only added to responses for users with a chosen role.', 'wp-graphql' ) . '</p>'
			);
		}

		$description  = '<p>' . __( 'These debugging details are added to GraphQL responses for any user, including logged-out visitors:', 'wp-graphql' ) . '</p>';
		$description .= '<ul><li>' . implode( '</li><li>', $items ) . '</li></ul>';
		$description .= '<p>' . __( 'Limit them to a role, such as administrators, or turn them off when you\'re not debugging.', 'wp-graphql' ) . '</p>';

		return $this->result(
			'wpgraphql_debug_output',
			'recommended',
			__( 'WPGraphQL shows tracing or query logs to any user', 'wp-graphql' ),
			$description,
			__( 'Review tracing and query logs', 'wp-graphql' )
		);
	}

	/**
	 * Returns what turned debug mode on, following WPGraphQL::debug(): the GRAPHQL_DEBUG constant
	 * when it's defined, otherwise the setting, and the graphql_debug_enabled filter when neither did.
	 *
	 * Only meaningful while debug mode is on.
	 *
	 * @return string 'constant', 'setting' or 'filter'.
	 */
	private function get_debug_mode_source(): string {
		$constant = $this->get_debug_constant();

		if ( null !== $constant ) {
			return $constant ? 'constant' : 'filter';
		}

		return 'on' === get_graphql_setting( 'debug_mode_enabled', 'off' ) ? 'setting' : 'filter';
	}

	/**
	 * Returns the value of the GRAPHQL_DEBUG constant, or null when it isn't defined.
	 *
	 * @since 2.26.0
	 */
	protected function get_debug_constant(): ?bool {
		return defined( 'GRAPHQL_DEBUG' ) ? (bool) GRAPHQL_DEBUG : null;
	}

	/**
	 * Whether the API only accepts requests from logged-in users.
	 *
	 * Follows the RequireAuthentication validation rule: a non-null value from the
	 * graphql_pre_restrict_endpoint filter replaces the setting.
	 */
	private function is_restricted_to_logged_in_users(): bool {
		/**
		 * Allows overriding the default graphql_restrict_endpoint behavior. Returning anything other
		 * than null will skip the default restrict checks.
		 *
		 * @param bool|null $restrict_endpoint Whether to restrict the endpoint. Defaults to null
		 *
		 * @hookGroup authentication
		 * @since 0.0.5
		 */
		$restricted = apply_filters( 'graphql_pre_restrict_endpoint', null );

		if ( null !== $restricted ) {
			return (bool) $restricted;
		}

		return 'on' === get_graphql_setting( 'restrict_endpoint_to_logged_in_users', 'off' );
	}

	/**
	 * Returns the site's environment type.
	 *
	 * @since 2.26.0
	 */
	protected function get_environment_type(): string {
		return wp_get_environment_type();
	}

	/**
	 * Builds a Site Health test result.
	 *
	 * Results that recommend a change link to the WPGraphQL settings and to the docs.
	 *
	 * @param string $test         The test identifier.
	 * @param string $status       'good' or 'recommended'.
	 * @param string $label        The result's heading.
	 * @param string $description  The result's description, as HTML.
	 * @param string $action_label The text of the link to the settings. Only used when $status is 'recommended'.
	 * @param string $docs_anchor  The anchor of the docs section that covers the setting, if there is one.
	 *
	 * @return SiteHealthResult
	 */
	private function result( string $test, string $status, string $label, string $description, string $action_label = '', string $docs_anchor = '' ): array {
		$actions = '';

		if ( 'recommended' === $status ) {
			$actions = sprintf(
				'<p><a href="%1$s">%2$s</a> | <a href="%3$s" target="_blank" rel="noopener">%4$s<span class="screen-reader-text"> %5$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
				esc_url( admin_url( 'admin.php?page=graphql-settings' ) ),
				esc_html( $action_label ),
				esc_url( self::DOCS_URL . $docs_anchor ),
				esc_html__( 'Learn more about WPGraphQL security', 'wp-graphql' ),
				/* translators: Hidden accessibility text. */
				esc_html__( '(opens in a new tab)', 'wp-graphql' )
			);
		}

		return [
			'label'       => $label,
			'status'      => $status,
			'badge'       => [
				'label' => __( 'Security', 'wp-graphql' ),
				'color' => 'blue',
			],
			'description' => $description,
			'actions'     => $actions,
			'test'        => $test,
		];
	}
}
