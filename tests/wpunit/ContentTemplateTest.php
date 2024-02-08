<?php

class ContentTemplateTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $template_slugs = [ // These are the slugs of the files in tests/_data/templates
		'custom',
		'custom-i18n',
		'תבנית-שלי',
	];

	public function setUp(): void {
		parent::setUp();
		// Copy the custom templates to the theme
		foreach ( $this->template_slugs as $template_slug ) {
			$custom_template      = codecept_data_dir( "templates/$template_slug.php" );
			$custom_template_path = get_stylesheet_directory() . "/$template_slug.php";
			copy( $custom_template, $custom_template_path );
		}

		$this->clearSchema();
	}

	public function tearDown(): void {
		// Remove the custom template from the theme
		foreach ( $this->template_slugs as $template_slug ) {
			unlink( get_stylesheet_directory() . "/$template_slug.php" );
		}

		$this->clearSchema();

		parent::tearDown();
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

	public function testRegisteredContentTemplateType(): void {
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

	public function testCustomTemplate(): void {
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

	public function testI18nTemplate(): void {
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

	public function testI18nTemplateFileName(): void {
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
}
