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
						// Pull the un-spliced layout sub-fields directly from ACF's storage
						// without going through the `acf/get_fields` filter chain, which
						// ACF Pro's clone field hooks at priority 5 to splice seamless
						// clones into their parent. We need the splice NOT to happen so
						// `CloneField::register_field_type` can register the prefixed
						// object type and `Registry::expand_cherry_picked_clone_fields`
						// can do its own scoped splicing.
						//
						// ACF stores flex layout sub-fields under the FLEX field's key/ID
						// (with `parent_layout` pointing at the individual layout), not
						// under the layout key itself, so we read by the flex field and
						// filter by `parent_layout` per layout below.
						//
						// Two storage paths to cover:
						//
						//  - **Local field groups** (registered in PHP via
						//    `acf_add_local_field_group()`): the sub-fields live in the
						//    local store keyed by the flex field's `key`. We probe with
						//    `acf_have_local_fields($flex_field_key)` so we only use this
						//    path when ACF's local store actually has them.
						//
						//  - **DB-imported field groups** (created via WP admin or via
						//    `acf_import_field_group()`): each sub-field is a stored
						//    `acf-field` post whose `parent` is the flex field's post ID.
						//    `acf_get_raw_fields($flex_field_id)` returns them without
						//    triggering any filters.
						//
						// Without the DB-storage path, prefixed seamless clones inside a
						// flex layout — when imported through the admin Tools UI — would
						// end up with no sub-fields registered on the layout type and
						// only the cloned source group's `_Fields` interface attached
						// (so the clone field's wrapper name was missing entirely).
						//
						// @see https://github.com/wp-graphql/wpgraphql-acf/issues/269
						$flex_field_key = $acf_field['key'] ?? '';
						$flex_field_id  = isset( $acf_field['ID'] ) ? (int) $acf_field['ID'] : 0;
						$all_flex_subs  = [];

						if ( $flex_field_key && function_exists( 'acf_have_local_fields' ) && acf_have_local_fields( $flex_field_key ) ) {
							$all_flex_subs = acf_get_local_fields( $flex_field_key );
						} elseif ( $flex_field_id > 0 && function_exists( 'acf_get_raw_fields' ) ) {
							$all_flex_subs = acf_get_raw_fields( $flex_field_id );
						}

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
