<?php

/**
 * Tests for querying ACF fields when the field group is assigned to a Page Template.
 *
 * Covers default page templates, custom page templates registered via the
 * theme_templates filter, and the not-equals (!=) location operator.
 *
 * Built strictly with free ACF core field types so the entire test suite executes
 * without requiring ACF PRO.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4550
 */
class TemplateLocationsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * Created test page IDs to clean up in tearDown.
	 *
	 * @var array<int>
	 */
	protected array $created_page_ids = [];

	/**
	 * Set up test environment and register custom theme templates.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'theme_templates', [ $this, 'filter_theme_templates' ], 10, 4 );
		$this->clearSchema();
	}

	/**
	 * Clean up test environment and remove custom theme template filter.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'theme_templates', [ $this, 'filter_theme_templates' ], 10 );

		foreach ( $this->created_page_ids as $page_id ) {
			wp_delete_post( $page_id, true );
		}
		$this->created_page_ids = [];

		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Filter theme templates to expose custom page templates for testing.
	 *
	 * @param array<string,string> $templates Array of template file paths and names.
	 * @param \WP_Theme            $theme     The active theme instance.
	 * @param \WP_Post|null        $post      The post object if available.
	 * @param string               $post_type The post type slug.
	 *
	 * @return array<string,string>
	 */
	public function filter_theme_templates( array $templates, $theme = null, $post = null, string $post_type = 'page' ): array {
		if ( 'page' === $post_type ) {
			$templates['templates/custom-landing.php'] = 'Custom Landing';
			$templates['templates/full-width.php']     = 'Full Width';
		}

		return $templates;
	}

	/**
	 * Test basic query on post template typename.
	 *
	 * @return void
	 */
	public function testBasicQuery(): void {
		$query  = '{ posts { nodes { id template { __typename } } } }';
		$actual = $this->graphql( [ 'query' => $query ] );
		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	/**
	 * Test that a field group assigned to the Default Template resolves its value
	 * via the ... on DefaultTemplate inline fragment.
	 *
	 * @return void
	 */
	public function testFieldGroupAssignedToDefaultTemplateResolvesValue(): void {
		$this->register_acf_field(
			[
				'key'                => 'field_default_headline',
				'label'              => 'Default Headline',
				'name'               => 'default_headline',
				'type'               => 'text',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'defaultHeadline',
			],
			[
				'key'                => 'group_default_template_fields',
				'title'              => 'Default Template Fields',
				'graphql_field_name' => 'defaultTemplateFields',
				'show_in_graphql'    => 1,
				'location'           => [
					[
						[
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => 'default',
						],
					],
				],
				'active'             => true,
			]
		);

		$expected_value = 'Welcome to the default template';
		update_field( 'default_headline', $expected_value, $this->published_page->ID );

		$query = '
		query GetDefaultTemplateData( $id: ID! ) {
		  page( id: $id, idType: DATABASE_ID ) {
		    databaseId
		    template {
		      __typename
		      templateName
		      ... on DefaultTemplate {
		        defaultTemplateFields {
		          defaultHeadline
		        }
		      }
		    }
		  }
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $this->published_page->ID,
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'page.databaseId', $this->published_page->ID ),
				$this->expectedField( 'page.template.__typename', 'DefaultTemplate' ),
				$this->expectedField( 'page.template.templateName', 'Default' ),
				$this->expectedField( 'page.template.defaultTemplateFields.defaultHeadline', $expected_value ),
			]
		);
	}

	/**
	 * Test that a field group assigned to a custom page template registered via
	 * the theme_templates filter resolves its value on Template_<Name>.
	 *
	 * @return void
	 */
	public function testFieldGroupAssignedToCustomTemplateResolvesValue(): void {
		$template_file = 'templates/custom-landing.php';

		$this->register_acf_field(
			[
				'key'                => 'field_custom_subheading',
				'label'              => 'Custom Subheading',
				'name'               => 'custom_subheading',
				'type'               => 'text',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'customSubheading',
			],
			[
				'key'                => 'group_custom_template_fields',
				'title'              => 'Custom Template Fields',
				'graphql_field_name' => 'customLandingFields',
				'show_in_graphql'    => 1,
				'location'           => [
					[
						[
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => $template_file,
						],
					],
				],
				'active'             => true,
			]
		);

		$custom_page = self::factory()->post->create_and_get(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Custom Landing Page',
				'meta_input'  => [
					'_wp_page_template' => $template_file,
				],
			]
		);
		$this->created_page_ids[] = $custom_page->ID;

		$expected_value = 'High-converting landing page headline';
		update_field( 'custom_subheading', $expected_value, $custom_page->ID );

		$query = '
		query GetCustomLandingData( $id: ID! ) {
		  page( id: $id, idType: DATABASE_ID ) {
		    databaseId
		    template {
		      __typename
		      templateName
		      ... on Template_CustomLanding {
		        customLandingFields {
		          customSubheading
		        }
		      }
		    }
		  }
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $custom_page->ID,
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'page.databaseId', $custom_page->ID ),
				$this->expectedField( 'page.template.__typename', 'Template_CustomLanding' ),
				$this->expectedField( 'page.template.templateName', 'Custom Landing' ),
				$this->expectedField( 'page.template.customLandingFields.customSubheading', $expected_value ),
			]
		);
	}

	/**
	 * Test that a field group with the not-equals (!=) operator excludes the targeted
	 * template from the schema while attaching to all other available templates.
	 *
	 * @return void
	 */
	public function testFieldGroupWithNotEqualsOperatorExcludesTargetTemplate(): void {
		$excluded_template = 'templates/custom-landing.php';

		$this->register_acf_field(
			[
				'key'                => 'field_shared_note',
				'label'              => 'Shared Note',
				'name'               => 'shared_note',
				'type'               => 'text',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'sharedNote',
			],
			[
				'key'                => 'group_non_custom_landing_fields',
				'title'              => 'Non Custom Landing Fields',
				'graphql_field_name' => 'nonCustomLandingFields',
				'show_in_graphql'    => 1,
				'location'           => [
					[
						[
							'param'    => 'page_template',
							'operator' => '!=',
							'value'    => $excluded_template,
						],
					],
				],
				'active'             => true,
			]
		);

		$full_width_page = self::factory()->post->create_and_get(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Full Width Page',
				'meta_input'  => [
					'_wp_page_template' => 'templates/full-width.php',
				],
			]
		);
		$this->created_page_ids[] = $full_width_page->ID;

		$custom_page = self::factory()->post->create_and_get(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Custom Landing Page',
				'meta_input'  => [
					'_wp_page_template' => $excluded_template,
				],
			]
		);
		$this->created_page_ids[] = $custom_page->ID;

		$expected_default_value    = 'Note on default template';
		$expected_full_width_value = 'Note on full width template';
		update_field( 'shared_note', $expected_default_value, $this->published_page->ID );
		update_field( 'shared_note', $expected_full_width_value, $full_width_page->ID );

		// 1. Verify field resolves on DefaultTemplate.
		$default_query = '
		query GetDefault( $id: ID! ) {
		  page( id: $id, idType: DATABASE_ID ) {
		    template {
		      __typename
		      ... on DefaultTemplate {
		        nonCustomLandingFields {
		          sharedNote
		        }
		      }
		    }
		  }
		}
		';

		$default_actual = $this->graphql(
			[
				'query'     => $default_query,
				'variables' => [
					'id' => $this->published_page->ID,
				],
			]
		);

		self::assertQuerySuccessful(
			$default_actual,
			[
				$this->expectedField( 'page.template.__typename', 'DefaultTemplate' ),
				$this->expectedField( 'page.template.nonCustomLandingFields.sharedNote', $expected_default_value ),
			]
		);

		// 2. Verify field resolves on Template_FullWidth (another non-excluded template).
		$full_width_query = '
		query GetFullWidth( $id: ID! ) {
		  page( id: $id, idType: DATABASE_ID ) {
		    template {
		      __typename
		      ... on Template_FullWidth {
		        nonCustomLandingFields {
		          sharedNote
		        }
		      }
		    }
		  }
		}
		';

		$full_width_actual = $this->graphql(
			[
				'query'     => $full_width_query,
				'variables' => [
					'id' => $full_width_page->ID,
				],
			]
		);

		self::assertQuerySuccessful(
			$full_width_actual,
			[
				$this->expectedField( 'page.template.__typename', 'Template_FullWidth' ),
				$this->expectedField( 'page.template.nonCustomLandingFields.sharedNote', $expected_full_width_value ),
			]
		);

		// 3. Verify field is excluded from the excluded template in schema introspection.
		$introspection_query = '
		query IntrospectExcludedType {
		  __type( name: "Template_CustomLanding" ) {
		    fields {
		      name
		    }
		  }
		}
		';

		$introspection_actual = $this->graphql( [ 'query' => $introspection_query ] );
		$this->assertArrayNotHasKey( 'errors', $introspection_actual );

		$field_names = array_column( $introspection_actual['data']['__type']['fields'] ?? [], 'name' );
		$this->assertNotContains( 'nonCustomLandingFields', $field_names );
	}

	/**
	 * Test that querying a template-specific fragment on a page with a different
	 * template leaves the fragment unexecuted and does not return conflicting data.
	 *
	 * @return void
	 */
	public function testQueryingTemplateFieldOnNonMatchingTemplateDoesNotResolve(): void {
		$template_file = 'templates/custom-landing.php';

		$this->register_acf_field(
			[
				'key'                => 'field_exclusive_landing',
				'label'              => 'Exclusive Landing Field',
				'name'               => 'exclusive_landing',
				'type'               => 'text',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'exclusiveLanding',
			],
			[
				'key'                => 'group_exclusive_landing',
				'title'              => 'Exclusive Landing Group',
				'graphql_field_name' => 'exclusiveLandingGroup',
				'show_in_graphql'    => 1,
				'location'           => [
					[
						[
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => $template_file,
						],
					],
				],
				'active'             => true,
			]
		);

		$custom_page = self::factory()->post->create_and_get(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Custom Landing Page',
				'meta_input'  => [
					'_wp_page_template' => $template_file,
				],
			]
		);
		$this->created_page_ids[] = $custom_page->ID;

		update_field( 'exclusive_landing', 'Landing Only Value', $custom_page->ID );

		$query = '
		query GetPageWithMismatchedFragment( $id: ID! ) {
		  page( id: $id, idType: DATABASE_ID ) {
		    databaseId
		    template {
		      __typename
		      ... on Template_CustomLanding {
		        exclusiveLandingGroup {
		          exclusiveLanding
		        }
		      }
		    }
		  }
		}
		';

		// Query on default page: the fragment for Template_CustomLanding does not apply.
		$actual_default = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $this->published_page->ID,
				],
			]
		);

		self::assertQuerySuccessful(
			$actual_default,
			[
				$this->expectedField( 'page.databaseId', $this->published_page->ID ),
				$this->expectedField( 'page.template.__typename', 'DefaultTemplate' ),
			]
		);

		$this->assertArrayNotHasKey(
			'exclusiveLandingGroup',
			$actual_default['data']['page']['template'] ?? []
		);
	}
}
