<?php
/**
 * Public access functions for the Document Settings API.
 *
 * Functions are declared in the global namespace to mirror WPGraphQL core's
 * `register_graphql_settings_field()` / `register_graphql_field()` style.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'register_graphql_document_setting_field' ) ) {
	/**
	 * Register a field that will appear in the IDE's per-document Settings drawer.
	 *
	 * Fields are persisted alongside the saved query via the existing
	 * `/wp/v2/graphql-ide-queries` REST endpoints.
	 *
	 * @param string              $field_name Unique field identifier.
	 * @param array<string,mixed> $config     {
	 *     Field configuration.
	 *
	 *     @type string   $label             Human-readable label.
	 *     @type string   $desc              Help text shown below the field.
	 *     @type string   $type              Field type. One of: text, textarea, number, tag_list, radio_with_default.
	 *     @type mixed    $default           Default value.
	 *     @type array    $options           For radio_with_default: list of [ 'value' => ..., 'label' => ... ] pairs.
	 *     @type string   $capability        Capability required to read/write (default 'edit_posts').
	 *     @type callable $sanitize_callback Optional sanitizer applied before storage.
	 *     @type array    $storage           {
	 *         @type string $kind   'post_field' | 'post_meta' | 'taxonomy'.
	 *         @type string $key    Post field name, meta key, or taxonomy slug.
	 *         @type bool   $multi  Taxonomy multi-value flag.
	 *         @type bool   $unique Taxonomy: enforce cross-document uniqueness.
	 *     }
	 * }
	 */
	function register_graphql_document_setting_field( string $field_name, array $config ): void {
		\WPGraphQLIDE\DocumentSettings\Registry::instance()->register_field( $field_name, $config );
	}
}
