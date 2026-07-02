<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class - ContentTemplateEnum
 *
 * The templates that can be assigned to content, used to filter connections by the
 * template a piece of content uses. Values are derived from the templates registered
 * for the active theme (the same source as the ContentTemplate types).
 *
 * @package WPGraphQL\Type\Enum
 */
class ContentTemplateEnum {

	/**
	 * Register the ContentTemplateEnum Type to the Schema.
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'ContentTemplateEnum',
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
			'DEFAULT_TEMPLATE' => [
				'value'       => 'default',
				'description' => static function () {
					return __( 'The default template, applied when no specific template is assigned.', 'wp-graphql' );
				},
			],
		];

		// Collect the templates registered for the active theme across the allowed post
		// types, mirroring how the ContentTemplate types are built. Keyed by the stored
		// identifier, which is unique per template.
		$templates = [];
		foreach ( \WPGraphQL::get_allowed_post_types() as $post_type ) {
			$post_type_templates = wp_get_theme()->get_page_templates( null, $post_type );

			foreach ( $post_type_templates as $file => $name ) {
				if ( is_string( $file ) && '' !== $file ) {
					$templates[ $file ] = is_string( $name ) && '' !== $name ? $name : $file;
				}
			}
		}

		// Sort by identifier so name assignment is deterministic regardless of the order
		// post types (or the theme) report their templates.
		ksort( $templates );

		// Each value name is qualified by the template's kind unconditionally, so a value's
		// name depends only on that template, never on which other templates exist. That keeps
		// the enum additive: registering a new template can add a value but never renames an
		// existing one (e.g. adding a classic `page-no-title.php` alongside a block
		// `page-no-title` adds PAGE_NO_TITLE_TEMPLATE without touching
		// PAGE_NO_TITLE_BLOCK_TEMPLATE).
		$used_names = [ 'DEFAULT_TEMPLATE' => true ];
		foreach ( $templates as $file => $name ) {
			$enum_name = self::get_enum_name( $file );

			if ( '' === $enum_name ) {
				graphql_debug(
					sprintf(
						// translators: %s is the template identifier.
						__( 'The "%s" template could not be added to the ContentTemplateEnum because a valid enum value name could not be generated for it.', 'wp-graphql' ),
						$file
					)
				);
				continue;
			}

			// Guard against the rare case where two same-kind templates still collapse to the
			// same name (e.g. `full-width.php` vs `full_width.php`) with a numeric suffix.
			$candidate = $enum_name;
			$suffix    = 2;
			while ( isset( $used_names[ $candidate ] ) ) {
				$candidate = $enum_name . '_' . $suffix;
				++$suffix;
			}
			$enum_name = $candidate;

			$used_names[ $enum_name ] = true;

			$values[ $enum_name ] = [
				'value'       => $file,
				'description' => static function () use ( $name ) {
					// translators: %s is the human-readable name of the template.
					return sprintf( __( 'The "%s" template.', 'wp-graphql' ), $name );
				},
			];
		}

		return $values;
	}

	/**
	 * The enum value name for a template identifier: a schema-friendly form of the
	 * identifier without its extension, qualified by the template's kind so the name is
	 * stable regardless of what other templates exist. A classic `full-width.php` becomes
	 * `FULL_WIDTH_TEMPLATE`; a block-theme `page-no-title` becomes `PAGE_NO_TITLE_BLOCK_TEMPLATE`.
	 * The `_TEMPLATE` / `_BLOCK_TEMPLATE` suffixes mirror the `Template_` / `BlockTemplate_`
	 * prefixes used for the generated template object types.
	 *
	 * @param string $file The template identifier (file name or slug).
	 */
	private static function get_enum_name( string $file ): string {
		$base = preg_replace( '/\.\w+$/', '', $file );
		$name = WPEnumType::get_safe_name( is_string( $base ) && '' !== $base ? $base : $file );

		if ( '' === $name ) {
			return '';
		}

		return $name . ( self::is_block_template( $file ) ? '_BLOCK_TEMPLATE' : '_TEMPLATE' );
	}

	/**
	 * Whether a template identifier is a block template rather than a classic one.
	 *
	 * Classic templates are PHP files; block templates are slugs. This is only used to
	 * qualify the enum value name by kind, so a heuristic on the identifier is enough, and
	 * the extension itself is never surfaced in the schema.
	 *
	 * @param string $file The template identifier (file name or slug).
	 */
	private static function is_block_template( string $file ): bool {
		return ! (bool) preg_match( '/\.php$/i', $file );
	}
}
