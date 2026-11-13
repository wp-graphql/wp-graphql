<?php

namespace WPGraphQL\Admin;

/**
 * Class EmailUpdates
 *
 * The Email Updates part of the Data Sharing tab: one address field, and one "email me about
 * security releases and breaking changes" checkbox for each plugin registered with
 * register_graphql_data_sharing_plugin().
 *
 * Each checkbox is its own consent, for that plugin's own mailing list. Saving the tab sends one
 * request to the telemetry service for every plugin whose box was just checked, and the service
 * emails a confirmation link for them, normally one email for all of them. Nobody is subscribed
 * until they click the link, and the service never says whether an address is subscribed. So the stored
 * state records, per plugin, that a confirmation was requested, for which address and when, not
 * that anyone is subscribed. Unchecking a box clears that plugin's record and sends nothing:
 * unsubscribing is done with the link in any of the emails.
 *
 * The request is sent straight to the telemetry service. It isn't part of the usage data
 * check-ins, and doesn't depend on sharing usage data.
 *
 * @package WPGraphQL\Admin
 *
 * @since x-release-please-version
 */
final class EmailUpdates {

	/**
	 * The name of the row that introduces the email updates checkboxes.
	 */
	public const HEADING_FIELD_NAME = 'email_updates';

	/**
	 * The name of the email address field.
	 */
	public const ADDRESS_FIELD_NAME = 'email_updates_address';

	/**
	 * The prefix of each plugin's checkbox field name. The plugin's slug follows it.
	 */
	public const FIELD_PREFIX = 'email_updates_for_';

	/**
	 * The option that records the confirmation requests made from this site, keyed by plugin slug.
	 */
	public const REQUESTS_OPTION = 'graphql_email_updates_requests';

	/**
	 * The default subscribe endpoint of the telemetry service.
	 */
	public const ENDPOINT = 'https://telemetry.wpgraphql.com/api/v1/email/subscribe';

	/**
	 * The messages of a sign-up made with sign_up(), collected instead of shown on the settings page.
	 *
	 * @var array<int,array{code:string,type:string,message:string}>|null
	 */
	private $collected;

	/**
	 * Register the hooks.
	 */
	public function init(): void {
		// After the Share Usage Data rows, which are added at the default priority, so the email
		// updates rows come last on the tab.
		add_action( 'graphql_init_settings', [ $this, 'add_fields' ], 110 );

		// Runs before `update_option()` skips a write that matches the stored value, and before it
		// falls through to `add_option()` for a new option, so every save of the section is seen.
		add_filter( 'pre_update_option_' . DataSharing::SECTION, [ $this, 'on_pre_update_option' ], 20, 2 );

		// The records hold email addresses, so remove them along with the plugin's other data.
		add_action( 'graphql_delete_data', [ $this, 'delete_requests' ] );
	}

	/**
	 * The name of a plugin's checkbox field.
	 *
	 * @param string $slug The plugin's Appsero client slug.
	 */
	public static function get_field_name( string $slug ): string {
		return self::FIELD_PREFIX . sanitize_key( $slug );
	}

	/**
	 * Remove the records of the confirmation requests. It doesn't unsubscribe anyone.
	 */
	public function delete_requests(): void {
		delete_option( self::REQUESTS_OPTION );
	}

	/**
	 * The confirmation requests made from this site for plugins whose box is checked.
	 *
	 * @return array<string,array{email:string,requested_at:int}> The address and the time of each request, keyed by plugin slug.
	 */
	public function get_requests(): array {
		$stored = get_option( self::REQUESTS_OPTION );

		if ( ! is_array( $stored ) ) {
			return [];
		}

		$requests = [];

		foreach ( $stored as $slug => $request ) {
			if ( ! is_string( $slug ) || ! is_array( $request ) || empty( $request['email'] ) || ! is_string( $request['email'] ) ) {
				continue;
			}

			$requests[ $slug ] = [
				'email'        => $request['email'],
				'requested_at' => isset( $request['requested_at'] ) ? (int) $request['requested_at'] : 0,
			];
		}

		return $requests;
	}

	/**
	 * Store the confirmation request records.
	 *
	 * @param array<string,array{email:string,requested_at:int}> $requests The records, keyed by plugin slug.
	 */
	private function save_requests( array $requests ): void {
		if ( empty( $requests ) ) {
			$this->delete_requests();
			return;
		}

		update_option( self::REQUESTS_OPTION, $requests, false );
	}

