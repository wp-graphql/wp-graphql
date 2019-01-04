<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;

class CommentRestore {
	public static function register_mutation() {
		register_graphql_mutation( 'restoreComment', [
			'inputFields'         => self::get_input_fields(),
			'outputFields'        => self::get_output_fields(),
			'mutateAndGetPayload' => self::mutate_and_get_payload(),
		] );
	}
	
	public static function get_input_fields() {
		return [
			'id' => [
				'type'        => [
					'non_null' => 'ID',
				],
			],
		];
	}

	public static function get_output_fields() {
		return [
			'restoredId' => [
				'type'        => 'Id',
				'description' => __( 'The ID of the restored comment', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$restore = ( object ) $payload['commentObject'];

					return ! empty( $restore->comment_ID ) ? Relay::toGlobalId( 'comment', absint( $restore->comment_ID ) ) : null;
				},
			],
			'mutateAndGetPayload' => function ( $input ) {
				/**
				 * Get the ID from the global ID
				 */
				$id_parts = Relay::fromGlobalId( $input['id'] );

					return ! empty( $restore ) ? $restore : null;
				},
			],
		];
	}

	public static function mutate_and_get_payload() {
		return function ( $input ) {
			/**
				* Get the ID from the global ID
				*/
			$id_parts = Relay::fromGlobalId( $input['id'] );

				return [
					'commentObject' => $comment,
				];
			}

			/**
				* Delete the comment
				*/
			$restored = wp_untrash_comment( $id_parts['id'] );

			$comment = get_comment( $comment_id );

			return [
				'commentObject' => $comment,
			];
		};
	}
}