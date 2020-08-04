<?php
namespace WPGraphQL\Type\Union;

class ContentTemplateUnion {
	public static function register_type( $type_registry ) {

		$registered_page_templates = wp_get_theme()->get_post_templates();
		$page_templates['default'] = 'Default';

		if ( ! empty( $registered_page_templates ) && is_array( $registered_page_templates ) ) {
			foreach ( $registered_page_templates as $post_type_templates ) {
				foreach ( $post_type_templates as $file => $name ) {
					$page_templates[ $file ] = $name;
				}
			}
		}

		if ( ! empty( $page_templates ) && is_array( $page_templates ) ) {
			$type_names         = [];
			$type_names_by_file = [];
			foreach ( $page_templates as $file => $name ) {
				$template_type_name = str_ireplace( '.php', '', $file );
				$template_type_name = str_ireplace( 'templates/', '', $template_type_name );
				$template_type_name = str_ireplace( '/', '_', $template_type_name );
				$template_type_name = graphql_format_type_name( $template_type_name );
				$template_type_name = 'Template_' . $template_type_name;
				register_graphql_object_type(
					$template_type_name,
					[
						'interfaces'  => [ 'ContentTemplate' ],
						// Translators: Placeholder is the name of the GraphQL Type in the Schema
						'description' => sprintf( __( 'The template assigned to the node.', 'wp-graphql' ), $file ),
						'fields'      => [
							'templateName' => [
								'resolve' => function( $template ) use ( $page_templates ) {
									return isset( $template['templateName'] ) ? $template['templateName'] : null;
								},
							],
							'templateFile' => [
								'resolve' => function( $template ) use ( $page_templates ) {
									return isset( $template['templateFile'] ) ? $template['templateFile'] : null;
								},
							],
						],
					]
				);

				if ( is_valid_graphql_name( $template_type_name ) ) {

					$type_names[]                = $template_type_name;
					$type_names_by_file[ $file ] = $template_type_name;

				}
			}

			if ( ! empty( $type_names ) ) {

				register_graphql_union_type(
					'ContentTemplateUnion',
					[
						'typeNames'   => $type_names,
						'resolveType' => function( $value ) use ( $type_names_by_file, $type_registry ) {
							return isset( $value['__typename'] ) ? $value['__typename'] : 'DefaultTemplate';
						},
					]
				);

			}
		}

	}
}
