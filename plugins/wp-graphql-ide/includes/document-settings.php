<?php
/**
 * Document Settings module loader.
 *
 * Per-document metadata (description, alias names, max-age, allow/deny) for
 * saved IDE queries. Field set is extensible: plugins call
 * {@see register_graphql_document_setting_field()} to contribute additional
 * fields and the IDE's drawer renders them automatically.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace WPGraphQLIDE\DocumentSettings;

require_once __DIR__ . '/document-settings/registry.php';
require_once __DIR__ . '/document-settings/access-functions.php';
require_once __DIR__ . '/document-settings/storage.php';
require_once __DIR__ . '/document-settings/taxonomies.php';
require_once __DIR__ . '/document-settings/built-in-fields.php';
require_once __DIR__ . '/document-settings/rest.php';
require_once __DIR__ . '/document-settings/localization.php';

// Fire the registration action after taxonomies are registered (init @ 10) and
// after WPGraphQL itself has finished its own registrations. Priority 11 keeps
// us comfortably after `register_taxonomies()` in this module.
add_action( 'init', __NAMESPACE__ . '\\dispatch_register_action', 11 );

function dispatch_register_action(): void {
	/**
	 * Register fields for the IDE's per-document Settings drawer.
	 *
	 * Use {@see register_graphql_document_setting_field()} inside the callback.
	 */
	do_action( 'wpgraphql_ide_register_document_settings' );
}
