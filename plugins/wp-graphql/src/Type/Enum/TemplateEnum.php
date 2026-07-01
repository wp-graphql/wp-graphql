<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class - TemplateEnum
 *
 * The templates that can be assigned to content, used to filter connections by the
 * template a piece of content uses. Values are derived from the templates registered
 * for the active theme (the same source as the ContentTemplate types).
 *
 * @package WPGraphQL\Type\Enum
 */
class TemplateEnum {

	/**
	 * Register the TemplateEnum Type to the Schema.
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'TemplateEnum',
			[
				'description' => static function () {
					return __( 'The templates that can be assigned to content. Used to filter a connection by the template its content uses.', 'wp-graphql' );
				},
				'values'      => self::get_values(),
			]
		);
	}

	/**
	 * Build the enum values from the templates registered for the active theme.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function get_values(): array {
		// Always include the default option so the enum is never empty and callers can
		// filter for content that uses no specific template.
		$values = [
			'DEFAULT' => [
				'value'       => 'default',
				'description' => static function () {
					return __( 'The default template, applied when no specific template is assigned.', 'wp-graphql' );
				},
			],
		];

		// Collect the templates registered for the active theme across the allowed post
		// types, mirroring how the ContentTemplate types are built.
		$templates = [];
		foreach ( \WPGraphQL::get_allowed_post_types() as $post_type ) {
			$post_type_templates = wp_get_theme()->get_page_templates( null, $post_type );

			foreach ( $post_type_templates as $file => $name ) {
				$templates[ $file ] = $name;
			}
		}

		foreach ( $templates as $file => $name ) {
			if ( ! is_string( $file ) || '' === $file ) {
				continue;
			}

			// The enum name is a schema-friendly form of the file (without its extension);
			// the value is the identifier stored on the content, e.g. `my-template.php`.
			$base      = preg_replace( '/\.\w+$/', '', $file );
			$enum_name = WPEnumType::get_safe_name( is_string( $base ) && '' !== $base ? $base : $file );

			// Skip empties, the reserved DEFAULT name, and collisions (first registration wins).
			if ( '' === $enum_name || isset( $values[ $enum_name ] ) ) {
				continue;
			}

			$template_name = is_string( $name ) && '' !== $name ? $name : $file;

			$values[ $enum_name ] = [
				'value'       => $file,
				'description' => static function () use ( $template_name ) {
					// translators: %s is the human-readable name of the template.
					return sprintf( __( 'The "%s" template.', 'wp-graphql' ), $template_name );
				},
			];
		}

		return $values;
	}
}
