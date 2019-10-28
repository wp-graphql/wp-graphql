<?php

namespace WPGraphQL\Type\Object;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class EditLock {
	public static function register_type() {
		register_graphql_object_type(
			'EditLock',
			[
				'description' => __( 'Info on whether the object is locked by another user editing it', 'wp-graphql' ),
				'fields'      => [
					'editTime' => [
						'type'        => 'String',
						'description' => __( 'The time when the object was last edited', 'wp-graphql' ),
						'resolve'     => function( $edit_lock, array $args, $context, $info ) {
							$time = ( is_array( $edit_lock ) && ! empty( $edit_lock[0] ) ) ? $edit_lock[0] : null;

							return ! empty( $time ) ? Types::prepare_date_response( null, date( 'Y-m-d H:i:s', $time ) ) : null;
						},
					],
					'user'     => [
						'type'        => 'User',
						'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
						'resolve'     => function( $edit_lock, array $args, $context, $info ) {
							$user_id = ( is_array( $edit_lock ) && ! empty( $edit_lock[1] ) ) ? $edit_lock[1] : null;
							if ( empty( $user_id ) || ! absint( $user_id ) ) {
								return null;
							}

							return DataSource::resolve_user( $user_id, $context );
						},
					],
				],
			]
		);

	}
}
