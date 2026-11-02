<?php

namespace WPGraphQL\Admin;

/**
 * Class EmailUpdates
 *
 * Adds an "email me about security releases and breaking changes" opt-in to a
 * settings section, separate from the usage tracking opt-in.
 *
 * Checking the box and saving asks the telemetry service to send a confirmation
 * email to the address in the form, which is prefilled with the current user's
 * email address until a confirmation has been requested.
 * Nobody is subscribed until they click the link in that email, and the service
 * never says whether an address is subscribed. So the stored state records that
 * a confirmation was requested, for which address and when, not that anyone is
 * subscribed. Unchecking the box clears that record and sends nothing:
 * unsubscribing is done with the link in any of the emails.
 *
 * The request is sent straight to the telemetry service. It isn't part of the
 * usage tracking check-in, and doesn't depend on usage tracking being enabled.
 *
 * @package WPGraphQL\Admin
 *
 * @since x-release-please-version
 */
class EmailUpdates {

	/**
	 * The name of the checkbox settings field.
	 */
	public const FIELD_NAME = 'email_updates';

	/**
	 * The name of the email address settings field.
	 */
	public const ADDRESS_FIELD_NAME = 'email_updates_address';

	/**
	 * The default subscribe endpoint of the telemetry service.
	 */
	public const ENDPOINT = 'https://telemetry.wpgraphql.com/api/v1/email/subscribe';

	/**
	 * The plugin's telemetry client hash, which tells the service which product's list to use.
	 *
	 * @var string
	 */
	protected $hash;

	/**
	 * The plugin's telemetry client slug, used as the prefix of the option that records a request.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * The plugin version sent with a request.
	 *
	 * @var string
	 */
	protected $plugin_version;

	/**
	 * The settings section (and option name) the fields are stored in.
	 *
	 * @var string
	 */
	protected $section;

	/**
	 * The product name shown in the field descriptions.
	 *
	 * @var string
	 */
	protected $product_name;

	/**
	 * Extensions can reuse this class with their own hash, section and name.
	 *
	 * @param string $hash           The plugin's telemetry client hash.
	 * @param string $slug           The plugin's telemetry client slug.
	 * @param string $plugin_version The plugin version.
	 * @param string $section        The settings section to add the fields to.
	 * @param string $product_name   The product name shown in the field descriptions.
	 *
	 * @since x-release-please-version
	 */
	public function __construct( string $hash, string $slug, string $plugin_version, string $section = 'graphql_general_settings', string $product_name = 'WPGraphQL' ) {
		$this->hash           = $hash;
		$this->slug           = $slug;
		$this->plugin_version = $plugin_version;
		$this->section        = $section;
		$this->product_name   = $product_name;
	}

	/**
	 * Register the hooks.
	 */
	public function init(): void {
		add_action( 'graphql_register_settings', [ $this, 'register_fields' ] );

		// Runs before `update_option()` skips a write that matches the stored value, and before it
		// falls through to `add_option()` for a new option, so every save of the section is seen.
		add_filter( 'pre_update_option_' . $this->section, [ $this, 'on_pre_update_option' ], 10, 2 );

		// The record holds an email address, so remove it along with the plugin's other data.
		add_action( 'graphql_delete_data', [ $this, 'delete_request' ] );
	}

	/**
	 * Remove the record of the confirmation request. It doesn't unsubscribe anyone.
	 */
	public function delete_request(): void {
		delete_option( $this->get_request_option_name() );
	}

	/**
	 * The name of the option that records the last confirmation request.
	 */
	protected function get_request_option_name(): string {
		return $this->slug . '_email_updates';
	}

	/**
	 * The confirmation request made from this site, if the box is checked.
	 *
	 * @return array{email:string,requested_at:int}|null The address and the time of the request, or null if none is recorded.
	 */
	public function get_request(): ?array {
		$request = get_option( $this->get_request_option_name() );

		if ( ! is_array( $request ) || empty( $request['email'] ) || ! is_string( $request['email'] ) ) {
			return null;
		}

		return [
			'email'        => $request['email'],
			'requested_at' => isset( $request['requested_at'] ) ? (int) $request['requested_at'] : 0,
		];
	}

