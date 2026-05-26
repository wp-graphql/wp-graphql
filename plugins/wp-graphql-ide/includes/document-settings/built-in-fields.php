<?php
/**
 * Register the four built-in Document Settings fields by calling the
 * public registration API.
 *
 * Hooked at `init` priority 11 so the post type + taxonomies (registered at
 * priority 10) are already in place when these fields reference them.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

add_action( 'init', __NAMESPACE__ . '\\register_built_in_fields', 11 );

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
				// Smart Cache's existing alias taxonomy — registered in
				// WPGraphQL\SmartCache\Document with TAXONOMY_NAME =
				// graphql_query_alias. The IDE binds to it directly.
				'kind'   => 'taxonomy',
				'key'    => 'graphql_query_alias',
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
				// Smart Cache's existing max-age taxonomy — see
				// WPGraphQL\SmartCache\Document\MaxAge::TAXONOMY_NAME.
				'kind' => 'taxonomy',
				'key'  => 'graphql_document_http_maxage',
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
				// Smart Cache's existing grant taxonomy — see
				// WPGraphQL\SmartCache\Document\Grant::TAXONOMY_NAME.
				// Term values match Smart Cache's: allow / deny / ''
				// (use global). Sanitizer below enforces.
				'kind' => 'taxonomy',
				'key'  => 'graphql_document_grant',
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
