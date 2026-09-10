<?php

use WPGraphQL\Admin\Extensions\Extensions;
use WPGraphQL\Admin\Extensions\Registry;

/**
 * Tests that the Extensions admin page detects installed and active extensions
 * regardless of the directory the plugin was installed into.
 *
 * In the test environment WPGraphQL itself is installed as
 * `wp-graphql/wp-graphql.php` and is active, so it doubles as the "installed extension".
 */
class ExtensionsInstalledDetectionTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var callable|null
	 */
	private $filter;

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		if ( null !== $this->filter ) {
			remove_filter( 'graphql_get_extensions', $this->filter );
			$this->filter = null;
		}
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
					// The slug in the URL does NOT match the installed directory (wp-graphql).
					'plugin_url'  => 'https://wordpress.org/plugins/some-other-slug/',
					'plugin_file' => 'wp-graphql.php',
				]
			)
		);

		$this->assertTrue( $extension['installed'] );
		$this->assertTrue( $extension['active'] );
		$this->assertSame( 'wp-graphql/wp-graphql.php', $extension['plugin_path'] );
		$this->assertSame( 'wp-graphql.php', $extension['plugin_file'] );
	}

	/**
	 * Without plugin_file, the directory name is matched against the plugin_url slug (previous behavior).
	 */
	public function testSlugFallbackDetectsInstalledPluginWithoutPluginFile(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					'plugin_url' => 'https://wordpress.org/plugins/wp-graphql/',
				]
			)
		);

		$this->assertTrue( $extension['installed'] );
		$this->assertTrue( $extension['active'] );
		$this->assertSame( 'wp-graphql/wp-graphql.php', $extension['plugin_path'] );
	}

	/**
	 * Without plugin_file, a slug that is not the directory name is reported as not installed.
	 */
	public function testSlugMismatchWithoutPluginFileIsReportedAsNotInstalled(): void {
		$extension = $this->get_populated_extension(
			array_merge(
				$this->base_extension(),
				[
					'plugin_url' => 'https://wordpress.org/plugins/some-other-slug/',
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
					'plugin_url'  => 'https://wordpress.org/plugins/wp-graphql/',
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
					'plugin_url'  => 'https://wordpress.org/plugins/some-other-slug/',
					'plugin_file' => '../../wp-graphql/wp-graphql.php',
				]
			)
		);

		$this->assertSame( 'wp-graphql.php', $extension['plugin_file'] );
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