	/**
	 * The URL a confirmation request is sent to.
	 */
	public function get_endpoint(): string {
		/**
		 * Filters the URL of the telemetry service endpoint that email update confirmation requests are sent to.
		 *
		 * Useful for pointing a staging site, or tests, at a different service.
		 *
		 * @param string $endpoint The subscribe endpoint URL.
		 * @param string $hash     The telemetry client hash of the plugin making the request.
		 * @param string $slug     The telemetry client slug of the plugin making the request.
		 *
		 * @hookGroup settings
		 * @since x-release-please-version
		 */
		$endpoint = apply_filters( 'graphql_email_updates_endpoint', self::ENDPOINT, $this->hash, $this->slug );

		return is_string( $endpoint ) ? $endpoint : self::ENDPOINT;
	}

	/**
	 * Register the fields on the settings page.
	 *
	 * The fields are built when the settings registry initializes rather than now, so they show
	 * the request record and the current user as they are when the settings are shown or saved.
	 */
	public function register_fields(): void {
		if ( false === has_action( 'graphql_init_settings', [ $this, 'add_fields' ] ) ) {
			add_action( 'graphql_init_settings', [ $this, 'add_fields' ] );
		}
	}

	/**
	 * Add the fields to the settings registry.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry The settings registry.
	 */
	public function add_fields( Settings\SettingsRegistry $registry ): void {
		$request = $this->get_request();

		$desc = sprintf(
			// translators: %s is the product name.
			__( 'Email me about security releases and breaking changes in %s. When you check this and save, a confirmation email is sent to the address below, and nothing is subscribed until you click the link in it. This is separate from sharing usage data. To unsubscribe, use the link in any of the emails.', 'wp-graphql' ),
			esc_html( $this->product_name )
		);

		if ( null !== $request ) {
			$desc .= '<br><br>' . sprintf(
				// translators: %1$s is an email address, %2$s is the date the confirmation email was requested.
				__( 'A confirmation email was requested for %1$s on %2$s. Unchecking this box doesn\'t unsubscribe that address.', 'wp-graphql' ),
				'<strong>' . esc_html( $request['email'] ) . '</strong>',
				esc_html( (string) wp_date( (string) get_option( 'date_format' ), $request['requested_at'] ) )
			);
		}

		$registry->register_field(
			$this->section,
			[
				'name'    => self::FIELD_NAME,
				'label'   => __( 'Email Updates', 'wp-graphql' ),
				'desc'    => $desc,
				'type'    => 'checkbox',
				'default' => 'off',
				// Always show whether a confirmation was requested rather than the stored copy.
				'value'   => null !== $request ? 'on' : 'off',
			]
		);

		$registry->register_field(
			$this->section,
			[
				'name'       => self::ADDRESS_FIELD_NAME,
				'label'      => __( 'Email Updates Address', 'wp-graphql' ),
				'desc'       => __( 'Where the confirmation email and the updates are sent. It starts as the email address of your account. Changing it while email updates are checked sends a confirmation email to the new address, and doesn\'t unsubscribe the old one.', 'wp-graphql' ),
				'type'       => 'text',
				'default'    => '',
				'value'      => null !== $request ? $request['email'] : $this->get_current_user_email(),
				'depends_on' => self::FIELD_NAME,
			]
		);
	}

	/**
	 * The email address of the current user, or an empty string without one.
	 */
	protected function get_current_user_email(): string {
		$user = wp_get_current_user();

		return $user->exists() ? (string) $user->user_email : '';
	}

	/**
	 * Request a confirmation when a save of the settings section asks for one.
	 *
	 * @param mixed $value     The new option value.
	 * @param mixed $old_value The stored option value, or false when the option is new.
	 *
	 * @return mixed The new option value, with the fields set to the state that resulted.
	 */
	public function on_pre_update_option( $value, $old_value ) {
		if ( ! is_array( $value ) || ! isset( $value[ self::FIELD_NAME ] ) ) {
			return $value;
		}

		$this->apply_requested_state( $value, $old_value );

		// Store the real state, not the requested one, so a held-back request isn't shown as made.
		$request = $this->get_request();

		$value[ self::FIELD_NAME ] = null !== $request ? 'on' : 'off';

		if ( null !== $request ) {
			$value[ self::ADDRESS_FIELD_NAME ] = $request['email'];
		} else {
			// Only keep an address a confirmation was requested for. The form posts the prefilled
			// address on every save, and keeping it would make a later request go to whoever saved
			// the form last rather than to the person turning email updates on.
			unset( $value[ self::ADDRESS_FIELD_NAME ] );
		}

		return $value;
	}

