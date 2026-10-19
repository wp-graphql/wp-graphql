<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;

class SendPasswordResetEmail {

	/**
	 * The most password reset emails one GraphQL request may send.
	 *
	 * A single operation can invoke this mutation many times with aliases, and a
	 * batch can carry many operations, so the request layer's batch limit cannot
	 * see them. This is counted in the resolver instead.
	 */
	public const DEFAULT_MAX_EMAILS_PER_REQUEST = 1;

	/**
	 * How many times the current request has invoked the mutation.
	 *
	 * Attempts are counted, not emails, so the limit behaves the same way whether
	 * or not the account exists.
	 *
	 * @var int
	 */
	private static $attempts_this_request = 0;

	/**
	 * The request the count above belongs to.
	 *
	 * Identified by the AppContext, which `Request` constructs once per request and
	 * passes to every resolver, so a batch of operations shares one. Keying on it
	 * avoids depending on when an action fires relative to schema building.
	 *
	 * @var int|null
	 */
	private static $counted_request = null;

	/**
	 * Registers the sendPasswordResetEmail Mutation
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'sendPasswordResetEmail',
			[
				'description'         => static function () {
					return __( 'Send password reset email to user', 'wp-graphql' );
				},
				'inputFields'         => self::get_input_fields(),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_input_fields(): array {
		return [
			'username' => [
				'type'        => [
					'non_null' => 'String',
				],
				'description' => static function () {
					return __( 'A string that contains the user\'s username or email address.', 'wp-graphql' );
				},
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_output_fields(): array {
		return [
			'success' => [
				'type'        => 'Boolean',
				'description' => static function () {
					return __( 'Whether the mutation completed successfully. This does NOT necessarily mean that an email was sent.', 'wp-graphql' );
				},
			],
		];
	}

	/**
	 * Records an invocation against the current request and returns the running total.
	 *
	 * @param \WPGraphQL\AppContext $context The request's app context.
	 */
	private static function count_attempt( $context ): int {
		$request = is_object( $context ) ? spl_object_id( $context ) : 0;

		if ( self::$counted_request !== $request ) {
			self::$counted_request       = $request;
			self::$attempts_this_request = 0;
		}

		return ++self::$attempts_this_request;
	}