	/**
	 * The URL confirmation requests are sent to.
	 *
	 * @param string[] $hashes The telemetry client hashes of the plugins in the request.
	 */
	public function get_endpoint( array $hashes ): string {
		/**
		 * Filters the URL of the telemetry service endpoint that email update confirmation requests are sent to.
		 *
		 * Useful for pointing a staging site, or tests, at a different service.
		 *
		 * @param string   $endpoint The subscribe endpoint URL.
		 * @param string[] $hashes   The telemetry client hashes of the plugins in the request, in the order the confirmation email lists them.
		 *
		 * @hookGroup settings
		 * @since x-release-please-version
		 */
		$endpoint = apply_filters( 'graphql_email_updates_endpoint', self::ENDPOINT, $hashes );

		return is_string( $endpoint ) && '' !== $endpoint ? $endpoint : self::ENDPOINT;
	}

	/**
	 * Add the email updates rows to the Data Sharing tab.
	 *
	 * The fields are built when the settings registry initializes, so they show the request records
	 * and the current user as they are when the settings are shown or saved.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry The settings registry.
	 */
	public function add_fields( Settings\SettingsRegistry $registry ): void {
		$plugins = DataSharing::get_registered_plugins();

		if ( empty( $plugins ) ) {
			return;
		}

		$requests = $this->get_requests();

		$registry->register_field(
			DataSharing::SECTION,
			[
				'name'  => self::HEADING_FIELD_NAME,
				'label' => __( 'Email Updates', 'wp-graphql' ),
				'desc'  => esc_html__( 'Get emails about security releases and breaking changes. Each box below is a separate signup, for that plugin\'s own mailing list. When you check boxes and save, a confirmation email is sent to the address below for the plugins you checked, unless it\'s already subscribed to them, and nothing is subscribed until you click the link in it. This is separate from sharing usage data. To unsubscribe, use the link in any of the emails.', 'wp-graphql' ),
				'type'  => 'html',
			]
		);

		$registry->register_field(
			DataSharing::SECTION,
			[
				'name'              => self::ADDRESS_FIELD_NAME,
				'label'             => __( 'Email Address', 'wp-graphql' ),
				'desc'              => __( 'Where the confirmation email and the updates are sent. Changing it while a box is checked sends a confirmation email to the new address, and doesn\'t unsubscribe the old one.', 'wp-graphql' ),
				'type'              => 'text',
				'sanitize_callback' => 'sanitize_email',
				// Show the address of the latest request rather than the stored copy. Without one the
				// field starts empty, so an address is only ever one someone typed in.
				'value'             => $this->get_display_address( $requests ),
				'placeholder'       => $this->get_current_user_email(),
			]
		);

		foreach ( $plugins as $slug => $plugin ) {
			$desc = sprintf(
				// translators: %s is the plugin name.
				esc_html__( 'Email me about security releases and breaking changes in %s', 'wp-graphql' ),
				esc_html( $plugin['name'] )
			);

			if ( isset( $requests[ $slug ] ) ) {
				$desc .= '<br>' . sprintf(
					// translators: %1$s is an email address, %2$s is the date the confirmation email was requested.
					esc_html__( 'A confirmation email was requested for %1$s on %2$s. Unchecking this box doesn\'t unsubscribe that address.', 'wp-graphql' ),
					'<strong>' . esc_html( $requests[ $slug ]['email'] ) . '</strong>',
					esc_html( (string) wp_date( (string) get_option( 'date_format' ), $requests[ $slug ]['requested_at'] ) )
				);
			}

			$registry->register_field(
				DataSharing::SECTION,
				[
					'name'    => self::get_field_name( $slug ),
					'label'   => esc_html( $plugin['name'] ),
					'desc'    => $desc,
					'type'    => 'checkbox',
					'default' => 'off',
					// Always show whether a confirmation was requested rather than the stored copy.
					'value'   => isset( $requests[ $slug ] ) ? 'on' : 'off',
				]
			);
		}
	}

	/**
	 * The address the form shows: the one most recently requested, or none.
	 *
	 * @param array<string,array{email:string,requested_at:int}> $requests The request records.
	 */
	private function get_display_address( array $requests ): string {
		$latest = null;

		foreach ( $requests as $request ) {
			if ( null === $latest || $request['requested_at'] >= $latest['requested_at'] ) {
				$latest = $request;
			}
		}

		return null !== $latest ? $latest['email'] : '';
	}

