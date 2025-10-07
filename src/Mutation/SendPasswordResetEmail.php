<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;

class SendPasswordResetEmail {

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
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
	 */
	public static function mutate_and_get_payload(): callable {
		return static function ( $input ) {
			if ( ! self::was_username_provided( $input ) ) {
				throw new UserError( esc_html__( 'Enter a username or email address.', 'wp-graphql' ) );
			}

			// We obsfucate the actual success of this mutation to prevent user enumeration.
			$payload = [
				'success' => true,
				'id'      => null,
			];

			$user_data = self::get_user_data( $input['username'] );

			if ( ! $user_data ) {
				graphql_debug( self::get_user_not_found_error_message( $input['username'] ) );

				return $payload;
			}

			// Get the password reset key.
			$key = get_password_reset_key( $user_data );
			if ( is_wp_error( $key ) ) {
				graphql_debug( __( 'Unable to generate a password reset key.', 'wp-graphql' ) );

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
				graphql_debug( __( 'The email could not be sent.', 'wp-graphql' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.', 'wp-graphql' ) );

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
}
