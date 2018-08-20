<?php

namespace WPGraphQL\Type\User\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Types;

/**
 * Class ResetUserPassword
 *
 * @package WPGraphQL\Type\User\Mutation
 */
class ResetUserPassword {

	/**
	 * Stores the user register mutation
	 *
	 * @var array $mutation
	 * @access private
	 */
	private static $mutation;

	/**
	 * Process the user register mutation
	 *
	 * @return array|null
	 * @access public
	 */
	public static function mutate() {

		if ( empty( self::$mutation ) ) {

			self::$mutation = Relay::mutationWithClientMutationId( [
				'name' => 'ResetUserPassword',
				'description' => __( 'Reset a user\'s password', 'wp-graphql' ),
				'inputFields' => self::input_fields(),
				'outputFields' => [
					'user' => [
						'type' => Types::user(),
						'resolve' => function( $payload ) {
							return get_user_by( 'ID', $payload['id'] );
						}
					]
				],
				'mutateAndGetPayload' => function( $input, AppContext $context, ResolveInfo $info ) {

					if ( ! self::was_arg_provided( $input, 'key' ) ) {
						throw new UserError( __( 'A password reset key is required.', 'wp-graphql' ) );
					}

					if ( ! self::was_arg_provided( $input, 'login' ) ) {
						throw new UserError( __( 'A user login is required.', 'wp-graphql' ) );
					}

					if ( ! self::was_arg_provided( $input, 'password' ) ) {
						throw new UserError( __( 'A new password is required.', 'wp-graphql' ) );
					}

					$user = check_password_reset_key( $input['key'], $input['login'] );

					if ( is_wp_error( $user ) ) {
						throw new UserError( self::get_invalid_key_error_message( $user ) );
					}

					reset_password( $user, $input['password'] );

					/**
					 * Return the user ID
					 */
					return [
						'id' => $user->ID,
					];

				}

			] );
		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;

	}

	/**
	 * Was this arg provided in the user input?
	 *
	 * @param array  $input User input.
	 * @param string $arg   The array key to check for.
	 *
	 * @return bool
	 */
	private static function was_arg_provided( $input, $arg ) {

		return ! empty( $input[ $arg ] ) && is_string( $input[ $arg ] );

	}

	/**
	 * Get error message for an expired/invalid password reset link.
	 *
	 * @param \WP_Error $wp_error Error object.
	 *
	 * @return string
	 */
	private static function get_invalid_key_error_message( $wp_error ) {

		if ( 'expired_key' === $wp_error->get_error_code() ) {
			return __( 'Password reset link has expired.', 'wp-graphql' );
		}

		return __( 'Password reset link is invalid.', 'wp-graphql' );
	}

	/**
	 * Add the fields required for a password reset.
	 *
	 * @return array
	 */
	private static function input_fields() {

		/**
		 * A password reset requires a reset key, login and password to be passed.
		 */
		return [
			'key'      => [
				'type'        => Types::string(),
				'description' => __( 'Password reset key', 'wp-graphql' ),
			],
			'login'    => [
				'type'        => Types::string(),
				'description' => __( 'The user\'s login (username).', 'wp-graphql' ),
			],
			'password' => [
				'type'        => Types::string(),
				'description' => __( 'The new password.', 'wp-graphql' ),
			],
		];

	}

}
