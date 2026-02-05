<?php
/**
 * Bootstrap file for functional tests.
 *
 * @package WPGraphQL\IDE\Tests\Functional
 */

// Load common bootstrap from wp-graphql plugin (shared across monorepo)
// Path: plugins/wp-graphql-ide/tests/functional -> plugins/wp-graphql/tests
// From: plugins/wp-graphql-ide/tests/functional
// To: plugins/wp-graphql/tests/bootstrap-common.php
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-graphql/tests/bootstrap-common.php';