	/**
	 * Clear the record, or request a confirmation, depending on what the save asks for.
	 *
	 * A confirmation is only requested when the save turns the box on or changes the address,
	 * so re-saving the section for another field never sends one, even with a stale stored value.
	 *
	 * Requesting one also requires `manage_options`, because the settings capability is
	 * filterable, and a save made without a user (WP-CLI, cron) never sends one.
	 *
	 * @param array<mixed,mixed> $value     The new option value.
	 * @param mixed              $old_value The stored option value, or false when the option is new.
	 */
	protected function apply_requested_state( array $value, $old_value ): void {
		if ( 'on' !== $value[ self::FIELD_NAME ] ) {
			// Unchecking sends nothing. Unsubscribing is done with the link in any of the emails.
			$this->delete_request();
			return;
		}

		// A write that only sets the checkbox, such as one field saved at a time, uses the
		// address the form would have been prefilled with.
		$address = array_key_exists( self::ADDRESS_FIELD_NAME, $value ) && is_string( $value[ self::ADDRESS_FIELD_NAME ] )
			? trim( $value[ self::ADDRESS_FIELD_NAME ] )
			: $this->get_current_user_email();

		$request = $this->get_request();

		if ( null !== $request && $this->is_same_address( $request['email'], $address ) ) {
			return;
		}

		$unchanged = is_array( $old_value )
			&& isset( $old_value[ self::FIELD_NAME ], $old_value[ self::ADDRESS_FIELD_NAME ] )
			&& 'on' === $old_value[ self::FIELD_NAME ]
			&& is_string( $old_value[ self::ADDRESS_FIELD_NAME ] )
			&& $this->is_same_address( $old_value[ self::ADDRESS_FIELD_NAME ], $address );

		if ( $unchanged ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_settings_error(
				'email_updates_not_allowed',
				__( 'Settings saved, but no confirmation email was requested for email updates. Only an administrator can sign up for them.', 'wp-graphql' )
			);
			return;
		}

		if ( ! is_email( $address ) ) {
			$this->add_settings_error(
				'email_updates_invalid_email',
				__( 'Settings saved, but no confirmation email was requested for email updates. Enter a valid email address and save again.', 'wp-graphql' )
			);
			return;
		}

		$response = wp_remote_post(
			$this->get_endpoint(),
			[
				'method'   => 'POST',
				'timeout'  => 3,
				'blocking' => false,
				'headers'  => [ 'Accept' => 'application/json' ],
				'body'     => [
					'hash'           => $this->hash,
					'email'          => $address,
					'site_url'       => home_url(),
					'plugin_version' => $this->plugin_version,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->add_settings_error(
				'email_updates_request_failed',
				__( 'Settings saved, but the confirmation email for email updates couldn\'t be requested. Try saving again later.', 'wp-graphql' )
			);
			return;
		}

		update_option(
			$this->get_request_option_name(),
			[
				'email'        => $address,
				'requested_at' => time(),
			],
			false
		);

		$this->add_settings_error(
			'email_updates_requested',
			sprintf(
				// translators: %s is an email address.
				__( 'Settings saved. A confirmation email was requested for %s. Nothing is subscribed until you click the link in it.', 'wp-graphql' ),
				$address
			),
			'success'
		);
	}

	/**
	 * Whether two email addresses are the same, ignoring case.
	 *
	 * @param string $a An email address.
	 * @param string $b An email address.
	 */
	protected function is_same_address( string $a, string $b ): bool {
		return 0 === strcasecmp( trim( $a ), trim( $b ) );
	}

	/**
	 * Show a message on the settings page after the save, when the settings page is the one saving.
	 *
	 * @param string $code    The message code.
	 * @param string $message The message.
	 * @param string $type    The message type: error, success, warning or info.
	 */
	protected function add_settings_error( string $code, string $message, string $type = 'error' ): void {
		// Only loaded in the admin. A save made elsewhere (a GraphQL mutation, WP-CLI) has no page to show it on.
		if ( ! function_exists( 'add_settings_error' ) ) {
			return;
		}

		add_settings_error( $this->section, $code, esc_html( $message ), $type );
	}
}
