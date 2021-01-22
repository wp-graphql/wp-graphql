<?php
namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class ResetUserPassword {
	/**
	 * Registers the ResetUserPassword mutation.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'resetUserPassword',
			[
				'inputFields'         => self::get_input_fields(),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array
	 */
	public static function get_input_fields() {
		return [
			'key'      => [
				'type'        => 'String',
				'description' => __( 'Password reset key', 'wp-graphql' ),
			],
			'login'    => [
				'type'        => 'String',
				'description' => __( 'The user\'s login (username).', 'wp-graphql' ),
			],
			'password' => [
				'type'        => 'String',
				'description' => __( 'The new password.', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return UserCreate::get_output_fields();
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function( $input, AppContext $context, ResolveInfo $info ) {

			if ( empty( $input['key'] ) ) {
				throw new UserError( __( 'A password reset key is required.', 'wp-graphql' ) );
			}

			if ( empty( $input['login'] ) ) {
				throw new UserError( __( 'A user login is required.', 'wp-graphql' ) );
			}

			if ( empty( $input['password'] ) ) {
				throw new UserError( __( 'A new password is required.', 'wp-graphql' ) );
			}

			$user = check_password_reset_key( $input['key'], $input['login'] );

			/**
			 * If the password reset key check returns an error
			 */
			if ( is_wp_error( $user ) ) {

				/**
				 * Determine the message to return
				 */
				if ( 'expired_key' === $user->get_error_code() ) {
					$message = __( 'Password reset link has expired.', 'wp-graphql' );
				} else {
					$message = __( 'Password reset link is invalid.', 'wp-graphql' );
				}

				/**
				 * Throw an error with the message
				 */
				throw new UserError( $message );
			}

			/**
			 * Reset the password
			 */
			reset_password( $user, $input['password'] );

			/**
			 * Return the user ID
			 */
			return [
				'id' => $user->ID,
			];
		};
	}
}
