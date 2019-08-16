<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\UserMutation;

class UserRegister {
	/**
	 * Registers the CommentCreate mutation.
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'registerUser',
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
		$input_fields = array_merge(
			UserCreate::get_input_fields(),
			[
				'username' => [
					'type'        => [
						'non_null' => 'String',
					],
					// translators: the placeholder is the name of the type of object being updated
					'description' => __( 'A string that contains the user\'s username.', 'wp-graphql' ),
				],
				'email'    => [
					'type'        => 'String',
					'description' => __( 'A string containing the user\'s email address.', 'wp-graphql' ),
				],
			]
		);

		/**
		 * make sure we don't allow input for role or roles
		 */
		unset( $input_fields['role'] );
		unset( $input_fields['roles'] );

		return $input_fields;

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

			if ( ! get_option( 'users_can_register' ) ) {
				throw new UserError( __( 'User registration is currently not allowed.', 'wp-graphql' ) );
			}

			if ( empty( $input['username'] ) ) {
				throw new UserError( __( 'A username was not provided.', 'wp-graphql' ) );
			}

			if ( empty( $input['email'] ) ) {
				throw new UserError( __( 'An email address was not provided.', 'wp-graphql' ) );
			}

			/**
			 * Map all of the args from GQL to WP friendly
			 */
			$user_args = UserMutation::prepare_user_object( $input, 'registerUser' );

			/**
			 * Register the new user
			 */
			$user_id = register_new_user( $user_args['user_login'], $user_args['user_email'] );

			/**
			 * Throw an exception if the user failed to register
			 */
			if ( is_wp_error( $user_id ) ) {
				$error_message = $user_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				} else {
					throw new UserError( __( 'The user failed to register but no error was provided', 'wp-graphql' ) );
				}
			}

			/**
			 * If the $user_id is empty, we should throw an exception
			 */
			if ( empty( $user_id ) ) {
				throw new UserError( __( 'The user failed to create', 'wp-graphql' ) );
			}

			/**
			 * If the client isn't already authenticated, set the state in the current session to
			 * the user they just registered. This is mostly so that they can get a response from
			 * the mutation about the user they just registered after the user object passes
			 * through the user model.
			 */
			if ( ! is_user_logged_in() ) {
				wp_set_current_user( $user_id );
			}

			/**
			 * Set the ID of the user to be used in the update
			 */
			$user_args['ID'] = absint( $user_id );

			/**
			 * Make sure we don't accept any role input during registration
			 */
			unset( $user_args['role'] );

			/**
			 * Update the registered user with the additional input (firstName, lastName, etc) from the mutation
			 */
			wp_update_user( $user_args );

			/**
			 * Update additional user data
			 */
			UserMutation::update_additional_user_object_data( $user_id, $input, 'registerUser', $context, $info );

			/**
			 * Return the new user ID
			 */
			return [
				'id' => $user_id,
			];

		};
	}
}
