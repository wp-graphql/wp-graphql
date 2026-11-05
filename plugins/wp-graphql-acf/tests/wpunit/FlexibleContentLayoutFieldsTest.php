<?php

/**
 * Regression tests for wp-graphql/wp-graphql#4537.
 *
 * A flexible content layout has no post ID, so building its type looked its fields up with
 * acf_get_raw_fields( null ), which loads every ACF field stored in the database, once per layout,
 * on every request.
 */
class FlexibleContentLayoutFieldsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function testBuildingLayoutTypesDoesNotLoadEveryStoredField(): void {
		if ( ! $this->is_acf_pro ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$this->register_acf_field(
			[
				'key'                => 'field_layout_fields_flex',
				'type'               => 'flexible_content',
				'name'               => 'layout_fields_flex',
				'graphql_field_name' => 'layoutFieldsFlex',
				'layouts'            => [
					'layout_layout_fields_flex_text' => [
						'key'        => 'layout_layout_fields_flex_text',
						'name'       => 'text_layout',
						'label'      => 'Text Layout',
						'display'    => 'block',
						'sub_fields' => [
							[
								'key'                => 'field_layout_fields_flex_text',
								'type'               => 'text',
								'name'               => 'text',
								'show_in_graphql'    => 1,
								'graphql_field_name' => 'text',
								'parent_layout'      => 'layout_layout_fields_flex_text',
							],
						],
					],
				],
			]
		);

		// Field queries that aren't scoped to a parent load every field stored in the database.
		$unscoped_field_queries = 0;
		$count_unscoped_queries = static function ( \WP_Query $query ) use ( &$unscoped_field_queries ) {
			if ( 'acf-field' === $query->get( 'post_type' ) && '' === (string) $query->get( 'post_parent' ) && empty( $query->get( 'acf_field_key' ) ) && empty( $query->get( 'acf_field_name' ) ) ) {
				++$unscoped_field_queries;
			}
		};
		add_action( 'pre_get_posts', $count_unscoped_queries );

		$actual = $this->graphql( [ 'query' => '{ __type( name: "AcfTestGroupLayoutFieldsFlexTextLayoutLayout" ) { fields { name } } }' ] );

		remove_action( 'pre_get_posts', $count_unscoped_queries );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertContains( 'text', wp_list_pluck( $actual['data']['__type']['fields'], 'name' ) );
		$this->assertSame( 0, $unscoped_field_queries );
	}
}
