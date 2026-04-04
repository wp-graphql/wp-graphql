<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;

class PostObject {

	/**
	 * Register support for the ACF post_object field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'post_object',
			[
				'exclude_admin_fields' => [ 'graphql_non_null' ],
				'admin_fields'         => static function ( $admin_fields, $field, $config, \WPGraphQL\Acf\Admin\Settings $settings ): array {
					return Relationship::get_admin_fields( $admin_fields, $field, $config, $settings );
				},
				'graphql_type'         => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {
					return Relationship::get_graphql_type( $field_config, $acf_field_type );
				},
			]
		);
	}
}
