<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class PostStatusEnum {
	use EnumDescriptionTrait;

	/**
	 * Register the PostStatusEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$post_status_enum_values = [
			'name'  => 'PUBLISH',
			'value' => 'publish',
		];

		$post_stati = get_post_stati();

		if ( ! empty( $post_stati ) && is_array( $post_stati ) ) {
			/**
			 * Reset the array
			 */
			$post_status_enum_values = [];
			/**
			 * Loop through the post_stati
			 */
			foreach ( $post_stati as $status ) {
				if ( ! is_string( $status ) ) {
					continue;
				}

				$post_status_enum_values[ WPEnumType::get_safe_name( $status ) ] = [
					'description' => self::get_filtered_description( 'PostStatusEnum', $status ),
					'value'       => $status,
				];
			}
		}

		register_graphql_enum_type(
			'PostStatusEnum',
			[
				'description' => __( 'Publishing status that controls the visibility and editorial state of content. Determines whether content is published, pending review, in draft state, or private.', 'wp-graphql' ),
				'values'      => $post_status_enum_values,
			]
		);
	}

	/**
	 * Get the default description for a post status.
	 *
	 * @param string               $value The post status (publish, draft, etc.)
	 * @param array<string, mixed> $context Additional context data
	 */
	protected static function get_default_description( string $value, array $context = [] ): string {
		switch ( $value ) {
			case 'publish':
				return __( 'Content that is publicly visible to all visitors', 'wp-graphql' );
			case 'draft':
				return __( 'Content that is saved but not yet published or visible to the public', 'wp-graphql' );
			case 'pending':
				return __( 'Content awaiting review before publication', 'wp-graphql' );
			case 'private':
				return __( 'Content only visible to authorized users with appropriate permissions', 'wp-graphql' );
			case 'trash':
				return __( 'Content marked for deletion but still recoverable', 'wp-graphql' );
			case 'auto-draft':
				return __( 'Automatically saved content that has not been manually saved', 'wp-graphql' );
			case 'inherit':
				return __( 'Content that inherits its status from a parent object', 'wp-graphql' );
			default:
				return sprintf(
					// translators: %1$s is the post status.
					__( 'Objects with the %1$s status', 'wp-graphql' ),
					$value
				);
		}
	}
}
