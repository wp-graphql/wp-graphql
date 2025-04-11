<?php

namespace WPGraphQL\Type\Enum;

/**
 * Trait for adding descriptions to enum types.
 *
 * @since next-version
 */
trait EnumDescriptionTrait {
	/**
	 * Get a filtered description for an enum value.
	 *
	 * @param string               $enum_type The enum type name (e.g. 'MediaItemSize', 'PostStatus', etc.)
	 * @param string               $value The enum value to get the description for
	 * @param array<string, mixed> $context Additional context data relevant to the enum type
	 */
	protected static function get_filtered_description( string $enum_type, string $value, array $context = [] ): string {
		// Allow pre-filtering of the description
		$pre_description = apply_filters(
			'graphql_pre_enum_description',
			null,
			$enum_type,
			$value,
			$context
		);

		if ( null !== $pre_description ) {
			return $pre_description;
		}

		return static::get_default_description( $value, $context );
	}

	/**
	 * Get the default description for an enum value.
	 * This should be implemented by classes using this trait.
	 *
	 * @param string               $value The enum value
	 * @param array<string, mixed> $context Additional context data
	 */
	abstract protected static function get_default_description( string $value, array $context = [] ): string;
}
