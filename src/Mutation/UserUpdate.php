<?php
namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\UserMutation;

class UserUpdate {
	/**
	 * Registers the CommentCreate mutation.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'updateUser',
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
		return array_merge(
			[
				'id' => [
					'type'        => [
						'non_null' => 'ID',
					],
					// translators: the placeholder is the name of the type of post object being updated
					'description' => __( 'The ID of the user', 'wp-graphql' ),
				],
			],
			UserCreate::get_input_fields()
		);
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
		return function ( $input, AppContext $context, ResolveInfo $info ) {

			$id_parts      = ! empty( $input['id'] ) ? Relay::fromGlobalId( $input['id'] ) : null;
			$existing_user = isset( $id_parts['id'] ) && absint( $id_parts['id'] ) ? get_user_by( 'ID', absint( $id_parts['id'] ) ) : null;

			/**
			 * If there's no existing user, throw an exception
			 */
			if ( ! isset( $id_parts['id'] ) || empty( $existing_user ) ) {
				throw new UserError( __( 'A user could not be updated with the provided ID', 'wp-graphql' ) );
			}

			if ( ! current_user_can( 'edit_user', $existing_user->ID ) ) {
				throw new UserError( __( 'You do not have the appropriate capabilities to perform this action', 'wp-graphql' ) );
			}

			if ( isset( $input['roles'] ) && ! current_user_can( 'edit_users' ) ) {
				unset( $input['roles'] );
				throw new UserError( __( 'You do not have the appropriate capabilities to perform this action', 'wp-graphql' ) );
			}

			$user_args       = UserMutation::prepare_user_object( $input, 'updateUser' );
			$user_args['ID'] = absint( $id_parts['id'] );

			/**
			 * Update the user
			 */
			$user_id = wp_update_user( $user_args );

			/**
			 * Throw an exception if the post failed to create
			 */
			if ( is_wp_error( $user_id ) ) {
				$error_message = $user_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				} else {
					throw new UserError( __( 'The user failed to update but no error was provided', 'wp-graphql' ) );
				}
			}

			/**
			 * If the $user_id is empty, we should throw an exception
			 */
			if ( empty( $user_id ) ) {
				throw new UserError( __( 'The user failed to update', 'wp-graphql' ) );
			}

			/**
			 * Update additional user data
			 */
			UserMutation::update_additional_user_object_data( $user_id, $input, 'updateUser', $context, $info );

			/**
			 * Return the new user ID
			 */
			return [
				'id'   => $user_id,
				'user' => $context->get_loader( 'user' )->load_deferred( $user_id ),
			];
		};
	}
}
