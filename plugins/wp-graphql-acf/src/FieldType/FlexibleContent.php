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
						// Pull the un-spliced layout sub-fields from ACF's local store.
						//
						// ACF Pro stores flex layout sub-fields under the FLEX field's key
						// (with `parent_layout` pointing at the individual layout), not
						// under the layout key itself. We can recover them by querying
						// `acf_get_local_fields($flex_field_key)` and filtering by
						// `parent_layout`.
						//
						// This avoids two pre-existing problems with reading
						// `$acf_field['layouts'][$key]['sub_fields']` directly:
						//
						//  1. ACF Pro's flex `prepare_field_for_import` rewrites a clone
						//     sub-field into its pre-spliced source fields (eg. a clone
						//     `yo` with `prefix_name=1` becomes `yo_title` carrying the
						//     source field's `graphql_field_name='title'`). The original
						//     clone field is lost from `$layout['sub_fields']` but is
						//     preserved in the local store.
						//  2. `acf_get_fields($layout_key)` (used previously) triggers the
						//     `acf/get_fields` filter which ACF Pro's clone field hooks
						//     at priority 5 to splice seamless clones into their parent.
						//     For prefixed seamless clones we need the splice NOT to
						//     happen so CloneField::register_field_type can build the
						//     prefixed object type.
						//
						// @see https://github.com/wp-graphql/wpgraphql-acf/issues/269
						$flex_field_key = $acf_field['key'] ?? '';
						$all_flex_subs  = $flex_field_key && function_exists( 'acf_get_local_fields' )
							? acf_get_local_fields( $flex_field_key )
							: [];

						foreach ( $acf_field['layouts'] as $layout ) {
							$layout_type_name              = Utils::format_type_name( $layout_interface_prefix . ' ' . $field_config->get_registry()->get_field_group_graphql_type_name( $layout ) ) . 'Layout';
							$layout['interfaces']          = [ $layout_interface_name ];
							$layout['eagerlyLoadType']     = true;
							$layout['isFlexLayout']        = true;
							$layout['parent_layout_group'] = $layout;
							$layout['graphql_type_name']   = $layout_type_name;

							$sub_fields = array_values(
								array_filter(
									array_map(
										static function ( $field ) use ( $layout ) {
											if ( ! is_array( $field ) ) {
												return null;
											}
											if ( ! isset( $field['parent_layout'] ) || $layout['key'] !== $field['parent_layout'] ) {
												return null;
											}
											$field['graphql_types']       = [];
											$field['parent_layout_group'] = $layout;
											$field['isFlexLayoutField']   = true;
											return $field;
										},
										$all_flex_subs
									)
								)
							);

							// Cherry-picked seamless+no-prefix clones inside this layout
							// need inline expansion the same way the parent group path does.
							$layout['sub_fields'] = $field_config->get_registry()->expand_cherry_picked_clone_fields( $sub_fields );

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
