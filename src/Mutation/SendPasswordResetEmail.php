<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class SendPasswordResetEmail {
	public static function register_mutation() {
		register_graphql_mutation( 'sendPasswordResetEmail', [
			'description'         => __( 'Send password reset email to user', 'wp-graphql' ),
			'inputFields'         => [
				'username' => [
					'type'        => [
						'non_null' => 'String',
					],
					'description' => __( 'A string that contains the user\'s username or email address.', 'wp-graphql' ),
				],
			],
			'outputFields'        => [
				'user' => [
					'type'        => 'User',
					'description' => __( 'The user that the password reset email was sent to', 'wp-graphql' ),
					'resolve'     => function ( $payload ) {
						$user = get_user_by( 'ID', absint( $payload['id'] ) );

						return ! empty( $user ) ? $user : null;
					},
				],
			],
			'mutateAndGetPayload' => function ( $input, AppContext $context, ResolveInfo $info ) {

				if ( ! self::was_username_provided( $input ) ) {
					throw new UserError( __( 'Enter a username or email address.', 'wp-graphql' ) );
				}
				$user_data = self::get_user_data( $input['username'] );
				if ( ! $user_data ) {
					throw new UserError( self::get_user_not_found_error_message( $input['username'] ) );
				}
				$key = get_password_reset_key( $user_data );
				if ( is_wp_error( $key ) ) {
					throw new UserError( __( 'Unable to generate a password reset key.', 'wp-graphql' ) );
				}
				$subject    = self::get_email_subject( $user_data );
				$message    = self::get_email_message( $user_data, $key );
				$email_sent = wp_mail( $user_data->user_email, wp_specialchars_decode( $subject ), $message );
				if ( ! $email_sent ) {
					throw new UserError( __( 'The email could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );
				}

				/**
				 * Return the ID of the user
				 */
				return [
					'id' => $user_data->ID,
				];
			}
		] );
	}

	/**
	 * Was a username or email address provided?
	 *
	 * @param array $input The input args.
	 *
	 * @return bool
	 */
	private static function was_username_provided( $input ) {
		return ! empty( $input['username'] ) && is_string( $input['username'] );
	}

	/**
	 * Get WP_User object representing this user
	 *
	 * @param  string $username The user's username or email address.
	 *
	 * @return \WP_User|false WP_User object on success, false on failure.
	 */
	private static function get_user_data( $username ) {
		if ( self::is_email_address( $username ) ) {
			return get_user_by( 'email', trim( wp_unslash( $username ) ) );
		}

		return get_user_by( 'login', trim( $username ) );
	}

	/**
	 * Get the error message indicating why the user wasn't found
	 *
	 * @param  string $username The user's username or email address.
	 *
	 * @return string
	 */
	private static function get_user_not_found_error_message( $username ) {
		if ( self::is_email_address( $username ) ) {
			return __( 'There is no user registered with that email address.', 'wp-graphql' );
		}

		return __( 'Invalid username.', 'wp-graphql' );
	}

	/**
	 * Is the provided username arg an email address?
	 *
	 * @param  string $username The user's username or email address.
	 *
	 * @return bool
	 */
	private static function is_email_address( $username ) {
		return strpos( $username, '@' );
	}

	/**
	 * Get the subject of the password reset email
	 *
	 * @param \WP_User $user_data User data
	 *
	 * @return string
	 */
	private static function get_email_subject( $user_data ) {
		/* translators: Password reset email subject. %s: Site name */
		$title = sprintf( __( '[%s] Password Reset' ), self::get_site_name() );

		/**
		 * Filters the subject of the password reset email.
		 *
		 * @param string   $title      Default email title.
		 * @param string   $user_login The username for the user.
		 * @param \WP_User $user_data  WP_User object.
		 */
		return apply_filters( 'retrieve_password_title', $title, $user_data->user_login, $user_data );
	}

	/**
	 * Get the site name.
	 *
	 * @return string
	 */
	private static function get_site_name() {
		if ( is_multisite() ) {
			return get_network()->site_name;
		}

		/*
		* The blogname option is escaped with esc_html on the way into the database
		* in sanitize_option we want to reverse this for the plain text arena of emails.
		*/

		return wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}

	/**
	 * Get the message body of the password reset email
	 *
	 * @param \WP_User $user_data User data
	 * @param string   $key       Password reset key
	 *
	 * @return string
	 */
	private static function get_email_message( $user_data, $key ) {
		$message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
		/* translators: %s: site name */
		$message .= sprintf( __( 'Site Name: %s' ), self::get_site_name() ) . "\r\n\r\n";
		/* translators: %s: user login */
		$message .= sprintf( __( 'Username: %s' ), $user_data->user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user_data->user_login ), 'login' ) . ">\r\n";

		/**
		 * Filters the message body of the password reset mail.
		 *
		 * If the filtered message is empty, the password reset email will not be sent.
		 *
		 * @param string   $message    Default mail message.
		 * @param string   $key        The activation key.
		 * @param string   $user_login The username for the user.
		 * @param \WP_User $user_data  WP_User object.
		 */
		return apply_filters( 'retrieve_password_message', $message, $key, $user_data->user_login, $user_data );
	}
}