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
			'DEFAULT' => [
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

		// Pass 1: compute the preferred (clean) name for each template and count how many
		// templates want each, so collisions can be detected up front.
		$preferred = [];
		$counts    = [];
		foreach ( $templates as $file => $name ) {
			$clean              = self::get_preferred_enum_name( $file );
			$preferred[ $file ] = $clean;
			if ( '' !== $clean ) {
				$counts[ $clean ] = ( $counts[ $clean ] ?? 0 ) + 1;
			}
		}

		// Pass 2: assign each template a unique value name. When two distinct templates want
		// the same name (e.g. a classic `my-template.php` and a block-theme `my-template`),
		// qualify each by its kind so both stay filterable, without leaking the extension.
		$used_names = [ 'DEFAULT' => true ];
		foreach ( $templates as $file => $name ) {
			$clean = $preferred[ $file ];

			if ( '' === $clean ) {
				graphql_debug(
					sprintf(
						// translators: %s is the template identifier.
						__( 'The "%s" template could not be added to the ContentTemplateEnum because a valid enum value name could not be generated for it.', 'wp-graphql' ),
						$file
					)
				);
				continue;
			}

			$candidate = $clean;
			if ( ( $counts[ $clean ] ?? 0 ) > 1 ) {
				$candidate .= self::is_block_template( $file ) ? '_BLOCK_TEMPLATE' : '_CONTENT_TEMPLATE';
			}

			// Guard against any remaining collision (e.g. two block templates whose slugs
			// collapse to the same name) with a numeric suffix.
			$enum_name = $candidate;
			$suffix    = 2;
			while ( isset( $used_names[ $enum_name ] ) ) {
				$enum_name = $candidate . '_' . $suffix;
				++$suffix;
			}

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
	 * The preferred enum value name for a template identifier: a schema-friendly form of
	 * the identifier without its extension, e.g. `my-template.php` -> `MY_TEMPLATE`.
	 *
	 * @param string $file The template identifier (file name or slug).
	 */
	private static function get_preferred_enum_name( string $file ): string {
		$base = preg_replace( '/\.\w+$/', '', $file );

		return WPEnumType::get_safe_name( is_string( $base ) && '' !== $base ? $base : $file );
	}

	/**
	 * Whether a template identifier is a block template rather than a classic one.
	 *
	 * Classic templates are PHP files; block templates are slugs. This is only used to
	 * qualify colliding enum names by kind, so a heuristic on the identifier is enough,
	 * and the extension itself is never surfaced in the schema.
	 *
	 * @param string $file The template identifier (file name or slug).
	 */
	private static function is_block_template( string $file ): bool {
		return ! (bool) preg_match( '/\.php$/i', $file );
	}
}
