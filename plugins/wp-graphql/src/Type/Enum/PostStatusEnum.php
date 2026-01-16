<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class - PostStatusEnum
 *
 * @package WPGraphQL\Type\Enum
 *
 * @phpstan-import-type PartialWPEnumValueConfig from \WPGraphQL\Type\WPEnumType
 */
class PostStatusEnum {

	/**
	 * Register the PostStatusEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {

		register_graphql_enum_type(
			'PostStatusEnum',
			[
				'description' => static function () {
					return __( 'Publishing status that controls the visibility and editorial state of content. Determines whether content is published, pending review, in draft state, or private.', 'wp-graphql' );
				},
				'values'      => self::get_values(),
			]
		);
	}

	/**
	 * Returns the values for the enum.
	 *
	 * @return array<string,PartialWPEnumValueConfig>
	 */
	private static function get_values(): array {
		$post_stati = get_post_stati();

		if ( empty( $post_stati ) || ! is_array( $post_stati ) ) {
			// Default to the publish status if no post stati are found.
			$post_stati = [
				'publish',
			];
		}

		$post_status_enum_values = [];
		/**
		 * Loop through the post_stati to build the enum values.
		 */
		foreach ( $post_stati as $status ) {
			if ( ! is_string( $status ) ) {
				continue;
			}

			switch ( $status ) {
				case 'publish':
					$description = static function () {
						return __( 'Content that is publicly visible to all visitors', 'wp-graphql' );
					};
					break;
				case 'draft':
					$description = static function () {
						return __( 'Content that is saved but not yet published or visible to the public', 'wp-graphql' );
					};
					break;
				case 'pending':
					$description = static function () {
						return __( 'Content awaiting review before publication', 'wp-graphql' );
					};
					break;
				case 'private':
					$description = static function () {
						return __( 'Content only visible to authorized users with appropriate permissions', 'wp-graphql' );
					};
					break;
				case 'trash':
					$description = static function () {
						return __( 'Content marked for deletion but still recoverable', 'wp-graphql' );
					};
					break;
				case 'auto-draft':
					$description = static function () {
						return __( 'Automatically saved content that has not been manually saved', 'wp-graphql' );
					};
					break;
				case 'inherit':
					$description = static function () {
						return __( 'Content that inherits its status from a parent object', 'wp-graphql' );
					};
					break;
				default:
					$description = static function () use ( $status ) {
						return sprintf(
							// translators: %1$s is the post status.
							__( 'Objects with the %1$s status', 'wp-graphql' ),
							$status
						);
					};
					break;
			}

			$post_status_enum_values[ WPEnumType::get_safe_name( $status ) ] = [
				'description' => $description,
				'value'       => $status,
			];
		}

		return $post_status_enum_values;
	}
}
