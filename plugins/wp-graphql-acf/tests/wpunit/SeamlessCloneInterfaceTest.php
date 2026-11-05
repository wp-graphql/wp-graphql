<?php

/**
 * A seamless clone of a field group puts the group's fields on the type it's in, and that type
 * implements the cloned group's `_Fields` interface, so fragments on the cloned group work there.
 *
 * Covers a seamless clone inside a group, a repeater and a flexible content layout, for fields
 * registered in PHP and fields stored in the database. ACF has already replaced the clone with
 * the fields it clones in these fields' sub_fields, which is what this guards against losing.
 *
 * Group display clones are left out on purpose: their shape changes with #4522 (see #4530).
 */
class SeamlessCloneInterfaceTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! $this->is_acf_pro ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		acf_add_local_field_group(
			[
				'key'                => 'group_seamless_clone_target',
				'title'              => 'Clone Target',
				'fields'             => [
					[
						'key'             => 'field_seamless_clone_target_text',
						'name'            => 'clone_target_text',
						'label'           => 'Text',
						'type'            => 'text',
						'show_in_graphql' => 1,
					],
				],
				'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ] ] ],
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'cloneTarget',
			]
		);
	}

	public function seamless_clone_cases(): array {
		return [
			'group, registered in PHP'                 => [ 'local', 'clonedInGroup' ],
			'repeater, registered in PHP'              => [ 'local', 'clonedInRepeater' ],
			'flexible content layout, registered in PHP' => [ 'local', 'clonedInLayouts' ],
			'group, stored in the database'            => [ 'database', 'clonedInGroup' ],
			'repeater, stored in the database'         => [ 'database', 'clonedInRepeater' ],
			'flexible content layout, stored in the database' => [ 'database', 'clonedInLayouts' ],
		];
	}

	/**
	 * @dataProvider seamless_clone_cases
	 *
	 * @param string $storage    Where the field group lives: 'local' or 'database'.
	 * @param string $field_name The GraphQL field holding the clone.
	 */
	public function testTypeWithSeamlessCloneImplementsClonedGroupInterface( string $storage, string $field_name ): void {
		$prefix = 'local' === $storage ? 'local' : 'db';

		// Unique keys, so database-stored fields from one case are never served from ACF's caches in the next.
		$suffix = 'local' === $storage ? '' : '_' . uniqid();

		$clone = static function ( string $key ): array {
			return [
				'key'             => $key,
				'name'            => $key,
				'label'           => 'Clone',
				'type'            => 'clone',
				'clone'           => [ 'group_seamless_clone_target' ],
				'display'         => 'seamless',
				'prefix_name'     => 0,
				'show_in_graphql' => 1,
			];
		};

		$field_group = [
			'key'                => "group_seamless_clone_{$prefix}{$suffix}",
			'title'              => 'Seamless Clone',
			'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
			'show_in_graphql'    => 1,
			'graphql_field_name' => 'seamlessClone',
			'fields'             => [
				[
					'key'                => "field_{$prefix}_cloned_in_group{$suffix}",
					'name'               => "{$prefix}_cloned_in_group",
					'label'              => 'Group',
					'type'               => 'group',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'clonedInGroup',
					'sub_fields'         => [ $clone( "field_{$prefix}_cloned_in_group_clone{$suffix}" ) ],
				],
				[
					'key'                => "field_{$prefix}_cloned_in_repeater{$suffix}",
					'name'               => "{$prefix}_cloned_in_repeater",
					'label'              => 'Repeater',
					'type'               => 'repeater',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'clonedInRepeater',
					'sub_fields'         => [ $clone( "field_{$prefix}_cloned_in_repeater_clone{$suffix}" ) ],
				],
				[
					'key'                => "field_{$prefix}_cloned_in_layouts{$suffix}",
					'name'               => "{$prefix}_cloned_in_layouts",
					'label'              => 'Flexible Content',
					'type'               => 'flexible_content',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'clonedInLayouts',
					'layouts'            => [
						"layout_{$prefix}_cloned{$suffix}" => [
							'key'        => "layout_{$prefix}_cloned{$suffix}",
							'name'       => 'cloned_layout',
							'label'      => 'Cloned Layout',
							'display'    => 'block',
							'sub_fields' => [
								array_merge(
									$clone( "field_{$prefix}_cloned_in_layout_clone{$suffix}" ),
									[ 'parent_layout' => "layout_{$prefix}_cloned{$suffix}" ]
								),
							],
						],
					],
				],
			],
		];

		if ( 'local' === $storage ) {
			acf_add_local_field_group( $field_group );
		} else {
			acf_import_field_group( $field_group );
		}

		$post_id = $this->published_post->ID;
		update_field( "field_{$prefix}_cloned_in_group{$suffix}", [ 'clone_target_text' => 'in a group' ], $post_id );
		update_field( "field_{$prefix}_cloned_in_repeater{$suffix}", [ [ 'clone_target_text' => 'in a repeater' ] ], $post_id );
		update_field(
			"field_{$prefix}_cloned_in_layouts{$suffix}",
			[
				[
					'acf_fc_layout'     => 'cloned_layout',
					'clone_target_text' => 'in a layout',
				],
			],
			$post_id
		);

		$expected_values = [
			'clonedInGroup'    => 'in a group',
			'clonedInRepeater' => 'in a repeater',
			'clonedInLayouts'  => 'in a layout',
		];

		$actual = $this->graphql(
			[
				'query'     => '
				query ( $id: ID! ) {
					post( id: $id, idType: DATABASE_ID ) {
						seamlessClone {
							' . $field_name . ' {
								__typename
								... on CloneTarget_Fields {
									cloneTargetText
								}
							}
						}
					}
				}',
				'variables' => [ 'id' => $post_id ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );

		$value = $actual['data']['post']['seamlessClone'][ $field_name ];

		// A repeater or flexible content field resolves a list of rows.
		$row = 'clonedInGroup' === $field_name ? $value : $value[0];

		$this->assertSame( $expected_values[ $field_name ], $row['cloneTargetText'] );

		$type = $this->graphql( [ 'query' => '{ __type( name: "' . $row['__typename'] . '" ) { interfaces { name } } }' ] );

		$this->assertContains( 'CloneTarget_Fields', wp_list_pluck( $type['data']['__type']['interfaces'], 'name' ) );
	}
}
