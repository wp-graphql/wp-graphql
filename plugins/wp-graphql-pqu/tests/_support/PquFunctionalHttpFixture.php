<?php
/**
 * Shared setup for Codeception functional tests that hit the real web container.
 *
 * @package WPGraphQL\PQU\Tests\Support
 */

namespace TestCase\WPGraphQLPQU;

/**
 * Ensures the test site database lists required plugins as active for HTTP requests.
 */
final class PquFunctionalHttpFixture {

	/**
	 * @var list<string>
	 */
	private const ACTIVE_PLUGINS = [
		'wp-graphql/wp-graphql.php',
		'wp-graphql-smart-cache/wp-graphql-smart-cache.php',
		'wp-graphql-pqu/wp-graphql-pqu.php',
	];

	/**
	 * @param \FunctionalTester $I Actor.
	 * @return void
	 */
	public static function ensure_plugins_activated( \FunctionalTester $I ): void {
		$I->haveOptionInDatabase( 'active_plugins', self::ACTIVE_PLUGINS );
	}
}
