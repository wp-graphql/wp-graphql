<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Model\User;

class UserDelete {
	/**
	 * Registers the CommentCreate mutation.
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'deleteUser',
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
			'id'         => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The ID of the user you want to delete', 'wp-graphql' ),
			],
			'reassignId' => [
				'type'        => 'ID',
				'description' => __( 'Reassign posts and links to new User ID.', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return [
			'deletedId' => [
				'type'        => 'ID',
				'description' => __( 'The ID of the user that you just deleted', 'wp-graphql' ),
				'resolve'     => function( $payload ) {
					$deleted = (object) $payload['userObject'];
					return ( ! empty( $deleted->ID ) ) ? Relay::toGlobalId( 'user', $deleted->ID ) : null;
				},
			],
			'user'      => [
				'type'        => 'User',
				'description' => __( 'The deleted user object', 'wp-graphql' ),
				'resolve'     => function( $payload ) {
					return new User( $payload['userObject'] );
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function( $input ) {
			/**
			 * Get the ID from the global ID
			 */
			$id_parts = Relay::fromGlobalId( $input['id'] );

			if ( ! current_user_can( 'delete_users' ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to delete users.', 'wp-graphql' ) );
			}

			/**
			 * Retrieve the user object before it's deleted
			 */
			$user_before_delete = get_user_by( 'id', absint( $id_parts['id'] ) );

			/**
			 * Throw an error if the user we are trying to delete doesn't exist
			 */
			if ( false === $user_before_delete ) {
				throw new UserError( __( 'Could not find an existing user to delete', 'wp-graphql' ) );
			}

			/**
			 * Get the DB id for the user to reassign posts to from the relay ID.
			 */
			$reassign_id_parts = ( ! empty( $input['reassignId'] ) ) ? Relay::fromGlobalId( $input['reassignId'] ) : null;
			$reassign_id       = ( ! empty( $reassign_id_parts ) ) ? absint( $reassign_id_parts['id'] ) : null;

			/**
			 * If the wp_delete_user doesn't exist yet, load the file in which it is
			 * registered so it is available in this context. I think we need to
			 * load this manually here because WordPress only uses this
			 * function on the user edit screen normally.
			 */
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			if ( is_multisite() ) {
				$deleted_user = wpmu_delete_user( absint( $id_parts['id'] ) );
			} else {
				$deleted_user = wp_delete_user( absint( $id_parts['id'] ), $reassign_id );
			}

			if ( true !== $deleted_user ) {
				throw new UserError( __( 'Could not delete the user.', 'wp-grapgql' ) );
			}

			return [
				'userObject' => $user_before_delete,
			];
		};
	}
}
