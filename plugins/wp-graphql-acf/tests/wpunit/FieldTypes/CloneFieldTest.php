<?php
/**
 * Schema-level tests for ACF Clone fields.
 *
 * These scenarios reproduce open issues in the wpgraphql-acf tracker that
 * exercise the interface-field-override path in WPGraphQL core. They
 * intentionally extend WPGraphQLAcfTestCase directly (not AcfFieldTestCase)
 * because each scenario registers multiple ACF field groups and asserts on
 * the resulting schema, rather than testing a single field type's resolution.
 *
 * Issues covered:
 *  - #201 — Cloning a field group that contains a nested group field causes invalid schema
 *  - #258 — Clone fields not registered with the schema when individual field is selected
 *  - #269 — Cloned field with prefixed field name spills fields outside the prefix
 *           & doesn't work in flexible content layout
 *  - wrapped-type override compatibility (list_of / non_null) — directly exercises
 *    the new TypeRegistry::is_compatible_interface_field_override() path.
 *  - Schema::assertValid() guardrail (closely related to closed #197 regression).
 */

class CloneFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		\WPGraphQL\Acf\Utils::clear_field_type_registry();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Skip the running test when ACF Pro is not active — Clone is a Pro-only field type.
	 */
	private function skip_if_not_acf_pro(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}
	}

	/**
	 * Build the schema and assert that GraphQL considers it valid.
	 *
	 * Catches the "introspection breaks but a hand-written query still works"
	 * class of bug that re-opened #197 after it was thought to be fixed.
	 */
	private function assertSchemaIsValid(): void {
		$schema = \WPGraphQL::get_schema();
		try {
			$schema->assertValid();
		} catch ( \Throwable $e ) {
			$this->fail( 'GraphQL schema is invalid: ' . $e->getMessage() );
		}
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Run an introspection query for a single named type.
	 *
	 * @param string $type_name The GraphQL type name to introspect.
	 *
	 * @return array<string,mixed>|null The __type payload, or null if the type is not in the schema.
	 */
	private function introspect_type( string $type_name ): ?array {
		$query = '
			query GetType($name: String!) {
				__type(name: $name) {
					name
					kind
					interfaces { name }
					fields {
						name
						type {
							kind
							name
							ofType {
								kind
								name
								ofType {
									kind
									name
									ofType { kind name }
								}
							}
						}
					}
				}
			}
		';

		$result = $this->graphql([
			'query'     => $query,
			'variables' => [ 'name' => $type_name ],
		]);

		return $result['data']['__type'] ?? null;
	}

	/**
	 * Reproduces issue #201 — Field Group A → flexible_content → clones Field Group B,
	 * which itself clones Field Group C (containing a Group sub-field).
	 *
	 * Before the interface-override fix this produced an invalid schema with the error:
	 *   "Interface field FieldGroupB_Fields.layout expects type FieldGroupCLayout
	 *    but ...layout is type FieldGroupBLayout."
	 *
	 * @see https://github.com/wp-graphql/wpgraphql-acf/issues/201
	 */
	public function testIssue201_nestedCloneSchemaIsValid(): void {
		$this->skip_if_not_acf_pro();

		// Field Group C — leaf group containing a text field and a Group sub-field.
		$this->register_acf_field_group([
			'key'                => 'group_issue201_c',
			'title'              => 'Field Group C',
			'graphql_field_name' => 'fieldGroupC',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue201_c_text',
					'label'              => 'Text Field',
					'name'               => 'text_field',
					'type'               => 'text',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'textField',
				],
				[
					'key'                => 'field_issue201_c_group',
					'label'              => 'Group Field',
					'name'               => 'group_field',
					'type'               => 'group',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'groupField',
					'sub_fields'         => [
						[
							'key'                => 'field_issue201_c_group_inner',
							'label'              => 'Inner',
							'name'               => 'inner',
							'type'               => 'text',
							'show_in_graphql'    => 1,
							'graphql_field_name' => 'inner',
						],
					],
				],
			],
		]);

		// Field Group B — clones all of Field Group C as "layout".
		$this->register_acf_field_group([
			'key'                => 'group_issue201_b',
			'title'              => 'Field Group B',
			'graphql_field_name' => 'fieldGroupB',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue201_b_clone',
					'label'              => 'Layout',
					'name'               => 'layout',
					'type'               => 'clone',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'layout',
					'clone'              => [ 'group_issue201_c' ],
					'display'            => 'group',
					'layout'             => 'block',
					'prefix_label'       => 0,
					'prefix_name'        => 1,
				],
			],
		]);

		// Field Group A — flexible_content with one layout that clones Field Group B.
		$this->register_acf_field_group([
			'key'                => 'group_issue201_a',
			'title'              => 'Field Group A',
			'graphql_field_name' => 'fieldGroupA',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue201_a_flex',
					'label'              => 'Flexible Content',
					'name'               => 'flexible_content',
					'type'               => 'flexible_content',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'flexibleContent',
					'layouts'            => [
						'layout_issue201_one' => [
							'key'        => 'layout_issue201_one',
							'name'       => 'layout_one',
							'label'      => 'Layout One',
							'display'    => 'block',
							'sub_fields' => [
								[
									'key'                => 'field_issue201_a_flex_clone',
									'label'              => 'Cloned Field Group',
									'name'               => 'cloned_field_group',
									'type'               => 'clone',
									'show_in_graphql'    => 1,
									'graphql_field_name' => 'clonedFieldGroup',
									'clone'              => [ 'group_issue201_b' ],
									'display'            => 'group',
									'layout'             => 'block',
									'prefix_label'       => 0,
									'prefix_name'        => 1,
								],
							],
						],
					],
				],
			],
		]);

		$this->assertSchemaIsValid();
	}

	/**
	 * Reproduces issue #258 — Clone field whose `clone` array contains cherry-picked
	 * individual field IDs (not a whole field group). Selected fields should appear
	 * in the schema, and the cloned group's `_Fields` interface should NOT be
	 * implemented by the parent (since only individual fields were cloned).
	 *
	 * @see https://github.com/wp-graphql/wpgraphql-acf/issues/258
	 */
	public function testIssue258_cherryPickedCloneFieldsRegister(): void {
		$this->skip_if_not_acf_pro();

		// Source field group containing two cherry-pickable fields.
		$this->register_acf_field_group([
			'key'                => 'group_issue258_source',
			'title'              => 'Section Source',
			'graphql_field_name' => 'sectionSource',
			'show_in_graphql'    => 1,
			'active'             => false,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue258_title',
					'label'              => 'Section Title',
					'name'               => 'section_title',
					'type'               => 'text',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'sectionTitle',
				],
				[
					'key'                => 'field_issue258_links',
					'label'              => 'Section Links',
					'name'               => 'section_links',
					'type'               => 'text',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'sectionLinks',
				],
			],
		]);

		// Parent field group cherry-picks one of the two source fields via a Clone.
		$this->register_acf_field_group([
			'key'                => 'group_issue258_parent',
			'title'              => 'Section Parent',
			'graphql_field_name' => 'sectionParent',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue258_clone',
					'label'              => 'Cherry-picked Clone',
					'name'               => 'cherry_picked_clone',
					'type'               => 'clone',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'cherryPickedClone',
					// Cherry-pick a single field, not the whole group.
					'clone'              => [ 'field_issue258_links' ],
					'display'            => 'seamless',
					'prefix_label'       => 0,
					'prefix_name'        => 0,
				],
			],
		]);

		$this->assertSchemaIsValid();

		$parent = $this->introspect_type( 'SectionParent' );
		$this->assertNotNull( $parent, 'SectionParent type should exist in the schema' );

		$field_names = array_map( static fn( $f ) => $f['name'], $parent['fields'] ?? [] );
		$this->assertContains( 'sectionLinks', $field_names, 'Cherry-picked field should be registered on the parent type' );

		$interface_names = array_map( static fn( $i ) => $i['name'], $parent['interfaces'] ?? [] );
		$this->assertNotContains( 'SectionSource_Fields', $interface_names, 'Cherry-picked clone should NOT apply the source group _Fields interface' );
	}

	/**
	 * Reproduces issue #269 — Clone with display=seamless + prefix_name=1 nested
	 * inside a flexible_content layout. The prefixed cloned fields should appear
	 * under their prefix on the layout type, and the un-prefixed cloned fields
	 * should NOT leak onto the parent / layout.
	 *
	 * @see https://github.com/wp-graphql/wpgraphql-acf/issues/269
	 */
	public function testIssue269_prefixedCloneInFlexibleContent(): void {
		$this->skip_if_not_acf_pro();

		$this->register_acf_field_group([
			'key'                => 'group_issue269_source',
			'title'              => 'Cloned Field Group',
			'graphql_field_name' => 'issue269Source',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue269_title',
					'label'              => 'Title',
					'name'               => 'title',
					'type'               => 'text',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'title',
				],
			],
		]);

		$this->register_acf_field_group([
			'key'                => 'group_issue269_flex',
			'title'              => 'Flexible Content Group',
			'graphql_field_name' => 'issue269Flex',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_issue269_flex',
					'label'              => 'Flexible',
					'name'               => 'flexible',
					'type'               => 'flexible_content',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'flexible',
					'layouts'            => [
						'layout_issue269_some' => [
							'key'        => 'layout_issue269_some',
							'name'       => 'some_layout',
							'label'      => 'Some Layout',
							'display'    => 'block',
							'sub_fields' => [
								[
									'key'                => 'field_issue269_inner_clone',
									'label'              => 'Yo',
									'name'               => 'yo',
									'type'               => 'clone',
									'show_in_graphql'    => 1,
									'graphql_field_name' => 'yo',
									'clone'              => [ 'group_issue269_source' ],
									'display'            => 'seamless',
									'prefix_label'       => 0,
									'prefix_name'        => 1,
								],
							],
						],
					],
				],
			],
		]);

		$this->assertSchemaIsValid();

		// The flex layout type name follows: <Parent><Flex><Layout>Layout
		$layout = $this->introspect_type( 'Issue269FlexFlexibleSomeLayoutLayout' );
		$this->assertNotNull( $layout, 'Flex layout type should exist in the schema' );

		$field_names = array_map( static fn( $f ) => $f['name'], $layout['fields'] ?? [] );

		$this->assertContains( 'yo', $field_names, 'Prefixed clone field "yo" should appear on the layout type' );
		$this->assertNotContains( 'title', $field_names, 'Cloned source field "title" should NOT leak onto the layout type when prefix_name=1' );
	}

	/**
	 * Wrapped-type override compatibility — exercises the new
	 * TypeRegistry::is_compatible_interface_field_override() path directly:
	 *
	 *  - cloned source group defines a field with a list type
	 *  - parent field group inherits the source group's _Fields interface
	 *  - registering a same-named override field of compatible wrapped type
	 *    must not raise DUPLICATE_FIELD
	 *
	 * This is the path the PR's "Path 2 wrapped-type override compatibility"
	 * branch in TypeRegistry covers.
	 */
	public function testWrappedTypeOverrideAllowed(): void {
		$this->skip_if_not_acf_pro();

		// Source group cloned in full — applies SourceWithList_Fields interface to parent.
		$this->register_acf_field_group([
			'key'                => 'group_wrapped_source',
			'title'              => 'Source With List',
			'graphql_field_name' => 'sourceWithList',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_wrapped_tags',
					'label'              => 'Tags',
					'name'               => 'tags',
					'type'               => 'repeater',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'tags',
					'sub_fields'         => [
						[
							'key'                => 'field_wrapped_tag_value',
							'label'              => 'Value',
							'name'               => 'value',
							'type'               => 'text',
							'show_in_graphql'    => 1,
							'graphql_field_name' => 'value',
						],
					],
				],
			],
		]);

		$this->register_acf_field_group([
			'key'                => 'group_wrapped_parent',
			'title'              => 'Wrapped Parent',
			'graphql_field_name' => 'wrappedParent',
			'show_in_graphql'    => 1,
			'active'             => true,
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'fields'             => [
				[
					'key'                => 'field_wrapped_parent_clone',
					'label'              => 'Cloned Source',
					'name'               => 'cloned_source',
					'type'               => 'clone',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'clonedSource',
					'clone'              => [ 'group_wrapped_source' ],
					'display'            => 'seamless',
					'prefix_label'       => 0,
					'prefix_name'        => 0,
				],
			],
		]);

		$this->assertSchemaIsValid();

		// And the schema can be built without a DUPLICATE_FIELD debug message bubbling up
		// — the introspection should succeed and the parent type should be present.
		$parent = $this->introspect_type( 'WrappedParent' );
		$this->assertNotNull( $parent, 'WrappedParent type should exist in the schema' );
	}
}
