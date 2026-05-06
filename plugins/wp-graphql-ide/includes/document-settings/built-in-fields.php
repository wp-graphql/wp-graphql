<?php
/**
 * Register the four built-in Document Settings fields by calling the
 * public registration API. Doing this through the same access function that
 * extensions will use keeps a single integration path.
 *
 * Fires on `wpgraphql_ide_register_document_settings`, an action dispatched
 * from the main plugin loader after `init` so taxonomies and the post type
 * are guaranteed to be registered before fields reference them.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

add_action( 'wpgraphql_ide_register_document_settings', __NAMESPACE__ . '\\register_built_in_fields' );

function register_built_in_fields(): void {
	register_graphql_document_setting_field(
		'description',
		[
			'label'             => __( 'Description', 'wpgraphql-ide' ),
			'desc'              => __( 'Helpful for teams to describe how this query is used.', 'wpgraphql-ide' ),
			'type'              => 'textarea',
			'default'           => '',
			'sanitize_callback' => 'sanitize_textarea_field',
			'storage'           => [
				'kind' => 'post_field',
				'key'  => 'post_excerpt',
			],
		]
	);

	register_graphql_document_setting_field(
		'aliases',
		[
			'label'             => __( 'Alias Names', 'wpgraphql-ide' ),
			'desc'              => __( 'Unique names that can execute this query in place of its hash. Each alias must be unique across all saved queries.', 'wpgraphql-ide' ),
			'type'              => 'tag_list',
			'default'           => [],
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_alias_list',
			'storage'           => [
				'kind'   => 'taxonomy',
				'key'    => 'graphql_ide_query_alias',
				'multi'  => true,
				'unique' => true,
			],
		]
	);

	register_graphql_document_setting_field(
		'maxAgeHeader',
		[
			'label'             => __( 'Max-Age Header', 'wpgraphql-ide' ),
			'desc'              => __( 'Cache-Control max-age value (in seconds) sent with responses for this query.', 'wpgraphql-ide' ),
			'type'              => 'number',
			'default'           => '',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_max_age',
			'storage'           => [
				'kind' => 'taxonomy',
				'key'  => 'graphql_ide_query_maxage',
			],
		]
	);

	register_graphql_document_setting_field(
		'grant',
		[
			'label'             => __( 'Allow / Deny', 'wpgraphql-ide' ),
			'desc'              => __( 'Override the global default for whether this query is allowed to execute.', 'wpgraphql-ide' ),
			'type'              => 'radio_with_default',
			'default'           => '',
			'options'           => [
				[
					'value' => 'allow',
					'label' => __( 'Allowed', 'wpgraphql-ide' ),
				],
				[
					'value' => 'deny',
					'label' => __( 'Deny', 'wpgraphql-ide' ),
				],
				[
					'value' => '',
					'label' => __( 'Use global default', 'wpgraphql-ide' ),
				],
			],
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_grant',
			'storage'           => [
				'kind' => 'taxonomy',
				'key'  => 'graphql_ide_query_grant',
			],
		]
	);
}

/**
 * @param mixed $value
 *
 * @return array<int,string>
 */
function sanitize_alias_list( $value ): array {
	if ( is_string( $value ) ) {
		$value = preg_split( '/[\s,]+/', $value ) ?: [];
	}

	if ( ! is_array( $value ) ) {
		return [];
	}

	$cleaned = [];
	foreach ( $value as $item ) {
		if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
			continue;
		}
		$item = trim( (string) $item );
		if ( '' === $item ) {
			continue;
		}
		$cleaned[ $item ] = true;
	}

	return array_keys( $cleaned );
}

/**
 * @param mixed $value
 */
function sanitize_max_age( $value ): string {
	if ( '' === $value || null === $value ) {
		return '';
	}

	if ( ! is_numeric( $value ) ) {
		return '';
	}

	$int = (int) $value;

	return $int < 0 ? '' : (string) $int;
}

/**
 * @param mixed $value
 */
function sanitize_grant( $value ): string {
	$value = is_string( $value ) ? $value : '';

	return in_array( $value, [ 'allow', 'deny', '' ], true ) ? $value : '';
}
