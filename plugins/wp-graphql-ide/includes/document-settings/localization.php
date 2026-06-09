<?php
/**
 * Localize Document Settings field descriptors to JS.
 *
 * Adds a `documentSettings` key to window.WPGRAPHQL_IDE_DATA via the existing
 * `wpgraphql_ide_localized_data` filter. The drawer React component reads this
 * to know which fields to render. Per-document values come from the REST
 * document object (see Storage / rest.php), not from this payload.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

add_filter( 'wpgraphql_ide_localized_data', __NAMESPACE__ . '\\inject_localized_data', 10, 2 );

/**
 * @param array<string,mixed> $data
 * @param array<string,mixed> $app_context
 *
 * @return array<string,mixed>
 */
function inject_localized_data( $data, $app_context ): array {
	$fields = [];

	foreach ( Registry::instance()->get_fields() as $name => $field ) {
		// Skip fields the current user cannot edit so they don't render at all.
		if ( ! current_user_can( $field['capability'] ?? 'edit_posts' ) ) {
			continue;
		}

		$fields[] = [
			'name'    => $name,
			'label'   => $field['label'],
			'desc'    => $field['desc'],
			'type'    => $field['type'],
			'default' => $field['default'],
			'options' => $field['options'],
		];
	}

	$data['documentSettings'] = [
		'fields'          => $fields,
		'globalGrantMode' => get_global_grant_mode(),
	];

	return $data;
}

/**
 * Read the global Allow/Deny default mode.
 *
 * Returns one of: 'public' | 'only_allowed' | 'some_denied'. Defaults to
 * 'public' (all queries allowed unless explicitly denied), matching Smart
 * Cache's default.
 */
function get_global_grant_mode(): string {
	$value = get_option( 'wpgraphql_ide_grant_mode', 'public' );

	return in_array( $value, [ 'public', 'only_allowed', 'some_denied' ], true ) ? (string) $value : 'public';
}
