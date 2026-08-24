<?php
/**
 * Guards that user-facing schema `description` strings stay backend-agnostic and
 * never leak WordPress implementation plumbing (WP_* class properties, database
 * tables/columns, etc.).
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4026
 */
class SchemaDescriptionLeaksTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Enable public introspection before each test and clear the cached schema.
	 */
	public function setUp(): void {
		parent::setUp();

		// Public introspection is required so the same query used to reproduce the
		// issue (as an unauthenticated consumer would run it) resolves descriptions.
		$settings = get_option( 'graphql_general_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$settings['public_introspection_enabled'] = 'on';
		update_option( 'graphql_general_settings', $settings );
		$this->clearSchema();
	}

	/**
	 * Clear the cached schema after each test.
	 */
	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * The exact fields called out in the issue: ContentNode.guid and ContentNode.slug
	 * used to describe themselves in terms of WP_Post properties and the underlying
	 * database table. Assert those leaks are gone.
	 */
	public function testContentNodeGuidAndSlugDescriptionsAreBackendAgnostic() {
		$query = '
		{
			__type(name: "ContentNode") {
				fields {
					name
					description
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$descriptions = [];
		foreach ( $actual['data']['__type']['fields'] as $field ) {
			$descriptions[ $field['name'] ] = (string) $field['description'];
		}

		$this->assertArrayHasKey( 'guid', $descriptions );
		$this->assertArrayHasKey( 'slug', $descriptions );

		foreach ( [ 'guid', 'slug' ] as $field_name ) {
			$description = $descriptions[ $field_name ];
			$this->assertStringNotContainsString( 'WP_Post', $description, "ContentNode.$field_name description leaks a WP_Post reference." );
			$this->assertStringNotContainsString( 'database table', $description, "ContentNode.$field_name description leaks a database table reference." );
			$this->assertNotEmpty( $description, "ContentNode.$field_name should still have a description." );
		}
	}

	/**
	 * Schema-wide guard: walk every type's field and input-field descriptions as an
	 * introspection consumer sees them, and assert none of them leak WordPress
	 * internals. This catches the whole class of regression, not just the two fields
	 * named in the issue.
	 */
	public function testNoFieldDescriptionLeaksWordPressInternals() {
		$introspection = $this->graphql(
			[
				'query' => '
				{
					__schema {
						types {
							name
							fields(includeDeprecated: true) {
								name
								description
							}
							inputFields {
								name
								description
							}
						}
					}
				}
				',
			]
		);

		$this->assertArrayNotHasKey( 'errors', $introspection );

		// Substrings that only make sense to someone reading WordPress source: class
		// property accessors and references to raw storage (tables/columns).
		$leak_markers = [
			'WP_Post->',
			'WP_Term->',
			'WP_User->',
			'WP_Comment->',
			'database table',
			'column in SQL',
			'post_objects',
		];

		$violations = [];

		foreach ( $introspection['data']['__schema']['types'] as $type ) {
			$fields = array_merge(
				is_array( $type['fields'] ) ? $type['fields'] : [],
				is_array( $type['inputFields'] ) ? $type['inputFields'] : []
			);

			foreach ( $fields as $field ) {
				$description = (string) $field['description'];

				foreach ( $leak_markers as $marker ) {
					if ( false !== strpos( $description, $marker ) ) {
						$violations[] = sprintf(
							'%1$s.%2$s description leaks "%3$s": %4$s',
							$type['name'],
							$field['name'],
							$marker,
							$description
						);
					}
				}
			}
		}

		$this->assertSame(
			[],
			$violations,
			"Found schema descriptions that leak WordPress implementation details:\n" . implode( "\n", $violations )
		);
	}
}
