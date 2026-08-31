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
 *
 * @since 2.18.0
 */
class ContentTemplateEnum {

	/**
	 * Register the ContentTemplateEnum Type to the Schema.
	 *
	 * @return void
	 *
	 * @since 2.18.0
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

		// Each value name is qualified by the template's kind unconditionally. That makes the
		// enum additive across the block/classic axis: a classic and a block template that share
		// a base name never collide or rename each other, so adding one never touches the other
		// (e.g. adding a classic `page-no-title.php` alongside a block `page-no-title` adds
		// PAGE_NO_TITLE_TEMPLATE without touching PAGE_NO_TITLE_BLOCK_TEMPLATE). The one case this
		// cannot cover is two *same-kind* templates whose identifiers sanitize to the same name
		// (e.g. `full-width.php` vs `full_width.php`); that clash falls back to a numeric suffix
		// below, which is the only place a value name depends on which other templates exist. It
		// requires a single theme to ship two files differing only by characters that sanitize
		// identically, so it is effectively a theme-authoring pathology rather than a real case.
		$used_names = [ 'DEFAULT_TEMPLATE' => true ];
		foreach ( $templates as $file => $name ) {
			$enum_name = self::get_enum_name( $file );

			// Defensive: get_enum_name() runs the identifier through WPEnumType::get_safe_name(),
			// which always returns a non-empty name, so this guard is not reachable via a registered
			// template. It stays as a safety net in case the naming rules change.
			if ( '' === $enum_name ) {
				// @codeCoverageIgnoreStart
				graphql_debug(
					sprintf(
						// translators: %s is the template identifier.
						__( 'The "%s" template could not be added to the ContentTemplateEnum because a valid enum value name could not be generated for it.', 'wp-graphql' ),
						$file
					)
				);
				continue;
				// @codeCoverageIgnoreEnd
			}

			// Last-resort fallback for the same-kind clash described above: keep both templates
			// filterable by appending a numeric suffix (e.g. `full-width.php` vs `full_width.php`
			// -> FULL_WIDTH_TEMPLATE + FULL_WIDTH_TEMPLATE_2). This is deterministic for a fixed
			// set (see ksort above) but set-dependent, so it is the one path that can shift a
			// value name when a colliding same-kind template is added or removed. WordPress itself
			// never hits this because it stores the raw, unique file/slug rather than a sanitized
			// name; the clash exists only because enum names live in a restricted character set.
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
	 *
	 * Qualifying by kind unconditionally keeps the enum additive across the block/classic axis:
	 * a classic and a block template that share a base name get distinct names and never rename
	 * each other, so adding one never touches the other. The remaining edge, two same-kind
	 * templates whose identifiers sanitize to the same name, is disambiguated by the caller with
	 * a numeric suffix and is the only case a value name depends on the wider template set.
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
