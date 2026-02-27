<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\Utils\Utils;

class FlexibleContent {

	/**
	 * Register support for the "flexible_content" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'flexible_content',
			[
				'graphql_type' => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {
					$acf_field               = $field_config->get_acf_field();
					$parent_type             = $field_config->get_parent_graphql_type_name( $acf_field );
					$field_name              = $field_config->get_graphql_field_name();
					$layout_interface_prefix = Utils::format_type_name( $parent_type . ' ' . $field_name );
					$layout_interface_name   = $layout_interface_prefix . '_Layout';

					if ( ! $field_config->get_registry()->has_registered_field_group( $layout_interface_name ) ) {
						register_graphql_interface_type(
							$layout_interface_name,
							[
								'eagerlyLoadType' => true,
								// translators: the %1$s is the name of the Flex Field Layout and the %2$s is the name of the field.
								'description'     => sprintf( __( 'Layout of the "%1$s" Field of the "%2$s" Field Group Field', 'wpgraphql-acf' ), $field_name, $parent_type ),
								'fields'          => [
									'fieldGroupName' => [
										'type'        => 'String',
										'resolve'     => static function ( $source ) use ( $layout_interface_prefix ) {
											$layout = $source['acf_fc_layout'] ?? null;
											return Utils::format_type_name( $layout_interface_prefix . ' ' . $layout ) . 'Layout';
										},
										'description' => __( 'The name of the ACF Flex Field Layout', 'wpgraphql-acf' ),
									],
								],
								'resolveType'     => static function ( $source ) use ( $layout_interface_prefix ) {
									$layout = $source['acf_fc_layout'] ?? null;
									return Utils::format_type_name( $layout_interface_prefix . ' ' . $layout ) . 'Layout';
								},
							]
						);

						$field_config->get_registry()->register_field_group( $layout_interface_name, $layout_interface_name );
					}

					$layouts = [];

					// If there are no layouts, return a NULL type
					if ( ! empty( $acf_field['layouts'] ) ) {
						foreach ( $acf_field['layouts'] as $layout ) {
							$layout_type_name              = Utils::format_type_name( $layout_interface_prefix . ' ' . $field_config->get_registry()->get_field_group_graphql_type_name( $layout ) ) . 'Layout';
							$layout['interfaces']          = [ $layout_interface_name ];
							$layout['eagerlyLoadType']     = true;
							$layout['isFlexLayout']        = true;
							$layout['parent_layout_group'] = $layout;
							$layout['graphql_type_name']   = $layout_type_name;

							$sub_fields = array_filter(
								array_map(
									static function ( $field ) use ( $layout ) {
										$field['graphql_types']       = [];
										$field['parent_layout_group'] = $layout;
										$field['isFlexLayoutField']   = true;

										return isset( $field['parent_layout'] ) && $layout['key'] === $field['parent_layout'] ? $field : null;
									},
									acf_get_fields( $layout['key'] )
								)
							);

							$layout_sub_fields = ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ? $layout['sub_fields'] : [];

							$layout['sub_fields'] = array_merge( $sub_fields, $layout_sub_fields );

							$layouts[] = $layout;
						}
					}

					if ( ! empty( $layouts ) ) {
						$field_config->get_registry()->register_acf_field_groups_to_graphql( $layouts );
					}

					return [ 'list_of' => $layout_interface_name ];
				},
			]
		);
	}
}
