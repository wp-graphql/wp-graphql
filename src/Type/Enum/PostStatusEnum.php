<?php

namespace WPGraphQL\Type;

class PostStatusRegister {

	protected static $post_status_enum_values = [
		'name'  => 'PUBLISH',
		'value' => 'publish',
	];

	public static function get_status_enum_values() {

		$post_stati = get_post_stati();

		$post_status_enum_values = self::$post_status_enum_values;

		if ( ! empty( $post_stati ) && is_array( $post_stati ) ) {
			/**
			 * Reset the array
			 */
			$post_status_enum_values = [];
			/**
			 * Loop through the post_stati
			 */
			foreach ( $post_stati as $status ) {
				$post_status_enum_values[ WPEnumType::get_safe_name( $status ) ] = [
					'description' => sprintf( __( 'Objects with the %1$s status', 'wp-graphql' ), $status ),
					'value'       => $status,
				];
			}
		}

		return $post_status_enum_values;
	}

	public static function register_status_enum_type( $type_name ) {

		$values = self::get_status_enum_values();

		$uc_type_name = ucfirst( $type_name );
		$post_status_enum_values = apply_filters( "graphql_{$uc_type_name}_status_enum", $values );

		register_graphql_enum_type( "{$type_name}StatusEnum", [
			'description' => __( "The status of the $type_name.", 'wp-graphql' ),
			'values'      => $values,
		] );
	}
}

PostStatusRegister::register_status_enum_type( 'Post' );
PostStatusRegister::register_status_enum_type( 'Page' );
PostStatusRegister::register_status_enum_type( 'Comment' );
