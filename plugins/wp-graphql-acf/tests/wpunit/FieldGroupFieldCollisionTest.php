<?php

/**
 * Tests for wp-graphql/wp-graphql#4563.
 *
 * A field group whose GraphQL name matches an existing field on its target type
 * replaces that field and breaks the schema.
 */
class FieldGroupFieldCollisionTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * Test that when a field group's GraphQL type name matches an existing field on a target type
	 * (e.g. "Title" on "Post"), the field group is not added to that type, core's existing field wins,
	 * the schema remains valid, and a debug message is logged.
	 */
	public function testFieldGroupCollidingWithExistingFieldIsSkipped(): void {
		$post_id = $this->factory->post->create(
			[
				'post_title' => 'Hello World Post',
			]
		);

		acf_add_local_field_group(
			[
				'key'                => 'group_colliding_title',
				'title'              => 'Title',
				'fields'             => [
					[
						'key'             => 'field_colliding_custom_text',
						'name'            => 'custom_text',
						'label'           => 'Custom Text',
						'type'            => 'text',
						'show_in_graphql' => 1,
					],
				],
				'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'Title',
			]
		);

		$this->clearSchema();

		// 1. The GraphQL schema must be valid.
		$schema = \WPGraphQL::get_schema();
		$schema->assertValid();

		// 2. Querying the post title should return the post title as a String (existing field wins).
		$actual = $this->graphql(
			[
				'query'     => 'query GetPost($id: ID!) {
					post(id: $id, idType: DATABASE_ID) {
						title
					}
				}',
				'variables' => [
					'id' => $post_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Hello World Post', $actual['data']['post']['title'] );

		// 3. Introspect Post type: title must be a String scalar, not an Object type.
		$introspection = $this->graphql(
			[
				'query' => '{
					__type(name: "Post") {
						fields {
							name
							type {
								name
								kind
							}
						}
					}
				}',
			]
		);

		$fields = [];
		foreach ( $introspection['data']['__type']['fields'] ?? [] as $field ) {
			$fields[ $field['name'] ] = $field['type']['name'] ?? $field['type']['kind'];
		}

		$this->assertArrayHasKey( 'title', $fields );
		$this->assertSame( 'String', $fields['title'] );

		// 4. Verify debug message was emitted with expected metadata.
		$debug_messages = $actual['extensions']['debug'] ?? [];
		$found_collision_debug = false;
		foreach ( $debug_messages as $message ) {
			if ( isset( $message['type'] ) && 'FIELD_GROUP_FIELD_COLLISION' === $message['type'] ) {
				$found_collision_debug = true;
				$this->assertSame( 'title', $message['field_name'] );
				$this->assertSame( 'Post', $message['target_type'] );
				$this->assertSame( 'graphql_types', $message['setting_to_change'] );
				$this->assertStringContainsString( 'Change the "GraphQL Type Name" setting', $message['message'] );
				break;
			}
		}

		$this->assertTrue( $found_collision_debug, 'Expected FIELD_GROUP_FIELD_COLLISION debug message was not found in extensions.debug.' );
	}

	/**
	 * Test that non-colliding field groups continue to be added to target types as expected.
	 */
	public function testNonCollidingFieldGroupIsAddedNormally(): void {
		$post_id = $this->factory->post->create(
			[
				'post_title' => 'Post with ACF group',
			]
		);

		acf_add_local_field_group(
			[
				'key'                => 'group_non_colliding_hero',
				'title'              => 'Hero Details',
				'fields'             => [
					[
						'key'             => 'field_hero_subtitle',
						'name'            => 'hero_subtitle',
						'label'           => 'Subtitle',
						'type'            => 'text',
						'show_in_graphql' => 1,
					],
				],
				'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'heroDetails',
			]
		);

		update_field( 'field_hero_subtitle', 'Subheading text', $post_id );

		$this->clearSchema();

		$actual = $this->graphql(
			[
				'query'     => 'query GetPostHero($id: ID!) {
					post(id: $id, idType: DATABASE_ID) {
						heroDetails {
							heroSubtitle
						}
					}
				}',
				'variables' => [
					'id' => $post_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Subheading text', $actual['data']['post']['heroDetails']['heroSubtitle'] );
	}

	/**
	 * Test that when a field group is assigned to multiple locations, it is only skipped
	 * on locations where a collision actually occurs, while continuing to be added on locations
	 * without a collision.
	 */
	public function testFieldGroupWithCollisionOnOneLocationStillRegistersOnNonCollidingLocation(): void {
		register_post_type(
			'custom_book',
			[
				'public'              => true,
				'show_in_graphql'     => true,
				'graphql_single_name' => 'CustomBook',
				'graphql_plural_name' => 'CustomBooks',
				'supports'            => [ 'editor' ],
			]
		);

		acf_add_local_field_group(
			[
				'key'                => 'group_multi_loc_title',
				'title'              => 'Title',
				'fields'             => [
					[
						'key'             => 'field_book_custom_text',
						'name'            => 'custom_text',
						'label'           => 'Custom Text',
						'type'            => 'text',
						'show_in_graphql' => 1,
					],
				],
				'location'           => [
					[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ],
					[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'custom_book' ] ],
				],
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'Title',
			]
		);

		$this->clearSchema();

		$introspection = $this->graphql(
			[
				'query' => '{
					postType: __type(name: "Post") {
						fields {
							name
							type {
								name
								kind
							}
						}
					}
					bookType: __type(name: "CustomBook") {
						fields {
							name
							type {
								name
								kind
							}
						}
					}
				}',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $introspection );

		$post_fields = [];
		foreach ( $introspection['data']['postType']['fields'] ?? [] as $field ) {
			$post_fields[ $field['name'] ] = $field['type']['name'] ?? $field['type']['kind'];
		}

		$book_fields = [];
		foreach ( $introspection['data']['bookType']['fields'] ?? [] as $field ) {
			$book_fields[ $field['name'] ] = $field['type']['name'] ?? $field['type']['kind'];
		}

		// On Post: title must remain the core String field.
		$this->assertSame( 'String', $post_fields['title'] );

		// On CustomBook: title field group should be present as Title object type.
		$this->assertArrayHasKey( 'title', $book_fields );
		$this->assertSame( 'Title', $book_fields['title'] );
	}
}