	/**
	 * The most password reset emails this request may send.
	 */
	private static function get_max_emails_per_request(): int {
		/**
		 * Filters how many password reset emails a single GraphQL request may send.
		 *
		 * A request can ask for many by aliasing the mutation, and the batch limit
		 * counts batch entries rather than aliases, so the limit is applied here.
		 * Requests past the limit are rejected with an error rather than silently
		 * dropped. The error does not depend on whether the account exists.
		 *
		 * @param int $max The maximum number of emails per request. Default 1.
		 *
		 * @since 2.24.1
		 * @hookGroup authentication
		 */
		$max = (int) apply_filters( 'graphql_allowed_password_reset_emails_per_request', self::DEFAULT_MAX_EMAILS_PER_REQUEST );

		return max( 1, $max );
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
	 */
	public static function mutate_and_get_payload(): callable {
		return static function ( $input, $context ) {
			if ( ! self::was_username_provided( $input ) ) {
				throw new UserError( esc_html__( 'Enter a username or email address.', 'wp-graphql' ) );
			}

			// Counted before the account is resolved, so the error cannot depend on
			// whether the account exists. Surplus aliases are rejected rather than
			// silently dropped, so a legitimate client is told what happened.
			// Counted here rather than where the email is sent. Counting sends would
			// make the limit bite only for accounts that exist, turning this error
			// into the enumeration oracle it is meant to avoid.
			if ( self::count_attempt( $context ) > self::get_max_emails_per_request() ) {
				throw new UserError( esc_html__( 'Too many password reset requests in one request. Send them separately.', 'wp-graphql' ) );
			}

			// The result of this mutation is deliberately the same whether or not the
			// account exists, so it cannot be used to discover who has an account.
			$payload = [
				'success' => true,
				'id'      => null,
			];

			$user_data = self::get_user_data( $input['username'] );

			if ( ! $user_data ) {
				self::debug_reason( self::get_user_not_found_error_message( $input['username'] ) );

				return $payload;
			}

			// Get the password reset key.
			$key = get_password_reset_key( $user_data );
			if ( is_wp_error( $key ) ) {
				self::debug_reason( __( 'Unable to generate a password reset key.', 'wp-graphql' ) );

				return $payload;
			}

			// Mail the reset key.
			$subject = self::get_email_subject( $user_data );
			$message = self::get_email_message( $user_data, $key );

			$email_sent = wp_mail( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
				$user_data->user_email,
				wp_specialchars_decode( $subject ),
				$message
			);

			// wp_mail can return a wp_error, but the docblock for it in WP Core is incorrect.
			if ( is_wp_error( $email_sent ) ) {
				self::debug_reason( __( 'The email could not be sent.', 'wp-graphql' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.', 'wp-graphql' ) );

				return $payload;
			}

			/**
			 * Return the ID of the user
			 */
			return [
				'id'      => $user_data->ID,
				'success' => true,
			];
		};
	}

	/**
	 * Was a username or email address provided?
	 *
	 * @param array<string,mixed> $input The input args.
	 */
	private static function was_username_provided( $input ): bool {
		return ! empty( $input['username'] ) && is_string( $input['username'] );
	}

	/**
	 * Get WP_User object representing this user
	 *
	 * @param string $username The user's username or email address.
	 *
	 * @return \WP_User|false WP_User object on success, false on failure.
	 */
	private static function get_user_data( $username ) {
		if ( self::is_email_address( $username ) ) {
			$username = wp_unslash( $username );

			if ( ! is_string( $username ) ) {
				return false;
			}

			return get_user_by( 'email', trim( $username ) );
		}

		return get_user_by( 'login', trim( $username ) );
	}

	/**
	 * Get the error message indicating why the user wasn't found
	 *
	 * @param string $username The user's username or email address.
	 */
	private static function get_user_not_found_error_message( string $username ): string {
		if ( self::is_email_address( $username ) ) {
			return __( 'There is no user registered with that email address.', 'wp-graphql' );
		}

		return __( 'Invalid username.', 'wp-graphql' );
	}

	/**
	 * Is the provided username arg an email address?
	 *
	 * @param string $username The user's username or email address.
	 */
	private static function is_email_address( string $username ): bool {
		return (bool) strpos( $username, '@' );
	}

	/**
	 * Get the subject of the password reset email
	 *
	 * @param \WP_User $user_data User data
	 */
	private static function get_email_subject( $user_data ): string {
		/* translators: Password reset email subject. %s: Site name */
		$title = sprintf( __( '[%s] Password Reset', 'wp-graphql' ), self::get_site_name() );

		/**
		 * Filters the subject of the password reset email.
		 *
		 * @param string   $title      Default email title.
		 * @param string   $user_login The username for the user.
		 * @param \WP_User $user_data WP_User object.
		 */
		return (string) apply_filters( 'retrieve_password_title', $title, $user_data->user_login, $user_data );
	}

	/**
	 * Get the site name.
	 */
	private static function get_site_name(): string {
		if ( is_multisite() ) {
			$network = get_network();
			if ( isset( $network->site_name ) ) {
				return $network->site_name;
			}
		}

		/*
		* The blogname option is escaped with esc_html on the way into the database
		* in sanitize_option we want to reverse this for the plain text arena of emails.
		*/

		return wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}

	/**
	 * Get the message body of the password reset email
	 *
	 * @param \WP_User $user_data User data
	 * @param string   $key       Password reset key
	 */
	private static function get_email_message( $user_data, $key ): string {
		$message = __( 'Someone has requested a password reset for the following account:', 'wp-graphql' ) . "\r\n\r\n";
		/* translators: %s: site name */
		$message .= sprintf( __( 'Site Name: %s', 'wp-graphql' ), self::get_site_name() ) . "\r\n\r\n";
		/* translators: %s: user login */
		$message .= sprintf( __( 'Username: %s', 'wp-graphql' ), $user_data->user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.', 'wp-graphql' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:', 'wp-graphql' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user_data->user_login ), 'login' ) . ">\r\n";

		/**
		 * Filters the message body of the password reset mail.
		 *
		 * If the filtered message is empty, the password reset email will not be sent.
		 *
		 * @param string   $message    Default mail message.
		 * @param string   $key        The activation key.
		 * @param string   $user_login The username for the user.
		 * @param \WP_User $user_data WP_User object.
		 */
		return (string) apply_filters( 'retrieve_password_message', $message, $key, $user_data->user_login, $user_data );
	}

	/**
	 * Surfaces why no password reset email was sent, to callers entitled to know.
	 *
	 * Every reason this mutation can give is specific to one account: that no such account
	 * exists, that a reset key could not be generated for it, or that mail to it failed. The
	 * mutation deliberately returns the same payload either way so it cannot be used to discover
	 * who has an account, but `extensions.debug` is attached to the response of every caller when
	 * debugging is enabled, anonymous ones included, so a reason that varies with the account puts
	 * the answer back in the response.
	 *
	 * Callers who are not entitled to the reason get no message at all. A generic message would
	 * read better but would only be emitted on the branches that fail, which leaves the same
	 * oracle: a message means no email went out, silence means one did.
	 *
	 * @param string $message The reason to surface.
	 */
	private static function debug_reason( string $message ): void {
		/**
		 * Filters the capability a caller needs to be told why no password reset email was sent.
		 *
		 * The default is `list_users` because a caller who holds it can already enumerate accounts
		 * through WordPress itself, so the reason tells them nothing they could not look up
		 * directly. By default only administrators hold it. Lower it to open the debug output up,
		 * for example while developing against the anonymous flow.
		 *
		 * @param string $capability The capability required to see the reason. Default 'list_users'.
		 *
		 * @since 2.24.1
		 * @hookGroup authentication
		 */
		$capability = (string) apply_filters( 'graphql_password_reset_debug_capability', 'list_users' );

		if ( ! current_user_can( $capability ) ) {
			return;
		}

		graphql_debug( $message );
	}
}
