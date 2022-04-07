<?php

namespace WPGraphQL\Type\InterfaceType;

class ContentTemplate {

	/**
	 * Register the ContentTemplate Interface
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_interface_type(
			'ContentTemplate',
			[
				'description' => __( 'The template assigned to a node of content', 'wp-graphql' ),
				'fields'      => [
					'templateName' => [
						'type'        => 'String',
						'description' => __( 'The name of the template', 'wp-graphql' ),
					],
				],
				'resolveType' => function ( $value ) {
					return isset( $value['__typename'] ) ? $value['__typename'] : 'DefaultTemplate';
				},
			]
		);
	}

	/**
	 * Register individual GraphQL objects for supported theme templates.
	 *
	 * @return void
	 */
	public static function register_content_template_types() {
		$page_templates['default'] = 'DefaultTemplate';

		// Cycle through the registered post types and get the template information
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		foreach ( $allowed_post_types as $post_type ) {
			$post_type_templates = wp_get_theme()->get_page_templates( null, $post_type );

			foreach ( $post_type_templates as $file => $name ) {
				$page_templates[ $file ] = $name;
			}
		}

		// Register each template to the schema
		foreach ( $page_templates as $file => $name ) {
			$name          = ucwords( $name );
			$replaced_name = preg_replace( '/[^\w]/', '', $name );

			if ( ! empty( $replaced_name ) ) {
				$name = $replaced_name;
			}

			if ( preg_match( '/^\d/', $name ) || false === strpos( strtolower( $name ), 'template' ) ) {
				$name = 'Template_' . $name;
			}
			$template_type_name = $name;

			register_graphql_object_type(
				$template_type_name,
				[
					'interfaces'      => [ 'ContentTemplate' ],
					// Translators: Placeholder is the name of the GraphQL Type in the Schema
					'description'     => __( 'The template assigned to the node', 'wp-graphql' ),
					'fields'          => [
						'templateName' => [
							'resolve' => function ( $template ) {
								return isset( $template['templateName'] ) ? $template['templateName'] : null;
							},
						],
					],
					'eagerlyLoadType' => true,
				]
			);
		}
	}
}