	/**
	 * The email address of the current user, or an empty string without one.
	 *
	 * Only a suggestion: it's shown as the placeholder, never sent unless typed in.
	 */
	private function get_current_user_email(): string {
		$user = wp_get_current_user();

		return $user->exists() ? (string) $user->user_email : '';
	}

	/**
	 * Request confirmations when a save of the Data Sharing tab asks for them.
	 *
	 * @param mixed $value     The new option value.
	 * @param mixed $old_value The stored option value, or false when the option is new.
	 *
	 * @return mixed The new option value, with the fields set to the state that resulted.
	 */
	public function on_pre_update_option( $value, $old_value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$plugins = array_filter(
			DataSharing::get_registered_plugins(),
			static function ( $slug ) use ( $value ) {
				return array_key_exists( self::get_field_name( (string) $slug ), $value );
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( empty( $plugins ) ) {
			return $value;
		}

		$this->apply_requested_state( $plugins, $value, $old_value );

		// Store the real state, not the requested one, so a request that wasn't made isn't shown as made.
		$requests = $this->get_requests();

		foreach ( array_keys( $plugins ) as $slug ) {
			$value[ self::get_field_name( $slug ) ] = isset( $requests[ $slug ] ) ? 'on' : 'off';
		}

		if ( ! empty( $requests ) ) {
			$value[ self::ADDRESS_FIELD_NAME ] = $this->get_display_address( $requests );
		} else {
			// Only keep an address a confirmation was requested for. Keeping one that was typed in
			// without checking a box would make a later request go to whoever saved the form last
			// rather than to the person turning email updates on.
			unset( $value[ self::ADDRESS_FIELD_NAME ] );
		}

		return $value;
	}

	/**
	 * Clear the records of unchecked plugins, and request a confirmation for the newly checked ones.
	 *
	 * A plugin is only included in a request when the save turns its box on or changes the address,
	 * so re-saving the tab for another field never sends one, even with a stale stored value.
	 *
	 * Requesting one also requires `manage_options`, because the settings capability is filterable,
	 * and a save made without a user (WP-CLI, cron) never sends one.
	 *
	 * @param array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}> $plugins   The registered plugins whose box is in the save.
	 * @param array<mixed,mixed>                                                                                                    $value     The new option value.
	 * @param mixed                                                                                                                 $old_value The stored option value, or false when the option is new.
	 */
	private function apply_requested_state( array $plugins, array $value, $old_value ): void {
		// A write that only sets the checkboxes, such as one field saved at a time, uses the address
		// the form would have been prefilled with.
		$address = array_key_exists( self::ADDRESS_FIELD_NAME, $value ) && is_string( $value[ self::ADDRESS_FIELD_NAME ] )
			? trim( $value[ self::ADDRESS_FIELD_NAME ] )
			: $this->get_display_address( $this->get_requests() );

		$requests   = $this->get_requests();
		$to_request = [];

		foreach ( $plugins as $slug => $plugin ) {
			$field = self::get_field_name( $slug );

			if ( 'on' !== $value[ $field ] ) {
				// Unchecking sends nothing. Unsubscribing is done with the link in any of the emails.
				unset( $requests[ $slug ] );
				continue;
			}

			if ( isset( $requests[ $slug ] ) && $this->is_same_address( $requests[ $slug ]['email'], $address ) ) {
				continue;
			}

			$unchanged = is_array( $old_value )
				&& isset( $old_value[ $field ], $old_value[ self::ADDRESS_FIELD_NAME ] )
				&& 'on' === $old_value[ $field ]
				&& is_string( $old_value[ self::ADDRESS_FIELD_NAME ] )
				&& $this->is_same_address( $old_value[ self::ADDRESS_FIELD_NAME ], $address );

			if ( ! $unchanged ) {
				$to_request[ $slug ] = $plugin;
			}
		}

		$this->save_requests( $requests );

		if ( empty( $to_request ) ) {
			return;
		}

		$this->request_if_allowed( $to_request, $address );
	}

	/**
	 * Ask the telemetry service for a confirmation email for some of the registered plugins.
	 *
	 * The opt-in notice uses this. It sends the request even for a plugin that already has one
	 * recorded, since asking from the notice is a new request.
	 *
	 * @param string[] $slugs   The Appsero client slugs of the plugins to sign up for.
	 * @param string   $address The address to send the confirmation email to.
	 *
	 * @return array<int,array{code:string,type:string,message:string}> What happened, as messages to show.
	 */
	public function sign_up( array $slugs, string $address ): array {
		// In registration order, which is the order the confirmation email lists them in.
		$plugins = array_intersect_key( DataSharing::get_registered_plugins(), array_flip( $slugs ) );

		$this->collected = [];
		$messages        = [];

		try {
			if ( empty( $plugins ) ) {
				$this->add_settings_error(
					'email_updates_no_plugin',
					__( 'No confirmation email was requested for email updates. Choose at least one plugin and try again.', 'wp-graphql' )
				);
			} else {
				$this->request_if_allowed( $plugins, trim( $address ) );
			}

			$messages = $this->collected ?? [];
		} finally {
			$this->collected = null;
		}

		return $messages;
	}

	/**
	 * Request a confirmation when the current user may sign up and the address is valid.
	 *
	 * @param array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}> $plugins The plugins to request a confirmation for.
	 * @param string                                                                                                                $address The address to send it to.
	 */
	private function request_if_allowed( array $plugins, string $address ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_settings_error(
				'email_updates_not_allowed',
				__( 'No confirmation email was requested for email updates. Only an administrator can sign up for them.', 'wp-graphql' )
			);
			return;
		}

		if ( ! is_email( $address ) ) {
			$this->add_settings_error(
				'email_updates_invalid_email',
				__( 'No confirmation email was requested for email updates. Enter a valid email address and try again.', 'wp-graphql' )
			);
			return;
		}

		$this->request_confirmation( $plugins, $address );
	}

	/**
	 * Send one confirmation request for several plugins, and record the plugins it was accepted for.
	 *
	 * The request waits for the response, so the message after the save says what happened for each
	 * plugin: accepted, not taking email signups yet, or not known to the service.
	 *
	 * @param array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}> $plugins The plugins to request a confirmation for, in the order the email should list them.
	 * @param string                                                                                                                $address The address to send it to.
	 */
	private function request_confirmation( array $plugins, string $address ): void {
		$hashes = array_values( array_unique( wp_list_pluck( $plugins, 'hash' ) ) );

		$response = wp_remote_post(
			$this->get_endpoint( $hashes ),
			[
				// The save waits for the response, and the service can be slow to answer, for example right after
				// it has been idle. The request is only made when someone asks for email updates, never on a page load.
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
				'headers' => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
				'body'    => (string) wp_json_encode(
					[
						'hashes'         => $hashes,
						'email'          => $address,
						'site_url'       => home_url(),
						'plugin_version' => defined( 'WPGRAPHQL_VERSION' ) ? WPGRAPHQL_VERSION : '',
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->add_settings_error(
				'email_updates_request_failed',
				sprintf(
					// translators: %s is the error message of the request, such as a timeout.
					__( 'The telemetry service couldn\'t be reached or didn\'t answer in time (%s), so it isn\'t known whether the confirmation email for email updates was requested. If it was, it will arrive shortly. Otherwise, try again later.', 'wp-graphql' ),
					$response->get_error_message()
				)
			);
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : [];

		if ( 429 === $code ) {
			$this->add_settings_error(
				'email_updates_rate_limited',
				__( 'No confirmation email was requested for email updates, because too many were requested recently. Try again later.', 'wp-graphql' )
			);
			return;
		}

		$outcomes = $this->get_outcomes( $code, $body, $hashes );

		if ( null === $outcomes ) {
			if ( 422 === $code && isset( $body['errors'] ) ) {
				$this->add_settings_error(
					'email_updates_address_rejected',
					__( 'No confirmation email was requested for email updates. The address wasn\'t accepted. Check it and try again.', 'wp-graphql' )
				);
				return;
			}

			$this->add_settings_error(
				'email_updates_request_failed',
				sprintf(
					// translators: %d is the HTTP status code of the response.
					__( 'The confirmation email for email updates couldn\'t be requested: the telemetry service gave an unexpected response (HTTP %d). Try again later.', 'wp-graphql' ),
					$code
				)
			);
			return;
		}

		$requests = $this->get_requests();
		$names    = [
			'accepted'    => [],
			'unavailable' => [],
			'invalid'     => [],
		];

		foreach ( $plugins as $slug => $plugin ) {
			$outcome = $outcomes[ $plugin['hash'] ];

			$names[ $outcome ][] = $plugin['name'];

			if ( 'accepted' === $outcome ) {
				$requests[ $slug ] = [
					'email'        => $address,
					'requested_at' => time(),
				];
			}
		}

		$this->save_requests( $requests );

		if ( ! empty( $names['accepted'] ) ) {
			$this->add_settings_error(
				'email_updates_requested',
				sprintf(
					// translators: %1$s is an email address, %2$s is a list of plugin names.
					__( 'A confirmation email was requested for %1$s for %2$s updates. If the address isn\'t subscribed yet, it\'s on its way, and nothing is subscribed until you click the link in it.', 'wp-graphql' ),
					$address,
					wp_sprintf_l( '%l', $names['accepted'] )
				),
				'success'
			);
		}

		if ( ! empty( $names['unavailable'] ) ) {
			$this->add_settings_error(
				'email_updates_unavailable',
				sprintf(
					// translators: %s is a list of plugin names.
					__( 'Email updates aren\'t available for %s yet.', 'wp-graphql' ),
					wp_sprintf_l( '%l', $names['unavailable'] )
				),
				'warning'
			);
		}

		if ( ! empty( $names['invalid'] ) ) {
			$this->add_settings_error(
				'email_updates_unknown_plugin',
				sprintf(
					// translators: %s is a list of plugin names.
					__( 'Email updates couldn\'t be requested for %s.', 'wp-graphql' ),
					wp_sprintf_l( '%l', $names['invalid'] )
				)
			);
		}
	}

	/**
	 * What the service did with each plugin in the request.
	 *
	 * Only a response that accounts for every hash is used: 202 when at least one was accepted, 422
	 * with `email_unavailable` when none was but at least one doesn't take email signups, and 401 when
	 * none is known. Anything else means the outcome is unknown, so nothing is recorded.
	 *
	 * @param int                $code   The response status code.
	 * @param array<mixed,mixed> $body   The decoded response body.
	 * @param string[]           $hashes The hashes that were sent.
	 *
	 * @return array<string,'accepted'|'unavailable'|'invalid'>|null The outcome of each hash, or null when the response doesn't say.
	 */
	private function get_outcomes( int $code, array $body, array $hashes ): ?array {
		$is_unavailable = 422 === $code && isset( $body['code'] ) && 'email_unavailable' === $body['code'];

		if ( ! in_array( $code, [ 202, 401 ], true ) && ! $is_unavailable ) {
			return null;
		}

		if ( ! isset( $body['products'] ) || ! is_array( $body['products'] ) ) {
			return null;
		}

		$outcomes = [];

		foreach ( [ 'accepted', 'unavailable', 'invalid' ] as $outcome ) {
			$listed = $body['products'][ $outcome ] ?? [];

			if ( ! is_array( $listed ) ) {
				return null;
			}

			foreach ( $listed as $hash ) {
				if ( is_string( $hash ) && in_array( $hash, $hashes, true ) ) {
					$outcomes[ $hash ] = $outcome;
				}
			}
		}

		// Allowlist: every hash sent has to be accounted for, or the outcome isn't known.
		if ( count( $outcomes ) !== count( $hashes ) ) {
			return null;
		}

		return $outcomes;
	}

	/**
	 * Whether two email addresses are the same, ignoring case.
	 *
	 * @param string $a An email address.
	 * @param string $b An email address.
	 */
	private function is_same_address( string $a, string $b ): bool {
		return 0 === strcasecmp( trim( $a ), trim( $b ) );
	}

	/**
	 * Report what happened: collected for sign_up(), or shown on the settings page after a save.
	 *
	 * @param string $code    The message code.
	 * @param string $message The message.
	 * @param string $type    The message type: error, success, warning or info.
	 */
	private function add_settings_error( string $code, string $message, string $type = 'error' ): void {
		if ( null !== $this->collected ) {
			$this->collected[] = [
				'code'    => $code,
				'type'    => $type,
				'message' => $message,
			];
			return;
		}

		// Only loaded in the admin. A save made elsewhere (WP-CLI, a script) has no page to show it on.
		if ( ! function_exists( 'add_settings_error' ) ) {
			return;
		}

		// WordPress only says the settings were saved when nothing else was reported, so say it here.
		if ( ! in_array( 'settings_updated', wp_list_pluck( get_settings_errors( 'general' ), 'code' ), true ) ) {
			add_settings_error( 'general', 'settings_updated', __( 'Settings saved.', 'wp-graphql' ), 'success' );
		}

		add_settings_error( DataSharing::SECTION, $code, esc_html( $message ), $type );
	}
}
