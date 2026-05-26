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
// The IDE's internal alias/maxage/grant taxonomies were removed in 5.0.
// The built-in fields now bind to Smart Cache's existing taxonomies.
// See includes/document-settings/built-in-fields.php.
require_once __DIR__ . '/document-settings/built-in-fields.php';
require_once __DIR__ . '/document-settings/rest.php';
require_once __DIR__ . '/document-settings/localization.php';

// Each sub-module wires its own `init` registration at priority 11, after
// taxonomies and WPGraphQL itself are registered. There used to be a
// `wpgraphql_ide_register_document_settings` action dispatched here, but
// the indirection had no external consumers and the Document Settings
// surface is moving into Smart Cache anyway (see 5.0 architecture notes),
// so callers register directly now.
