<?php

use WPGraphQL\Admin\Extensions\Extensions;
use WPGraphQL\Admin\Extensions\Registry;

/**
 * Tests that the Extensions admin page detects installed and active extensions
 * regardless of the directory the plugin was installed into.
 *
 * The installed plugin list is seeded into the `plugins` cache that get_plugins()
 * reads from, so the assertions do not depend on which plugins happen to be
 * mounted in the test environment. Two fixtures cover both lookup paths:
 *
 * - Smart Cache lives in a directory (`wp-graphql-smart-cache`) that does NOT
 *   match its WordPress.org slug (`wpgraphql-smart-cache`), and is active.
 * - The IDE lives in a directory that matches its slug, and is inactive.
 */
class ExtensionsInstalledDetectionTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private const SMART_CACHE_PATH = 'wp-graphql-smart-cache/wp-graphql-smart-cache.php';
	private const IDE_PATH         = 'wpgraphql-ide/wpgraphql-ide.php';

	/**
	 * @var callable|null
	 */
	private $filter;

	/**
	 * @var mixed The active_plugins option before the test seeded it.
	 */
	private $original_active_plugins;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		$this->original_active_plugins = get_option( 'active_plugins' );

		// get_plugins() returns the cached list (keyed by plugin folder, '' = the plugins root)
		// when one is present, so seeding it makes the installed list deterministic.
		wp_cache_set(
			'plugins',
			[
				'' => [
					self::SMART_CACHE_PATH => [
						'Name'        => 'WPGraphQL Smart Cache',
						'Description' => 'Caching for WPGraphQL.',
						'Author'      => 'WPGraphQL',
					],
					self::IDE_PATH         => [
						'Name'        => 'WPGraphQL IDE',
						'Description' => 'An IDE for WPGraphQL.',
						'Author'      => 'WPGraphQL',
					],
				],
			],
			'plugins'
		);

		update_option( 'active_plugins', [ self::SMART_CACHE_PATH ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		if ( null !== $this->filter ) {
			remove_filter( 'graphql_get_extensions', $this->filter );
			$this->filter = null;
		}

		wp_cache_delete( 'plugins', 'plugins' );
		update_option( 'active_plugins', $this->original_active_plugins );

		parent::tearDown();
	}

	/**
	 * Replace the registry with a single test extension and return the populated result.
	 *
	 * @param array<string,mixed> $extension
	 * @return array<string,mixed>
	 */
	private function get_populated_extension( array $extension ): array {
		$this->filter = static function () use ( $extension ) {
			return [ 'test/extension' => $extension ];
		};
		add_filter( 'graphql_get_extensions', $this->filter );

		$extensions = ( new Extensions() )->get_extensions();

		$this->assertCount( 1, $extensions );

		return $extensions[0];
	}

	/**
	 * A minimal valid extension config without plugin_url / plugin_file.
	 *
	 * @return array<string,mixed>
	 */
	private function base_extension(): array {
		return [
			'name'              => 'Test Extension',
			'description'       => 'An extension used to test installed detection.',
			'support_url'       => 'https://example.com/support',
			'documentation_url' => 'https://example.com/docs',
			'author'            => [
				'name' => 'Tester',
			],
		];
	}

	/**
	 * A declared plugin_file finds the installed plugin even when the plugin_url slug is not the directory name.
	 */
	public function testPluginFileDetectsInstalledPluginWhenDirectoryDoesNotMatchSlug(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					// The WordPress.org slug does NOT match the installed directory (wp-graphql-smart-cache).
					'plugin_url'  => 'https://wordpress.org/plugins/wpgraphql-smart-cache/',
					'plugin_file' => 'wp-graphql-smart-cache.php',
				]
			)
		);

		$this->assertTrue( $extension['installed'] );
		$this->assertTrue( $extension['active'] );
		$this->assertSame( self::SMART_CACHE_PATH, $extension['plugin_path'] );
		$this->assertSame( 'wp-graphql-smart-cache.php', $extension['plugin_file'] );
	}

	/**
	 * Without plugin_file, the directory name is matched against the plugin_url slug (previous behavior).
	 */
	public function testSlugFallbackDetectsInstalledPluginWithoutPluginFile(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					'plugin_url' => 'https://wordpress.org/plugins/wpgraphql-ide/',
				]
			)
		);

		$this->assertTrue( $extension['installed'] );
		$this->assertFalse( $extension['active'] );
		$this->assertSame( self::IDE_PATH, $extension['plugin_path'] );
	}

	/**
	 * Without plugin_file, a slug that is not the directory name is reported as not installed.
	 *
	 * This is the original bug: Smart Cache is installed and active, but its
	 * WordPress.org slug is not its directory name, so the slug fallback misses it.
	 */
	public function testSlugMismatchWithoutPluginFileIsReportedAsNotInstalled(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					'plugin_url' => 'https://wordpress.org/plugins/wpgraphql-smart-cache/',
				]
			)
		);

		$this->assertFalse( $extension['installed'] );
		$this->assertFalse( $extension['active'] );
		$this->assertArrayNotHasKey( 'plugin_path', $extension );
	}

	/**
	 * A declared plugin_file that matches nothing wins over the slug fallback and reports not installed.
	 */
	public function testUnknownPluginFileIsReportedAsNotInstalled(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					'plugin_url'  => 'https://wordpress.org/plugins/wpgraphql-ide/',
					'plugin_file' => 'definitely-not-installed.php',
				]
			)
		);

		// A declared plugin_file takes precedence over the slug fallback.
		$this->assertFalse( $extension['installed'] );
		$this->assertFalse( $extension['active'] );
	}

	/**
	 * The plugin_file value is reduced to a file name, so path segments cannot reach the lookup.
	 */
	public function testPluginFileIsSanitizedToABasename(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					'plugin_url'  => 'https://wordpress.org/plugins/wpgraphql-smart-cache/',
					'plugin_file' => '../../wp-graphql-smart-cache/wp-graphql-smart-cache.php',
				]
			)
		);

		$this->assertSame( 'wp-graphql-smart-cache.php', $extension['plugin_file'] );
		$this->assertTrue( $extension['installed'] );
	}

	/**
	 * The first-party registry entries use their real WordPress.org slugs and declare their main files.
	 */
	public function testFirstPartyRegistryEntriesUseWordPressOrgSlugsAndDeclarePluginFiles(): void {
		$registry = Registry::get_extensions();

		$expected = [
			'wp-graphql/wpgraphql-ide'          => [ 'https://wordpress.org/plugins/wpgraphql-ide/', 'wpgraphql-ide.php' ],
			'wp-graphql/wp-graphql-smart-cache' => [ 'https://wordpress.org/plugins/wpgraphql-smart-cache/', 'wp-graphql-smart-cache.php' ],
			'wp-graphql/wpgraphql-acf'          => [ 'https://wordpress.org/plugins/wpgraphql-acf/', 'wpgraphql-acf.php' ],
		];

		foreach ( $expected as $key => [ $plugin_url, $plugin_file ] ) {
			$this->assertArrayHasKey( $key, $registry );
			$this->assertSame( $plugin_url, $registry[ $key ]['plugin_url'], "Unexpected plugin_url for $key" );
			$this->assertSame( $plugin_file, $registry[ $key ]['plugin_file'], "Unexpected plugin_file for $key" );
		}
	}
}
