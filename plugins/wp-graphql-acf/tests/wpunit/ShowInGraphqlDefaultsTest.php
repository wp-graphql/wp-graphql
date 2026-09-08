<?php

use WPGraphQL\Acf\Utils;

/**
 * Characterization tests for the `show_in_graphql` visibility contract.
 *
 * The contract: field groups, fields, and options pages are shown in GraphQL
 * unless explicitly configured with `show_in_graphql => false`. In particular,
 * `show_in_rest` has no effect on GraphQL visibility, and options pages are
 * shown regardless of their `post_id` (see the custom post_id regression below).
 *
 * Explicit `show_in_graphql => true/false` handling is covered in RegistryTest.
 */
class ShowInGraphqlDefaultsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function setUp(): void {
		WPGraphQL::clear_schema();
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testFieldGroupWithNoShowInGraphqlIsShownByDefault(): void {
		$this->assertTrue(
			Utils::should_field_group_show_in_graphql(
				[
					'key'   => 'group_default_visibility',
					'title' => 'Default Visibility Group',
				]
			)
		);
	}

	public function testShowInRestHasNoEffectOnFieldGroupVisibility(): void {
		// A field group opted out of REST is still shown in GraphQL.
		$this->assertTrue(
			Utils::should_field_group_show_in_graphql(
				[
					'key'          => 'group_rest_false',
					'title'        => 'Rest False Group',
					'show_in_rest' => false,
				]
			)
		);

		// A field group opted into REST is shown in GraphQL (unchanged).
		$this->assertTrue(
			Utils::should_field_group_show_in_graphql(
				[
					'key'          => 'group_rest_true',
					'title'        => 'Rest True Group',
					'show_in_rest' => true,
				]
			)
		);
	}

	public function testOptionsPageConfigWithNoShowInGraphqlIsShownByDefault(): void {
		// As Registry::register_options_pages() sees it (is_options_page flag applied).
		$this->assertTrue(
			Utils::should_field_group_show_in_graphql(
				[
					'page_title'      => 'Default Options Page',
					'menu_slug'       => 'default-options-page',
					'post_id'         => 'options',
					'is_options_page' => true,
				]
			)
		);
	}

	public function testOptionsPageWithCustomPostIdIsShownByDefault(): void {
		// Regression guard: options pages registered with a custom post_id were
		// once hidden by a show_in_rest fallback keyed off `'options' !== post_id`.
		$this->assertTrue(
			Utils::should_field_group_show_in_graphql(
				[
					'page_title'      => 'Custom PostId Options Page',
					'menu_slug'       => 'custom-post-id-options-page',
					'post_id'         => 'custom_settings',
					'is_options_page' => true,
				]
			)
		);

		// The raw config as acf_get_options_pages() returns it, before the
		// is_options_page flag is applied.
		$this->assertTrue(
			Utils::should_field_group_show_in_graphql(
				[
					'page_title' => 'Custom PostId Options Page',
					'menu_slug'  => 'custom-post-id-options-page',
					'post_id'    => 'custom_settings',
				]
			)
		);
	}

	public function testFieldGroupWithNoShowInGraphqlIsQueryable(): void {
		// Register a field group and field directly (not via the test helpers,
		// which set show_in_graphql) so neither declares a show_in_graphql value.
		acf_add_local_field_group(
			[
				'key'                => 'group_default_shown',
				'title'              => 'Default Shown Group',
				'graphql_field_name' => 'defaultShownFields',
				'location'           => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						],
					],
				],
			]
		);

		acf_add_local_field(
			[
				'parent' => 'group_default_shown',
				'key'    => 'field_default_shown_text',
				'label'  => 'Default Shown Text',
				'name'   => 'default_shown_text',
				'type'   => 'text',
			]
		);

		$post_id = self::factory()->post->create(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Default Visibility Test Post',
				'post_content' => 'test',
			]
		);

		$expected = 'default visibility value';
		update_field( 'field_default_shown_text', $expected, $post_id );

		$query = '
		query GetPostWithDefaultShownFields( $postId: Int ) {
			postBy( postId: $postId ) {
				defaultShownFields {
					defaultShownText
				}
			}
		}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'postId' => $post_id,
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['defaultShownFields']['defaultShownText'] );
	}
}
