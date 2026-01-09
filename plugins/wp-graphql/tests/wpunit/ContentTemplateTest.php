<?php

/**
 * Tests for ContentTemplate functionality in WPGraphQL.
 *
 * IMPORTANT: Template behavior differs between Classic and Block themes.
 *
 * Classic Themes (e.g., twentytwentyone):
 * - PHP template files placed in the theme root are recognized via get_page_templates()
 * - WPGraphQL exposes these as ContentTemplate types (e.g., MyCustomTemplate)
 * - The _wp_page_template post meta stores the template filename
 *
 * Block Themes (e.g., twentytwentyfive - default in WP 6.7+):
 * - Use block templates in the templates/ directory (HTML files with block markup)
 * - PHP template files in theme root are NOT recognized the same way
 * - Block themes have their own template registration system via theme.json
 *
 * This test class covers classic theme template functionality. Tests that require
 * classic PHP templates are skipped when a block theme is active, as this is
 * expected WordPress behavior, not a WPGraphQL limitation.
 *
 * @see https://developer.wordpress.org/themes/templates/
 */
class ContentTemplateTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * These are the slugs of the files in tests/_data/templates
	 *
	 * @var string[]
	 */
	public $template_slugs = [
		'custom',
		'custom-i18n',
		'תבנית-שלי',
	];

	/**
	 * Whether the active theme is a block theme.
	 *
	 * @var bool
	 */
	private $is_block_theme;

	/**
	 * Whether classic PHP templates can be used with the current theme.
	 *
	 * This is false when:
	 * - No theme is active
	 * - A block theme is active (block themes don't recognize PHP templates in theme root)
	 *
	 * @var bool
	 */
	private $supports_classic_templates;

	public function setUp(): void {
		parent::setUp();

		// Check if there's an active theme
		$active_theme = wp_get_theme();
		$has_active_theme = $active_theme->exists() && ! empty( $active_theme->get_template() );

		// Check if we're using a block theme (FSE theme)
		$this->is_block_theme = $has_active_theme && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

		// Classic PHP templates only work with classic (non-block) themes
		$this->supports_classic_templates = $has_active_theme && ! $this->is_block_theme;

		// Only copy classic PHP templates if the theme supports them
		if ( $this->supports_classic_templates ) {
			foreach ( $this->template_slugs as $template_slug ) {
				$custom_template      = codecept_data_dir( "templates/$template_slug.php" );
				$custom_template_path = get_stylesheet_directory() . "/$template_slug.php";
				copy( $custom_template, $custom_template_path );
			}
		}

		$this->clearSchema();
	}

	public function tearDown(): void {
		// Only clean up if we copied templates
		if ( $this->supports_classic_templates ) {
			foreach ( $this->template_slugs as $template_slug ) {
				$template_path = get_stylesheet_directory() . "/$template_slug.php";
				if ( file_exists( $template_path ) ) {
					unlink( $template_path );
				}
			}
		}

		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * Skip test with explanation when classic PHP templates aren't supported.
	 *
	 * This happens when:
	 * - No theme is active (no theme directory to copy templates to)
	 * - A block theme is active (block themes use HTML templates in templates/ directory)
	 *
	 * @param string $reason Additional context.
	 */
	private function skipIfClassicTemplatesNotSupported( string $reason = '' ): void {
		if ( ! $this->supports_classic_templates ) {
			$active_theme = wp_get_theme();
			
			if ( ! $active_theme->exists() || empty( $active_theme->get_template() ) ) {
				$message = 'This test requires an active theme. No theme is currently active.';
			} elseif ( $this->is_block_theme ) {
				$message = 'This test requires a classic theme. Block themes use a different template system (HTML files in templates/ directory).';
			} else {
				$message = 'This test requires classic PHP template support.';
			}
			
			if ( $reason ) {
				$message .= ' ' . $reason;
			}
			
			$this->markTestSkipped( $message );
		}
	}

	public function getQuery(): string {
		return '
			query GetContentNodeWithTemplate( $id: ID! ) {
				contentNode( id: $id, idType: DATABASE_ID ) {
					databaseId
					... on NodeWithTemplate {
						template {
							__typename
							templateName
						}
					}
				}
			}
		';
	}

	public function runContentTemplateTest( $post_id, $expected_type_name, $expected_template_name ) {
		$query = $this->getQuery();

		$variables = [
			'id' => $post_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $post_id, $actual['data']['contentNode']['databaseId'] );
		$this->assertSame( $expected_type_name, $actual['data']['contentNode']['template']['__typename'] );
		$this->assertSame( $expected_template_name, $actual['data']['contentNode']['template']['templateName'] );
	}

	/**
	 * Test that custom PHP templates are registered as GraphQL types.
	 *
	 * This test verifies that classic PHP template files (with Template Name headers)
	 * are exposed as ContentTemplate types in the GraphQL schema.
	 *
	 * Note: Block themes use a different template system and won't register
	 * PHP templates the same way.
	 */
	public function testRegisteredContentTemplateType(): void {
		$this->skipIfClassicTemplatesNotSupported( 'PHP template files are not registered as page templates in block themes.' );

		// Introspect the types implementing the ContentTemplate interface.
		$query = '
			query GetContentTemplateTypes {
				__type(name:"ContentTemplate"){
					possibleTypes{
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$possible_types = wp_list_pluck( $actual['data']['__type']['possibleTypes'], 'name' );

		$this->assertContains( 'DefaultTemplate', $possible_types );
		$this->assertContains( 'MyCustomTemplate', $possible_types );
		$this->assertContains( 'Template_CustomI18n', $possible_types );
	}


	public function testDefaultTemplate(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Default Template',
				'post_content' => 'This is the default template',
				'post_status'  => 'publish',
			]
		);

		$expected_type_name     = 'DefaultTemplate';
		$expected_template_name = 'Default';

		$this->runContentTemplateTest( $post_id, $expected_type_name, $expected_template_name );
	}

	public function testPostWithTemplate(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'post',
				'post_title'   => 'Post with Template',
				'post_content' => 'This is a post with a template',
				'post_status'  => 'publish',
			]
		);

		$expected_type_name     = 'DefaultTemplate';
		$expected_template_name = 'Default';

		$this->runContentTemplateTest( $post_id, $expected_type_name, $expected_template_name );
	}

	/**
	 * Test that a page with a custom PHP template returns the correct template type.
	 *
	 * This test verifies that when a page is assigned a custom PHP template,
	 * WPGraphQL correctly identifies and returns the template information.
	 */
	public function testCustomTemplate(): void {
		$this->skipIfClassicTemplatesNotSupported( 'Custom PHP templates require a classic theme to be recognized.' );

		$template_slug = 'custom';

		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Custom Template',
				'post_content' => 'This is a custom template',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_wp_page_template' => "$template_slug.php",
				],
			]
		);

		// The type name is derived from the template name.
		$expected_type_name     = 'MyCustomTemplate';
		$expected_template_name = 'My Custom Template';

		$this->runContentTemplateTest( $post_id, $expected_type_name, $expected_template_name );
	}

	/**
	 * Test that templates with internationalized names work correctly.
	 *
	 * This test verifies that PHP templates with non-ASCII characters in their
	 * Template Name header are properly handled by WPGraphQL.
	 */
	public function testI18nTemplate(): void {
		$this->skipIfClassicTemplatesNotSupported( 'Custom PHP templates require a classic theme to be recognized.' );

		$template_slug = 'custom-i18n';

		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Custom i18n Template',
				'post_content' => 'This is a custom template',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_wp_page_template' => "$template_slug.php",
				],
			]
		);

		$expected_type_name = 'Template_CustomI18n';

		$registered_templates = wp_get_theme()->get_page_templates( get_post( $post_id ), 'page' );
		$template_key         = get_page_template_slug( $post_id );

		// The template name should be the i18n name inside the file.
		$expected_template_name = $registered_templates[ $template_key ];

		$this->runContentTemplateTest( $post_id, $expected_type_name, $expected_template_name );
	}

	/**
	 * Test that templates with non-ASCII filenames are handled gracefully.
	 *
	 * This test verifies that PHP templates with non-ASCII characters in their
	 * filename produce a debug message but still return template information.
	 */
	public function testI18nTemplateFileName(): void {
		$this->skipIfClassicTemplatesNotSupported( 'Custom PHP templates require a classic theme to be recognized.' );

		$template_slug = 'תבנית-שלי';

		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Custom i18n Template and File',
				'post_content' => 'This is a custom template',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_wp_page_template' => "$template_slug.php",
				],
			]
		);

		$query = $this->getQuery();

		$variables = [
			'id' => $post_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertStringContainsString( 'Unable to register the תבנית-שלי.php template file as a GraphQL Type.', $actual['extensions']['debug'][0]['message'] );

		$this->assertEquals( 'DefaultTemplate', $actual['data']['contentNode']['template']['__typename'] );
		$this->assertEquals( 'תבנית שלי', $actual['data']['contentNode']['template']['templateName'] );
	}

	/**
	 * Test that block theme templates are exposed via GraphQL.
	 *
	 * Block themes define templates differently (HTML files in templates/ directory,
	 * or via theme.json). WordPress's get_page_templates() should return these,
	 * and WPGraphQL should expose them.
	 *
	 * This test verifies that whatever templates WordPress reports for the current
	 * theme are properly exposed in the GraphQL schema.
	 */
	public function testAvailableTemplatesAreExposed(): void {
		// Get all templates WordPress knows about for pages
		$registered_templates = wp_get_theme()->get_page_templates( null, 'page' );

		// Query the schema for available template types
		$query = '
			query GetContentTemplateTypes {
				__type(name:"ContentTemplate"){
					possibleTypes{
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$possible_types = wp_list_pluck( $actual['data']['__type']['possibleTypes'], 'name' );

		// DefaultTemplate should always exist
		$this->assertContains( 'DefaultTemplate', $possible_types );

		// Log what templates are available (helpful for debugging across theme types)
		codecept_debug( 'Theme: ' . wp_get_theme()->get( 'Name' ) );
		codecept_debug( 'Is Block Theme: ' . ( $this->is_block_theme ? 'Yes' : 'No' ) );
		codecept_debug( 'Registered Templates: ' . print_r( $registered_templates, true ) );
		codecept_debug( 'GraphQL Template Types: ' . print_r( $possible_types, true ) );

		// Each registered template should have a corresponding GraphQL type
		foreach ( $registered_templates as $file => $name ) {
			$expected_type = \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( $name, $file );
			
			// Skip templates that can't be converted to valid type names
			if ( empty( $expected_type ) ) {
				continue;
			}

			$this->assertContains(
				$expected_type,
				$possible_types,
				sprintf(
					'Template "%s" (file: %s) should be exposed as GraphQL type "%s"',
					$name,
					$file,
					$expected_type
				)
			);
		}
	}

	/**
	 * Test that querying a page returns template information regardless of theme type.
	 *
	 * This test verifies the core use case: a headless client can fetch a node
	 * and use template { __typename } to determine which component to render.
	 */
	public function testTemplateQueryReturnsTypeInfo(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Template Query Test',
				'post_content' => 'Testing template query',
				'post_status'  => 'publish',
			]
		);

		$query = '
			query GetPageTemplate( $id: ID! ) {
				page( id: $id, idType: DATABASE_ID ) {
					databaseId
					title
					template {
						__typename
						templateName
					}
				}
			}
		';

		$variables = [
			'id' => $post_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertArrayHasKey( 'data', $actual );
		$this->assertArrayHasKey( 'page', $actual['data'] );
		$this->assertArrayHasKey( 'template', $actual['data']['page'] );

		// Template should have __typename and templateName
		$template = $actual['data']['page']['template'];
		$this->assertArrayHasKey( '__typename', $template );
		$this->assertArrayHasKey( 'templateName', $template );

		// For a page with no custom template, should return DefaultTemplate
		$this->assertEquals( 'DefaultTemplate', $template['__typename'] );
		$this->assertEquals( 'Default', $template['templateName'] );

		// Log for debugging
		codecept_debug( 'Template response: ' . print_r( $template, true ) );
	}

	/**
	 * Skip test unless a block theme is active.
	 *
	 * @param string $reason Additional context.
	 */
	private function skipIfNotBlockTheme( string $reason = '' ): void {
		if ( ! $this->is_block_theme ) {
			$message = 'This test requires a block theme (FSE theme).';
			if ( $reason ) {
				$message .= ' ' . $reason;
			}
			$this->markTestSkipped( $message );
		}
	}

	/**
	 * Helper to create a custom block template for testing.
	 *
	 * Block templates are stored as wp_template custom post types and associated
	 * with a theme via the wp_theme taxonomy.
	 *
	 * @param string $slug  Template slug (e.g., 'custom-test-template').
	 * @param string $title Template title/name (can include i18n characters).
	 * @return int|WP_Error Template post ID or WP_Error on failure.
	 */
	private function createBlockTemplate( string $slug, string $title ) {
		$template_id = wp_insert_post(
			[
				'post_type'    => 'wp_template',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:post-content /-->',
			]
		);

		if ( ! is_wp_error( $template_id ) ) {
			// Associate template with the current theme
			wp_set_object_terms( $template_id, wp_get_theme()->get_stylesheet(), 'wp_theme' );
		}

		return $template_id;
	}

	/**
	 * Test that custom block templates (created via Site Editor) are exposed in GraphQL.
	 *
	 * Block themes allow users to create custom templates via the Site Editor.
	 * These are stored as wp_template post types. WPGraphQL should expose them
	 * just like it exposes classic PHP templates.
	 */
	public function testBlockThemeCustomTemplate(): void {
		$this->skipIfNotBlockTheme( 'Custom block templates are specific to block themes.' );

		$template_slug  = 'test-custom-block-template';
		$template_title = 'Test Custom Block Template';

		// Create a custom block template
		$template_id = $this->createBlockTemplate( $template_slug, $template_title );
		$this->assertNotWPError( $template_id, 'Failed to create block template' );

		// Clear schema cache to pick up new template
		$this->clearSchema();

		// Query the schema for available template types
		$query = '
			query GetContentTemplateTypes {
				__type(name:"ContentTemplate"){
					possibleTypes{
						name
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$possible_types = wp_list_pluck( $actual['data']['__type']['possibleTypes'], 'name' );

		// The custom template should be registered
		$expected_type = \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( $template_title, $template_slug );
		
		codecept_debug( 'Block template slug: ' . $template_slug );
		codecept_debug( 'Block template title: ' . $template_title );
		codecept_debug( 'Expected GraphQL type: ' . $expected_type );
		codecept_debug( 'Available types: ' . print_r( $possible_types, true ) );

		$this->assertContains(
			$expected_type,
			$possible_types,
			sprintf( 'Custom block template "%s" should be exposed as GraphQL type "%s"', $template_title, $expected_type )
		);

		// Cleanup
		wp_delete_post( $template_id, true );
	}

	/**
	 * Test that block templates with i18n names are handled correctly.
	 *
	 * Users can create block templates with non-ASCII names via the Site Editor.
	 * WPGraphQL should handle these gracefully, similar to how it handles
	 * classic i18n templates.
	 */
	public function testBlockThemeI18nTemplate(): void {
		$this->skipIfNotBlockTheme( 'Block template i18n testing is specific to block themes.' );

		$template_slug  = 'test-i18n-block-template';
		$template_title = 'カスタムブロックテンプレート'; // "Custom Block Template" in Japanese

		// Create a custom block template with i18n title
		$template_id = $this->createBlockTemplate( $template_slug, $template_title );
		$this->assertNotWPError( $template_id, 'Failed to create i18n block template' );

		// Clear schema cache
		$this->clearSchema();

		// Create a page and assign the template
		$page_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Page with i18n Block Template',
				'post_content' => 'Testing i18n block template',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_wp_page_template' => $template_slug,
				],
			]
		);

		$query = $this->getQuery();

		$variables = [
			'id' => $page_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( 'i18n Block Template Result: ' . print_r( $actual, true ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $page_id, $actual['data']['contentNode']['databaseId'] );

		// The template name should be the i18n name
		$this->assertSame( $template_title, $actual['data']['contentNode']['template']['templateName'] );

		// Cleanup
		wp_delete_post( $template_id, true );
	}

	/**
	 * Test assigning a block theme's built-in template to a page.
	 *
	 * Block themes come with built-in templates (e.g., page-no-title in twentytwentyfive).
	 * This test verifies that assigning one of these templates works correctly.
	 */
	public function testBlockThemeBuiltInTemplate(): void {
		$this->skipIfNotBlockTheme( 'Built-in block templates are specific to block themes.' );

		// Get available templates for pages
		$available_templates = wp_get_theme()->get_page_templates( null, 'page' );

		// Skip if no templates available (unlikely for block themes)
		if ( empty( $available_templates ) ) {
			$this->markTestSkipped( 'No page templates available in the current block theme.' );
		}

		// Use the first available template
		$template_slug = array_key_first( $available_templates );
		$template_name = $available_templates[ $template_slug ];

		codecept_debug( 'Testing with block theme template: ' . $template_slug . ' => ' . $template_name );

		// Create a page with the built-in template assigned
		$page_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Page with Built-in Block Template',
				'post_content' => 'Testing built-in block template',
				'post_status'  => 'publish',
				'meta_input'   => [
					'_wp_page_template' => $template_slug,
				],
			]
		);

		$query = $this->getQuery();

		$variables = [
			'id' => $page_id,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $page_id, $actual['data']['contentNode']['databaseId'] );

		// Verify the template info
		$expected_type = \WPGraphQL\Utils\Utils::format_type_name_for_wp_template( $template_name, $template_slug );
		
		$this->assertSame( $expected_type, $actual['data']['contentNode']['template']['__typename'] );
		$this->assertSame( $template_name, $actual['data']['contentNode']['template']['templateName'] );

		codecept_debug( 'Built-in template response: ' . print_r( $actual['data']['contentNode']['template'], true ) );
	}
}
