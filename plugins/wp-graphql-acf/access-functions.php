<?php

/**
 * Register how an ACF field type maps to the GraphQL schema.
 *
 * The config (or a callable that returns it, passed the field type name and the
 * AcfGraphQLFieldType instance) supports these keys:
 *
 * - `graphql_type` (string|array|callable): The GraphQL type the field resolves as. Defaults to `String`.
 * - `resolve` (callable): Resolves the field, overriding the default resolver.
 * - `admin_fields` (array|callable): The settings shown on the field's GraphQL tab in the ACF admin.
 * - `exclude_admin_fields` (string[]): Default settings to leave off the field's GraphQL tab.
 * - `format_value` (bool): `true` to resolve the value ACF formats for the field (what get_field()
 *   returns with formatting) instead of the stored value, `false` to resolve the stored value. Only a
 *   boolean is used: any other value, including a callable, is ignored, and the field falls back to
 *   the default, which formats select, WYSIWYG, oEmbed, repeater, flexible content and clone fields.
 *   The formatting is the field type's own ACF formatting, not a custom callback.
 *
 * @param string                $acf_field_type The ACF field type name, like `text` or `font-awesome`.
 * @param array<mixed>|callable $config         The field type config, or a callable that returns it.
 */
function register_graphql_acf_field_type( string $acf_field_type, $config = [] ): void {
	add_action(
		'wpgraphql/acf/register_field_types',
		static function ( \WPGraphQL\Acf\FieldTypeRegistry $registry ) use ( $acf_field_type, $config ) {
			$registry->register_field_type( $acf_field_type, $config );
		}
	);
}
