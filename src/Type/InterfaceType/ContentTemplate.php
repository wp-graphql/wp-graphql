<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Utils\Utils;

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
				'description' => static function () {
					return __( 'A layout pattern that can help inform how content might be structured and displayed. Templates can define specialized layouts for different types of content.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'templateName' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The name of the template', 'wp-graphql' );
							},
						],
					];
				},
				'resolveType' => static function ( $value ) {
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
		$page_templates            = [];
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
			$template_type_name = Utils::format_type_name_for_wp_template( $name, $file );

			// If the type name is empty, log an error and continue.
			if ( empty( $template_type_name ) ) {
				graphql_debug(
					sprintf(
						// Translators: %s is the file name.
						__( 'Unable to register the %1s template file as a GraphQL Type. Either the template name or the file name must only use ASCII characters. "DefaultTemplate" will be used instead.', 'wp-graphql' ),
						(string) $file
					)
				);

				continue;
			}

			register_graphql_object_type(
				$template_type_name,
				[
					'interfaces'      => [ 'ContentTemplate' ],
					// Translators: Placeholder is the name of the GraphQL Type in the Schema
					'description'     => static function () {
						return __( 'The template assigned to the node', 'wp-graphql' );
					},
					'fields'          => [
						'templateName' => [
							'resolve' => static function ( $template ) {
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
