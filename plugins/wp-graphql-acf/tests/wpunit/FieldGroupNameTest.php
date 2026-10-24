<?php

/**
 * Regression tests for wp-graphql/wp-graphql#4526.
 *
 * fieldGroupName was deprecated on the field group types and the AcfFieldGroup and
 * AcfFieldGroupFields interfaces, but not on the flexible content _Layout interfaces. Tools that
 * leave deprecated fields out when they introspect the schema (Gatsby, for example) then saw layout
 * types that didn't implement a field their interface requires, and rejected the schema.
 * fieldGroupName is no longer deprecated anywhere.
 */
class FieldGroupNameTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * Returns whether each type's fieldGroupName field is deprecated.
	 *
	 * @param string[] $type_names The types to check.
	 *
	 * @return array<string,bool|null> Keyed by type name; null when the type has no fieldGroupName.
	 */
	private function field_group_name_deprecation( array $type_names ): array {
		$result = [];

		foreach ( $type_names as $type_name ) {
			$actual = $this->graphql(
				[
					'query'     => 'query ( $name: String! ) { __type( name: $name ) { fields( includeDeprecated: true ) { name isDeprecated } } }',
					'variables' => [ 'name' => $type_name ],
				]
			);

			$result[ $type_name ] = null;
			foreach ( $actual['data']['__type']['fields'] ?? [] as $field ) {
				if ( 'fieldGroupName' === $field['name'] ) {
					$result[ $type_name ] = $field['isDeprecated'];
				}
			}
		}

		return $result;
	}

	public function testFieldGroupNameIsNotDeprecated(): void {
		$this->register_acf_field(
			[
				'type'               => 'text',
				'name'               => 'text',
				'graphql_field_name' => 'text',
			]
		);

		$this->assertSame(
			[
				'AcfFieldGroup'       => false,
				'AcfFieldGroupFields' => false,
				'AcfTestGroup'        => false,
				'AcfTestGroup_Fields' => false,
			],
			$this->field_group_name_deprecation( [ 'AcfFieldGroup', 'AcfFieldGroupFields', 'AcfTestGroup', 'AcfTestGroup_Fields' ] )
		);
	}

	/**
	 * Widest surface: the schema a client builds from an introspection that leaves out deprecated
	 * fields is still valid, with a flexible content field in it.
	 */
	public function testSchemaIsValidWhenIntrospectedWithoutDeprecatedFields(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$this->register_acf_field(
			[
				'key'                => 'field_field_group_name_flex',
				'type'               => 'flexible_content',
				'name'               => 'field_group_name_flex',
				'graphql_field_name' => 'fieldGroupNameFlex',
				'layouts'            => [
					'layout_field_group_name_flex_text' => [
						'key'        => 'layout_field_group_name_flex_text',
						'name'       => 'text_layout',
						'label'      => 'Text Layout',
						'display'    => 'block',
						'sub_fields' => [
							[
								'key'                => 'field_field_group_name_flex_text',
								'type'               => 'text',
								'name'               => 'text',
								'show_in_graphql'    => 1,
								'graphql_field_name' => 'text',
								'parent_layout'      => 'layout_field_group_name_flex_text',
							],
						],
					],
				],
			]
		);

		$query = str_replace(
			'includeDeprecated: true',
			'includeDeprecated: false',
			\GraphQL\Type\Introspection::getIntrospectionQuery()
		);

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$schema = \GraphQL\Utils\BuildClientSchema::build( $actual['data'] );

		// Throws if a type doesn't implement a field its interface requires.
		$schema->assertValid();

		$this->assertNotNull( $schema->getType( 'AcfTestGroupFieldGroupNameFlexTextLayoutLayout' ) );
	}
}
