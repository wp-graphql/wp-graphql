<?php
/**
 * Bootstrap file for functional tests.
 *
 * @package WPGraphQL\ACF\Tests\Functional
 */

// Load common bootstrap from wp-graphql plugin (shared across monorepo)
// Path: plugins/wp-graphql-acf/tests/functional -> plugins/wp-graphql/tests
// From: plugins/wp-graphql-acf/tests/functional
// To: plugins/wp-graphql/tests/bootstrap-common.php
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-graphql/tests/bootstrap-common.php';
