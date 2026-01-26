<?php
/**
 * Bootstrap file for acceptance tests.
 *
 * @package WPGraphQL\SmartCache\Tests\Acceptance
 */

// Load common bootstrap from wp-graphql plugin (shared across monorepo)
// Path: plugins/wp-graphql-smart-cache/tests/acceptance -> plugins/wp-graphql/tests
// From: plugins/wp-graphql-smart-cache/tests/acceptance
// To: plugins/wp-graphql/tests/bootstrap-common.php
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-graphql/tests/bootstrap-common.php';
