<?php

use WPGraphQL\Acf\Utils;

/**
 * Tests for the `show_in_graphql` visibility contract.
 *
 * The contract:
 * - Field groups and fields are shown in GraphQL unless explicitly configured
 *   with `show_in_graphql => false`. `show_in_rest` has no effect.
 * - Options Pages are an explicit opt-in: only pages registered with
 *   `show_in_graphql => true` are added to the schema, matching the default of
 *   the ACF UI registration screen.
 *
 * Explicit `show_in_graphql => true/false` handling for field groups is covered
 * in RegistryTest.
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

	public function testOptionsPageWithNoShowInGraphqlIsExcludedByDefault(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			$this->markTestSkipped( 'ACF Options Pages are not available in this test environment' );
		}

		acf_add_options_page(
			[
				'page_title' => 'Default Unset Page',
				'menu_slug'  => 'default-unset-page',
			]
		);

		$this->assertArrayNotHasKey( 'default-unset-page', Utils::get_acf_options_pages() );
	}

	public function testOptionsPageWithShowInGraphqlTrueIsIncluded(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			$this->markTestSkipped( 'ACF Options Pages are not available in this test environment' );
		}

		acf_add_options_page(
			[
				'page_title'      => 'Opted In Page',
				'menu_slug'       => 'opted-in-page',
				'show_in_graphql' => true,
			]
		);

		// Regression guard: options pages registered with a custom post_id were
		// once hidden by a show_in_rest fallback keyed off `'options' !== post_id`.
		acf_add_options_page(
			[
				'page_title'      => 'Opted In Custom PostId Page',
				'menu_slug'       => 'opted-in-custom-post-id-page',
				'post_id'         => 'custom_settings',
				'show_in_graphql' => true,
			]
		);

		$graphql_pages = Utils::get_acf_options_pages();

		$this->assertArrayHasKey( 'opted-in-page', $graphql_pages );
		$this->assertArrayHasKey( 'opted-in-custom-post-id-page', $graphql_pages );
	}

	public function testAcfPreservesShowInGraphqlOnProgrammaticOptionsPages(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			$this->markTestSkipped( 'ACF Options Pages are not available in this test environment' );
		}

		// Calls acf_get_options_pages() directly (not Utils::get_acf_options_pages())
		// to observe what ACF itself preserves from the registration args.
		acf_add_options_page(
			[
				'page_title'      => 'Probe False',
				'menu_slug'       => 'probe-false',
				'show_in_graphql' => false,
			]
		);

		acf_add_options_page(
			[
				'page_title'      => 'Probe True',
				'menu_slug'       => 'probe-true',
				'show_in_graphql' => true,
			]
		);

		$pages = acf_get_options_pages();

		codecept_debug( $pages['probe-false'] ?? 'probe-false missing' );

		$this->assertArrayHasKey( 'probe-false', $pages );
		$this->assertArrayHasKey( 'show_in_graphql', $pages['probe-false'] );
		$this->assertFalse( (bool) $pages['probe-false']['show_in_graphql'] );

		$this->assertArrayHasKey( 'probe-true', $pages );
		$this->assertArrayHasKey( 'show_in_graphql', $pages['probe-true'] );
		$this->assertTrue( (bool) $pages['probe-true']['show_in_graphql'] );
	}

	public function testOptionsPageWithShowInGraphqlFalseIsExcluded(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			$this->markTestSkipped( 'ACF Options Pages are not available in this test environment' );
		}

		acf_add_options_page(
			[
				'page_title'      => 'Opted Out Page',
				'menu_slug'       => 'opted-out-page',
				'show_in_graphql' => false,
			]
		);

		$graphql_pages = Utils::get_acf_options_pages();

		$this->assertArrayNotHasKey( 'opted-out-page', $graphql_pages );
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
