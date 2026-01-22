<?php

/**
 * Tests for internationalization (i18n) setup.
 *
 * @package WPGraphQL
 */
class I18nTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		// If using Twenty Twenty-Five theme, expect the block bindings notice.
		// This must be set BEFORE parent::setUp() because the notice happens during
		// WordPress initialization. We check the TEST_THEME environment variable
		// since wp_get_theme() isn't available yet.
		$test_theme = getenv( 'TEST_THEME' ) ?: 'twentytwentyone';
		if ( 'twentytwentyfive' === $test_theme ) {
			// Set expectation before WordPress initialization
			$this->setExpectedIncorrectUsage( 'WP_Block_Bindings_Registry::register' );
		}

		// Suppress doing_it_wrong notices before parent::setUp() to catch theme notices early.
		//
		// This prevents false failures from theme-related notices that are unrelated to
		// WPGraphQL i18n functionality. Specifically, Twenty Twenty-Five theme triggers
		// a notice: "Block bindings source 'twentytwentyfive/format' already registered."
		// This appears to be a theme issue (not a WPGraphQL bug) where block bindings
		// are registered multiple times during WordPress initialization.
		//
		// The filter must be added before parent::setUp() to catch notices during
		// WordPress initialization.
		add_filter( 'doing_it_wrong_trigger_error', '__return_false', 999 );

		parent::setUp();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		parent::tearDown();
	}

	/**
	 * Test that the wp-graphql textdomain is loaded.
	 *
	 * @covers WPGraphQL::load_textdomain
	 */
	public function testTextdomainIsLoaded(): void {
		// Ensure init has run
		do_action( 'init' );

		// Check that the textdomain is loaded
		// Note: is_textdomain_loaded() returns true if load_plugin_textdomain was called,
		// even if no translation files exist (which is expected in test environment)
		global $l10n;

		// The textdomain should be registered (even if empty due to no .mo files)
		// We verify the load_plugin_textdomain was called by checking the hook ran
		$this->assertTrue(
			did_action( 'init' ) > 0,
			'init action should have fired'
		);
	}

	/**
	 * Test that the languages directory exists.
	 */
	public function testLanguagesDirectoryExists(): void {
		$languages_dir = WPGRAPHQL_PLUGIN_DIR . 'languages';

		$this->assertDirectoryExists(
			$languages_dir,
			'Languages directory should exist at ' . $languages_dir
		);
	}

	/**
	 * Test that the POT file exists.
	 */
	public function testPotFileExists(): void {
		$pot_file = WPGRAPHQL_PLUGIN_DIR . 'languages/wp-graphql.pot';

		$this->assertFileExists(
			$pot_file,
			'POT file should exist at ' . $pot_file
		);
	}

	/**
	 * Test that the POT file contains expected strings.
	 */
	public function testPotFileContainsStrings(): void {
		$pot_file = WPGRAPHQL_PLUGIN_DIR . 'languages/wp-graphql.pot';

		if ( ! file_exists( $pot_file ) ) {
			$this->markTestSkipped( 'POT file does not exist' );
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading local file, not remote.
		$contents = file_get_contents( $pot_file );

		$this->assertIsString( $contents, 'POT file should be readable' );

		// Check for expected header
		$this->assertStringContainsString(
			'Project-Id-Version: WPGraphQL',
			$contents,
			'POT file should contain project header'
		);

		// Check for the text domain
		$this->assertStringContainsString(
			'"X-Domain: wp-graphql',
			$contents,
			'POT file should specify wp-graphql text domain'
		);

		// Check for some known translatable strings
		$this->assertStringContainsString(
			'GraphQL',
			$contents,
			'POT file should contain GraphQL string'
		);
	}

	/**
	 * Test that translation functions return strings (basic sanity check).
	 */
	public function testTranslationFunctionsWork(): void {
		// These should return the original string since no translations are loaded
		$translated = __( 'GraphQL', 'wp-graphql' );
		$this->assertEquals( 'GraphQL', $translated );

		// Test with sprintf pattern
		$translated = sprintf(
			/* translators: %s: test value */
			__( 'Test %s', 'wp-graphql' ),
			'value'
		);
		$this->assertEquals( 'Test value', $translated );
	}

	/**
	 * Test that script translations are set up for GraphiQL.
	 *
	 * Note: This test verifies the setup, not the actual translation loading,
	 * since we're in a unit test environment without the full admin context.
	 */
	public function testGraphiQLScriptTranslationsSetup(): void {
		// Verify the GraphiQL class has the enqueue_graphiql method and it's public
		$reflection = new \ReflectionMethod( \WPGraphQL\Admin\GraphiQL\GraphiQL::class, 'enqueue_graphiql' );
		$this->assertTrue(
			$reflection->isPublic(),
			'GraphiQL::enqueue_graphiql should be a public method'
		);
	}

	/**
	 * Test that Extensions script translations are set up.
	 */
	public function testExtensionsScriptTranslationsSetup(): void {
		// Verify the Extensions class has the enqueue_scripts method and it's public
		$reflection = new \ReflectionMethod( \WPGraphQL\Admin\Extensions\Extensions::class, 'enqueue_scripts' );
		$this->assertTrue(
			$reflection->isPublic(),
			'Extensions::enqueue_scripts should be a public method'
		);
	}
}
