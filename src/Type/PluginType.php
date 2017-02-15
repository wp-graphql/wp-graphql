<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class PluginType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'plugin',
			'description' => __( 'An plugin object', 'wp-graphql' ),
			'fields' => function() {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ( ! empty( $plugin ) && ! empty( $plugin['Name'] ) ) ? Relay::toGlobalId( 'plugin', $plugin['Name'] ) : null;
						},
					],
					'name'            => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Display name of the plugin.', 'wp-graphql' ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ! empty( $plugin['Name'] ) ? $plugin['Name'] : null;
						},
					],
					'pluginUri'      => [
						'type'        => Types::string(),
						'description' => esc_html__( 'URI for the plugin website. This is useful for directing users 
						for support requests etc.', 'wp-graphql' ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ! empty( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : null;
						},
					],
					'description'     => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Description of the plugin.', 'wp-graphql' ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ! empty( $plugin['Description'] ) ? $plugin['Description'] : null;
						},
					],
					'author'          => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Name of the plugin author(s), may also be a company 
						name.', 'wp-graphql' ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ! empty( $plugin['Author'] ) ? $plugin['Author'] : null;
						},
					],
					'authorUri'      => [
						'type'        => Types::string(),
						'description' => esc_html__( 'URI for the related author(s)/company website.', 'wp-graphql' ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ! empty( $plugin['AuthorURI'] ) ? $plugin['AuthorURI'] : null;
						},
					],
					'version'         => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Current version of the plugin.', 'wp-graphql' ),
						'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
							return ! empty( $plugin['Version'] ) ? $plugin['Version'] : null;
						},
					],
				];
				ksort( $fields );
				return $fields;
			},
			'interfaces' => [ $node_definition['nodeInterface'] ],
		];

		parent::__construct( $config );

	}

}
