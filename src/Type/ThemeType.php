<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class ThemeType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'theme',
			'description' => __( 'A theme object', 'wp-graphql' ),
			'fields' => function() {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( \WP_Theme $theme, $args, $context, ResolveInfo $info ) {
							$stylesheet = $theme->get_stylesheet();

							return ( ! empty( $info->parentType ) && ! empty( $stylesheet ) ) ? Relay::toGlobalId( $info->parentType, $stylesheet ) : null;
						},
					],
					'slug' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The theme slug is used to internally match themes. Theme 
						slugs can have subdirectories like: my-theme/sub-theme. This field is equivalent to 
						WP_Theme->get_stylesheet().', 'wp-graphql' ),
						'resolve' => function( \WP_Theme $theme, $args, $context, ResolveInfo $info ) {
							$stylesheet = $theme->get_stylesheet();

							return ! empty( $stylesheet ) ? $stylesheet : null;
						},
					],
					'name' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Display name of the theme. This field is equivalent to 
						WP_Theme->get( "Name" ).', 'wp-graphql' ),
						'resolve' => function( \WP_Theme $theme, $args, $context, ResolveInfo $info ) {
							$name = $theme->get( 'Name' );

							return ! empty( $name ) ? $name : null;
						},
					],
					'screenshot' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The URL of the screenshot for the theme. The screenshot 
						is intended to give an overview of what the theme looks like. This field is equivalent 
						to WP_Theme->get_screenshot().', 'wp-graphql' ),
						'resolve' => function( \WP_Theme $theme, $args, $context, ResolveInfo $info ) {
							$screenshot = $theme->get_screenshot();

							return ! empty( $screenshot ) ? $screenshot : null;
						},
					],
					'theme_uri' => [
						'type' => Types::string(),
						'description' => esc_html__( 'A URI if the theme has a website associated with it. The 
						Theme URI is handy for directing users to a theme site for support etc. This field is 
						equivalent to WP_Theme->get( "ThemeURI" ).', 'wp-graphql' ),
					],
					'description' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The description of the theme. This field is equivalent to 
						WP_Theme->get( "Description" ).', 'wp-graphql' ),
					],
					'author' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Name of the theme author(s), could also be a company name. 
						This field is equivalent to WP_Theme->get( "Author" ).', 'wp-graphql' ),
					],
					'author_uri' => [
						'type' => Types::string(),
						'description' => esc_html__( 'URI for the author/company website. This field is equivalent 
						to WP_Theme->get( "AuthorURI" ).', 'wp-graphql' ),
					],
					'tags' => [
						'type' => Types::list_of( Types::string() ),
						'description' => esc_html__( 'URI for the author/company website. This field is equivalent
						 to WP_Theme->get( "Tags" ).', 'wp-graphql' ),
					],
					'version' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The current version of the theme. This field is equivalent 
						to WP_Theme->get( "Version" ).', 'wp-graphql' ),
					],
				];

				/**
				 * Pass the fields through a filter
				 *
				 * @param array $fields
				 *
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_theme_type_fields', $fields );

				/**
				 * Sort the fields alphabetically by key. This makes reading through docs much easier
				 * @since 0.0.2
				 */
				ksort( $fields );

				return $fields;

			},
			'interfaces' => [ $node_definition['nodeInterface'] ],
		];

		parent::__construct( $config );

	}
}
